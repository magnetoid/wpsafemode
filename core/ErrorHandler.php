<?php
/**
 * ErrorHandler
 * Custom error and exception handler for comprehensive error logging
 */
class ErrorHandler
{
    private static $instance = null;
    private $enabled = false;
    private $appLogPath = '';
    private $previousErrorHandler = null;
    private $previousExceptionHandler = null;

    /**
     * Get singleton instance
     * 
     * @return ErrorHandler
     */
    public static function getInstance(): ErrorHandler
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        // Set default app log path
        $this->appLogPath = dirname(__DIR__) . '/sfstore/app_errors.log';
    }

    /**
     * Initialize and register error handlers
     * 
     * @param bool $enabled Enable error logging
     * @param string $appLogPath Custom app log path
     * @return void
     */
    public function init(bool $enabled = true, string $appLogPath = ''): void
    {
        $this->enabled = $enabled;

        if (!empty($appLogPath)) {
            $this->appLogPath = $appLogPath;
        }

        if ($this->enabled) {
            $this->register();
        }
    }

    /**
     * Register error handlers
     * 
     * @return void
     */
    private function register(): void
    {
        // Store previous handlers
        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);

        // Register shutdown function for fatal errors
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Unregister error handlers
     * 
     * @return void
     */
    public function unregister(): void
    {
        if ($this->previousErrorHandler !== null) {
            set_error_handler($this->previousErrorHandler);
        } else {
            restore_error_handler();
        }

        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        } else {
            restore_exception_handler();
        }

        $this->enabled = false;
    }

    /**
     * Handle PHP errors
     * 
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number
     * @return bool
     */
    public function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        // Don't log if error reporting is disabled
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = $this->getErrorType($errno);
        $severity = $this->getErrorSeverity($errno);

        $context = [
            'type' => $errorType,
            'severity' => $severity,
            'file' => $errfile,
            'line' => $errline,
            'trace' => $this->getStackTrace()
        ];

        $this->logError($errstr, $context, 'PHP');

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     * 
     * @param Throwable $exception
     * @return void
     */
    public function handleException($exception): void
    {
        $context = [
            'type' => get_class($exception),
            'severity' => 'CRITICAL',
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        $this->logError($exception->getMessage(), $context, 'PHP');

        // Call previous exception handler if exists
        if ($this->previousExceptionHandler !== null) {
            call_user_func($this->previousExceptionHandler, $exception);
        }
    }

    /**
     * Handle fatal errors on shutdown
     * 
     * @return void
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $context = [
                'type' => $this->getErrorType($error['type']),
                'severity' => 'CRITICAL',
                'file' => $error['file'],
                'line' => $error['line'],
                'trace' => 'Fatal error - no trace available'
            ];

            $this->logError($error['message'], $context, 'PHP');
        }
    }

    /**
     * Log application-level error
     * 
     * @param string $message Error message
     * @param string $severity Severity level (INFO, WARNING, ERROR, CRITICAL)
     * @param array $context Additional context
     * @return void
     */
    public function logAppError(string $message, string $severity = 'ERROR', array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $context['severity'] = $severity;
        $this->logError($message, $context, 'APP');
    }

    /**
     * Write error to log file
     * 
     * @param string $message Error message
     * @param array $context Error context
     * @param string $source Error source (PHP or APP)
     * @return void
     */
    private function logError(string $message, array $context, string $source): void
    {
        if (!$this->enabled) {
            return;
        }

        // Ensure log directory exists
        $logDir = dirname($this->appLogPath);
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Format log entry
        $timestamp = date('d-M-Y H:i:s T');
        $severity = $context['severity'] ?? 'ERROR';
        $type = $context['type'] ?? 'Unknown';

        $logEntry = sprintf(
            "[%s] %s %s: %s in %s:%s\n",
            $timestamp,
            $source,
            $type,
            $message,
            $context['file'] ?? 'unknown',
            $context['line'] ?? '0'
        );

        // Add stack trace if available
        if (!empty($context['trace'])) {
            $logEntry .= "Stack trace:\n" . $context['trace'] . "\n";
        }

        $logEntry .= str_repeat('-', 80) . "\n";

        // Write to app log file
        @file_put_contents($this->appLogPath, $logEntry, FILE_APPEND | LOCK_EX);

        // Also log to PHP error_log for critical errors
        if (in_array($severity, ['CRITICAL', 'ERROR'])) {
            error_log(sprintf('[%s] %s: %s', $source, $type, $message));
        }
    }

    /**
     * Get error type name from error number
     * 
     * @param int $errno Error number
     * @return string
     */
    private function getErrorType(int $errno): string
    {
        $errorTypes = [
            E_ERROR => 'PHP Fatal Error',
            E_WARNING => 'PHP Warning',
            E_PARSE => 'PHP Parse Error',
            E_NOTICE => 'PHP Notice',
            E_CORE_ERROR => 'PHP Core Error',
            E_CORE_WARNING => 'PHP Core Warning',
            E_COMPILE_ERROR => 'PHP Compile Error',
            E_COMPILE_WARNING => 'PHP Compile Warning',
            E_USER_ERROR => 'PHP User Error',
            E_USER_WARNING => 'PHP User Warning',
            E_USER_NOTICE => 'PHP User Notice',
            2048 => 'PHP Strict Standards',
            E_RECOVERABLE_ERROR => 'PHP Recoverable Error',
            E_DEPRECATED => 'PHP Deprecated',
            E_USER_DEPRECATED => 'PHP User Deprecated',
        ];

        return $errorTypes[$errno] ?? 'PHP Unknown Error';
    }

    /**
     * Get error severity from error number
     * 
     * @param int $errno Error number
     * @return string
     */
    private function getErrorSeverity(int $errno): string
    {
        if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            return 'CRITICAL';
        } elseif (in_array($errno, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR])) {
            return 'ERROR';
        } elseif (in_array($errno, [E_NOTICE, E_USER_NOTICE, 2048, E_DEPRECATED, E_USER_DEPRECATED])) {
            return 'WARNING';
        }
        return 'INFO';
    }

    /**
     * Get formatted stack trace
     * 
     * @return string
     */
    private function getStackTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        // Remove first 2 entries (this method and handleError)
        array_shift($trace);
        array_shift($trace);

        $output = [];
        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'] ?? 'unknown';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';

            $output[] = sprintf(
                "#%d %s(%d): %s%s%s()",
                $i,
                $file,
                $line,
                $class,
                $type,
                $function
            );
        }

        return implode("\n", $output);
    }

    /**
     * Check if error logging is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable error logging
     * 
     * @return void
     */
    public function enable(): void
    {
        if (!$this->enabled) {
            $this->enabled = true;
            $this->register();
        }
    }

    /**
     * Disable error logging
     * 
     * @return void
     */
    public function disable(): void
    {
        if ($this->enabled) {
            $this->unregister();
        }
    }

    /**
     * Get app log path
     * 
     * @return string
     */
    public function getAppLogPath(): string
    {
        return $this->appLogPath;
    }
}
