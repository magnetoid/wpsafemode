<?php
/**
 * Cache Service
 * Handles Object Cache and Transient operations
 */
class CacheService
{

    private $model;
    private $db_prefix;

    public function __construct(?DashboardModel $model = null)
    {
        $this->model = $model ?? new DashboardModel();
        $this->db_prefix = $this->model->get_db_prefix();
    }

    /**
     * Get Object Cache Status
     * checks for object-cache.php drop-in
     * 
     * @return array
     */
    public function getCacheStatus(): array
    {
        $wp_dir = $this->model->get_wp_dir();
        $dropin_path = $wp_dir . 'wp-content/object-cache.php';

        $status = [
            'has_dropin' => file_exists($dropin_path),
            'dropin_size' => file_exists($dropin_path) ? $this->formatSize(filesize($dropin_path)) : 0,
            'type' => 'None', // Redis, Memcached, etc.
        ];

        if ($status['has_dropin']) {
            $content = file_get_contents($dropin_path);
            if (stripos($content, 'redis') !== false) {
                $status['type'] = 'Redis';
            } elseif (stripos($content, 'memcached') !== false) {
                $status['type'] = 'Memcached';
            } else {
                $status['type'] = 'Unknown (Custom)';
            }
        }

        return $status;
    }

    /**
     * Flush Object Cache
     * Tries to use WP function if available, or specific redis/memcached flush if possible
     * 
     * @return bool
     */
    public function flushCache(): bool
    {
        // Best effort: Try to load WP and call wp_cache_flush
        // This is risky if WP is broken, but object cache is usually flushed via WP.
        // If WP is broken, we might try to connect to Redis directly if configured, but that's complex.

        $wp_load = $this->model->get_wp_dir() . 'wp-load.php';
        if (file_exists($wp_load)) {
            // Suppress output
            ob_start();
            define('WP_USE_THEMES', false);
            include_once $wp_load;
            ob_end_clean();

            if (function_exists('wp_cache_flush')) {
                return wp_cache_flush();
            }
        }

        return false;
    }

    /**
     * Get Transient Statistics
     * 
     * @return array
     */
    public function getTransientStats(): array
    {
        try {
            // Count all transients
            $q_all = $this->model->prepare("SELECT COUNT(*) as count FROM {$this->db_prefix}options WHERE option_name LIKE '_transient_%'");
            $q_all->execute();
            $all = $q_all->fetch(PDO::FETCH_ASSOC)['count'];

            // Count expired transients (timeout)
            // Transients have a pair: _transient_NAME and _transient_timeout_NAME
            // We look for _transient_timeout_NAME < time()
            $q_expired = $this->model->prepare("SELECT COUNT(*) as count FROM {$this->db_prefix}options WHERE option_name LIKE '_transient_timeout_%' AND option_value < :now");
            $q_expired->bindValue(':now', time(), PDO::PARAM_INT);
            $q_expired->execute();
            $expired = $q_expired->fetch(PDO::FETCH_ASSOC)['count'];

            return [
                'total' => $all,
                'expired' => $expired
            ];
        } catch (Exception $e) {
            return ['total' => 0, 'expired' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Clean Expired Transients
     * 
     * @return int Number of deleted transients
     */
    public function cleanExpiredTransients(): int
    {
        try {
            $now = time();
            // 1. Find expired timeouts
            $sql = "SELECT option_name FROM {$this->db_prefix}options WHERE option_name LIKE '_transient_timeout_%' AND option_value < :now";
            $stmt = $this->model->prepare($sql);
            $stmt->bindValue(':now', $now, PDO::PARAM_INT);
            $stmt->execute();
            $expired_timeouts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($expired_timeouts)) {
                return 0;
            }

            $count = 0;
            foreach ($expired_timeouts as $timeout_name) {
                // timeout name is _transient_timeout_KEY
                // data name is _transient_KEY
                $transient_name = str_replace('_transient_timeout_', '_transient_', $timeout_name);

                // Delete both
                $del = $this->model->prepare("DELETE FROM {$this->db_prefix}options WHERE option_name = :t_name OR option_name = :d_name");
                $del->execute([':t_name' => $timeout_name, ':d_name' => $transient_name]);
                $count++;
            }

            return $count;
        } catch (Exception $e) {
            Logger::error('Error cleaning expired transients: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean All Transients
     * 
     * @return bool
     */
    public function cleanAllTransients(): bool
    {
        try {
            $sql = "DELETE FROM {$this->db_prefix}options WHERE option_name LIKE '_transient_%'";
            $this->model->prepare($sql)->execute();
            return true;
        } catch (Exception $e) {
            Logger::error('Error cleaning all transients: ' . $e->getMessage());
            return false;
        }
    }

    private function formatSize($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
    }
}
