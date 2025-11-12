# Example Security Fixes - Before and After

This document shows specific examples of how to fix vulnerable code in the WP Safe Mode codebase.

---

## Example 1: Fixing SQL Injection in dbModel::add_condition()

### File: `model/db.model.php`

### BEFORE (Vulnerable):
```php
function add_condition( $field, $value = '', $options = array('condition'=>'AND','operator'=>'=','exact'=> true)){
    if( $this->check_value_type($value) == 'string' &&  $options['operator'] == '='){
        return;	     			
    }
    if(empty($this->condition)){
        $this->condition = ' WHERE ';
    }else{
        $this->condition.= ' ' . $options['condition'] . ' ';
    }
    if($options['operator'] == 'LIKE' && $options['exact'] == false){
        $value = '%'. $value . '%';
    }
    // VULNERABLE: Direct string concatenation
    $this->condition.=  ' '.  $field .' '. $options['operator'] .' '.  " '" . $value . "' ";	
}
```

### AFTER (Secure):
```php
private $condition_params = array();

function add_condition( $field, $value = '', $options = array('condition'=>'AND','operator'=>'=','exact'=> true)){
    // Whitelist allowed field names
    $allowed_fields = array('option_name', 'option_value', 'post_type', 'comment_approved', 'post_status');
    if (!in_array($field, $allowed_fields)) {
        throw new InvalidArgumentException("Field name not allowed: " . htmlspecialchars($field));
    }
    
    // Whitelist allowed operators
    $allowed_operators = array('=', 'LIKE', '!=', '<', '>', '<=', '>=');
    if (!in_array($options['operator'], $allowed_operators)) {
        throw new InvalidArgumentException("Operator not allowed: " . htmlspecialchars($options['operator']));
    }
    
    if(empty($this->condition)){
        $this->condition = ' WHERE ';
    }else{
        $this->condition.= ' ' . $options['condition'] . ' ';
    }
    if($options['operator'] == 'LIKE' && $options['exact'] == false){
        $value = '%'. $value . '%';
    }
    
    // SECURE: Use parameter binding
    $param_name = ':param_' . count($this->condition_params);
    $this->condition .= $field . ' ' . $options['operator'] . ' ' . $param_name;
    $this->condition_params[$param_name] = $value;
}

function get_condition_params() {
    return $this->condition_params;
}

function reset_condition() {
    $this->condition = '';
    $this->condition_params = array();
}
```

### Usage Example:
```php
// When building query
$this->add_condition('option_name', 'active_plugins', array('operator' => '='));
$query = "SELECT * FROM " . $this->db_prefix . "options " . $this->condition;
$q = $this->prepare($query);

// Bind parameters
foreach ($this->get_condition_params() as $param => $value) {
    $q->bindValue($param, $value, PDO::PARAM_STR);
}
$q->execute();
```

---

## Example 2: Fixing SQL Injection in save_plugins()

### File: `model/plugins.model.php` and `model/dashboard.model.php`

### BEFORE (Vulnerable):
```php
public function save_plugins($option_value = '' , $serialize = false){
    // VULNERABLE: Direct string concatenation
    $q = $this->prepare("UPDATE ".$this->db_prefix."options SET option_value = '" . $option_value . "' WHERE option_name LIKE 'active_plugins';");
    $q->execute();
}
```

### AFTER (Secure):
```php
public function save_plugins($option_value = '' , $serialize = false){
    // Validate input
    if (!is_string($option_value)) {
        throw new InvalidArgumentException("Option value must be a string");
    }
    
    // SECURE: Use parameter binding
    $q = $this->prepare("UPDATE `" . $this->db_prefix . "options` SET option_value = :option_value WHERE option_name = 'active_plugins'");
    $q->bindValue(':option_value', $option_value, PDO::PARAM_STR);
    $q->execute();
    
    if ($q->rowCount() === 0) {
        throw new Exception("Failed to update active plugins");
    }
}
```

---

## Example 3: Fixing File Path Traversal in action_download()

### File: `controller/dashboard.controller.php`

### BEFORE (Vulnerable):
```php
function action_download(){
    $download = filter_input(INPUT_GET,'download');
    $filename = filter_input(INPUT_GET,'filename');
    
    if($download == 'database'){
        $db_backups = $this->dashboard_model->get_database_backups();
        foreach($db_backups as $db_backups_section){
            foreach($db_backups_section as $backupfile){
                $basename = basename($backupfile);
                if($basename==$filename){
                    // VULNERABLE: No path validation
                    DashboardHelpers::download_file($filename, $backupfile);	
                    exit;
                }
            }
        }
    }
}
```

