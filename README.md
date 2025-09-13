# Laravel Tab Session Guard

A Laravel package that allows developers to restrict and control how many tabs, sessions, and resource windows a user can open, with a flexible configuration system.

## Features

- **Max Tabs Control**: Limit how many tabs a user can open for specific routes
- **Role-based Restrictions**: Different limits for different user roles  
- **Application Single-Session**: Ensure only one Application can be open at a time
- **Global Tab Limits**: Control total tabs across the entire application
- **Incognito Prevention**: Track via browser localStorage + server sessions
- **Graceful Alerts**: User-friendly messages with customizable responses
- **Audit & Logging**: Optional logging of violations and attempts
- **Fully Configurable**: Toggle features and customize limits per your needs

> 🚀 Inspired by real-world CRM use cases where multiple open sessions lead to errors and performance issues.

## Key Benefits

- 🔒 **Prevent Resource Conflicts**: Stop users from opening multiple instances of the same application/form
- 🛡️ **Reduce Server Load**: Limit concurrent sessions to improve performance
- ⚙️ **Flexible Configuration**: Easily customize limits per role, route, or globally
- 🖥️ **Real-time Tracking**: Monitor active tabs and sessions in real-time
- 🔄 **Seamless Integration**: Middleware support for automatic route protection
- 🎛️ **Granular Control**: Enable/disable features as per your project needs

## Real-World Use Cases

### CRM Application Management
- **Prevent Duplicate Applications**: Stop users from accidentally opening the same application in multiple tabs
- **Role-based Limits**: Users can open up to 3 profile tabs but only 1 application at a time
- **Data Integrity**: Prevent conflicts when multiple tabs try to modify the same application

### Performance Optimization
- **Server Load Management**: Limit the number of concurrent sessions per user
- **Memory Usage Control**: Prevent users from overwhelming the system with too many open tabs
- **Database Connection Limits**: Reduce strain on database resources

### User Experience
- **Guided Workflow**: Keep users focused on current tasks
- **Error Prevention**: Avoid confusion from multiple open instances
- **Graceful Notifications**: User-friendly messages when limits are reached

## Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 9.x, 10.x, 11.x, or 12.x
- **Browser**: Modern browsers with localStorage support

## Installation

Install the package via Composer:

```bash
composer require slowestwind/laravel-tab-session-guard
```

### Laravel Auto-Discovery

The package will automatically register its service provider and facade.

For Laravel versions < 5.5, add the service provider to `config/app.php`:

```php
'providers' => [
    // Other providers...
    SlowestWind\TabSessionGuard\Providers\TabSessionGuardServiceProvider::class,
],
```

And optionally add the facade:

```php
'aliases' => [
    // Other aliases...
    'TabGuard' => SlowestWind\TabSessionGuard\Facades\TabGuard::class,
],
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=tab-guard-config
```

Optionally publish views and assets:

```bash
php artisan vendor:publish --tag=tab-guard-views
php artisan vendor:publish --tag=tab-guard-assets
```

## Configuration Options

## Complete Configuration Reference

