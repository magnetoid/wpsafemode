<?php
/**
 * Configuration Manager
 * Centralized configuration management without globals
 */
class Config {
    
    private static $instance = null;
    private $settings = array();
    
    /**
     * Get singleton instance
     * 
     * @return Config
     */
    public static function getInstance(): Config {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - load settings
     */
    private function __construct() {
        if (file_exists(__DIR__ . '/../settings.php')) {
            include_once(__DIR__ . '/../settings.php');
            if (isset($settings) && is_array($settings)) {
                $this->settings = $settings;
            }
        }
    }
    
    /**
     * Get setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Set setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return void
     */
    public function set(string $key, $value): void {
        $this->settings[$key] = $value;
    }
    
    /**
     * Get all settings
     * 
     * @return array
     */
    public function all(): array {
        return $this->settings;
    }
    
    /**
     * Check if setting exists
     * 
     * @param string $key Setting key
     * @return bool
     */
    public function has(string $key): bool {
        return isset($this->settings[$key]);
    }
}