### AFTER (Secure):
```php
function action_download(){
    $download = SecureInput::get_input('download', INPUT_GET, 'string');
    $filename = SecureInput::get_input('filename', INPUT_GET, 'filename');
    
    // Validate filename format
    if (!SecureInput::validate($filename, 'filename')) {
        $this->set_message('Invalid filename');
        $this->redirect();
        return;
    }
    
    if($download == 'database'){
        $base_directory = $this->settings['sfstore'] . 'db_backup/';
        
        // SECURE: Validate file path
        $filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
        if ($filepath === false || !file_exists($filepath)) {
            $this->set_message('File not found');
            $this->redirect();
            return;
        }
        
        SecureFileOperations::secure_download_file($filename, $base_directory);
        exit;
    }
}
```

---

## Example 4: Adding CSRF Protection to Forms

### File: `view/login.php`

### BEFORE (Vulnerable):
```php
<form method="post" action="">
    <input type="text" name="username" />
    <input type="password" name="password" />
    <input type="submit" name="submit_login" value="Login" />
</form>
```

### AFTER (Secure):
```php
<form method="post" action="">
    <?php echo CSRFProtection::get_token_field('login'); ?>
    <input type="text" name="username" />
    <input type="password" name="password" />
    <input type="submit" name="submit_login" value="Login" />
</form>
```

### File: `controller/main.controller.php`

### BEFORE (Vulnerable):
```php
function submit_login(){
    $user_data = array(
        'username' =>'',
        'password' => '',					
    );
    foreach($user_data as $key=>$user_item){
        $user_data[$key] = filter_input(INPUT_POST, $key);
    }
    // ... rest of login logic
}
```

### AFTER (Secure):
```php
function submit_login(){
    // SECURE: Validate CSRF token first
    if (!CSRFProtection::validate_post_token('login')) {
        $this->set_message('Invalid security token. Please try again.');
        $this->redirect('?view=login');
        return;
    }
    
    // SECURE: Use rate limiting
    if (!RateLimiter::check_rate_limit('login', 5, 300)) {
        $remaining = RateLimiter::get_reset_time('login', 300);
        $this->set_message('Too many login attempts. Please try again in ' . $remaining . ' seconds.');
        $this->redirect('?view=login');
        return;
    }
    
    RateLimiter::record_attempt('login');
    
    $user_data = array(
        'username' => SecureInput::get_input('username', INPUT_POST, 'string'),
        'password' => SecureInput::get_input('password', INPUT_POST, 'string'),					
    );
    
    // ... rest of login logic
    
    // Reset rate limit on successful login
    if ($login_successful) {
        RateLimiter::reset_rate_limit('login');
    }
}
```

---

## Example 5: Fixing XSS in Output

### File: `view/info.php`

### BEFORE (Vulnerable):
```php
<div class="option_title">
    <pre><?php echo $php_slug; ?> : <?php echo $php_info['value']; ?></pre>
</div>
```

### AFTER (Secure):
```php
<div class="option_title">
    <pre><?php echo SecureOutput::escape_html($php_slug); ?> : <?php echo SecureOutput::escape_html($php_info['value']); ?></pre>
</div>
```

---

## Example 6: Fixing Remote Download Security

### File: `helpers/helpers.php`

### BEFORE (Vulnerable):
```php
public static function remote_download($url = '', $filename = ''){
    set_time_limit(0);
    $file = fopen($filename , 'w+');
    $curl = curl_init($url);
    
    curl_setopt_array($curl, array(
        CURLOPT_URL            => $url,
        CURLOPT_BINARYTRANSFER => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FILE           => $file,
        CURLOPT_TIMEOUT        => 50,
        CURLOPT_SSL_VERIFYPEER => false, // VULNERABLE: SSL verification disabled
        CURLOPT_USERAGENT      => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)'
    ));
    $response = curl_exec($curl);
    // ...
}
```

