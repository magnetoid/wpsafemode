<?php
/**
 * Logger
 * Centralized logging utility
 */
class Logger {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return Logger
     */
    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public static function error(string $message, array $context = array()): void {
        $logMessage = self::formatMessage('ERROR', $message, $context);
        error_log($logMessage);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     * @return void
     */
    public static function warning(string $message, array $context = array()): void {
        $logMessage = self::formatMessage('WARNING', $message, $context);
        error_log($logMessage);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Info message
     * @param array $context Additional context
     * @return void
     */
    public static function info(string $message, array $context = array()): void {
        if (defined('WPSM_DEBUG') && WPSM_DEBUG) {
            $logMessage = self::formatMessage('INFO', $message, $context);
            error_log($logMessage);
        }
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Debug message
     * @param array $context Additional context
     * @return void
     */
    public static function debug(string $message, array $context = array()): void {
        if (defined('WPSM_DEBUG') && WPSM_DEBUG) {
            $logMessage = self::formatMessage('DEBUG', $message, $context);
            error_log($logMessage);
        }
    }
    
    /**
     * Format log message
     * 
     * @param string $level Log level
     * @param string $message Message
     * @param array $context Context
     * @return string
     */
    private static function formatMessage(string $level, string $message, array $context = array()): string {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        return "[{$timestamp}] [{$level}] WP Safe Mode: {$message}{$contextStr}";
    }
}

