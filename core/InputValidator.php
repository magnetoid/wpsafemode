<?php
/**
 * Input Validator
 * Centralized input validation and sanitization
 */
class InputValidator {
    
    /**
     * Sanitize string input
     * 
     * @param mixed $input Input to sanitize
     * @param string $type Sanitization type
     * @return mixed
     */
    public static function sanitize($input, string $type = 'string') {
        if ($input === null) {
            return null;
        }
        
        // Use SecureInput if available, otherwise fallback
        if (class_exists('SecureInput')) {
            return SecureInput::sanitize($input, $type);
        }
        
        // Fallback sanitization
        switch ($type) {
            case 'string':
                if (PHP_VERSION_ID >= 80100) {
                    return filter_var($input, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
                } else {
                    return filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
                }
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Get and sanitize input from GET/POST
     * 
     * @param string $name Input name
     * @param int $type Input type (INPUT_GET, INPUT_POST)
     * @param string $sanitize_type Sanitization type
     * @return mixed
     */
    public static function getInput(string $name, int $type = INPUT_POST, string $sanitize_type = 'string') {
        if (class_exists('SecureInput')) {
            return SecureInput::get_input($name, $type, $sanitize_type);
        }
        
        $input = filter_input($type, $name);
        if ($input === null) {
            return null;
        }
        return self::sanitize($input, $sanitize_type);
    }
    
    /**
     * Validate email
     * 
     * @param string $email Email address
     * @return bool
     */
    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL
     * @return bool
     */
    public static function validateUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate table name
     * 
     * @param string $table Table name
     * @return bool
     */
    public static function validateTableName(string $table): bool {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $table) === 1;
    }
    
    /**
     * Validate filename
     * 
     * @param string $filename Filename
     * @return bool
     */
    public static function validateFilename(string $filename): bool {
        $basename = basename($filename);
        return preg_match('/^[a-zA-Z0-9._-]+$/', $basename) === 1;
    }
}

