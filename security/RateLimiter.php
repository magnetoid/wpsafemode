<?php
/**
 * Rate Limiting for WP Safe Mode
 * 
 * Implements rate limiting to prevent brute force attacks
 */

class RateLimiter {
    
    /**
     * Check if action is rate limited
     * 
     * @param string $action Action identifier (e.g., 'login', 'api_call')
     * @param int $max_attempts Maximum attempts allowed
     * @param int $time_window Time window in seconds
     * @param string $identifier User identifier (IP address, user ID, etc.)
     * @return bool True if allowed, false if rate limited
     */
    public static function check_rate_limit($action, $max_attempts = 5, $time_window = 300, $identifier = null) {
        if ($identifier === null) {
            $identifier = self::get_client_ip();
        }
        
        $key = 'rate_limit_' . $action . '_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = array(
                'attempts' => 0,
                'first_attempt' => time(),
                'last_attempt' => time()
            );
        }
        
        $rate_data = $_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $rate_data['first_attempt'] > $time_window) {
            $_SESSION[$key] = array(
                'attempts' => 0,
                'first_attempt' => time(),
                'last_attempt' => time()
            );
            return true;
        }
        
        // Check if max attempts exceeded
        if ($rate_data['attempts'] >= $max_attempts) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Record an attempt
     * 
     * @param string $action Action identifier
     * @param string $identifier User identifier
     * @return void
     */
    public static function record_attempt($action, $identifier = null) {
        if ($identifier === null) {
            $identifier = self::get_client_ip();
        }
        
        $key = 'rate_limit_' . $action . '_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = array(
                'attempts' => 0,
                'first_attempt' => time(),
                'last_attempt' => time()
            );
        }
        
        $_SESSION[$key]['attempts']++;
        $_SESSION[$key]['last_attempt'] = time();
    }
    
    /**
     * Get remaining attempts
     * 
     * @param string $action Action identifier
     * @param int $max_attempts Maximum attempts allowed
     * @param string $identifier User identifier
     * @return int Remaining attempts
     */
    public static function get_remaining_attempts($action, $max_attempts = 5, $identifier = null) {
        if ($identifier === null) {
            $identifier = self::get_client_ip();
        }
        
        $key = 'rate_limit_' . $action . '_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return $max_attempts;
        }
        
        $rate_data = $_SESSION[$key];
        $remaining = $max_attempts - $rate_data['attempts'];
        return max(0, $remaining);
    }
    
    /**
     * Get time until rate limit resets
     * 
     * @param string $action Action identifier
     * @param int $time_window Time window in seconds
     * @param string $identifier User identifier
     * @return int Seconds until reset
     */
    public static function get_reset_time($action, $time_window = 300, $identifier = null) {
        if ($identifier === null) {
            $identifier = self::get_client_ip();
        }
        
        $key = 'rate_limit_' . $action . '_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $rate_data = $_SESSION[$key];
        $elapsed = time() - $rate_data['first_attempt'];
        $remaining = $time_window - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Reset rate limit for action
     * 
     * @param string $action Action identifier
     * @param string $identifier User identifier
     * @return void
     */
    public static function reset_rate_limit($action, $identifier = null) {
        if ($identifier === null) {
            $identifier = self::get_client_ip();
        }
        
        $key = 'rate_limit_' . $action . '_' . md5($identifier);
        unset($_SESSION[$key]);
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}


