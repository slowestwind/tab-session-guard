{{-- 
    Example Laravel Application Integration
    
    This file shows how to integrate Laravel Tab Session Guard
    into your existing Laravel application.
    
    1. Install the package
    composer require slowestwind/laravel-tab-session-guard
    
    2. Publish configuration
    php artisan vendor:publish --tag=tab-guard-config
    
    3. Add to your layout file (resources/views/layouts/app.blade.php)
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="session-id" content="{{ session()->getId() }}">
    <title>Your App</title>
</head>
<body>
    <div id="app">
        @yield('content')
    </div>
    
    <!-- Tab Guard JavaScript -->
    <script src="{{ asset('vendor/tab-guard/tab-guard.js') }}"></script>
    <script>
        // Optional: Custom configuration
        if (window.LaravelTabGuard) {
            window.tabGuardInstance = new LaravelTabGuard({
                debug: {{ config('app.debug') ? 'true' : 'false' }},
                heartbeatInterval: 30000,
                storageKey: 'my_app_tab_guard'
            });
        }
    </script>
</body>
</html>

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SlowestWind\TabSessionGuard\Services\TabGuardService;
use TabGuard; // Using facade

class ProfileController extends Controller
{
    /**
     * Using dependency injection
     */
    public function show(Request $request, $id, TabGuardService $tabGuard)
    {
        // Option 1: Manual check
        if ($tabGuard->shouldGuard($request)) {
            $validation = $tabGuard->validateTabLimits($request);
            
            if (!$validation['allowed']) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => $validation['message'],
                        'validation' => $validation
                    ], 403);
                }
                
                return redirect()->route('dashboard')
                    ->with('error', $validation['message']);
            }
        }
        
        $profile = Profile::findOrFail($id);
        return view('profile.show', compact('profile'));
    }
    
    /**
     * Using facade
     */
    public function edit(Request $request, $id)
    {
        // Get current tab information
        $tabInfo = TabGuard::getTabInfo(auth()->id());
        
        // Check if we're approaching limits
        if ($tabInfo['total_tabs'] >= $tabInfo['global_limit'] - 1) {
            session()->flash('warning', 'You are approaching your tab limit.');
        }
        
        $profile = Profile::findOrFail($id);
        return view('profile.edit', compact('profile', 'tabInfo'));
    }
}

// 5. In your routes (routes/web.php)

use Illuminate\Support\Facades\Route;

// Option 1: Apply middleware to specific routes
Route::group(['middleware' => ['auth', 'tab.guard']], function () {
    Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/{id}/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/application/{id}', [ApplicationController::class, 'show'])->name('application.show');
});

// Option 2: The middleware is automatically applied to all web routes
// if enabled in config. You can exclude specific routes if needed.

// 6. In your configuration (config/tab-session-guard.php)

return [
    'enabled' => env('TAB_GUARD_ENABLED', true),
    
    'global' => [
        'enabled' => true,
        'max_tabs' => env('TAB_GUARD_GLOBAL_MAX', 5),
        'excluded_routes' => [
            'login',
            'logout',
            'password.*',
            'register',
            'api.*', // Exclude API routes
        ],
    ],
    
    'roles' => [
        'counselor' => [
            'profile' => [
                'enabled' => true,
                'max_tabs' => 3,
                'routes' => [
                    'profile.show',
                    'profile.edit',
                ],
            ],
            'application' => [
                'enabled' => true,
                'max_tabs' => 1,
                'routes' => [
                    'application.show',
                    'application.edit',
                ],
            ],
        ],
        'admin' => [
            'profile' => [
                'enabled' => true,
                'max_tabs' => 10,
                'routes' => ['profile.*'],
            ],
        ],
    ],
    
    'routes' => [
        'application.*' => [
            'enabled' => true,
            'max_tabs' => 1,
            'message' => 'Application can only be opened once per session.',
        ],
    ],
    
    'response' => [
        'type' => 'json', // For AJAX requests
        'redirect_route' => 'dashboard',
        'json_response' => [
            'success' => false,
            'message' => 'Tab limit exceeded',
            'code' => 'TAB_LIMIT_EXCEEDED',
        ],
    ],
    
    'logging' => [
        'enabled' => env('TAB_GUARD_LOGGING', true),
        'channel' => env('TAB_GUARD_LOG_CHANNEL', 'daily'),
        'log_violations' => true,
    ],
];

// 7. Environment variables (.env)
/*
TAB_GUARD_ENABLED=true
TAB_GUARD_GLOBAL_MAX=5
TAB_GUARD_LOGGING=true
TAB_GUARD_LOG_CHANNEL=daily
TAB_GUARD_DEBUG=false
*/

// 8. Optional: Custom middleware for specific logic
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use TabGuard;

class CustomTabGuard
{
    public function handle(Request $request, Closure $next)
    {
        // Custom logic before tab guard
        $user = auth()->user();
        
        if ($user && $user->hasRole('premium')) {
            // Premium users get higher limits
            // You could modify config dynamically here
        }
        
        return $next($request);
    }
}

// 9. Using in JavaScript for dynamic UI updates
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get tab information
    fetch('/tab-guard/tab-info')
        .then(response => response.json())
        .then(data => {
            console.log('Current tabs:', data.total_tabs);
            console.log('Max allowed:', data.global_limit);
            
            // Update UI based on tab count
            if (data.total_tabs >= data.global_limit - 1) {
                showTabLimitWarning();
            }
        });
    
    // Listen for tab limit events
    document.addEventListener('tab-limit-exceeded', function(event) {
        alert('Tab limit exceeded: ' + event.detail.message);
    });
});

function showTabLimitWarning() {
    const warning = document.createElement('div');
    warning.className = 'alert alert-warning';
    warning.textContent = 'You are approaching your tab limit. Please close some tabs.';
    document.body.prepend(warning);
}
</script>

<?php
// 10. Testing the package

use Tests\TestCase;
use SlowestWind\TabSessionGuard\Services\TabGuardService;

class TabGuardTest extends TestCase
{
    public function test_tab_limit_is_enforced()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Simulate opening multiple tabs
        for ($i = 0; $i < 6; $i++) {
            $response = $this->get('/profile/1');
            if ($i < 5) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(403);
            }
        }
    }
    
    public function test_application_single_session()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // First Application tab should work
        $response = $this->get('/application/1');
        $response->assertStatus(200);
        
        // Second Application tab should be blocked
        $response = $this->get('/application/2');
        $response->assertStatus(403);
    }
}
?>
