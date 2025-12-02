<?php
/**
 * Logger
 * Centralized logging utility with enhanced error tracking
 */
class Logger
{

    private static $instance = null;
    private static $appLogPath = '';

    /**
     * Get singleton instance
     * 
     * @return Logger
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$appLogPath = dirname(__DIR__) . '/sfstore/app_errors.log';
        }
        return self::$instance;
    }

    /**
     * Set custom app log path
     * 
     * @param string $path Log file path
     * @return void
     */
    public static function setAppLogPath(string $path): void
    {
        self::$appLogPath = $path;
    }

    /**
     * Log critical message
     * 
     * @param string $message Critical message
     * @param array $context Additional context
     * @return void
     */
    public static function critical(string $message, array $context = array()): void
    {
        $logMessage = self::formatMessage('CRITICAL', $message, $context);
        error_log($logMessage);
        self::writeToAppLog($logMessage);
    }

    /**
     * Log error message
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public static function error(string $message, array $context = array()): void
    {
        $logMessage = self::formatMessage('ERROR', $message, $context);
        error_log($logMessage);
        self::writeToAppLog($logMessage);
    }

    /**
     * Log warning message
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     * @return void
     */
    public static function warning(string $message, array $context = array()): void
    {
        $logMessage = self::formatMessage('WARNING', $message, $context);
        error_log($logMessage);
        self::writeToAppLog($logMessage);
    }

    /**
     * Log info message
     * 
     * @param string $message Info message
     * @param array $context Additional context
     * @return void
     */
    public static function info(string $message, array $context = array()): void
    {
        if (defined('WPSM_DEBUG') && WPSM_DEBUG) {
            $logMessage = self::formatMessage('INFO', $message, $context);
            error_log($logMessage);
            self::writeToAppLog($logMessage);
        }
    }

    /**
     * Log debug message
     * 
     * @param string $message Debug message
     * @param array $context Additional context
     * @return void
     */
    public static function debug(string $message, array $context = array()): void
    {
        if (defined('WPSM_DEBUG') && WPSM_DEBUG) {
            $logMessage = self::formatMessage('DEBUG', $message, $context);
            error_log($logMessage);
            self::writeToAppLog($logMessage);
        }
    }

    /**
     * Format log message with enhanced context
     * 
     * @param string $level Log level
     * @param string $message Message
     * @param array $context Context
     * @return string
     */
    private static function formatMessage(string $level, string $message, array $context = array()): string
    {
        $timestamp = date('d-M-Y H:i:s T');

        // Add request context
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $userIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Build context string
        $contextParts = [];
        if (!empty($context)) {
            foreach ($context as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $contextParts[] = "$key: $value";
            }
        }
        $contextStr = !empty($contextParts) ? ' | ' . implode(', ', $contextParts) : '';

        return "[{$timestamp}] APP {$level}: {$message} | URI: {$requestUri} | IP: {$userIp}{$contextStr}";
    }

    /**
     * Write to application log file
     * 
     * @param string $message Formatted log message
     * @return void
     */
    private static function writeToAppLog(string $message): void
    {
        if (empty(self::$appLogPath)) {
            self::$appLogPath = dirname(__DIR__) . '/sfstore/app_errors.log';
        }

        // Ensure log directory exists
        $logDir = dirname(self::$appLogPath);
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Write to log file
        @file_put_contents(self::$appLogPath, $message . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Get app log path
     * 
     * @return string
     */
    public static function getAppLogPath(): string
    {
        if (empty(self::$appLogPath)) {
            self::$appLogPath = dirname(__DIR__) . '/sfstore/app_errors.log';
        }
        return self::$appLogPath;
    }
}

