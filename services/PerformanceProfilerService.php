<?php
/**
 * Performance Profiler Service
 * Analyzes WordPress performance and provides optimization recommendations
 */
class PerformanceProfilerService {
    
    private $config;
    private $wp_dir;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->wp_dir = $this->config->get('wp_dir', '../');
    }
    
    /**
     * Get performance metrics
     * 
     * @return array
     */
    public function getMetrics(): array {
        $metrics = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => $this->getServerMetrics(),
            'php' => $this->getPhpMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'wordpress' => $this->getWordPressMetrics(),
            'recommendations' => array()
        );
        
        // Generate recommendations
        $metrics['recommendations'] = $this->generateRecommendations($metrics);
        
        return $metrics;
    }
    
    /**
     * Get server metrics
     * 
     * @return array
     */
    private function getServerMetrics(): array {
        return array(
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'disk_free_space' => $this->formatBytes(disk_free_space($this->wp_dir)),
            'disk_total_space' => $this->formatBytes(disk_total_space($this->wp_dir))
        );
    }
    
    /**
     * Get PHP metrics
     * 
     * @return array
     */
    private function getPhpMetrics(): array {
        return array(
            'version' => phpversion(),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
            'opcache_status' => function_exists('opcache_get_status') ? opcache_get_status() : null,
            'extensions' => get_loaded_extensions(),
            'sapi' => php_sapi_name()
        );
    }
    
    /**
     * Get database metrics
     * 
     * @return array
     */
    private function getDatabaseMetrics(): array {
        $metrics = array(
            'connection' => false,
            'tables' => 0,
            'total_size' => 0,
            'optimized' => 0,
            'needs_optimization' => array()
        );
        
        try {
            $db_model = new DBModel();
            $metrics['connection'] = true;
            
            $tables = $db_model->show_tables();
            $metrics['tables'] = count($tables);
            
            $total_size = 0;
            $needs_optimization = array();
            
            foreach ($tables as $table) {
                $result = $db_model->query("SHOW TABLE STATUS LIKE ?", array($table));
                if ($result && isset($result[0])) {
                    $data_length = $result[0]['Data_length'] ?? 0;
                    $index_length = $result[0]['Index_length'] ?? 0;
                    $data_free = $result[0]['Data_free'] ?? 0;
                    $table_size = $data_length + $index_length;
                    
                    $total_size += $table_size;
                    
                    // Check if table needs optimization
                    if ($data_free > 0) {
                        $needs_optimization[] = array(
                            'table' => $table,
                            'waste' => $this->formatBytes($data_free)
                        );
                    }
                }
            }
            
            $metrics['total_size'] = $this->formatBytes($total_size);
            $metrics['needs_optimization'] = $needs_optimization;
            $metrics['optimized'] = $metrics['tables'] - count($needs_optimization);
            
        } catch (Throwable $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get WordPress metrics
     * 
     * @return array
     */
    private function getWordPressMetrics(): array {
        $metrics = array(
            'version' => null,
            'plugins' => array('active' => 0, 'inactive' => 0, 'total' => 0),
            'themes' => array('active' => null, 'total' => 0),
            'posts' => 0,
            'pages' => 0,
            'comments' => 0,
            'users' => 0,
            'transients' => 0
        );
        
        try {
            $dashboard_model = new DashboardModel();
            
            // Get WordPress version
            include_once $this->wp_dir . 'wp-includes/version.php';
            if (isset($wp_version)) {
                $metrics['version'] = $wp_version;
            }
            
            // Get plugins
            $plugins = $dashboard_model->get_plugins();
            if (isset($plugins['active'])) {
                $metrics['plugins']['active'] = count($plugins['active']);
            }
            if (isset($plugins['inactive'])) {
                $metrics['plugins']['inactive'] = count($plugins['inactive']);
            }
            $metrics['plugins']['total'] = $metrics['plugins']['active'] + $metrics['plugins']['inactive'];
            
            // Get themes
            $themes = $dashboard_model->get_themes();
            if (isset($themes['active'])) {
                $metrics['themes']['active'] = $themes['active'];
            }
            if (isset($themes['installed'])) {
                $metrics['themes']['total'] = count($themes['installed']);
            }
            
            // Get database counts
            $db_model = new DBModel();
            $db_prefix = $this->config->get('wp_db_prefix', 'wp_');
            
            // Posts
            $posts = $db_model->query("SELECT COUNT(*) as count FROM {$db_prefix}posts WHERE post_type = 'post'");
            if ($posts && isset($posts[0]['count'])) {
                $metrics['posts'] = intval($posts[0]['count']);
            }
            
            // Pages
            $pages = $db_model->query("SELECT COUNT(*) as count FROM {$db_prefix}posts WHERE post_type = 'page'");
            if ($pages && isset($pages[0]['count'])) {
                $metrics['pages'] = intval($pages[0]['count']);
            }
            
            // Comments
            $comments = $db_model->query("SELECT COUNT(*) as count FROM {$db_prefix}comments");
            if ($comments && isset($comments[0]['count'])) {
                $metrics['comments'] = intval($comments[0]['count']);
            }
            
            // Users
            $users = $db_model->query("SELECT COUNT(*) as count FROM {$db_prefix}users");
            if ($users && isset($users[0]['count'])) {
                $metrics['users'] = intval($users[0]['count']);
            }
            
            // Transients
            $transients = $db_model->query("SELECT COUNT(*) as count FROM {$db_prefix}options WHERE option_name LIKE '_transient_%'");
            if ($transients && isset($transients[0]['count'])) {
                $metrics['transients'] = intval($transients[0]['count']);
            }
            
        } catch (Throwable $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Generate performance recommendations
     * 
     * @param array $metrics
     * @return array
     */
    private function generateRecommendations(array $metrics): array {
        $recommendations = array();
        
        // Memory recommendations
        $memory_limit = $this->parseBytes($metrics['server']['memory_limit']);
        if ($memory_limit < 256 * 1024 * 1024) { // Less than 256MB
            $recommendations[] = array(
                'priority' => 'high',
                'category' => 'Server',
                'title' => 'Increase PHP memory limit',
                'description' => 'Current memory limit is ' . $metrics['server']['memory_limit'] . '. Consider increasing to at least 256M.',
                'action' => 'Edit php.ini or .htaccess to increase memory_limit'
            );
        }
        
        // OPcache recommendations
        if (!$metrics['php']['opcache_enabled']) {
            $recommendations[] = array(
                'priority' => 'medium',
                'category' => 'PHP',
                'title' => 'Enable OPcache',
                'description' => 'OPcache can significantly improve PHP performance by caching compiled scripts.',
                'action' => 'Enable OPcache in php.ini'
            );
        }
        
        // Database optimization
        if (count($metrics['database']['needs_optimization']) > 0) {
            $recommendations[] = array(
                'priority' => 'medium',
                'category' => 'Database',
                'title' => 'Optimize database tables',
                'description' => count($metrics['database']['needs_optimization']) . ' tables need optimization.',
                'action' => 'Run database optimization from Quick Actions'
            );
        }
        
        // Plugin recommendations
        if ($metrics['wordpress']['plugins']['total'] > 30) {
            $recommendations[] = array(
                'priority' => 'low',
                'category' => 'WordPress',
                'title' => 'Consider reducing plugin count',
                'description' => 'You have ' . $metrics['wordpress']['plugins']['total'] . ' plugins installed. Too many plugins can slow down your site.',
                'action' => 'Review and remove unused plugins'
            );
        }
        
        // Transients cleanup
        if ($metrics['wordpress']['transients'] > 100) {
            $recommendations[] = array(
                'priority' => 'low',
                'category' => 'WordPress',
                'title' => 'Clean up expired transients',
                'description' => 'You have ' . $metrics['wordpress']['transients'] . ' transients. Some may be expired.',
                'action' => 'Use Quick Actions to clean up transients'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Format bytes to human readable
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    
    /**
     * Parse bytes from string (e.g., "256M" to bytes)
     * 
     * @param string $value
     * @return int
     */
    private function parseBytes(string $value): int {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = intval($value);
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}

