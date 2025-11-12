<?php
/**
 * CSRF Protection for WP Safe Mode
 * 
 * Implements CSRF token generation and validation
 */

class CSRFProtection {
    
    /**
     * Generate CSRF token
     * 
     * @param string $form_name Form identifier
     * @return string CSRF token
     */
    public static function generate_token($form_name = 'default') {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = array();
        }
        
        // Generate random token
        $token = bin2hex(random_bytes(32));
        
        // Store token in session with expiration (1 hour)
        $_SESSION['csrf_tokens'][$form_name] = array(
            'token' => $token,
            'expires' => time() + 3600
        );
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @param string $form_name Form identifier
     * @return bool
     */
    public static function validate_token($token, $form_name = 'default') {
        if (!isset($_SESSION['csrf_tokens'][$form_name])) {
            return false;
        }
        
        $stored = $_SESSION['csrf_tokens'][$form_name];
        
        // Check expiration
        if (time() > $stored['expires']) {
            unset($_SESSION['csrf_tokens'][$form_name]);
            return false;
        }
        
        // Compare tokens using constant-time comparison
        return hash_equals($stored['token'], $token);
    }
    
    /**
     * Get CSRF token for form (generate if doesn't exist)
     * 
     * @param string $form_name Form identifier
     * @return string CSRF token
     */
    public static function get_token($form_name = 'default') {
        if (!isset($_SESSION['csrf_tokens'][$form_name])) {
            return self::generate_token($form_name);
        }
        
        $stored = $_SESSION['csrf_tokens'][$form_name];
        
        // Check expiration
        if (time() > $stored['expires']) {
            return self::generate_token($form_name);
        }
        
        return $stored['token'];
    }
    
    /**
     * Generate hidden input field for CSRF token
     * 
     * @param string $form_name Form identifier
     * @return string HTML input field
     */
    public static function get_token_field($form_name = 'default') {
        $token = self::get_token($form_name);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Validate CSRF token from POST request
     * 
     * @param string $form_name Form identifier
     * @return bool
     */
    public static function validate_post_token($form_name = 'default') {
        $token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
        if ($token === null) {
            return false;
        }
        return self::validate_token($token, $form_name);
    }
    
    /**
     * Validate CSRF token from GET request
     * 
     * @param string $form_name Form identifier
     * @return bool
     */
    public static function validate_get_token($form_name = 'default') {
        $token = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);
        if ($token === null) {
            return false;
        }
        return self::validate_token($token, $form_name);
    }
    
    /**
     * Clean expired tokens from session
     * 
     * @return void
     */
    public static function clean_expired_tokens() {
        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }
        
        foreach ($_SESSION['csrf_tokens'] as $form_name => $token_data) {
            if (time() > $token_data['expires']) {
                unset($_SESSION['csrf_tokens'][$form_name]);
            }
        }
    }
}