```php
<?php
// config/tab-session-guard.php

return [
    // Enable/Disable whole package
    'enabled' => env('TAB_GUARD_ENABLED', true),

    // Global max tabs across app  
    'global_max_tabs' => env('TAB_GUARD_GLOBAL_MAX', 5),

    // Global settings
    'global' => [
        'enabled' => env('TAB_GUARD_GLOBAL_ENABLED', true),
        'max_tabs' => env('TAB_GUARD_GLOBAL_MAX', 5),
        'excluded_routes' => [
            'login',
            'logout', 
            'password.*',
            'register',
            'api.*', // Exclude API routes
        ],
    ],

    // Role-based rules
    'roles' => [
        'user' => [
            'profile' => [
                'enabled' => true,
                'max_tabs' => 3,
                'routes' => [
                    'profile.show',
                    'profile.edit',
                    'profiles.*',
                ],
            ],
            'application' => [
                'enabled' => true,
                'max_tabs' => 1,
                'routes' => [
                    'application.*',
                    'applications.*',
                ],
            ],
        ],
        'admin' => [
            'profile' => [
                'enabled' => true,
                'max_tabs' => 10,
                'routes' => [
                    'profile.*',
                    'profiles.*',
                ],
            ],
        ],
    ],

    // Route-specific rules (independent of roles)
    'routes' => [
        'application.*' => [
            'enabled' => true,
            'max_tabs' => 1,
            'message' => 'Application can only be opened once per session.',
        ],
        'sensitive.*' => [
            'enabled' => true,
            'max_tabs' => 2,
        ],
    ],

    // Session tracking
    'session' => [
        'key_prefix' => 'tab_guard_',
        'cleanup_interval' => 300, // 5 minutes
        'tab_timeout' => 1800, // 30 minutes
    ],

    // Browser storage settings
    'browser_storage' => [
        'use_local_storage' => true,
        'use_session_storage' => true,
        'storage_key' => 'laravel_tab_guard',
    ],

    // Security settings
    'security' => [
        'prevent_incognito_bypass' => true,
        'track_user_agent' => true,
        'track_ip' => false,
        'fingerprint_check' => true,
    ],

    // Logging
    'logging' => [
        'enabled' => env('TAB_GUARD_LOGGING', true),
        'channel' => env('TAB_GUARD_LOG_CHANNEL', 'daily'),
        'log_attempts' => true,
        'log_violations' => true,
        'log_cleanup' => false,
    ],

    // Response behavior
    'response' => [
        'type' => 'json', // 'json', 'redirect', 'view'
        'redirect_route' => 'dashboard',
        'view' => 'tab-guard::limit-exceeded',
        'json_response' => [
            'success' => false,
            'message' => 'Tab limit exceeded',
            'code' => 'TAB_LIMIT_EXCEEDED',
        ],
    ],

    // Messages
    'messages' => [
        'global_limit_exceeded' => 'You have reached the maximum number of allowed tabs (:max) for this application.',
        'role_limit_exceeded' => 'You have reached the maximum number of allowed tabs (:max) for this section.',
        'route_limit_exceeded' => 'You have reached the maximum number of allowed tabs (:max) for this page.',
        'application_limit' => 'Application can only be opened once per session.',
        'session_conflict' => 'This tab conflicts with another active session.',
        'generic_limit' => 'You have reached the maximum number of allowed tabs for this section.',
    ],

    // UI settings
    'ui' => [
        'show_alert' => true,
        'alert_type' => 'warning', // 'error', 'warning', 'info'
        'auto_close_alert' => true,
        'alert_duration' => 5000, // milliseconds
        'custom_css' => null,
        'custom_js' => null,
    ],

    // Development settings
    'debug' => [
        'enabled' => env('TAB_GUARD_DEBUG', false),
        'log_all_requests' => false,
        'show_debug_info' => false,
    ],
];
```

## Usage

### Automatic Protection

The middleware is automatically applied to all web routes when enabled. You can disable auto-application and manually apply it:

```php
// In routes/web.php
Route::group(['middleware' => 'tab.guard'], function () {
    Route::get('/profile/{id}', 'ProfileController@show')->name('profile.show');
    Route::get('/application/{id}', 'ApplicationController@show')->name('application.show');
});
```

### Using the Service

```php
use SlowestWind\TabSessionGuard\Services\TabGuardService;

class ProfileController extends Controller
{
    public function show(Request $request, TabGuardService $tabGuard)
    {
        // Check if request should be guarded
        if ($tabGuard->shouldGuard($request)) {
            $validation = $tabGuard->validateTabLimits($request);
            
            if (!$validation['allowed']) {
                // Handle limit exceeded
                return response()->json([
                    'error' => $validation['message']
                ], 403);
            }
        }
        
        // Continue with normal logic
        return view('profile.show');
    }
}
```

### Using the Facade

```php
use TabGuard;

// Get tab information for current user
$tabInfo = TabGuard::getTabInfo(auth()->id());

// Manually close a tab
TabGuard::closeTab(auth()->id(), $tabId);

// Check if request should be guarded
if (TabGuard::shouldGuard($request)) {
    // Apply custom logic
}
```

### JavaScript Integration

Include the JavaScript library for client-side tracking:

