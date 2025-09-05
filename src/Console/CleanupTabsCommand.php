<?php

namespace SlowestWind\TabSessionGuard\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use SlowestWind\TabSessionGuard\Services\TabGuardService;

class CleanupTabsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tab-guard:cleanup 
                            {--user= : Specific user ID to cleanup}
                            {--force : Force cleanup without confirmation}
                            {--dry-run : Show what would be cleaned without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup expired tab sessions from storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Tab Guard cleanup...');

        $config = config('tab-session-guard');
        $sessionPrefix = $config['session']['key_prefix'] ?? 'tab_guard_';
        $timeout = $config['session']['tab_timeout'] ?? 1800;
        $isDryRun = $this->option('dry-run');
        $userFilter = $this->option('user');

        if (!$this->option('force') && !$isDryRun) {
            if (!$this->confirm('This will remove expired tab sessions. Continue?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $cleanedCount = 0;
        $totalTabs = 0;

        // Get all session keys that match our prefix
        $sessionKeys = $this->getSessionKeys($sessionPrefix);

        if ($userFilter) {
            $sessionKeys = array_filter($sessionKeys, function($key) use ($sessionPrefix, $userFilter) {
                return $key === $sessionPrefix . $userFilter;
            });
        }

        foreach ($sessionKeys as $sessionKey) {
            $userId = str_replace($sessionPrefix, '', $sessionKey);
            
            if ($isDryRun) {
                $this->line("Would process user: {$userId}");
            } else {
                $this->line("Processing user: {$userId}");
            }

            // Get tabs for this user
            $tabs = Session::get($sessionKey, []);
            $originalCount = count($tabs);
            $totalTabs += $originalCount;

            // Clean expired tabs
            $cleanedTabs = $this->cleanupUserTabs($tabs, $timeout);
            $cleanedInThisSession = $originalCount - count($cleanedTabs);
            $cleanedCount += $cleanedInThisSession;

            if (!$isDryRun && $cleanedInThisSession > 0) {
                Session::put($sessionKey, $cleanedTabs);
            }

            // Also cleanup cache if using cross-session tracking
            if ($config['security']['prevent_incognito_bypass']) {
                $cacheKey = "tab_guard_user_{$userId}";
                $cachedTabs = Cache::get($cacheKey, []);
                
                if (!empty($cachedTabs)) {
                    $cleanedCachedTabs = $this->cleanupUserTabs($cachedTabs, $timeout);
                    if (!$isDryRun) {
                        Cache::put($cacheKey, $cleanedCachedTabs, now()->addSeconds($timeout));
                    }
                }
            }

            if ($cleanedInThisSession > 0) {
                if ($isDryRun) {
                    $this->line("  Would clean {$cleanedInThisSession} expired tabs");
                } else {
                    $this->line("  Cleaned {$cleanedInThisSession} expired tabs");
                }
            }
        }

        if ($isDryRun) {
            $this->info("Dry run completed. Would clean {$cleanedCount} tabs out of {$totalTabs} total.");
        } else {
            $this->info("Cleanup completed. Cleaned {$cleanedCount} expired tabs out of {$totalTabs} total.");
        }

        return 0;
    }

    /**
     * Get session keys that match the tab guard prefix
     */
    protected function getSessionKeys(string $prefix): array
    {
        // This is a simplified implementation
        // In a real scenario, you might need to scan actual session storage
        // or maintain a separate index of active sessions
        
        $keys = [];
        
        // For demonstration, we'll check common user IDs
        // In production, you might want to scan the actual session store
        for ($i = 1; $i <= 1000; $i++) {
            $key = $prefix . $i;
            if (Session::has($key)) {
                $keys[] = $key;
            }
        }
        
        return $keys;
    }

    /**
     * Clean up expired tabs for a user
     */
    protected function cleanupUserTabs(array $tabs, int $timeout): array
    {
        $cutoff = now()->subSeconds($timeout);
        
        return array_filter($tabs, function ($tab) use ($cutoff) {
            $lastActivity = $tab['last_activity'] ?? $tab['created_at'] ?? now();
            return $lastActivity >= $cutoff;
        });
    }
}
