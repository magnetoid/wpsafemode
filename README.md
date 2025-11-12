# WP Safe Mode v1.0

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
├── security/                      # Security classes
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

- **Backend:** PHP 7.4+ with PDO (PHP 8.0+ recommended)
- **Frontend:** JavaScript (ES6+), AdminLTE 3, Bootstrap 4, Material Design 3
- **Framework:** Custom MVC architecture with service layer
- **Database:** MySQL 5.7+ / MariaDB 10.2+ with PDO
- **Security:** CSRF protection, rate limiting, input validation, SQL injection prevention
- **AI:** OpenAI GPT-4 API integration
- **Icons:** Font Awesome 6, Material Icons
- **Architecture:** Service-oriented design with dependency injection support
- **Caching:** In-memory caching system for performance optimization

## 🔐 Security

WP Safe Mode includes comprehensive security features:

- ✅ SQL Injection prevention (PDO parameter binding)
- ✅ CSRF token protection
- ✅ Rate limiting for login attempts
- ✅ Input sanitization and validation
- ✅ XSS prevention
- ✅ Secure file operations
- ✅ Path traversal protection
- ✅ Secure session management

## 📱 Mobile Support

- Fully responsive design
- Mobile-optimized sidebar
- Touch-friendly interface
- Optimized loading screens
- Mobile-specific CSS optimizations

## 🚀 Performance

- Single Page Application (SPA) architecture
- AJAX-powered content loading
- No page reloads
- Efficient caching
- Optimized asset loading
- Database query optimization
- Lazy loading support
- Service-based architecture for better code organization

## 📝 Change Log

### v1.0 (Current) - Major Feature Release
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

- This tool is still in beta/experimental phase
- **Always test in a development environment first**
- **Always backup your site before making changes**
- **Change default login credentials immediately after installation**
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