```html
<script src="{{ asset('vendor/tab-guard/tab-guard.js') }}"></script>
<script>
    // The library auto-initializes, but you can also manually create instances
    const tabGuard = new LaravelTabGuard({
        debug: true,
        heartbeatInterval: 30000,
        storageKey: 'my_custom_tab_guard'
    });
    
    // Get current tab information
    console.log(tabGuard.getTabInfo());
</script>
```

### Adding Meta Tags

For optimal client-side tracking, add these meta tags to your layout:

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="user-id" content="{{ auth()->id() }}">
<meta name="session-id" content="{{ session()->getId() }}">
```

## Advanced Configuration

### Environment Variables

You can control key settings via environment variables:

```env
# Enable/disable the package
TAB_GUARD_ENABLED=true

# Global tab limits
TAB_GUARD_GLOBAL_MAX=5

# Logging settings
TAB_GUARD_LOGGING=true
TAB_GUARD_LOG_CHANNEL=daily
TAB_GUARD_DEBUG=false
```

### Role-based Configuration Examples

#### Healthcare/CRM Application
```php
'roles' => [
    'user' => [
        'patient_profile' => [
            'enabled' => true,
            'max_tabs' => 3,
            'routes' => ['patient.show', 'patient.edit', 'patients.*'],
        ],
        'application_form' => [
            'enabled' => true,
            'max_tabs' => 1, // Only one application at a time
            'routes' => ['application.*', 'forms.application.*'],
        ],
    ],
    'supervisor' => [
        'patient_profile' => [
            'enabled' => true,
            'max_tabs' => 10, // Supervisors can handle more
            'routes' => ['patient.*', 'patients.*'],
        ],
        'reports' => [
            'enabled' => true,
            'max_tabs' => 5,
            'routes' => ['reports.*', 'analytics.*'],
        ],
    ],
    'admin' => [
        // Admins have higher limits or no limits
        'all_modules' => [
            'enabled' => false, // Disable for admins
        ],
    ],
],
```

#### E-commerce Platform
```php
'roles' => [
    'customer_service' => [
        'order_management' => [
            'enabled' => true,
            'max_tabs' => 5,
            'routes' => ['orders.*', 'order.*'],
        ],
        'customer_profile' => [
            'enabled' => true,
            'max_tabs' => 3,
            'routes' => ['customers.*', 'customer.*'],
        ],
    ],
    'inventory_manager' => [
        'product_management' => [
            'enabled' => true,
            'max_tabs' => 8,
            'routes' => ['products.*', 'inventory.*'],
        ],
    ],
],
```

### Custom Response Handling

#### JSON Response
```php
'response' => [
    'type' => 'json',
    'json_response' => [
        'success' => false,
        'message' => 'Tab limit exceeded',
        'code' => 'TAB_LIMIT_EXCEEDED',
    ],
],
```

#### Redirect Response
```php
'response' => [
    'type' => 'redirect',
    'redirect_route' => 'dashboard',
],
```

#### Custom View Response
```php
'response' => [
    'type' => 'view',
    'view' => 'custom.limit-exceeded',
],
```

### Security Features

```php
'security' => [
    'prevent_incognito_bypass' => true,
    'track_user_agent' => true,
    'track_ip' => false,
    'fingerprint_check' => true,
],
```

### Session Management

```php
'session' => [
    'key_prefix' => 'tab_guard_',
    'cleanup_interval' => 300, // 5 minutes
    'tab_timeout' => 1800, // 30 minutes
],
```

## API Endpoints

The package provides several API endpoints for JavaScript integration:

- `POST /tab-guard/close-tab` - Close a specific tab
- `GET /tab-guard/tab-info` - Get current tab information
- `POST /tab-guard/heartbeat` - Send tab heartbeat
- `GET /tab-guard/status` - Get guard status

## Console Commands

The package includes Artisan commands for maintenance:

```bash
# Cleanup expired tabs
php artisan tab-guard:cleanup

# Dry run to see what would be cleaned
php artisan tab-guard:cleanup --dry-run

# Cleanup specific user
php artisan tab-guard:cleanup --user=123

