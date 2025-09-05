<?php

namespace SlowestWind\TabSessionGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View;
use SlowestWind\TabSessionGuard\Services\TabGuardService;

class TabSessionGuardMiddleware
{
    protected TabGuardService $tabGuard;

    public function __construct(TabGuardService $tabGuard)
    {
        $this->tabGuard = $tabGuard;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip if guard is not enabled or shouldn't guard this request
        if (!$this->tabGuard->shouldGuard($request)) {
            return $next($request);
        }

        // Validate tab limits
        $validation = $this->tabGuard->validateTabLimits($request);

        if (!$validation['allowed']) {
            return $this->handleLimitExceeded($request, $validation);
        }

        $response = $next($request);

        // Inject JavaScript for client-side tracking
        if ($response instanceof \Illuminate\Http\Response && 
            $response->headers->get('content-type', '') !== 'application/json') {
            $this->injectTrackingScript($response);
        }

        return $response;
    }

    /**
     * Handle when tab limit is exceeded
     */
    protected function handleLimitExceeded(Request $request, array $validation)
    {
        $config = config('tab-session-guard');
        $responseType = $config['response']['type'] ?? 'json';

        // Log the violation if needed
        if ($config['logging']['enabled'] && $config['logging']['log_violations']) {
            \Log::channel($config['logging']['channel'])->warning('Tab limit exceeded', [
                'user_id' => auth()->id(),
                'route' => $request->route()?->getName(),
                'validation' => $validation,
                'request_url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        switch ($responseType) {
            case 'redirect':
                return $this->redirectResponse($validation);
            
            case 'view':
                return $this->viewResponse($validation);
            
            case 'json':
            default:
                return $this->jsonResponse($validation);
        }
    }

    /**
     * Return JSON response
     */
    protected function jsonResponse(array $validation): JsonResponse
    {
        $config = config('tab-session-guard');
        $response = $config['response']['json_response'];
        
        $response['message'] = $validation['message'] ?? $response['message'];
        $response['validation'] = $validation;

        return response()->json($response, 403);
    }

    /**
     * Return redirect response
     */
    protected function redirectResponse(array $validation): RedirectResponse
    {
        $config = config('tab-session-guard');
        $redirectRoute = $config['response']['redirect_route'] ?? 'dashboard';
        
        return redirect()->route($redirectRoute)
            ->with('tab_guard_error', $validation['message'])
            ->with('tab_guard_validation', $validation);
    }

    /**
     * Return view response
     */
    protected function viewResponse(array $validation)
    {
        $config = config('tab-session-guard');
        $view = $config['response']['view'] ?? 'tab-guard::limit-exceeded';
        
        return response()->view($view, [
            'validation' => $validation,
            'message' => $validation['message'],
            'config' => $config,
        ], 403);
    }

    /**
     * Inject tracking script into HTML response
     */
    protected function injectTrackingScript($response): void
    {
        $content = $response->getContent();
        
        if (strpos($content, '</body>') !== false) {
            $script = $this->generateTrackingScript();
            $content = str_replace('</body>', $script . '</body>', $content);
            $response->setContent($content);
        }
    }

    /**
     * Generate tracking script
     */
    protected function generateTrackingScript(): string
    {
        $config = config('tab-session-guard');
        $storageKey = $config['browser_storage']['storage_key'] ?? 'laravel_tab_guard';
        $userId = auth()->id();
        $sessionId = session()->getId();
        
        return "
        <script>
        (function() {
            const TabGuard = {
                config: " . json_encode($config['browser_storage']) . ",
                userId: " . json_encode($userId) . ",
                sessionId: " . json_encode($sessionId) . ",
                tabId: null,
                
                init() {
                    this.tabId = this.generateTabId();
                    this.registerTab();
                    this.setupEventListeners();
                },
                
                generateTabId() {
                    return 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                },
                
                registerTab() {
                    if (this.config.use_local_storage) {
                        const tabs = this.getStoredTabs('localStorage') || {};
                        tabs[this.tabId] = {
                            created: Date.now(),
                            lastActivity: Date.now(),
                            url: window.location.href,
                            sessionId: this.sessionId
                        };
                        localStorage.setItem(this.config.storage_key, JSON.stringify(tabs));
                    }
                    
                    if (this.config.use_session_storage) {
                        const tabs = this.getStoredTabs('sessionStorage') || {};
                        tabs[this.tabId] = {
                            created: Date.now(),
                            lastActivity: Date.now(),
                            url: window.location.href
                        };
                        sessionStorage.setItem(this.config.storage_key, JSON.stringify(tabs));
                    }
                },
                
                getStoredTabs(storage) {
                    try {
                        const stored = window[storage].getItem(this.config.storage_key);
                        return stored ? JSON.parse(stored) : {};
                    } catch (e) {
                        return {};
                    }
                },
                
                updateActivity() {
                    const now = Date.now();
                    
                    if (this.config.use_local_storage) {
                        const tabs = this.getStoredTabs('localStorage') || {};
                        if (tabs[this.tabId]) {
                            tabs[this.tabId].lastActivity = now;
                            localStorage.setItem(this.config.storage_key, JSON.stringify(tabs));
                        }
                    }
                    
                    if (this.config.use_session_storage) {
                        const tabs = this.getStoredTabs('sessionStorage') || {};
                        if (tabs[this.tabId]) {
                            tabs[this.tabId].lastActivity = now;
                            sessionStorage.setItem(this.config.storage_key, JSON.stringify(tabs));
                        }
                    }
                },
                
                cleanupTabs() {
                    const timeout = 30 * 60 * 1000; // 30 minutes
                    const cutoff = Date.now() - timeout;
                    
                    ['localStorage', 'sessionStorage'].forEach(storage => {
                        if (!this.config['use_' + storage.toLowerCase()]) return;
                        
                        const tabs = this.getStoredTabs(storage) || {};
                        let hasChanges = false;
                        
                        Object.keys(tabs).forEach(tabId => {
                            if (tabs[tabId].lastActivity < cutoff) {
                                delete tabs[tabId];
                                hasChanges = true;
                            }
                        });
                        
                        if (hasChanges) {
                            window[storage].setItem(this.config.storage_key, JSON.stringify(tabs));
                        }
                    });
                },
                
                setupEventListeners() {
                    // Update activity on user interaction
                    ['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
                        document.addEventListener(event, () => this.updateActivity(), { passive: true });
                    });
                    
                    // Cleanup on page unload
                    window.addEventListener('beforeunload', () => {
                        // Send close notification to server
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon('/tab-guard/close-tab', JSON.stringify({
                                tabId: this.tabId,
                                _token: document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content')
                            }));
                        }
                    });
                    
                    // Periodic cleanup
                    setInterval(() => this.cleanupTabs(), 5 * 60 * 1000); // Every 5 minutes
                    
                    // Focus/blur tracking
                    window.addEventListener('focus', () => this.updateActivity());
                    window.addEventListener('blur', () => this.updateActivity());
                }
            };
            
            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => TabGuard.init());
            } else {
                TabGuard.init();
            }
            
            // Make TabGuard globally available
            window.TabGuard = TabGuard;
        })();
        </script>";
    }
}
