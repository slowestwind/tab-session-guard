<?php

namespace SlowestWind\TabSessionGuard\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TabGuardService
{
    protected array $config;
    protected string $sessionPrefix;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sessionPrefix = $config['session']['key_prefix'] ?? 'tab_guard_';
    }

    /**
     * Check if the request should be guarded
     */
    public function shouldGuard(Request $request): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        // Skip excluded routes
        $currentRoute = $request->route()?->getName() ?? '';
        $excludedRoutes = $this->config['global']['excluded_routes'] ?? [];
        
        foreach ($excludedRoutes as $pattern) {
            if (Str::is($pattern, $currentRoute)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate tab limits for the current request
     */
    public function validateTabLimits(Request $request): array
    {
        $user = Auth::user();
        if (!$user) {
            return ['allowed' => true];
        }

        $currentRoute = $request->route()?->getName() ?? '';
        $tabId = $this->generateTabId($request);

        // Register this tab
        $this->registerTab($user->id, $tabId, $currentRoute, $request);

        // Check global limit
        $globalCheck = $this->checkGlobalLimit($user->id);
        if (!$globalCheck['allowed']) {
            return $globalCheck;
        }

        // Check role-based limits
        $roleCheck = $this->checkRoleBasedLimits($user, $currentRoute);
        if (!$roleCheck['allowed']) {
            return $roleCheck;
        }

        // Check route-specific limits
        $routeCheck = $this->checkRouteSpecificLimits($currentRoute, $user->id);
        if (!$routeCheck['allowed']) {
            return $routeCheck;
        }

        return ['allowed' => true];
    }

    /**
     * Generate a unique tab ID
     */
    protected function generateTabId(Request $request): string
    {
        $components = [
            $request->session()->getId(),
            $request->userAgent() ?? '',
            $request->ip(),
            microtime(true),
            random_int(1000, 9999)
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Register a tab in the session/cache
     */
    protected function registerTab(int $userId, string $tabId, string $route, Request $request): void
    {
        $sessionKey = $this->sessionPrefix . $userId;
        $tabs = Session::get($sessionKey, []);

        $tabData = [
            'id' => $tabId,
            'route' => $route,
            'created_at' => now(),
            'last_activity' => now(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'session_id' => $request->session()->getId(),
        ];

        $tabs[$tabId] = $tabData;

        // Clean up old tabs
        $tabs = $this->cleanupOldTabs($tabs);

        Session::put($sessionKey, $tabs);

        // Also store in cache for cross-session tracking
        if ($this->config['security']['prevent_incognito_bypass']) {
            $cacheKey = "tab_guard_user_{$userId}";
            Cache::put($cacheKey, $tabs, now()->addSeconds($this->config['session']['tab_timeout']));
        }

        $this->logActivity('tab_registered', $userId, $tabId, $route);
    }

    /**
     * Check global tab limit
     */
    protected function checkGlobalLimit(int $userId): array
    {
        if (!$this->config['global']['enabled']) {
            return ['allowed' => true];
        }

        $maxTabs = $this->config['global']['max_tabs'];
        $currentTabs = $this->getCurrentTabs($userId);

        if (count($currentTabs) > $maxTabs) {
            $this->logViolation('global_limit_exceeded', $userId, count($currentTabs), $maxTabs);
            
            return [
                'allowed' => false,
                'type' => 'global',
                'current' => count($currentTabs),
                'max' => $maxTabs,
                'message' => str_replace(':max', $maxTabs, $this->config['messages']['global_limit_exceeded'])
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check role-based limits
     */
    protected function checkRoleBasedLimits($user, string $currentRoute): array
    {
        $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
        
        if (empty($userRoles)) {
            return ['allowed' => true];
        }

        foreach ($userRoles as $roleName) {
            $roleConfig = $this->config['roles'][$roleName] ?? null;
            if (!$roleConfig) {
                continue;
            }

            foreach ($roleConfig as $module => $moduleConfig) {
                if (!$moduleConfig['enabled']) {
                    continue;
                }

                $routes = $moduleConfig['routes'] ?? [];
                $routeMatches = false;

                foreach ($routes as $pattern) {
                    if (Str::is($pattern, $currentRoute)) {
                        $routeMatches = true;
                        break;
                    }
                }

                if ($routeMatches) {
                    $maxTabs = $moduleConfig['max_tabs'];
                    $currentTabs = $this->getCurrentTabsForRoutes($user->id, $routes);

                    if (count($currentTabs) > $maxTabs) {
                        $this->logViolation('role_limit_exceeded', $user->id, count($currentTabs), $maxTabs, [
                            'role' => $roleName,
                            'module' => $module
                        ]);

                        return [
                            'allowed' => false,
                            'type' => 'role',
                            'role' => $roleName,
                            'module' => $module,
                            'current' => count($currentTabs),
                            'max' => $maxTabs,
                            'message' => str_replace(':max', $maxTabs, $this->config['messages']['role_limit_exceeded'])
                        ];
                    }
                }
            }
        }

        return ['allowed' => true];
    }

    /**
     * Check route-specific limits
     */
    protected function checkRouteSpecificLimits(string $currentRoute, int $userId): array
    {
        foreach ($this->config['routes'] as $routePattern => $routeConfig) {
            if (!$routeConfig['enabled']) {
                continue;
            }

            if (Str::is($routePattern, $currentRoute)) {
                $maxTabs = $routeConfig['max_tabs'];
                $currentTabs = $this->getCurrentTabsForRoutes($userId, [$routePattern]);

                if (count($currentTabs) > $maxTabs) {
                    $this->logViolation('route_limit_exceeded', $userId, count($currentTabs), $maxTabs, [
                        'route_pattern' => $routePattern
                    ]);

                    $message = $routeConfig['message'] ?? 
                               str_replace(':max', $maxTabs, $this->config['messages']['route_limit_exceeded']);

                    return [
                        'allowed' => false,
                        'type' => 'route',
                        'route_pattern' => $routePattern,
                        'current' => count($currentTabs),
                        'max' => $maxTabs,
                        'message' => $message
                    ];
                }
            }
        }

        return ['allowed' => true];
    }

    /**
     * Get current tabs for a user
     */
    protected function getCurrentTabs(int $userId): array
    {
        $sessionKey = $this->sessionPrefix . $userId;
        $tabs = Session::get($sessionKey, []);

        // Also check cache for cross-session tabs
        if ($this->config['security']['prevent_incognito_bypass']) {
            $cacheKey = "tab_guard_user_{$userId}";
            $cachedTabs = Cache::get($cacheKey, []);
            $tabs = array_merge($tabs, $cachedTabs);
        }

        return $this->cleanupOldTabs($tabs);
    }

    /**
     * Get current tabs for specific routes
     */
    protected function getCurrentTabsForRoutes(int $userId, array $routePatterns): array
    {
        $allTabs = $this->getCurrentTabs($userId);
        $matchingTabs = [];

        foreach ($allTabs as $tab) {
            foreach ($routePatterns as $pattern) {
                if (Str::is($pattern, $tab['route'])) {
                    $matchingTabs[] = $tab;
                    break;
                }
            }
        }

        return $matchingTabs;
    }

    /**
     * Clean up old/expired tabs
     */
    protected function cleanupOldTabs(array $tabs): array
    {
        $timeout = $this->config['session']['tab_timeout'];
        $cutoff = now()->subSeconds($timeout);

        return array_filter($tabs, function ($tab) use ($cutoff) {
            $lastActivity = $tab['last_activity'] ?? $tab['created_at'];
            return $lastActivity >= $cutoff;
        });
    }

    /**
     * Close a specific tab
     */
    public function closeTab(int $userId, string $tabId): void
    {
        $sessionKey = $this->sessionPrefix . $userId;
        $tabs = Session::get($sessionKey, []);

        if (isset($tabs[$tabId])) {
            unset($tabs[$tabId]);
            Session::put($sessionKey, $tabs);

            // Also remove from cache
            $cacheKey = "tab_guard_user_{$userId}";
            $cachedTabs = Cache::get($cacheKey, []);
            if (isset($cachedTabs[$tabId])) {
                unset($cachedTabs[$tabId]);
                Cache::put($cacheKey, $cachedTabs, now()->addSeconds($this->config['session']['tab_timeout']));
            }

            $this->logActivity('tab_closed', $userId, $tabId);
        }
    }

    /**
     * Get tab information
     */
    public function getTabInfo(int $userId): array
    {
        $tabs = $this->getCurrentTabs($userId);
        
        return [
            'total_tabs' => count($tabs),
            'global_limit' => $this->config['global']['max_tabs'],
            'tabs' => $tabs,
        ];
    }

    /**
     * Log activity
     */
    protected function logActivity(string $action, int $userId, string $tabId, string $route = null): void
    {
        if (!$this->config['logging']['enabled'] || !$this->config['logging']['log_attempts']) {
            return;
        }

        $data = [
            'action' => $action,
            'user_id' => $userId,
            'tab_id' => $tabId,
            'route' => $route,
            'timestamp' => now(),
        ];

        Log::channel($this->config['logging']['channel'])->info('TabGuard Activity', $data);
    }

    /**
     * Log violation
     */
    protected function logViolation(string $type, int $userId, int $current, int $max, array $context = []): void
    {
        if (!$this->config['logging']['enabled'] || !$this->config['logging']['log_violations']) {
            return;
        }

        $data = array_merge([
            'violation_type' => $type,
            'user_id' => $userId,
            'current_tabs' => $current,
            'max_allowed' => $max,
            'timestamp' => now(),
        ], $context);

        Log::channel($this->config['logging']['channel'])->warning('TabGuard Violation', $data);
    }
}
