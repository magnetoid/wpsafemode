<?php
/**
 * Cron Service
 * Manages WordPress cron jobs
 */
class CronService {
    
    private $model;
    private $db_prefix;
    
    /**
     * Constructor
     * 
     * @param DashboardModel|null $model Dashboard model instance
     */
    public function __construct(?DashboardModel $model = null) {
        $this->model = $model ?? new DashboardModel();
        $this->db_prefix = $this->model->db_prefix ?? 'wp_';
    }
    
    /**
     * Get all cron jobs
     * 
     * @return array
     */
    public function getCronJobs(): array {
        try {
            $cron_option = $this->model->get_option_data('cron');
            if (empty($cron_option) || empty($cron_option['option_value'])) {
                return array();
            }
            
            $cron_array = maybe_unserialize($cron_option['option_value']);
            if (!is_array($cron_array)) {
                return array();
            }
            
            $jobs = array();
            foreach ($cron_array as $timestamp => $cron) {
                if (!is_array($cron)) continue;
                
                foreach ($cron as $hook => $dings) {
                    foreach ($dings as $key => $data) {
                        $jobs[] = array(
                            'hook' => $hook,
                            'timestamp' => $timestamp,
                            'schedule' => $data['schedule'] ?? false,
                            'args' => $data['args'] ?? array(),
                            'next_run' => $timestamp,
                            'next_run_formatted' => date('Y-m-d H:i:s', $timestamp),
                            'is_past_due' => $timestamp < time()
                        );
                    }
                }
            }
            
            // Sort by next run time
            usort($jobs, function($a, $b) {
                return $a['timestamp'] <=> $b['timestamp'];
            });
            
            return $jobs;
        } catch (Throwable $e) {
            Logger::error('Error getting cron jobs', ['error' => $e->getMessage()]);
            return array();
        }
    }
    
    /**
     * Run cron job manually
     * 
     * @param string $hook Hook name
     * @param array $args Arguments
     * @return array Result
     */
    public function runCronJob(string $hook, array $args = array()): array {
        try {
            if (!has_action($hook)) {
                // Try to load WordPress
                $wp_load = $this->model->wp_dir . 'wp-load.php';
                if (file_exists($wp_load)) {
                    require_once $wp_load;
                }
            }
            
            if (has_action($hook)) {
                do_action($hook, $args);
                Logger::info('Cron job executed', ['hook' => $hook]);
                return array('success' => true, 'message' => 'Cron job executed successfully');
            }
            
            return array('success' => false, 'message' => 'Hook not found');
        } catch (Throwable $e) {
            Logger::error('Error running cron job', ['hook' => $hook, 'error' => $e->getMessage()]);
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Delete cron job
     * 
     * @param string $hook Hook name
     * @param int $timestamp Timestamp
     * @return bool
     */
    public function deleteCronJob(string $hook, int $timestamp): bool {
        try {
            $cron_option = $this->model->get_option_data('cron');
            if (empty($cron_option)) {
                return false;
            }
            
            $cron_array = maybe_unserialize($cron_option['option_value']);
            if (!is_array($cron_array) || !isset($cron_array[$timestamp][$hook])) {
                return false;
            }
            
            unset($cron_array[$timestamp][$hook]);
            
            if (empty($cron_array[$timestamp])) {
                unset($cron_array[$timestamp]);
            }
            
            $this->model->update_option_data('cron', $cron_array);
            
            Logger::info('Cron job deleted', ['hook' => $hook, 'timestamp' => $timestamp]);
            return true;
        } catch (Throwable $e) {
            Logger::error('Error deleting cron job', ['hook' => $hook, 'error' => $e->getMessage()]);
            return false;
        }
    }
}

// Helper function if not available
if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($original) {
        if (is_serialized($original)) {
            return unserialize($original);
        }
        return $original;
    }
}

if (!function_exists('is_serialized')) {
    function is_serialized($data) {
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' == $data) {
            return true;
        }
        if (!preg_match('/^([adObis]):/', $data, $badions)) {
            return false;
        }
        switch ($badions[1]) {
            case 'a' :
            case 'O' :
            case 's' :
                if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data)) {
                    return true;
                }
                break;
            case 'b' :
            case 'i' :
            case 'd' :
                if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data)) {
                    return true;
                }
                break;
        }
        return false;
    }
}


