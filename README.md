# WP Safe Mode v1.1

A powerful, self-hosted administration and development tool for WordPress that helps you fix, manage, and optimize your WordPress site outside of WordPress itself.

## 🚀 Overview

WP Safe Mode is an essential tool for WordPress end users and developers. It provides a comprehensive set of features for managing WordPress installations, troubleshooting issues, performing backups, and optimizing performance - all without needing to access the WordPress admin panel.

**Managed by:** [imbamarketing.com](http://imbamarketing.com) and [cloud-industry.com](http://cloud-industry.com)

## ✨ Key Features

### Core Management
- **WordPress Configuration** - Edit wp-config.php constants and variables
- **Plugin Management** - Activate/deactivate plugins without accessing WordPress
- **Theme Management** - Switch themes and download default themes
- **Database Management** - Full and partial database backups (SQL/CSV formats)
- **File Backups** - Complete WordPress installation backups with ZIP archiving
- **Error Log Viewer** - View and search PHP error logs with formatted display

### Advanced Features
- **.htaccess Editor** - Edit and generate .htaccess files with common configurations
- **robots.txt Manager** - Create and edit robots.txt files
- **Quick Actions** - One-click actions for common tasks:
  - Optimize database tables
  - Enable/disable maintenance mode
  - Delete spam/unapproved comments
  - Delete post revisions
  - Change site URL and home URL
  - Scan WordPress core files
- **Auto Backup** - Configure automatic backups for files and database
- **Search & Replace** - Database search and replace functionality

### 📊 New Management Features (v1.0)
- **Activity Log** - Complete audit trail system:
  - Track all user actions and system events
  - Filter logs by action type and user
  - View activity statistics
  - Auto-cleanup of old logs
  - IP address tracking
  - JSON-based log storage (last 1000 entries)

- **Email Testing** - Comprehensive email diagnostics:
  - Test WordPress `wp_mail()` function
  - Test PHP `mail()` function
  - Check SMTP configuration
  - Detect email plugins (WP Mail SMTP, etc.)
  - View email configuration details
  - Fallback support when WordPress not loaded

- **Security Scanner** - Automated security auditing:
  - File permission checks (wp-config.php, .htaccess)
  - WordPress version comparison with latest
  - Plugin security analysis
  - Database security validation (table prefix)
  - wp-config.php security settings check
  - .htaccess security rules validation
  - User account security analysis
  - Security score calculation (0-100)
  - Detailed recommendations for fixes

- **Performance Profiler** - Real-time performance analysis:
  - Server metrics (memory, disk, execution time)
  - PHP metrics (version, OPcache status, extensions)
  - Database metrics (table count, sizes, optimization status)
  - WordPress metrics (version, plugins, themes, posts, comments)
  - Automatic performance recommendations
  - Resource usage tracking
  - Optimization suggestions

- **Media Library Manager** - WordPress media management:
  - Browse media files with pagination
  - Search and filter media files
  - View file details (size, type, dimensions)
  - Delete media files
  - Media statistics and analytics
  - Group by file type (image, video, audio, PDF)
  - File existence verification

- **Database Optimizer** - Advanced database maintenance:
  - Analyze all database tables
  - Find orphaned data (postmeta, commentmeta, term relationships)
  - Detect duplicate data
  - Find unused data (revisions, spam comments, trashed posts, expired transients)
  - One-click table optimization
  - Clean orphaned data
  - Clean post revisions (configurable keep count)
  - Clean expired transients
  - Generate optimization recommendations

### 🔒 Security Features
- **CSRF Protection** - All forms protected with CSRF tokens
- **Rate Limiting** - Brute force protection for login attempts
- **Input Validation** - Comprehensive input sanitization and validation
- **SQL Injection Prevention** - PDO parameter binding throughout
- **Secure File Operations** - Path validation and secure file handling
- **Secure Sessions** - HTTP-only and secure cookie settings

### 🤖 AI-Powered Features (NEW!)
- **AI Chat Assistant** - Interactive troubleshooting with OpenAI GPT-4
- **Error Log Analysis** - AI-powered analysis of PHP errors with solutions
- **Plugin Conflict Detection** - Automatic detection of plugin conflicts
- **Security Analysis** - Comprehensive security audit and recommendations
- **Performance Optimization** - AI-suggested performance improvements
- **Error Explanation** - Simple explanations of complex errors

### 🎨 Modern UI/UX
- **AdminLTE 3 Design** - Modern, responsive admin interface
- **Single Page Application** - No page reloads, smooth navigation
- **Mobile Responsive** - Fully optimized for mobile devices
- **Real-time Updates** - AJAX-powered dynamic content loading
- **Loading States** - Visual feedback during operations
- **Bootstrap 4** - Modern UI components and styling

## 📋 Requirements

- **Web Server:** Apache (recommended) or Nginx
- **PHP:** 7.4 or newer (PHP 8.x recommended)
- **MySQL:** 5.7 or newer (or MariaDB 10.2+)
- **Permissions:** Write access to WordPress directory and backup storage
- **WordPress:** Functional WordPress installation
- **Database:** MySQL user with all privileges

### Optional for AI Features
- **OpenAI API Key** - For AI Assistant features (get from [platform.openai.com](https://platform.openai.com/api-keys))

## 🛠️ Installation

1. **Download/Clone** the repository to your web server
   ```bash
   git clone https://github.com/magnetoid/wpsafemode.git
   ```

2. **Place in WordPress Directory**
   - Copy the `wpsafemode` folder to your WordPress root directory
   - Example: `http://www.yourdomain.com/wpsafemode/`

3. **Configure Settings**
   - Copy `settings.sample.php` to `settings.php`
   - Edit `settings.php` and configure:
     - `$settings['wp_dir']` - Path to WordPress directory (default: `../`)
     - `$settings['sfstore']` - Backup storage directory (default: `sfstore/`)

4. **Set Permissions**
   - Ensure backup directory is writable
   - Ensure wp-config.php is readable/writable

5. **Access the Tool**
   - Navigate to: `http://www.yourdomain.com/wpsafemode/`
   - Default login: `demo` / `demo`
   - **Important:** Change default credentials in Global Settings immediately!

## 🔧 Configuration

### Basic Configuration
Edit `settings.php`:
```php
$settings['wp_dir'] = '../';  // WordPress directory path
$settings['sfstore'] = 'sfstore/';  // Backup storage directory
```

### AI Features Setup
1. Get an OpenAI API key from [platform.openai.com](https://platform.openai.com/api-keys)
2. Go to **Global Settings** in WP Safe Mode
3. Enter your OpenAI API key
4. Save settings

## 📡 API Endpoints

WP Safe Mode provides a comprehensive REST API for all features:

### Activity Log API
- `GET /api/activity-log?action=list&limit=100` - Get activity logs
- `GET /api/activity-log?action=statistics` - Get activity statistics
- `POST /api/activity-log?action=clear&days=30` - Clear old logs

### Email Testing API
- `GET /api/email?action=info` - Get email configuration
- `POST /api/email?action=test` - Test WordPress email (requires: to, subject, message)
- `POST /api/email?action=test_php` - Test PHP mail() function

### Security Scanner API
- `GET /api/security-scanner?action=scan` - Run comprehensive security scan

### Performance Profiler API
- `GET /api/performance?action=metrics` - Get performance metrics

### Media Library API
- `GET /api/media?action=list&limit=50&offset=0&search=term` - List media files
- `GET /api/media?action=statistics` - Get media statistics
- `POST /api/media?action=delete` - Delete media file (requires: file_id)

### Database Optimizer API
- `GET /api/database-optimizer?action=analyze` - Analyze database
- `POST /api/database-optimizer?action=optimize` - Optimize all tables
- `POST /api/database-optimizer?action=clean_orphaned` - Clean orphaned data
- `POST /api/database-optimizer?action=clean_revisions&keep=3` - Clean revisions
- `POST /api/database-optimizer?action=clean_transients` - Clean expired transients

### Other APIs
- `GET /api/system-health` - Get system health metrics
- `GET /api/file-manager?action=list&path=...` - File manager operations
- `GET /api/users?action=list` - User management operations
- `GET /api/cron?action=list` - Cron job management

All API endpoints return JSON responses with `success`, `message`, and `data` fields.

## 📁 Project Structure

```
wpsafemode/
├── assets/
│   ├── css/
│   │   └── admin-custom.css      # Custom AdminLTE styles
│   └── js/
│       ├── app.js                 # Main application
│       ├── admin-custom.js        # Admin customizations
│       └── modules/               # Feature modules (16 modules)
├── controller/
│   ├── main.controller.php        # Base controller
│   ├── dashboard.controller.php   # Main dashboard
│   ├── api.controller.php        # API endpoints
│   └── ai.controller.php          # AI features
├── model/                         # Data models
├── view/                          # View templates
│   ├── *-admin.php               # AdminLTE views
│   └── *.php                     # Legacy fallback views
├── core/
│   ├── Config.php                 # Configuration management
│   ├── Database.php               # Database connection
│   ├── Response.php               # API response handler
│   ├── InputValidator.php         # Input validation (single source of truth)
│   ├── Logger.php                 # Logging system
│   └── Cache.php                  # Caching system
├── security/                      # Security classes
│   ├── SecurityFixes.php          # Security utilities
│   ├── CSRFProtection.php         # CSRF token management
│   └── RateLimiter.php            # Rate limiting
├── services/
│   ├── AIService.php              # AI service
│   ├── ActivityLogService.php     # Activity logging
│   ├── EmailService.php            # Email testing
│   ├── SecurityScannerService.php # Security scanning
│   ├── PerformanceProfilerService.php # Performance profiling
│   ├── MediaLibraryService.php    # Media management
│   ├── DatabaseOptimizerService.php # Database optimization
│   ├── SystemHealthService.php    # System health monitoring
│   ├── FileManagerService.php    # File management
│   ├── UserManagementService.php  # User management
│   └── CronService.php            # Cron job management
└── settings.php                   # Configuration
```

## 🎯 Technology Stack

- **Backend:** PHP 7.4+ with PDO (PHP 8.0+ recommended, fully PHP 8.1+ compatible)
- **Frontend:** JavaScript (ES6+), AdminLTE 3, Bootstrap 4, Material Design 3
- **Framework:** Custom MVC architecture with service layer
- **Database:** MySQL 5.7+ / MariaDB 10.2+ with PDO
- **Security:** CSRF protection, rate limiting, input validation, SQL injection prevention
- **AI:** OpenAI GPT-4 API integration
- **Icons:** Font Awesome 6, Material Icons
- **Architecture:** Service-oriented design with dependency injection support
- **Caching:** In-memory caching system for performance optimization
- **Code Standards:** PSR-compliant naming conventions with strict type hints

## 🔐 Security

WP Safe Mode includes comprehensive security features:

- ✅ **SQL Injection Prevention** - PDO parameter binding throughout
- ✅ **CSRF Token Protection** - All forms protected with CSRF tokens
- ✅ **Rate Limiting** - Brute force protection for login attempts (5 attempts/5 minutes)
- ✅ **Input Validation** - Centralized `InputValidator` class with strict type checking
- ✅ **XSS Prevention** - Output escaping and input sanitization
- ✅ **Secure File Operations** - Path validation and secure file handling
- ✅ **Path Traversal Protection** - Realpath validation for all file operations
- ✅ **Secure Session Management** - HTTP-only and secure cookie settings
- ✅ **PHP 8.0+ Compatibility** - Modern error handling with `Throwable`
- ✅ **No Circular Dependencies** - Clean architecture prevents security gaps

## 📱 Mobile Support

- Fully responsive design
- Mobile-optimized sidebar
- Touch-friendly interface
- Optimized loading screens
- Mobile-specific CSS optimizations

## 🚀 Performance

- **Single Page Application (SPA)** - No page reloads, smooth navigation
- **AJAX-Powered Loading** - Dynamic content loading without full page refresh
- **Efficient Caching** - In-memory caching system for repeated operations
- **Optimized Asset Loading** - Minimized HTTP requests
- **Database Optimization** - Query optimization and prepared statements
- **Lazy Loading** - Load resources only when needed
- **Service Architecture** - Modular design for better code organization and maintainability
- **Clean Code** - Reduced codebase size through refactoring (43 lines removed in v1.0.1)
- **Type Safety** - Strict type hints reduce runtime overhead
- **No Code Duplication** - Single source of truth pattern eliminates redundancy

## 📝 Change Log

### v1.1.0 (Current) - Advanced Recovery & Security
**Released:** December 2025

#### 🛡️ Advanced Security & Recovery
- **Malware Scanner** (Signature Match)
  - Scans `.php` files for suspicious functions (`eval`, `base64_decode`, `shell_exec`)
  - Identifies known malware patterns (obfuscated code, backdoors)
  - Displays severity levels (Critical, Warning, Info)
  - Direct links to inspect suspicious files in File Manager
  
- **Emergency Admin Link** ("Magic Login")
  - Generates a temporary, secure one-time login link
  - Bypasses password requirements (useful if locked out or hacked)
  - Uses secure token validation with expiration

- **Database Inspector**
  - Full table browser with pagination
  - View table schema (columns, indexes)
  - Execute custom SQL queries via API
  - Integrated into new "Database Inspector" view
  
#### 🎨 UI/UX & System Health
- **Dark Mode Support**
  - Toggle between Light and Dark themes
  - Persists preference via local storage
  
- **Enhanced System Health**
  - **Database Insights**: Identifies largest tables and fragmented tables
  - **Auto-Security Scan**: AI-powered security analysis integration
  
- **Secure Architecture**
  - **Secure Backups**: Replaced `SELECT INTO OUTFILE` with PHP CSV generation (safer, works without FILE privilege)
  - **CSRF Protection**: Extended to all AI and API endpoints
  
### v1.0.2 - Service Layer & Security
**Released:** December 2025

#### 🏗️ Architecture & Refactoring
- **Service Layer Expansion**
  - Created `ThemeService` to handle all theme-related operations
  - Enhanced `PluginService` with complete plugin management capabilities
  - Refactored `DashboardController` to use dependency injection for services
  - Removed direct model dependencies from controller
  - Improved separation of concerns

- **Code Quality**
  - Added strict type hints to `DashboardController` methods
  - Added return type declarations (`: void`, `: bool`, `: array`)
  - Fixed lint errors and improved code standards compliance
  - Removed unnecessary serialization logic

#### 🔒 Security Improvements
- **CSRF Protection**
  - Implemented CSRF token validation for all `DashboardController` actions
  - Secured plugin and theme management forms
  - Secured backup and settings operations

### v1.0.1 - Code Quality & Refactoring
**Released:** November 2025

#### 🔧 Bug Fixes
- **Fixed undefined class references** - Resolved fatal errors caused by `SecureInput` class references
- **Fixed circular dependencies** - Eliminated circular references between `InputValidator` and `SecureInput`
- **Fixed syntax errors** - Resolved missing closing braces in `UserManagementService`
- **Fixed method compatibility** - Updated all deprecated method calls to use new naming conventions

#### ♻️ Major Refactoring
- **Input Validation Architecture**
  - Consolidated all input validation into `InputValidator` as single source of truth
  - Converted `SecureInput` to legacy compatibility wrapper
  - Added `filename` and `table_name` sanitization types
  - Removed code duplication (43 lines of code removed)
  - Enhanced validation with null/empty checks

- **Method Naming Standardization (PSR Compliance)**
  - Converted all service methods from `snake_case` to `camelCase`
  - Added strict type hints to all method signatures
  - Added return type declarations for better type safety
  - Improved PHPDoc comments throughout

- **Service Method Updates:**
  ```
  ActivityLogService:
    get_logs() → getLogs()
    clear_old_logs() → clearOldLogs()
    get_statistics() → getStatistics()
  
  EmailService:
    test_email() → testEmail()
    get_email_info() → getEmailInfo()
    test_php_mail() → testPhpMail()
  ```

#### 🏗️ Architecture Improvements
- **Clean Dependencies** - No circular class dependencies
- **Type Safety** - Strict type hints on all refactored methods
- **Backwards Compatibility** - 100% maintained through wrapper classes
- **Code Quality** - Consistent coding standards across all services
- **Maintainability** - Easier to maintain with single source of truth pattern

#### 🎯 PHP 8.0+ Compatibility
- Updated all deprecated `FILTER_SANITIZE_STRING` usage
- Improved exception handling with `Throwable` instead of `Exception`
- Version-aware filtering for PHP 8.1+ (`FILTER_SANITIZE_FULL_SPECIAL_CHARS`)
- Enhanced error logging with file and line information

### v1.0 - Major Feature Release
- ✨ **AI-Powered Features** - Complete AI Assistant with OpenAI GPT-4 integration
- 🎨 **AdminLTE 3 Redesign** - Modern, professional admin interface
- 🔄 **JavaScript Refactoring** - Complete SPA architecture with 21+ modules
- 🔒 **Security Enhancements** - Comprehensive security fixes and improvements
- 📱 **Mobile Optimization** - Full mobile responsive design
- 🧹 **Code Cleanup** - Removed 50+ unused files, modernized codebase
- ⚡ **Performance Improvements** - Faster loading, better UX
- 🏗️ **Service Architecture** - Modular service-based architecture for better maintainability
- 📊 **Activity Log** - Complete audit trail system for tracking all user actions with filtering and statistics
- 📧 **Email Testing** - Test WordPress and PHP email functionality with SMTP configuration detection
- 🔍 **Security Scanner** - Automated security vulnerability scanning with 0-100 security score
- 📈 **Performance Profiler** - Real-time performance metrics, analysis, and optimization recommendations
- 🖼️ **Media Library Manager** - Complete WordPress media file management with search and statistics
- 🗄️ **Database Optimizer** - Advanced database optimization with orphaned data cleanup and analysis
- 🔧 **Core Classes** - New core classes: Config, Database, Response, InputValidator, Logger, Cache
- 🛡️ **Enhanced Security** - PHP 8.0+ compatibility, improved error handling, comprehensive input validation

### v0.06 beta
- Added login feature with secure authentication
- Added global settings feature
- Automatically create settings.php if doesn't exist
- All backups stored in wp safe mode storage
- Minor fixes

### v0.05 beta
- Quick actions, .htaccess generator, robots.txt editor
- PHP error_log read functionality
- Maintenance mode
- Optimize tables, delete spam comments
- Search database, autobackup features
- New design
- Major code fixes

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

Please check `license.txt` or visit [http://wpsafemode.com/licenses/](http://wpsafemode.com/licenses/)

## 👥 Authors and Contributors

**CloudIndustry** - [http://cloud-industry.com](http://cloud-industry.com)

**Contributors:**
- Nikola Kirincic
- Marko Tiosavljevic
- Daliborka Ciric
- Luka Cvetinovic
- Nikola Stojanovic

## ⚠️ Important Notes

- This tool is production-ready but always test in development first
- **Always test in a development environment first**
- **Always backup your site before making changes**
- **Change default login credentials immediately after installation**
- **PHP 8.0+ Recommended** - Full compatibility with PHP 8.1+
- **Code Quality** - Follows PSR standards with strict type hints
- Please do not remove branding or links
- For support, visit [http://wpsafemode.com/](http://wpsafemode.com/)

## 🆘 Support

- **Website:** [http://wpsafemode.com/](http://wpsafemode.com/)
- **Issues:** Use GitHub Issues for bug reports
- **Feedback:** Visit the website to leave feedback

## 🎉 Acknowledgments

Special thanks to all contributors and the WordPress community for their support and feedback.

---

**Trademark Note:** WP Safe Mode and wpsafemode are trademarks of Cloud Industry LLC, © Cloud Industry LLC, all rights reserved.

**Best Regards,**

**Cloud Industry Team**

---

# WP Safe Mode v2 (in development)

v2 is a ground-up rewrite: an AI agent that diagnoses and repairs WordPress from
outside it, with every mutating action snapshotted, approved, verified, and
reversible. Design: `docs/superpowers/specs/2026-07-24-wp-safe-mode-design.md`.

v1.1 remains in this repository at the root (`controller/`, `model/`, `view/`, …)
and is untouched by v2 work. v2 lives in `src/`, `frontend/`, `docker/`, `tests/`.

## Requirements

Docker and Node. The canonical setup runs all PHP in the `php:8.0-cli` dev
container, which pins every test to the real version floor.

Where Docker is unavailable, PHP on the host works too, but on its own it does
**not** enforce the floor — a host running 8.3 will happily execute 8.1+ syntax
that breaks on a real 8.0 server. `phpstan.neon.dist` closes that gap by pinning
`phpVersion` to 80000, so `readonly`, enums, `never`, first-class callables and
`array_is_list()` are reported regardless of the local PHP. Run `make check-host`
rather than `make test-host`; the container remains the reference.

## Getting started

    make install    # composer install (in container) + npm install
    make test       # phpunit
    make build      # compile the frontend into src/public
    make up         # start WordPress + MariaDB playground

Then open http://localhost:8080/wpsafemode/ — the application is mounted beside
`wp-admin`, exactly as in a real install.

Without Docker:

    composer install
    make check-host    # phpstan 8.0 gate, then phpunit
    make build
