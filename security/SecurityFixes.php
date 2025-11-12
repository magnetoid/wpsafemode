<?php
/**
 * Security Fixes for WP Safe Mode
 * 
 * This file contains fixed versions of vulnerable code patterns
 * Replace the vulnerable code in the original files with these secure implementations
 */

/**
 * SECURITY FIX 1: SQL Injection Prevention
 * 
 * Fixed version of dbModel::add_condition() and related methods
 */
class SecureDbModel extends PDO {
    
    private $condition_params = array();
    
    /**
     * Secure version of add_condition using parameter binding
     * 
     * @param string $field Database field name (must be whitelisted)
     * @param mixed $value Value to search for
     * @param array $options Query options
     * @return void
     */
    function add_condition_secure($field, $value = '', $options = array('condition'=>'AND','operator'=>'=','exact'=> true)){
        
        // Whitelist allowed field names to prevent SQL injection
        $allowed_fields = array('option_name', 'option_value', 'post_type', 'comment_approved');
        if (!in_array($field, $allowed_fields)) {
            throw new InvalidArgumentException("Field name not allowed: " . $field);
        }
        
        // Whitelist allowed operators
        $allowed_operators = array('=', 'LIKE', '!=', '<', '>', '<=', '>=');
        if (!in_array($options['operator'], $allowed_operators)) {
            throw new InvalidArgumentException("Operator not allowed: " . $options['operator']);
        }
        
        if(empty($this->condition)){
            $this->condition = ' WHERE ';
        } else {
            $this->condition .= ' ' . $options['condition'] . ' ';
        }
        
        // Prepare value for LIKE operator
        if($options['operator'] == 'LIKE' && $options['exact'] == false){
            $value = '%' . $value . '%';
        }
        
        // Use parameter binding instead of string concatenation
        $param_name = ':param_' . count($this->condition_params);
        $this->condition .= $field . ' ' . $options['operator'] . ' ' . $param_name;
        $this->condition_params[$param_name] = $value;
    }
    
    /**
     * Get condition parameters for binding
     * 
     * @return array
     */
    function get_condition_params() {
        return $this->condition_params;
    }
    
    /**
     * Reset condition and parameters
     * 
     * @return void
     */
    function reset_condition() {
        $this->condition = '';
        $this->condition_params = array();
    }
    
