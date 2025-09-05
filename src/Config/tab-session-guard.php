<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tab & Session Guard Configuration
    |--------------------------------------------------------------------------
    */

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
        ],
    ],

    // Role-based rules
    'roles' => [
        'counselor' => [
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
