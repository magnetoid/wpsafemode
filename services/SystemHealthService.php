<?php
/**
 * System Health Service
 * Monitors system health, performance, and resources
 */
class SystemHealthService
{

    private $config;
    private $model;

    /**
     * Constructor
     * 
     * @param Config|null $config Configuration instance
     * @param DashboardModel|null $model Dashboard model instance
     */
    public function __construct(?Config $config = null, ?DashboardModel $model = null)
    {
        $this->config = $config ?? Config::getInstance();
        $this->model = $model ?? new DashboardModel();
    }

    /**
     * Get system health metrics
     * 
     * @return array
     */
    public function getHealthMetrics(): array
    {
        return array(
            'server' => $this->getServerMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'wordpress' => $this->getWordPressMetrics(),
            'disk' => $this->getDiskMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'php' => $this->getPHPMetrics(),
            'security' => $this->getSecurityMetrics()
        );
    }

    /**
     * Get server metrics
     * 
     * @return array
     */
    private function getServerMetrics(): array
    {
        return array(
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_version' => PHP_VERSION,
            'uptime' => $this->getUptime(),
            'load_average' => $this->getLoadAverage(),
            'timezone' => date_default_timezone_get()
        );
    }

    /**
     * Get database metrics
     * 
     * @return array
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $tables = $this->model->show_tables();
            $total_size = 0;
            $table_count = count($tables);
            $largest_tables = array();
            $fragmented_tables = array();

            foreach ($tables as $table) {
                $validated = $this->model->validate_table_name($table);
                if ($validated) {
                    $q = $this->model->query("SHOW TABLE STATUS LIKE '{$validated}'");
                    $status = $q->fetch(PDO::FETCH_ASSOC);
                    if ($status) {
                        $size = ($status['Data_length'] ?? 0) + ($status['Index_length'] ?? 0);
                        $total_size += $size;

                        // Collect for largest tables
                        $largest_tables[] = array(
                            'name' => $table,
                            'size' => $size,
                            'formatted_size' => $this->formatBytes($size),
                            'rows' => $status['Rows'] ?? 0
                        );

                        // Check for fragmentation
                        if (($status['Data_free'] ?? 0) > 0) {
                            $fragmented_tables[] = array(
                                'name' => $table,
                                'wasted' => $status['Data_free'],
                                'formatted_wasted' => $this->formatBytes($status['Data_free'])
                            );
                        }
                    }
                }
            }

            // Sort and limit largest tables
            usort($largest_tables, function ($a, $b) {
                return $b['size'] <=> $a['size'];
            });
            $largest_tables = array_slice($largest_tables, 0, 10);

            // Sort fragmented tables
            usort($fragmented_tables, function ($a, $b) {
                return $b['wasted'] <=> $a['wasted'];
            });

            return array(
                'table_count' => $table_count,
                'total_size' => $total_size,
                'total_size_formatted' => $this->formatBytes($total_size),
                'largest_tables' => $largest_tables,
                'fragmented_tables' => $fragmented_tables,
                'status' => 'healthy'
            );
        } catch (Throwable $e) {
            Logger::error('Error getting database metrics', ['error' => $e->getMessage()]);
            return array('status' => 'error', 'error' => $e->getMessage());
        }
    }

    /**
     * Get WordPress metrics
     * 
     * @return array
     */
    private function getWordPressMetrics(): array
    {
        try {
            $plugins = $this->model->get_active_plugins();
            $active_plugins = is_array($plugins) ? count($plugins) : 0;

            $themes_method = method_exists($this->model, 'get_all_themes') ? 'get_all_themes' : 'scan_themes_directory';
            if (method_exists($this->model, $themes_method)) {
                $themes = $this->model->$themes_method($this->config->get('wp_dir', '../'));
                $theme_count = is_array($themes) ? count($themes) : 0;
            } else {
                $theme_count = 0;
            }

            return array(
                'active_plugins' => $active_plugins,
                'total_themes' => $theme_count,
                'wp_version' => $this->getWordPressVersion(),
                'status' => 'healthy'
            );
        } catch (Throwable $e) {
            Logger::error('Error getting WordPress metrics', ['error' => $e->getMessage()]);
            return array('status' => 'error');
        }
    }

