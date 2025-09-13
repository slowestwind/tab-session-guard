# Changelog

All notable changes to `laravel-tab-session-guard` will be documented in this file.

## [Unreleased]

## [1.1.0] - 2025-09-14

### Added
- Laravel 12.x compatibility
- Updated PHP requirement to 8.1+ (aligned with Laravel 12 requirements)
- Enhanced testing support for PHPUnit 11.x
- Updated Orchestra Testbench to support version 10.x

### Changed
- Minimum PHP version requirement updated to 8.1
- Expanded Laravel framework support to include 12.x
- Updated development dependencies for broader version compatibility

## [1.0.0] - 2025-09-05

### Added
- Initial release
- Max tabs control with configurable limits
- Role-based tab restrictions
- Application single-session enforcement
- Global tab limits across application
- Incognito/session bypass prevention
- Graceful alerts and custom response handling
- Comprehensive audit logging
- Client-side JavaScript tracking library
- Flexible configuration system
- Support for Laravel 9.x, 10.x, and 11.x
- Auto-discovery for Laravel service providers
- Customizable views and messages
- API endpoints for tab management
- Session-based and cache-based tab tracking
- Browser storage integration (localStorage/sessionStorage)
- Heartbeat system for active tab tracking
- Automatic cleanup of expired tabs

### Features
- **Global Tab Control**: Set maximum tabs per user across entire application
- **Role-based Limits**: Different tab limits for different user roles (counselor, admin, etc.)
- **Route-specific Rules**: Custom limits for specific routes or route patterns
- **Application Protection**: Ensure only one Application session at a time
- **Security Features**: Prevent incognito bypassing with fingerprinting
- **Flexible Responses**: Support for JSON, redirect, and custom view responses
- **Client-side Tracking**: JavaScript library for browser-side tab management
- **Real-time Updates**: Heartbeat system to track active tabs
- **Audit Logging**: Comprehensive logging of violations and activities
- **Easy Configuration**: Environment variables and config file support

### Configuration Options
- Enable/disable entire package or specific features
- Customize messages and response types
- Configure session timeouts and cleanup intervals
- Set up role-based and route-specific rules
- Control logging verbosity and channels
- Customize UI behavior and styling

### Technical Features
- Laravel auto-discovery support
- Middleware-based protection
- Service provider architecture
- Facade support for easy access
- PHPUnit tests ready
- Composer package structure
- PSR-4 autoloading
- Modern PHP 8.0+ syntax