### AFTER (Secure):
```php
public static function remote_download($url = '', $filename = ''){
    // SECURE: Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Invalid URL: ' . htmlspecialchars($url));
    }
    
    // SECURE: Only allow HTTPS for sensitive downloads
    if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
        throw new InvalidArgumentException('Only HTTPS URLs are allowed');
    }
    
    // SECURE: Validate filename
    $validated_path = SecureFileOperations::validate_file_path($filename, __DIR__ . '/../sfstore/temp/');
    if ($validated_path === false) {
        throw new InvalidArgumentException('Invalid file path');
    }
    
    set_time_limit(0);
    $file = fopen($validated_path, 'w+');
    if ($file === false) {
        throw new RuntimeException('Could not open file for writing');
    }
    
    $curl = curl_init($url);
    
    curl_setopt_array($curl, array(
        CURLOPT_URL            => $url,
        CURLOPT_BINARYTRANSFER => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FILE           => $file,
        CURLOPT_TIMEOUT        => 50,
        CURLOPT_SSL_VERIFYPEER => true,  // SECURE: Enable SSL verification
        CURLOPT_SSL_VERIFYHOST => 2,     // SECURE: Verify hostname
        CURLOPT_USERAGENT      => 'WP-SafeMode/1.0',
        CURLOPT_FOLLOWLOCATION => false, // SECURE: Don't follow redirects automatically
        CURLOPT_MAXREDIRS      => 0
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        fclose($file);
        throw new Exception('Curl error: ' . $error);
    }
    
    // SECURE: Check HTTP status code
    if ($http_code !== 200) {
        curl_close($curl);
        fclose($file);
        unlink($validated_path); // Clean up failed download
        throw new Exception('HTTP error: ' . $http_code);
    }
    
    curl_close($curl);
    fclose($file);
    
    return $validated_path;
}
```

---

## Example 7: Fixing Directory Creation Permissions

### File: `helpers/helpers.php`

### BEFORE (Vulnerable):
```php
public static function check_directory($filename = '' , $create = true){    
    if (!file_exists($filename)) {
        if($create == true ){
            mkdir($filename, 0777); // VULNERABLE: World-writable
        }			   
        return;
    }
}
```

### AFTER (Secure):
```php
public static function check_directory($filename = '' , $create = true){    
    // SECURE: Validate path
    $filename = str_replace("\0", '', $filename);
    
    if (!file_exists($filename)) {
        if($create == true ){
            // SECURE: Use secure permissions (0755 = owner rwx, group rx, others rx)
            $permissions = 0755;
            if (!mkdir($filename, $permissions, true)) {
                throw new RuntimeException('Could not create directory: ' . htmlspecialchars($filename));
            }
        }			   
        return;
    }
}
```

---

## Example 8: Fixing Table Name Validation

### File: `model/db.model.php`

### BEFORE (Vulnerable):
```php
function db_show_columns( $table = ''){
    if(!empty($table)){
        // VULNERABLE: Table name not validated
        $q = $this->prepare("SHOW FULL COLUMNS FROM ".$table);
        $q->execute();
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

### AFTER (Secure):
```php
function db_show_columns( $table = ''){
    if(empty($table)){
        return false;
    }
    
    // SECURE: Validate table name
    $table = SecureInput::sanitize($table, 'table_name');
    if (!SecureInput::validate($table, 'table_name')) {
        throw new InvalidArgumentException('Invalid table name');
    }
    
    // SECURE: Get list of valid tables and verify
    $valid_tables = $this->show_tables();
    if (!in_array($table, $valid_tables)) {
        throw new InvalidArgumentException('Table does not exist: ' . htmlspecialchars($table));
    }
    
    // Note: SHOW commands don't support parameter binding, but we've validated the table name
    $q = $this->prepare("SHOW FULL COLUMNS FROM `" . $table . "`");
    $q->execute();
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
```

---

## Summary

Key principles for all fixes:

1. **Never trust user input** - Always validate and sanitize
2. **Use parameter binding** - Never concatenate user input into SQL
3. **Validate file paths** - Prevent directory traversal
4. **Enable SSL verification** - Don't disable security features
5. **Use secure permissions** - Not 0777
6. **Escape output** - Prevent XSS attacks
7. **Implement CSRF protection** - Protect all forms
8. **Add rate limiting** - Prevent brute force attacks
9. **Whitelist, don't blacklist** - Only allow known good values
10. **Fail securely** - Default to denying access on errors

---

**Note**: These examples should be adapted to your specific codebase. Always test thoroughly after implementing fixes.


