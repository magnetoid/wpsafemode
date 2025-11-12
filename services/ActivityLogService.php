<?php
/**
 * Activity Log Service
 * Tracks all user actions and system events for audit trail
 */
class ActivityLogService {
    
    private $log_file;
    private $max_log_size = 10485760; // 10MB
    
    public function __construct() {
        $config = Config::getInstance();
        $sfstore = $config->get('sfstore', 'sfstore/');
        $this->log_file = $sfstore . 'activity_log.json';
        
        // Ensure directory exists
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    
    /**
     * Log an activity
     * 
     * @param string $action Action performed (e.g., 'plugin_activated', 'user_created')
     * @param string $description Human-readable description
     * @param array $data Additional data
     * @param string $user User identifier (default: current user)
     * @return bool
     */
    public function log($action, $description, $data = array(), $user = null) {
        try {
            $activity = array(
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $action,
                'description' => $description,
                'user' => $user ?? $this->getCurrentUser(),
                'ip' => $this->getClientIp(),
                'data' => $data
            );
            
            $logs = $this->readLogs();
            $logs[] = $activity;
            
            // Keep only last 1000 entries
            if (count($logs) > 1000) {
                $logs = array_slice($logs, -1000);
            }
            
            return $this->writeLogs($logs);
        } catch (Throwable $e) {
            error_log('Activity Log Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get activity logs
     * 
     * @param int $limit Number of entries to return
     * @param string $action Filter by action
     * @param string $user Filter by user
     * @return array
     */
    public function getLogs(int $limit = 100, ?string $action = null, ?string $user = null): array {
        $logs = $this->readLogs();
        
        // Filter logs
        if ($action !== null) {
            $logs = array_filter($logs, function($log) use ($action) {
                return $log['action'] === $action;
            });
        }
        
        if ($user !== null) {
            $logs = array_filter($logs, function($log) use ($user) {
                return $log['user'] === $user;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Limit results
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Clear old logs
     * 
     * @param int $days Keep logs newer than this many days
     * @return bool
     */
    public function clearOldLogs(int $days = 30): bool {
        try {
            $logs = $this->readLogs();
            $cutoff = time() - ($days * 24 * 60 * 60);
            
            $logs = array_filter($logs, function($log) use ($cutoff) {
                return strtotime($log['timestamp']) > $cutoff;
            });
            
            return $this->writeLogs(array_values($logs));
        } catch (Throwable $e) {
            error_log('Activity Log Clear Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get activity statistics
     * 
     * @return array
     */
    public function getStatistics(): array {
        $logs = $this->readLogs();
        
        $stats = array(
            'total_activities' => count($logs),
            'actions' => array(),
            'users' => array(),
            'recent_24h' => 0,
            'recent_7d' => 0
        );
        
        $now = time();
        $day_ago = $now - 86400;
        $week_ago = $now - 604800;
        
        foreach ($logs as $log) {
            // Count actions
            if (!isset($stats['actions'][$log['action']])) {
                $stats['actions'][$log['action']] = 0;
            }
            $stats['actions'][$log['action']]++;
            
            // Count users
            if (!isset($stats['users'][$log['user']])) {
                $stats['users'][$log['user']] = 0;
            }
            $stats['users'][$log['user']]++;
            
            // Count recent activities
            $timestamp = strtotime($log['timestamp']);
            if ($timestamp > $day_ago) {
                $stats['recent_24h']++;
            }
            if ($timestamp > $week_ago) {
                $stats['recent_7d']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Read logs from file
     * 
     * @return array
     */
    private function readLogs(): array {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        // Check file size
        if (filesize($this->log_file) > $this->max_log_size) {
            $this->clearOldLogs(7); // Keep only last 7 days if file too large
        }
        
        $content = file_get_contents($this->log_file);
        $logs = json_decode($content, true);
        
        return is_array($logs) ? $logs : array();
    }
    
    /**
     * Write logs to file
     * 
     * @param array $logs
     * @return bool
     */
    private function writeLogs(array $logs): bool {
        $content = json_encode($logs, JSON_PRETTY_PRINT);
        return file_put_contents($this->log_file, $content) !== false;
    }
    
    /**
     * Get current user identifier
     * 
     * @return string
     */
    private function getCurrentUser(): string {
        if (isset($_SESSION['wpsm']['login'])) {
            return $_SESSION['wpsm']['login'];
        }
        return 'system';
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function getClientIp(): string {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return 'unknown';
    }
}