    /**
     * Secure table name validation
     * 
     * @param string $table Table name
     * @return string|false Validated table name or false
     */
    function validate_table_name($table) {
        // Only allow alphanumeric, underscore, and dash
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $table)) {
            return false;
        }
        
        // Get list of valid tables
        $valid_tables = $this->show_tables();
        if (!in_array($table, $valid_tables)) {
            return false;
        }
        
        return $table;
    }
    
    /**
     * Secure version of db_show_table_info
     * 
     * @param string $table Table name
     * @return array|false
     */
    function db_show_table_info_secure($table = '') {
        $validated_table = $this->validate_table_name($table);
        if (!$validated_table) {
            return false;
        }
        
        $q = $this->prepare("SHOW TABLE STATUS FROM `" . DB_NAME . "` WHERE Name = :table_name");
        $q->bindValue(':table_name', $validated_table, PDO::PARAM_STR);
        $q->execute();
        return $q->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Secure version of db_show_columns
     * 
     * @param string $table Table name
     * @return array|false
     */
    function db_show_columns_secure($table = '') {
        $validated_table = $this->validate_table_name($table);
        if (!$validated_table) {
            return false;
        }
        
        // Table names in SHOW commands cannot be parameterized, but we validate them
        $q = $this->prepare("SHOW FULL COLUMNS FROM `" . $validated_table . "`");
        $q->execute();
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * SECURITY FIX 2: Secure Plugin Model Methods
 */
class SecurePluginsModel {
    
    /**
     * Secure version of save_plugins using parameter binding
     * 
     * @param PDO $db Database connection
     * @param string $db_prefix Table prefix
     * @param string $option_value Serialized plugin data
     * @return bool
     */
    public static function save_plugins_secure($db, $db_prefix, $option_value = '') {
        // Validate that option_value is serialized data
        if (!is_string($option_value)) {
            return false;
        }
        
        // Use parameter binding
        $q = $db->prepare("UPDATE `" . $db_prefix . "options` SET option_value = :option_value WHERE option_name = 'active_plugins'");
        $q->bindValue(':option_value', $option_value, PDO::PARAM_STR);
        return $q->execute();
    }
    
    /**
     * Secure version of disable_all_plugins
     * 
     * @param PDO $db Database connection
     * @param string $db_prefix Table prefix
     * @return bool
     */
    public static function disable_all_plugins_secure($db, $db_prefix) {
        $q = $db->prepare("UPDATE `" . $db_prefix . "options` SET option_value = '' WHERE option_name = 'active_plugins'");
        return $q->execute();
    }
    
    /**
     * Secure version of get_active_plugins
     * 
     * @param PDO $db Database connection
     * @param string $db_prefix Table prefix
     * @return array|false
     */
    public static function get_active_plugins_secure($db, $db_prefix) {
        $q = $db->prepare("SELECT * FROM `" . $db_prefix . "options` WHERE option_name = 'active_plugins'");
        $q->execute();
        return $q->fetch(PDO::FETCH_ASSOC);
    }
}

/**
 * SECURITY FIX 3: File Path Validation
 */
class SecureFileOperations {
    
    /**
     * Validate and sanitize file path to prevent directory traversal
     * 
     * @param string $filepath File path to validate
     * @param string $base_directory Base directory (allowed root)
     * @return string|false Sanitized path or false if invalid
     */
    public static function validate_file_path($filepath, $base_directory) {
        // Remove any null bytes
        $filepath = str_replace("\0", '', $filepath);
        
        // Get real paths
        $base_directory = realpath($base_directory);
        if ($base_directory === false) {
            return false;
        }
        
        $full_path = realpath($base_directory . DIRECTORY_SEPARATOR . $filepath);
        if ($full_path === false) {
            return false;
        }
        
        // Ensure the resolved path is within the base directory
        if (strpos($full_path, $base_directory) !== 0) {
            return false;
        }
        
        return $full_path;
    }
    
    /**
     * Secure file download with path validation
     * 
     * @param string $filename Requested filename
     * @param string $base_directory Base directory for downloads
     * @return void
     */
    public static function secure_download_file($filename, $base_directory) {
        // Validate filename (only alphanumeric, dash, underscore, dot)
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            http_response_code(400);
            die('Invalid filename');
        }
        
        // Validate file path
        $filepath = self::validate_file_path($filename, $base_directory);
        if ($filepath === false || !file_exists($filepath)) {
            http_response_code(404);
            die('File not found');
        }
        
        // Check if it's a file (not directory)
        if (!is_file($filepath)) {
            http_response_code(403);
            die('Access denied');
        }
        
        // Get MIME type
        $mime_type = mime_content_type($filepath);
        if ($mime_type === false) {
            $mime_type = 'application/octet-stream';
        }
        
        // Set headers
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('X-Content-Type-Options: nosniff');
        
        // Output file
        readfile($filepath);
        exit;
    }
    
    /**
     * Secure directory creation with proper permissions
     * 
     * @param string $dirpath Directory path
     * @param int $permissions Permissions (default 0755)
     * @return bool
     */
    public static function secure_create_directory($dirpath, $permissions = 0755) {
        // Validate path
        $dirpath = str_replace("\0", '', $dirpath);
        
        // Create directory with secure permissions (not 0777)
        if (!file_exists($dirpath)) {
            return mkdir($dirpath, $permissions, true);
        }
        return true;
    }
}

/**
 * SECURITY FIX 4: Input Validation and Sanitization
 */
class SecureInput {
    
    /**
     * Sanitize string input
     * 
     * @param mixed $input Input to sanitize
     * @param string $type Type of sanitization (string, int, email, url, etc.)
     * @return mixed Sanitized input
     */
    public static function sanitize($input, $type = 'string') {
        if ($input === null) {
            return null;
        }
        
        switch ($type) {
            case 'string':
                return filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            
            case 'filename':
                // Only allow alphanumeric, dash, underscore, dot
                return preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
            
            case 'table_name':
                // Only allow alphanumeric, underscore
                return preg_replace('/[^a-zA-Z0-9_]/', '', $input);
            
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate input against pattern
     * 
     * @param mixed $input Input to validate
     * @param string $pattern Validation pattern
     * @return bool
     */
    public static function validate($input, $pattern) {
        switch ($pattern) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) !== false;
            
            case 'ip':
                return filter_var($input, FILTER_VALIDATE_IP) !== false;
            
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT) !== false;
            
            case 'filename':
                return preg_match('/^[a-zA-Z0-9._-]+$/', $input) === 1;
            
            case 'table_name':
                return preg_match('/^[a-zA-Z0-9_]+$/', $input) === 1;
            
            default:
                return true;
        }
    }
    
    /**
     * Get and sanitize input from GET/POST
     * 
     * @param string $name Input name
     * @param string $type Input type (INPUT_GET, INPUT_POST)
     * @param string $sanitize_type Sanitization type
     * @return mixed
     */
    public static function get_input($name, $type = INPUT_POST, $sanitize_type = 'string') {
        $input = filter_input($type, $name);
        if ($input === null) {
            return null;
        }
        return self::sanitize($input, $sanitize_type);
    }
}

/**
 * SECURITY FIX 5: Output Escaping
 */
class SecureOutput {
    
    /**
     * Escape output for HTML
     * 
     * @param string $output Output to escape
     * @return string Escaped output
     */
    public static function escape_html($output) {
        return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Escape output for JavaScript
     * 
     * @param string $output Output to escape
     * @return string Escaped output
     */
    public static function escape_js($output) {
        return json_encode($output, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * Escape output for URL
     * 
     * @param string $output Output to escape
     * @return string Escaped output
     */
    public static function escape_url($output) {
        return urlencode($output);
    }
}


