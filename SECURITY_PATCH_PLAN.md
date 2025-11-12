# Security Patch Plan for WP Safe Mode

## Overview
This document outlines the step-by-step plan to fix critical security vulnerabilities in WP Safe Mode v0.06 beta.

## Priority Levels
- **P0 (Critical)**: Fix immediately - Active security vulnerabilities
- **P1 (High)**: Fix within 1 week - Significant security risks
- **P2 (Medium)**: Fix within 1 month - Important improvements
- **P3 (Low)**: Fix when possible - Code quality improvements

---

## Phase 1: Critical SQL Injection Fixes (P0)

### Step 1.1: Fix dbModel::add_condition()
**File**: `model/db.model.php`
**Current Issue**: Direct string concatenation in SQL queries
**Fix**: Use parameter binding

**Before**:
```php
$this->condition.=  ' '.  $field .' '. $options['operator'] .' '.  " '" . $value . "' ";
```

**After**:
```php
// Use the secure version from SecurityFixes.php
$param_name = ':param_' . count($this->condition_params);
$this->condition .= $field . ' ' . $options['operator'] . ' ' . $param_name;
$this->condition_params[$param_name] = $value;
```

**Action Items**:
1. Replace `add_condition()` method with secure version
2. Update all queries using `add_condition()` to bind parameters
3. Add field name whitelist validation
4. Test all database queries

### Step 1.2: Fix db_show_table_info()
**File**: `model/db.model.php:109`
**Current Issue**: Table name concatenated directly
**Fix**: Validate table name and use parameter binding

**Before**:
```php
$q = $this->prepare("SHOW TABLE STATUS FROM " . DB_NAME . " WHERE Name = '" . $table . "'");
```

**After**:
```php
$validated_table = $this->validate_table_name($table);
if (!$validated_table) {
    return false;
}
$q = $this->prepare("SHOW TABLE STATUS FROM `" . DB_NAME . "` WHERE Name = :table_name");
$q->bindValue(':table_name', $validated_table, PDO::PARAM_STR);
```

### Step 1.3: Fix save_plugins()
**Files**: `model/plugins.model.php:141`, `model/dashboard.model.php:1332`
**Current Issue**: Option value concatenated directly
**Fix**: Use parameter binding

**Before**:
```php
$q = $this->prepare("UPDATE ".$this->db_prefix."options SET option_value = '" . $option_value . "' WHERE option_name LIKE 'active_plugins';");
```

**After**:
```php
$q = $this->prepare("UPDATE `" . $this->db_prefix . "options` SET option_value = :option_value WHERE option_name = 'active_plugins'");
$q->bindValue(':option_value', $option_value, PDO::PARAM_STR);
```

**Action Items**:
1. Update `save_plugins()` in both files
2. Update `disable_all_plugins()` method
3. Update `get_active_plugins()` method
4. Test plugin activation/deactivation

### Step 1.4: Fix SELECT INTO OUTFILE
**File**: `model/dashboard.model.php:1591`
**Current Issue**: File path not validated
**Fix**: Validate file path before use

**Before**:
```php
$q = $this->query("SELECT * INTO OUTFILE '". $backup_file_csv. "' ...");
```

**After**:
```php
$validated_path = SecureFileOperations::validate_file_path($backup_file_csv, $this->settings['sfstore']);
if (!$validated_path) {
    throw new Exception('Invalid file path');
}
$q = $this->query("SELECT * INTO OUTFILE '" . $validated_path . "' ...");
```

---

## Phase 2: CSRF Protection Implementation (P0)

### Step 2.1: Include CSRF Protection Class
**File**: `autoload.php`
**Action**: Add CSRF protection class

```php
include_once('security/CSRFProtection.php');
```

### Step 2.2: Add CSRF Tokens to Forms
**Files**: All view files with forms
**Action**: Add CSRF token field to each form

**Example** (in view files):
```php
<?php echo CSRFProtection::get_token_field('form_name'); ?>
```

