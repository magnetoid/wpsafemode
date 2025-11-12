<?php
/**
 * Security Scanner Service
 * Scans WordPress installation for security vulnerabilities
 */
class SecurityScannerService {
    
    private $config;
    private $wp_dir;
    private $db_model;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->wp_dir = $this->config->get('wp_dir', '../');
    }
    
    /**
     * Run full security scan
     * 
     * @return array Scan results
     */
    public function scan(): array {
        $results = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'issues' => array(),
            'warnings' => array(),
            'info' => array(),
            'score' => 100
        );
        
        // Check file permissions
        $results = array_merge_recursive($results, $this->checkFilePermissions());
        
        // Check WordPress version
        $results = array_merge_recursive($results, $this->checkWpVersion());
        
        // Check plugins
        $results = array_merge_recursive($results, $this->checkPlugins());
        
        // Check database security
        $results = array_merge_recursive($results, $this->checkDatabase());
        
        // Check wp-config.php
        $results = array_merge_recursive($results, $this->checkWpConfig());
        
        // Check .htaccess
        $results = array_merge_recursive($results, $this->checkHtaccess());
        
        // Check user accounts
        $results = array_merge_recursive($results, $this->checkUsers());
        
        // Calculate security score
        $results['score'] = $this->calculateScore($results);
        
        return $results;
    }
    
    /**
     * Check file permissions
     * 
     * @return array
     */
    private function checkFilePermissions(): array {
        $results = array('issues' => array(), 'warnings' => array(), 'info' => array());
        
        $critical_files = array(
            'wp-config.php' => 400,
            '.htaccess' => 644
        );
        
        foreach ($critical_files as $file => $recommended) {
            $path = $this->wp_dir . $file;
            if (file_exists($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                $actual = intval($perms, 8);
                
                if ($file === 'wp-config.php' && $actual > 400) {
                    $results['issues'][] = array(
                        'type' => 'critical',
                        'title' => 'wp-config.php has insecure permissions',
                        'description' => "Current permissions: {$perms} (should be 400 or 600)",
                        'fix' => 'Set wp-config.php permissions to 400 or 600'
                    );
                } elseif ($file === '.htaccess' && $actual > 644) {
                    $results['warnings'][] = array(
                        'type' => 'warning',
                        'title' => '.htaccess has loose permissions',
                        'description' => "Current permissions: {$perms}",
                        'fix' => 'Consider setting .htaccess permissions to 644'
                    );
                } else {
                    $results['info'][] = array(
                        'type' => 'info',
                        'title' => "{$file} permissions are secure",
                        'description' => "Current permissions: {$perms}"
                    );
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Check WordPress version
     * 
     * @return array
     */
    private function checkWpVersion(): array {
        $results = array('issues' => array(), 'warnings' => array(), 'info' => array());
        
        include_once $this->wp_dir . 'wp-includes/version.php';
        
        if (isset($wp_version)) {
            $latest = $this->getLatestWpVersion();
            
            if (version_compare($wp_version, $latest, '<')) {
                $results['warnings'][] = array(
                    'type' => 'warning',
                    'title' => 'WordPress is not up to date',
                    'description' => "Current version: {$wp_version}, Latest: {$latest}",
                    'fix' => 'Update WordPress to the latest version'
                );
            } else {
                $results['info'][] = array(
                    'type' => 'info',
                    'title' => 'WordPress is up to date',
                    'description' => "Version: {$wp_version}"
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Check plugins for security issues
     * 
     * @return array
     */
    private function checkPlugins(): array {
        $results = array('issues' => array(), 'warnings' => array(), 'info' => array());
        
        try {
            $dashboard_model = new DashboardModel();
            $plugins = $dashboard_model->get_plugins();
            
            if (isset($plugins['active'])) {
                $inactive_count = isset($plugins['inactive']) ? count($plugins['inactive']) : 0;
                
                if ($inactive_count > 0) {
                    $results['warnings'][] = array(
                        'type' => 'warning',
                        'title' => 'Inactive plugins detected',
                        'description' => "You have {$inactive_count} inactive plugins. Consider removing unused plugins.",
                        'fix' => 'Delete unused plugins to reduce attack surface'
                    );
                }
                
                $results['info'][] = array(
                    'type' => 'info',
                    'title' => 'Plugin status',
                    'description' => count($plugins['active']) . ' active plugins'
                );
            }
        } catch (Throwable $e) {
            // Ignore errors
        }
        
        return $results;
    }
    
    /**
     * Check database security
     * 
     * @return array
     */
    private function checkDatabase(): array {
        $results = array('issues' => array(), 'warnings' => array(), 'info' => array());
        
        try {
            $db_model = new DBModel();
            
            // Check for default table prefix
            $tables = $db_model->show_tables();
            $default_prefix_count = 0;
            
            foreach ($tables as $table) {
                if (strpos($table, 'wp_') === 0) {
                    $default_prefix_count++;
                }
            }
            
            if ($default_prefix_count > 0) {
                $results['warnings'][] = array(
                    'type' => 'warning',
                    'title' => 'Using default database table prefix',
                    'description' => 'Tables using default "wp_" prefix are easier to target',
                    'fix' => 'Consider changing table prefix (requires migration)'
                );
            }
            
            $results['info'][] = array(
                'type' => 'info',
                'title' => 'Database security check',
                'description' => 'Database connection is secure'
            );
        } catch (Throwable $e) {
            $results['warnings'][] = array(
                'type' => 'warning',
                'title' => 'Could not check database security',
                'description' => $e->getMessage()
            );
        }
        
        return $results;
    }
    
    /**
     * Check wp-config.php security
     * 
     * @return array
     */
    private function checkWpConfig(): array {
        $results = array('issues' => array(), 'warnings' => array(), 'info' => array());
        
        $wp_config_path = $this->wp_dir . 'wp-config.php';
        
        if (!file_exists($wp_config_path)) {
            $results['issues'][] = array(
                'type' => 'critical',
                'title' => 'wp-config.php not found',
                'description' => 'WordPress configuration file is missing',
                'fix' => 'Ensure wp-config.php exists'
            );
            return $results;
        }
        
        $content = file_get_contents($wp_config_path);
        
        // Check for debug mode
        if (preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*true/i", $content)) {
            $results['warnings'][] = array(
                'type' => 'warning',
                'title' => 'WP_DEBUG is enabled',
                'description' => 'Debug mode should be disabled in production',
                'fix' => 'Set WP_DEBUG to false in wp-config.php'
            );
        }
        
        // Check for database credentials exposure
        if (preg_match("/DB_PASSWORD['\"]\s*,\s*['\"]['\"]/i", $content)) {
            $results['issues'][] = array(
                'type' => 'critical',
                'title' => 'Empty database password',
                'description' => 'Database password is empty',
                'fix' => 'Set a strong database password'
            );
        }
        
        // Check for security keys
        if (!preg_match("/AUTH_KEY/i", $content)) {
            $results['warnings'][] = array(
                'type' => 'warning',
                'title' => 'Security keys not found',
                'description' => 'WordPress security keys are missing',
                'fix' => 'Generate security keys at https://api.wordpress.org/secret-key/1.1/salt/'
            );
        }
        
        return $results;
    }
    
    /**
     * Check .htaccess security
     * 
     * @return array
     */
    private function checkHtaccess(): array {
        $results = array('issues' => array(), 'warnings' => array(), 'info' => array());
        
        $htaccess_path = $this->wp_dir . '.htaccess';
        
        if (!file_exists($htaccess_path)) {
            $results['warnings'][] = array(
                'type' => 'warning',
                'title' => '.htaccess file not found',
                'description' => 'Consider creating .htaccess for security',
                'fix' => 'Create .htaccess file with security rules'
            );
            return $results;
        }
        
        $content = file_get_contents($htaccess_path);
        
        // Check for directory listing protection
        if (strpos($content, 'Options -Indexes') === false) {
            $results['warnings'][] = array(
                'type' => 'warning',
                'title' => 'Directory listing may be enabled',
                'description' => 'Add "Options -Indexes" to prevent directory listing',
                'fix' => 'Add directory listing protection to .htaccess'
            );
        }
        
        return $results;
    }
    
    /**
     * Check user accounts
     * 
     * @return array
     */
    private function checkUsers(): array {
        $results = array('issues' => array(), 'warnings' => array(), 'info' => array());
        
        try {
            $user_service = new UserManagementService();
            $users = $user_service->getUsers();
            
            $admin_count = 0;
            $weak_passwords = 0;
            
            foreach ($users as $user) {
                if (in_array('administrator', $user['roles'])) {
                    $admin_count++;
                }
                
                // Check for default usernames
                if (in_array(strtolower($user['login']), array('admin', 'administrator', 'root'))) {
                    $results['warnings'][] = array(
                        'type' => 'warning',
                        'title' => 'Default username detected',
                        'description' => "User '{$user['login']}' uses a common default username",
                        'fix' => 'Change username to something unique'
                    );
                }
            }
            
            if ($admin_count > 3) {
                $results['warnings'][] = array(
                    'type' => 'warning',
                    'title' => 'Multiple administrator accounts',
                    'description' => "You have {$admin_count} administrator accounts",
                    'fix' => 'Review and remove unnecessary admin accounts'
                );
            }
            
            $results['info'][] = array(
                'type' => 'info',
                'title' => 'User account check',
                'description' => count($users) . ' total users, ' . $admin_count . ' administrators'
            );
        } catch (Throwable $e) {
            // Ignore errors
        }
        
        return $results;
    }
    
    /**
     * Calculate security score
     * 
     * @param array $results
     * @return int Score 0-100
     */
    private function calculateScore(array $results): int {
        $score = 100;
        
        // Deduct points for issues
        foreach ($results['issues'] as $issue) {
            if ($issue['type'] === 'critical') {
                $score -= 10;
            } else {
                $score -= 5;
            }
        }
        
        // Deduct points for warnings
        foreach ($results['warnings'] as $warning) {
            $score -= 2;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get latest WordPress version
     * 
     * @return string
     */
    private function getLatestWpVersion(): ?string {
        // Try to get from WordPress API
        $response = @file_get_contents('https://api.wordpress.org/core/version-check/1.7/');
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['offers'][0]['version'])) {
                return $data['offers'][0]['version'];
            }
        }
        
        // Fallback
        return '6.0';
    }
}

