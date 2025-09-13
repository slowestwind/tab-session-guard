# Laravel Tab Session Guard - Integration Guide

This comprehensive guide shows how to integrate Laravel Tab Session Guard into your existing Laravel application.

## Step 1: Installation

```bash
composer require slowestwind/laravel-tab-session-guard
```

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=tab-guard-config
php artisan vendor:publish --tag=tab-guard-views
php artisan vendor:publish --tag=tab-guard-assets
```

## Step 3: Environment Configuration

Add to your `.env` file:

```env
TAB_GUARD_ENABLED=true
TAB_GUARD_GLOBAL_MAX=5
TAB_GUARD_LOGGING=true
TAB_GUARD_LOG_CHANNEL=daily
TAB_GUARD_DEBUG=false
```

## Step 4: Controller Implementation

See ControllerExamples.php for complete controller code examples.

## Step 5: Route Configuration

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

// Option 1: Apply middleware to specific routes
Route::group(['middleware' => ['auth', 'tab.guard']], function () {
    Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/{id}/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/application/{id}', [ApplicationController::class, 'show'])->name('application.show');
});

// Option 2: The middleware is automatically applied to all web routes
// if enabled in config. You can exclude specific routes if needed.
```

## Step 6: Configuration File

The configuration file will be published to `config/tab-session-guard.php`. 
Key configuration options include:

- Global tab limits
- Role-based restrictions
- Route-specific limits
- Response handling
- Logging settings

## Step 7: Frontend Integration

Add to your layout file (resources/views/layouts/app.blade.php):

```html
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
```

## Step 8: JavaScript Integration

```javascript
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
```

## Step 9: Testing

Create tests in your `tests/Feature` directory. See the package documentation for complete testing examples.

## Step 10: Advanced Usage

### Custom Middleware

You can create custom middleware to extend the package functionality:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use TabGuard;

class CustomTabGuard
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('premium')) {
            // Premium users get higher limits
            // Modify config dynamically here if needed
        }
        
        return $next($request);
    }
}
```

For complete code examples, see the ControllerExamples.php file in this directory.
