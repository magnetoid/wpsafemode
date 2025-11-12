<?php
/**
 * Output Buffer Helper
 * Centralized output buffering management to prevent code duplication
 */
class OutputBuffer {
    
    /**
     * Execute callback with output buffering and return captured output
     * 
     * @param callable $callback Function to execute
     * @param bool $cleanOnError Whether to clean buffer on error
     * @return string|false Captured output or false on error
     */
    public static function capture(callable $callback, $cleanOnError = true) {
        ob_start();
        try {
            call_user_func($callback);
            return ob_get_clean();
        } catch (Throwable $e) {
            if ($cleanOnError) {
                ob_end_clean();
            } else {
                ob_end_flush();
            }
            throw $e;
        }
    }
    
    /**
     * Execute callback with output buffering, discarding output
     * 
     * @param callable $callback Function to execute
     * @return void
     */
    public static function suppress(callable $callback) {
        ob_start();
        try {
            call_user_func($callback);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        ob_end_clean();
    }
    
    /**
     * Clean all output buffers
     * 
     * @return void
     */
    public static function cleanAll() {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    /**
     * Check if output buffering is active
     * 
     * @return bool
     */
    public static function isActive() {
        return ob_get_level() > 0;
    }
    
    /**
     * Clean current output buffer if active
     * 
     * @return void
     */
    public static function clean() {
        if (self::isActive()) {
            ob_clean();
        }
    }
}

