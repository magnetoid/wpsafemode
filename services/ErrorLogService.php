<?php

class ErrorLogService
{
    private $error_log_file;

    public function __construct()
    {
        $this->error_log_file = ini_get('error_log');
    }

    /**
     * Get the error log file path
     * 
     * @return string|false Path or false if not configured
     */
    public function getErrorLogPath()
    {
        return $this->error_log_file;
    }

    /**
     * Get error log statistics
     * 
     * @return array Statistics
     */
    public function getStats()
    {
        if (!$this->error_log_file || !file_exists($this->error_log_file)) {
            return [
                'exists' => false,
                'size' => 0,
                'lines' => 0,
                'last_modified' => null
            ];
        }

        return [
            'exists' => true,
            'size' => filesize($this->error_log_file),
            'lines' => DashboardHelpers::number_lines_file($this->error_log_file),
            'last_modified' => filemtime($this->error_log_file)
        ];
    }

    /**
     * Get paginated and filtered error log entries
     * 
     * @param int $page Current page
     * @param int $lines Lines per page
     * @param string $search Search term
     * @param string $date_from Start date (YYYY-MM-DD)
     * @param string $date_to End date (YYYY-MM-DD)
     * @return array Results
     */
    public function getErrorLog($page = 1, $lines = 20, $search = '', $date_from = '', $date_to = '')
    {
        if (!$this->error_log_file || !file_exists($this->error_log_file)) {
            return [
                'headers' => ['Date', 'Type', 'Message'],
                'rows' => [],
                'number_lines' => 0,
                'error' => 'Error log file not found or not configured'
            ];
        }

        // Calculate offset
        $seek = ($page - 1) * $lines;

        // If we have filters, we need to scan the file differently
        // For simple pagination without filters, we can use seek
        // But with filters, we need to read and filter

        $rows = [];
        $file = new SplFileObject($this->error_log_file);

        // If no filters, use efficient seeking
        if (empty($search) && empty($date_from) && empty($date_to)) {
            $file->seek($seek);
            $count = 0;
            while (!$file->eof() && $count < $lines) {
                $line = $file->fgets();
                if (empty(trim($line)))
                    continue;

                $parsed = $this->parseLine($line);
                if ($parsed) {
                    $rows[] = $parsed;
                    $count++;
                }
            }
            $total_lines = DashboardHelpers::number_lines_file($this->error_log_file);
        } else {
            // With filters, we need to scan
            // This can be slow for large files, so we might need to limit scanning
            // For now, simple implementation

            $matches = [];
            while (!$file->eof()) {
                $line = $file->fgets();
                if (empty(trim($line)))
                    continue;

                $parsed = $this->parseLine($line);
                if (!$parsed)
                    continue;

                // Apply filters
                if (!empty($search)) {
                    if (stripos($line, $search) === false)
                        continue;
                }

                if (!empty($date_from) || !empty($date_to)) {
                    $log_date = strtotime($parsed[0]); // Date is first column
                    if ($log_date === false)
                        continue;

                    if (!empty($date_from) && $log_date < strtotime($date_from))
                        continue;
                    if (!empty($date_to) && $log_date > strtotime($date_to . ' 23:59:59'))
                        continue;
                }

                $matches[] = $parsed;
            }

            $total_lines = count($matches);
            $rows = array_slice($matches, $seek, $lines);
        }

        return [
            'headers' => ['Date', 'Type', 'Message'],
            'rows' => $rows,
            'number_lines' => $total_lines
        ];
    }

    /**
     * Parse a single error log line
     * 
     * @param string $line
     * @return array|null [Date, Type, Message]
     */
    private function parseLine($line)
    {
        // Format: [01-Dec-2025 06:08:19 UTC] PHP Warning:  Message...
        if (preg_match('/^\[(.*?)\] (.*?): (.*)$/', $line, $matches)) {
            return [$matches[1], $matches[2], $matches[3]];
        }
        return null;
    }

    /**
     * Clear the error log
     * 
     * @return bool Success
     */
    public function clearErrorLog()
    {
        if (!$this->error_log_file || !file_exists($this->error_log_file)) {
            return false;
        }

        // Truncate file
        $f = @fopen($this->error_log_file, "r+");
        if ($f !== false) {
            ftruncate($f, 0);
            fclose($f);
            return true;
        }
        return false;
    }

    /**
     * Archive the error log
     * 
     * @return string|false Archive path or false on failure
     */
    public function archiveErrorLog()
    {
        if (!$this->error_log_file || !file_exists($this->error_log_file)) {
            return false;
        }

        $archive_dir = dirname(__DIR__) . '/sfstore/error_log_archives';
        if (!file_exists($archive_dir)) {
            mkdir($archive_dir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'error_log_' . $timestamp . '.log';
        $destination = $archive_dir . '/' . $filename;

        if (copy($this->error_log_file, $destination)) {
            return $destination;
        }
        return false;
    }
}