**Forms to Update**:
- `view/login.php`
- `view/plugins.php`
- `view/themes.php`
- `view/wpconfig.php`
- `view/htaccess.php`
- `view/robots.php`
- `view/autobackup.php`
- All other forms

### Step 2.3: Validate CSRF Tokens in Controllers
**Files**: All controller files
**Action**: Validate CSRF token before processing forms

**Example** (in controller methods):
```php
function submit_plugins(){
    if (!CSRFProtection::validate_post_token('plugins')) {
        $this->set_message('Invalid security token. Please try again.');
        $this->redirect();
        return;
    }
    // ... rest of method
}
```

**Controllers to Update**:
- `controller/dashboard.controller.php` - All submit methods
- `controller/main.controller.php` - Login methods
- All other controllers with form submissions

---

## Phase 3: Input Validation & Sanitization (P0)

### Step 3.1: Include Security Classes
**File**: `autoload.php`
**Action**: Add security classes

```php
include_once('security/SecurityFixes.php');
```

### Step 3.2: Replace filter_input() with SecureInput
**Files**: All controller and model files
**Action**: Replace direct filter_input() calls with SecureInput::get_input()

**Before**:
```php
$search = filter_input(INPUT_GET, 'search');
```

**After**:
```php
$search = SecureInput::get_input('search', INPUT_GET, 'string');
```

### Step 3.3: Validate Table Names
**Files**: `model/db.model.php`, `model/search_and_replace.model.php`
**Action**: Validate table names before use

**Example**:
```php
$table = SecureInput::sanitize($table, 'table_name');
if (!SecureInput::validate($table, 'table_name')) {
    throw new InvalidArgumentException('Invalid table name');
}
```

### Step 3.4: Validate File Paths
**Files**: All files handling file operations
**Action**: Use SecureFileOperations::validate_file_path()

**Example**:
```php
$filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
if ($filepath === false) {
    throw new Exception('Invalid file path');
}
```

---

## Phase 4: File System Security (P0)

### Step 4.1: Fix File Download
**File**: `controller/dashboard.controller.php:820`
**Action**: Use secure download method

**Before**:
```php
DashboardHelpers::download_file($filename, $backupfile);
```

**After**:
```php
SecureFileOperations::secure_download_file($filename, $this->settings['sfstore']);
```

### Step 4.2: Fix Directory Creation
**File**: `helpers/helpers.php:998`
**Action**: Use secure directory creation

**Before**:
```php
mkdir($filename, 0777);
```

**After**:
```php
SecureFileOperations::secure_create_directory($filename, 0755);
```

### Step 4.3: Fix Remote Downloads
**File**: `helpers/helpers.php:798`
**Action**: Enable SSL verification and validate URLs

**Before**:
```php
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
```

**After**:
```php
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
// Validate URL before use
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    throw new InvalidArgumentException('Invalid URL');
}
```

---

## Phase 5: Authentication & Rate Limiting (P1)

### Step 5.1: Include Rate Limiter
**File**: `autoload.php`
**Action**: Add rate limiter class

```php
include_once('security/RateLimiter.php');
```

### Step 5.2: Add Rate Limiting to Login
**File**: `controller/main.controller.php:441`
**Action**: Add rate limiting before login attempt

**Example**:
```php
function submit_login(){
    // Check rate limit
    if (!RateLimiter::check_rate_limit('login', 5, 300)) {
        $remaining = RateLimiter::get_reset_time('login', 300);
        $this->set_message('Too many login attempts. Please try again in ' . $remaining . ' seconds.');
        $this->redirect();
        return;
    }
    
    // Record attempt
    RateLimiter::record_attempt('login');
    
    // ... existing login code ...
    
    // Reset on successful login
    if ($login_successful) {
        RateLimiter::reset_rate_limit('login');
    }
}
```

### Step 5.3: Improve Session Security
**File**: `autoload.php`
**Action**: Configure secure session settings