# Force cleanup without confirmation
php artisan tab-guard:cleanup --force
```

## Performance Considerations

### Server-side Performance
- **Session Storage**: Tab data is stored in Laravel sessions (minimal overhead)
- **Cache Integration**: Optional cache layer for cross-session tracking
- **Cleanup Jobs**: Built-in cleanup commands to prevent data accumulation
- **Middleware Efficiency**: Lightweight middleware with configurable bypass routes

### Client-side Performance  
- **JavaScript Library**: ~15KB minified, non-blocking initialization
- **Storage APIs**: Uses efficient browser localStorage/sessionStorage
- **Heartbeat System**: Configurable intervals to balance accuracy vs. performance
- **Event Delegation**: Minimal DOM manipulation and event listeners

### Scaling Considerations
```php
// For high-traffic applications, consider:
'session' => [
    'cleanup_interval' => 600, // Increase cleanup interval
    'tab_timeout' => 900, // Shorter timeout for faster cleanup
],

'browser_storage' => [
    'use_local_storage' => false, // Disable for privacy-focused apps
    'use_session_storage' => true, // Keep only session-based tracking
],

'logging' => [
    'enabled' => false, // Disable in production if not needed
    'log_attempts' => false, // Reduce log volume
],
```

## Security Features

### Data Protection
- **Session-based Tracking**: No sensitive data stored in browser
- **CSRF Protection**: All API endpoints protected with CSRF tokens
- **User Context**: Tab tracking isolated per authenticated user
- **Automatic Cleanup**: Expired sessions automatically removed

### Privacy Considerations
- **Optional Browser Storage**: Can be disabled entirely
- **No Personal Data**: Only tracks tab counts and route patterns
- **Configurable Logging**: Choose what activities to log
- **User Agent Tracking**: Optional and can be disabled

### Security Best Practices
```php
// Recommended production settings
'security' => [
    'prevent_incognito_bypass' => true,
    'track_user_agent' => true,
    'track_ip' => false, // Disable if not needed for privacy
    'fingerprint_check' => true,
],

'browser_storage' => [
    'use_local_storage' => false, // More secure
    'use_session_storage' => true,
],

'debug' => [
    'enabled' => false, // Always disable in production
    'log_all_requests' => false,
    'show_debug_info' => false,
],
```

## Events and Logging

The package logs various events:

- Tab registrations
- Limit violations
- Tab closures
- Heartbeat failures

```php
// Log levels
'logging' => [
    'enabled' => true,
    'channel' => 'daily',
    'log_attempts' => true,
    'log_violations' => true,
    'log_cleanup' => false,
],
```

## Testing

Run the package tests:

```bash
# Install dependencies
composer install

# Run PHPUnit tests
composer test

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage
```

### Manual Testing

You can test the package functionality with these examples:

#### Test Application Single-Session
1. Open your application route (e.g., `/application/1`)
2. Try to open another application in a new tab (e.g., `/application/2`)
3. The second tab should be blocked with a limit exceeded message

#### Test Role-based Limits
1. Login as a user
2. Open 3 profile tabs (should work)
3. Try to open a 4th profile tab (should be blocked)
4. Test application tab limit (only 1 allowed)

#### Test Global Limits
1. Open multiple different types of pages
2. When you reach the global limit (default 5), new tabs should be blocked
3. Check the browser console and network tabs for API calls

## Troubleshooting

### Common Issues

#### Package Not Working
```bash
# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Republish package assets
php artisan vendor:publish --tag=tab-guard-config --force
```

#### JavaScript Not Loading
```bash
# Publish assets
php artisan vendor:publish --tag=tab-guard-assets

# Check if files are published
ls -la public/vendor/tab-guard/
```

#### Sessions Not Tracking
1. Ensure session driver is properly configured
2. Check if cookies are enabled in browser
3. Verify CSRF token is included in requests

#### Role Detection Issues
```php
// In your User model, ensure role detection works
public function getRoleNames()
{
    // Return array of role names
    return $this->roles->pluck('name')->toArray();
    
    // Or if using Spatie Permission
    return $this->getRoleNames()->toArray();
}
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email slowestwind@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## Credits

- [SlowestWind](https://github.com/slowestwind)
- [All Contributors](../../contributors)