    /**
     * Get disk metrics
     * 
     * @return array
     */
    private function getDiskMetrics(): array
    {
        $wp_dir = $this->config->get('wp_dir', '../');
        $wp_dir = realpath($wp_dir);

        if ($wp_dir && function_exists('disk_total_space') && function_exists('disk_free_space')) {
            $total = disk_total_space($wp_dir);
            $free = disk_free_space($wp_dir);
            $used = $total - $free;
            $percent = $total > 0 ? ($used / $total) * 100 : 0;

            return array(
                'total' => $total,
                'total_formatted' => $this->formatBytes($total),
                'used' => $used,
                'used_formatted' => $this->formatBytes($used),
                'free' => $free,
                'free_formatted' => $this->formatBytes($free),
                'percent_used' => round($percent, 2),
                'status' => $percent > 90 ? 'critical' : ($percent > 75 ? 'warning' : 'healthy')
            );
        }

        return array('status' => 'unknown');
    }

    /**
     * Get memory metrics
     * 
     * @return array
     */
    private function getMemoryMetrics(): array
    {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);

        $limit_bytes = $this->parseSize($memory_limit);
        $percent = $limit_bytes > 0 ? ($memory_usage / $limit_bytes) * 100 : 0;

        return array(
            'limit' => $memory_limit,
            'limit_bytes' => $limit_bytes,
            'usage' => $memory_usage,
            'usage_formatted' => $this->formatBytes($memory_usage),
            'peak' => $memory_peak,
            'peak_formatted' => $this->formatBytes($memory_peak),
            'percent_used' => round($percent, 2),
            'status' => $percent > 90 ? 'critical' : ($percent > 75 ? 'warning' : 'healthy')
        );
    }

    /**
     * Get PHP metrics
     * 
     * @return array
     */
    private function getPHPMetrics(): array
    {
        return array(
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'extensions' => get_loaded_extensions()
        );
    }

    /**
     * Get security metrics
     * 
     * @return array
     */
    private function getSecurityMetrics(): array
    {
        $checks = array();

        // Check if wp-config.php is readable
        $wp_config = $this->config->get('wp_dir', '../') . 'wp-config.php';
        $checks['wp_config_readable'] = is_readable($wp_config);

        // Check PHP version
        $php_version = PHP_VERSION_ID;
        $checks['php_version_secure'] = $php_version >= 70400;

        // Check if display_errors is off
        $checks['display_errors_off'] = !ini_get('display_errors');

        return array(
            'checks' => $checks,
            'score' => $this->calculateSecurityScore($checks),
            'status' => $this->calculateSecurityScore($checks) >= 80 ? 'good' : 'needs_attention'
        );
    }

    /**
     * Get WordPress version
     * 
     * @return string
     */
    private function getWordPressVersion(): string
    {
        $version_file = $this->config->get('wp_dir', '../') . 'wp-includes/version.php';
        if (file_exists($version_file)) {
            include_once $version_file;
            return $wp_version ?? 'Unknown';
        }
        return 'Unknown';
    }

    /**
     * Get server uptime
     * 
     * @return string
     */
    private function getUptime(): string
    {
        if (function_exists('sys_getloadavg') && PHP_OS !== 'WINNT') {
            $uptime = @shell_exec('uptime');
            return $uptime ? trim($uptime) : 'Unknown';
        }
        return 'Unknown';
    }

    /**
     * Get load average
     * 
     * @return array
     */
    private function getLoadAverage(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return array(
                '1min' => $load[0] ?? 0,
                '5min' => $load[1] ?? 0,
                '15min' => $load[2] ?? 0
            );
        }
        return array('1min' => 0, '5min' => 0, '15min' => 0);
    }

    /**
     * Calculate security score
     * 
     * @param array $checks Security checks
     * @return int Score out of 100
     */
    private function calculateSecurityScore(array $checks): int
    {
        $total = count($checks);
        $passed = 0;
        foreach ($checks as $check) {
            if ($check)
                $passed++;
        }
        return $total > 0 ? round(($passed / $total) * 100) : 0;
    }

    /**
     * Format bytes to human readable
     * 
     * @param int $bytes Bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse size string to bytes
     * 
     * @param string $size Size string (e.g., "128M")
     * @return int Bytes
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;

        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }
}