```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Use HTTPS only
ini_set('session.use_strict_mode', 1);
```

---

## Phase 6: Output Escaping (P1)

### Step 6.1: Escape All Output
**Files**: All view files
**Action**: Use SecureOutput::escape_html() for all user-generated content

**Before**:
```php
<?php echo $user_input; ?>
```

**After**:
```php
<?php echo SecureOutput::escape_html($user_input); ?>
```

### Step 6.2: Update View Files
**Files**: All files in `view/` directory
**Action**: Review and escape all output

---

## Phase 7: Error Handling (P2)

### Step 7.1: Implement Proper Error Handling
**Files**: All model and controller files
**Action**: Replace direct error output with logging

**Before**:
```php
echo '<p style="color:red">Error: </p>'. $ex->getMessage();
```

**After**:
```php
error_log('Database error: ' . $ex->getMessage());
// Show generic error to user
$this->set_message('An error occurred. Please contact support.');
```

### Step 7.2: Add Error Logging
**Action**: Create error logging system

```php
class ErrorLogger {
    public static function log($message, $level = 'error') {
        $log_file = __DIR__ . '/logs/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [$level] $message\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
```

---

## Phase 8: Code Quality Improvements (P3)

### Step 8.1: Remove Dead Code
**Action**: Remove all `.orig` and `.bak` files
- `controller/dashboard.controller.php.orig`
- `model/dashboard.model.php.orig`
- `view/autobackup.php.orig`
- All other backup files

### Step 8.2: Remove Commented Code
**Action**: Remove all commented-out code blocks

### Step 8.3: Fix Naming Conventions
**Action**: Standardize naming (use camelCase consistently)

---

## Testing Checklist

After each phase, test the following:

### SQL Injection Tests
- [ ] Attempt SQL injection in all form fields
- [ ] Test table name validation
- [ ] Test parameter binding works correctly

### CSRF Tests
- [ ] Submit forms without CSRF token (should fail)
- [ ] Submit forms with invalid CSRF token (should fail)
- [ ] Submit forms with valid CSRF token (should succeed)

### Input Validation Tests
- [ ] Test with malicious input (XSS, SQL injection, path traversal)
- [ ] Test with valid input (should work)
- [ ] Test with edge cases (empty, null, special characters)

### File System Tests
- [ ] Attempt path traversal attacks
- [ ] Test file download with invalid paths
- [ ] Test directory creation with proper permissions

### Authentication Tests
- [ ] Test rate limiting (should block after 5 attempts)
- [ ] Test session security
- [ ] Test password validation

---

## Implementation Timeline

### Week 1 (Critical Fixes)
- Day 1-2: Phase 1 - SQL Injection Fixes
- Day 3-4: Phase 2 - CSRF Protection
- Day 5: Phase 3 - Input Validation (partial)

### Week 2 (High Priority)
- Day 1-2: Phase 3 - Input Validation (complete)
- Day 3-4: Phase 4 - File System Security
- Day 5: Phase 5 - Authentication & Rate Limiting

### Week 3 (Medium Priority)
- Day 1-2: Phase 6 - Output Escaping
- Day 3-4: Phase 7 - Error Handling
- Day 5: Testing and bug fixes

### Week 4 (Code Quality)
- Day 1-3: Phase 8 - Code Quality Improvements
- Day 4-5: Final testing and documentation

---

## Rollback Plan

Before implementing fixes:
1. Create full backup of codebase
2. Create database backup
3. Test fixes in development environment first
4. Have rollback procedure documented

## Post-Implementation

After fixes are complete:
1. Security audit by third party
2. Penetration testing
3. Code review
4. Update documentation
5. Release notes for users

---

## Notes

- All fixes should be tested thoroughly before deployment
- Consider implementing fixes incrementally
- Monitor error logs after deployment
- Keep security classes in separate directory for easy updates
- Document all changes for future reference

---

**Last Updated**: $(date)
**Status**: Planning Phase
**Next Review**: After Phase 1 completion


