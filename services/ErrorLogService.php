<?php

class ErrorLogService
{
    private $error_log_file;
    private $app_log_file;
    private $settings_file;

    public function __construct()
    {
        $this->error_log_file = ini_get('error_log');
        $this->app_log_file = dirname(__DIR__) . '/sfstore/app_errors.log';
        $this->settings_file = dirname(__DIR__) . '/sfstore/error_log_settings.json';
    }

    /**
     * Get the PHP error log file path
     * 
     * @return string|false Path or false if not configured
     */
    public function getErrorLogPath()
    {
        return $this->error_log_file;
    }

    /**
     * Get the app error log file path
     * 
     * @return string
     */
    public function getAppLogPath()
    {
        return $this->app_log_file;
    }

    /**
     * Check if error logging is enabled
     * 
     * @return bool
     */
    public function isEnabled()
    {
        if (!file_exists($this->settings_file)) {
            return true; // Default to enabled
        }

        $settings = json_decode(file_get_contents($this->settings_file), true);
        return $settings['enabled'] ?? true;
    }

    /**
     * Enable error logging
     * 
     * @return bool Success
     */
    public function enable()
    {
        $settings = ['enabled' => true, 'updated_at' => date('Y-m-d H:i:s')];

        $dir = dirname($this->settings_file);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = file_put_contents($this->settings_file, json_encode($settings, JSON_PRETTY_PRINT));

        // Initialize error handler
        if (class_exists('ErrorHandler')) {
            ErrorHandler::getInstance()->enable();
        }

        return $result !== false;
    }

    /**
     * Disable error logging
     * 
     * @return bool Success
     */
    public function disable()
    {
        $settings = ['enabled' => false, 'updated_at' => date('Y-m-d H:i:s')];

        $dir = dirname($this->settings_file);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = file_put_contents($this->settings_file, json_encode($settings, JSON_PRETTY_PRINT));

        // Disable error handler
        if (class_exists('ErrorHandler')) {
            ErrorHandler::getInstance()->disable();
        }

        return $result !== false;
    }

    /**
     * Get combined error log statistics
     * 
     * @return array Statistics
     */
    public function getStats()
    {
        $phpStats = $this->getFileStats($this->error_log_file);
        $appStats = $this->getFileStats($this->app_log_file);

        return [
            'enabled' => $this->isEnabled(),
            'php_log' => $phpStats,
            'app_log' => $appStats,
            'total_size' => $phpStats['size'] + $appStats['size'],
            'total_lines' => $phpStats['lines'] + $appStats['lines'],
            'exists' => $phpStats['exists'] || $appStats['exists'],
            'last_modified' => max($phpStats['last_modified'] ?? 0, $appStats['last_modified'] ?? 0)
        ];
    }

    /**
     * Get statistics for a single log file
     * 
     * @param string $file File path
     * @return array Statistics
     */
    private function getFileStats($file)
    {
        if (!$file || !file_exists($file)) {
            return [
                'exists' => false,
                'size' => 0,
                'lines' => 0,
                'last_modified' => null
            ];
        }

        return [
            'exists' => true,
            'size' => filesize($file),
            'lines' => DashboardHelpers::number_lines_file($file),
            'last_modified' => filemtime($file)
        ];
    }

    /**
     * Get paginated and filtered error log entries from both sources
     * 
     * @param int $page Current page
     * @param int $lines Lines per page
     * @param string $search Search term
     * @param string $date_from Start date (YYYY-MM-DD)
     * @param string $date_to End date (YYYY-MM-DD)
     * @param string $severity Severity filter
     * @param string $source Source filter (php, app, all)
     * @return array Results
     */
    public function getErrorLog($page = 1, $lines = 20, $search = '', $date_from = '', $date_to = '', $severity = '', $source = 'all')
    {
        $allErrors = [];

        // Read PHP errors
        if ($source === 'all' || $source === 'php') {
            $phpErrors = $this->readLogFile($this->error_log_file, 'PHP');
            $allErrors = array_merge($allErrors, $phpErrors);
        }

        // Read App errors
        if ($source === 'all' || $source === 'app') {
            $appErrors = $this->readLogFile($this->app_log_file, 'APP');
            $allErrors = array_merge($allErrors, $appErrors);
        }

        // Apply filters
        $filtered = $this->filterErrors($allErrors, $search, $date_from, $date_to, $severity);

        // Sort by timestamp (newest first)
        usort($filtered, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        // Paginate
        $total = count($filtered);
        $offset = ($page - 1) * $lines;
        $paginated = array_slice($filtered, $offset, $lines);

        // Format for display
        $rows = array_map(function ($error) {
            return [
                $error['date'],
                $error['source'],
                $error['type'],
                $error['severity'],
                $error['message'],
                $error['file'] ?? '',
                $error['line'] ?? '',
                $error['trace'] ?? ''
            ];
        }, $paginated);

        return [
            'headers' => ['Date', 'Source', 'Type', 'Severity', 'Message', 'File', 'Line', 'Trace'],
            'rows' => $rows,
            'number_lines' => $total
        ];
    }

    /**
     * Read and parse a log file
     * 
     * @param string $file File path
     * @param string $source Source identifier
     * @return array Parsed errors
     */
    private function readLogFile($file, $source)
    {
        if (!$file || !file_exists($file)) {
            return [];
        }

        $errors = [];
        $content = file_get_contents($file);

        if (empty($content)) {
            return [];
        }

        // Split by separator or by lines
        $entries = preg_split('/\n-{80}\n/', $content);

        foreach ($entries as $entry) {
            if (empty(trim($entry))) {
                continue;
            }

            $parsed = $this->parseLogEntry($entry, $source);
            if ($parsed) {
                $errors[] = $parsed;
            }
        }

        return $errors;
    }

    /**
     * Parse a single log entry
     * 
     * @param string $entry Log entry
     * @param string $source Source identifier
     * @return array|null Parsed error
     */
    private function parseLogEntry($entry, $source)
    {
        // Try to parse different formats

        // Format 1: [01-Dec-2025 06:08:19 UTC] PHP Warning: Message...
        if (preg_match('/^\[(.*?)\]\s+(PHP|APP)\s+(.*?):\s+(.*)$/s', $entry, $matches)) {
            $dateStr = $matches[1];
            $detectedSource = $matches[2];
            $type = $matches[3];
            $rest = $matches[4];

            // Extract message and additional info
            $message = $rest;
            $file = '';
            $line = '';
            $trace = '';

            // Look for "in /path/to/file.php:123"
            if (preg_match('/^(.*?)\s+in\s+(.*?):(\d+)(.*)$/s', $rest, $detailMatches)) {
                $message = trim($detailMatches[1]);
                $file = $detailMatches[2];
                $line = $detailMatches[3];
                $trace = trim($detailMatches[4]);
            }

            // Look for stack trace
            if (preg_match('/Stack trace:\n(.*)$/s', $entry, $traceMatches)) {
                $trace = $traceMatches[1];
            }

            $timestamp = strtotime($dateStr);
            if ($timestamp === false) {
                $timestamp = time();
            }

            return [
                'timestamp' => $timestamp,
                'date' => $dateStr,
                'source' => $detectedSource,
                'type' => $type,
                'severity' => $this->detectSeverity($type),
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'trace' => $trace
            ];
        }

        // Format 2: Simple format without source prefix
        if (preg_match('/^\[(.*?)\]\s+(.*?):\s+(.*)$/s', $entry, $matches)) {
            $dateStr = $matches[1];
            $type = $matches[2];
            $message = $matches[3];

            $timestamp = strtotime($dateStr);
            if ($timestamp === false) {
                $timestamp = time();
            }

            return [
                'timestamp' => $timestamp,
                'date' => $dateStr,
                'source' => $source,
                'type' => $type,
                'severity' => $this->detectSeverity($type),
                'message' => $message,
                'file' => '',
                'line' => '',
                'trace' => ''
            ];
        }

        return null;
    }

    /**
     * Detect severity from error type
     * 
     * @param string $type Error type
     * @return string Severity level
     */
    private function detectSeverity($type)
    {
        $type = strtolower($type);

        if (strpos($type, 'fatal') !== false || strpos($type, 'critical') !== false || strpos($type, 'parse') !== false) {
            return 'CRITICAL';
        } elseif (strpos($type, 'error') !== false) {
            return 'ERROR';
        } elseif (strpos($type, 'warning') !== false) {
            return 'WARNING';
        } elseif (strpos($type, 'notice') !== false || strpos($type, 'deprecated') !== false) {
            return 'INFO';
        }

        return 'INFO';
    }

    /**
     * Filter errors based on criteria
     * 
     * @param array $errors Errors to filter
     * @param string $search Search term
     * @param string $date_from Start date
     * @param string $date_to End date
     * @param string $severity Severity filter
     * @return array Filtered errors
     */
    private function filterErrors($errors, $search, $date_from, $date_to, $severity)
    {
        return array_filter($errors, function ($error) use ($search, $date_from, $date_to, $severity) {
            // Search filter
            if (!empty($search)) {
                $searchIn = strtolower($error['message'] . ' ' . $error['type'] . ' ' . ($error['file'] ?? ''));
                if (stripos($searchIn, strtolower($search)) === false) {
                    return false;
                }
            }

            // Date range filter
            if (!empty($date_from)) {
                $fromTimestamp = strtotime($date_from);
                if ($error['timestamp'] < $fromTimestamp) {
                    return false;
                }
            }

            if (!empty($date_to)) {
                $toTimestamp = strtotime($date_to . ' 23:59:59');
                if ($error['timestamp'] > $toTimestamp) {
                    return false;
                }
            }

            // Severity filter
            if (!empty($severity) && $severity !== 'all') {
                if (strtolower($error['severity']) !== strtolower($severity)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Clear the error log
     * 
     * @return bool Success
     */
    public function clearErrorLog()
    {
        $success = true;

        // Clear PHP error log
        if ($this->error_log_file && file_exists($this->error_log_file)) {
            $f = @fopen($this->error_log_file, "r+");
            if ($f !== false) {
                ftruncate($f, 0);
                fclose($f);
            } else {
                $success = false;
            }
        }

        // Clear app error log
        if (file_exists($this->app_log_file)) {
            $f = @fopen($this->app_log_file, "r+");
            if ($f !== false) {
                ftruncate($f, 0);
                fclose($f);
            } else {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Archive the error log
     * 
     * @return string|false Archive path or false on failure
     */
    public function archiveErrorLog()
    {
        $archive_dir = dirname(__DIR__) . '/sfstore/error_log_archives';
        if (!file_exists($archive_dir)) {
            mkdir($archive_dir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $success = true;
        $archivePaths = [];

        // Archive PHP error log
        if ($this->error_log_file && file_exists($this->error_log_file)) {
            $filename = 'php_error_log_' . $timestamp . '.log';
            $destination = $archive_dir . '/' . $filename;
            if (copy($this->error_log_file, $destination)) {
                $archivePaths[] = $destination;
            } else {
                $success = false;
            }
        }

        // Archive app error log
        if (file_exists($this->app_log_file)) {
            $filename = 'app_error_log_' . $timestamp . '.log';
            $destination = $archive_dir . '/' . $filename;
            if (copy($this->app_log_file, $destination)) {
                $archivePaths[] = $destination;
            } else {
                $success = false;
            }
        }

        return $success && !empty($archivePaths) ? implode(', ', array_map('basename', $archivePaths)) : false;
    }
}
