<?php
/**
 * API Controller for JSON responses
 * Handles AJAX requests and returns JSON data
 * PHP 8.0+ Compatible
 */

class ApiController extends MainController {
    
    private $response = array(
        'success' => false,
        'message' => '',
        'data' => null
    );
    
    function __construct() {
        // Define API context to prevent HTML output in models
        if (!defined('WPSM_API')) {
            define('WPSM_API', true);
        }
        
        // Clear any output that might have been sent
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set JSON header FIRST, before any parent constructor
        header('Content-Type: application/json', true);
        
        parent::__construct();
        
        // Handle CORS if needed
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
        
        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }
            exit(0);
        }
    }
    
    /**
     * Handle API requests
     */
    function handle() {
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $this->get_endpoint();
        
        try {
            switch ($endpoint) {
                case 'view':
                    $this->handle_view();
                    break;
                case 'submit':
                    $this->handle_submit();
                    break;
                case 'action':
                    $this->handle_action();
                    break;
                case 'data':
                    $this->handle_data();
                    break;
                case 'csrf':
                    $this->handle_csrf();
                    break;
                case 'system-health':
                    $this->handle_system_health();
                    break;
                case 'file-manager':
                    $this->handle_file_manager();
                    break;
                case 'users':
                    $this->handle_users();
                    break;
                case 'cron':
                    $this->handle_cron();
                    break;
                case 'activity-log':
                    $this->handle_activity_log();
                    break;
                case 'email':
                    $this->handle_email();
                    break;
                case 'security-scanner':
                    $this->handle_security_scanner();
                    break;
                case 'performance':
                    $this->handle_performance();
                    break;
                case 'media':
                    $this->handle_media();
                    break;
                case 'database-optimizer':
                    $this->handle_database_optimizer();
                    break;
                default:
                    $this->error('Invalid endpoint', 404);
            }
        } catch (Throwable $e) {
            // PHP 8.0+ compatible error handling
            error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->error('Internal server error', 500);
        }
    }
    
    /**
     * Get endpoint from request
     */
    private function get_endpoint() {
        // PHP 8.0+ compatible: FILTER_SANITIZE_STRING is deprecated, use FILTER_SANITIZE_FULL_SPECIAL_CHARS
        $endpoint = filter_input(INPUT_GET, 'endpoint', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($endpoint)) {
            // Try to extract from URL
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('#/api/([^/?]+)#', $uri, $matches)) {
                $endpoint = $matches[1];
            } else {
                $endpoint = 'view';
            }
        }
        return $endpoint ?: 'view';
    }
    
    /**
     * Validate CSRF token
     */
    private function validate_csrf() {
        $token = null;
        
        // Check header first
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        // Check POST data
        elseif (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
        // Check JSON body
        else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['csrf_token'])) {
                $token = $input['csrf_token'];
            }
        }
        
        if (!$token) {
            return false;
        }
        
        // Validate token
        // PHP 8.0+ compatible
        $form_name = filter_input(INPUT_GET, 'form', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'default';
        return CSRFProtection::validate_token($token, $form_name);
    }
    
    /**
     * Handle view requests
     */
    private function handle_view() {
        // PHP 8.0+ compatible
        $view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'info';
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        // Set current page
        $this->current_page = $view;
        
        // Get view HTML
        ob_start();
        $this->load_view($view, $action);
        $html = ob_get_clean();
        
        $this->success('View loaded', array('html' => $html, 'view' => $view));
    }
    
    /**
     * Get dashboard controller instance (optimized)
     * Note: DashboardController constructor has side effects, so we can't cache it
     * but we can optimize the instantiation
     */
    private function getDashboardInstance() {
        // Suppress output from DashboardController constructor
        ob_start();
        $dashboard = new DashboardController();
        ob_end_clean();
        return $dashboard;
    }
    
    private function load_view($view, $action = null) {
        $dashboard = $this->getDashboardInstance();
        
        if ($action) {
            $dashboard->action = $action;
        }
        
        $dashboard->init_data();
        $dashboard->get_message();
        
        // Normalize view_url once
        $view_url = rtrim($this->settings['view_url'] ?? 'view/', '/\\') . '/';
        
        // Try admin view first, then regular view
        $admin_template = $view_url . $view . '-admin.php';
        $regular_template = $view_url . $view . '.php';
        
        $admin_path = $this->resolveTemplatePath($admin_template);
        $regular_path = $this->resolveTemplatePath($regular_template);
        
        // Use cached file existence checks
        $file_to_load = null;
        if ($admin_path) {
            if (!isset(self::$template_cache[$admin_path])) {
                self::$template_cache[$admin_path] = file_exists($admin_path);
            }
            if (self::$template_cache[$admin_path]) {
                $file_to_load = $admin_path;
            }
        }
        
        if (!$file_to_load && $regular_path) {
            if (!isset(self::$template_cache[$regular_path])) {
                self::$template_cache[$regular_path] = file_exists($regular_path);
            }
            if (self::$template_cache[$regular_path]) {
                $file_to_load = $regular_path;
            }
        }
        
        if ($file_to_load) {
            $data = $dashboard->data;
            include $file_to_load;
        } else {
            // Only log in debug mode
            if ($this->settings['debug'] ?? false) {
                error_log("View not found: $view (checked: $admin_path, $regular_path)");
            }
            echo '<div class="alert alert-danger">View not found: ' . htmlspecialchars($view) . '</div>';
        }
    }
    
    /**
     * Handle action requests
     */
    private function handle_action() {
        // PHP 8.0+ compatible
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if (!$action) {
            $this->error('Action not specified');
            return;
        }
        
        $dashboard = $this->getDashboardInstance();
        $dashboard->action = $action;
        
        // Execute action
        $dashboard->actions();
        
        // Get message
        $dashboard->get_message();
        $message = isset($dashboard->data['message']) ? $dashboard->data['message'] : 'Action completed';
        
        $this->success($message);
    }
    
    /**
     * Handle form submissions
     */
    private function handle_submit() {
        
        // PHP 8.0+ compatible
        $form_type = filter_input(INPUT_GET, 'form', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if (!$form_type) {
            $this->error('Form type not specified');
            return;
        }
        
        // Handle login separately (doesn't need DashboardController)
        if ($form_type === 'login') {
            $this->handle_login();
            return; // Return early to prevent default handling
        }
        
        $dashboard = $this->getDashboardInstance();
        
        // Handle different form types
        switch ($form_type) {
            case 'plugins':
                $dashboard->submit_plugins();
                break;
            case 'themes':
                $dashboard->submit_themes();
                break;
            case 'wpconfig':
                $dashboard->submit_wpconfig();
                break;
            case 'wpconfig_advanced':
                $dashboard->submit_wpconfig_advanced();
                break;
            case 'htaccess':
                $dashboard->submit_htaccess();
                break;
            case 'robots':
                $dashboard->submit_robots();
                break;
            case 'autobackup':
                $dashboard->submit_autobackup();
                break;
            case 'global_settings':
                $dashboard->submit_global_settings();
                break;
            default:
                $this->error('Unknown form type: ' . $form_type);
                return;
        }
        
        // Get message
        $dashboard->get_message();
        $message = isset($dashboard->data['message']) ? $dashboard->data['message'] : 'Form submitted successfully';
        
        // Determine redirect
        $redirect = null;
        if (isset($dashboard->current_page)) {
            $redirect = array('view' => $dashboard->current_page);
        }
        
        $this->success($message, null, $redirect);
    }
    
    /**
     * Handle login submission (without redirects)
     */
    private function handle_login() {
        // SECURITY FIX: Validate CSRF token first
        if (!CSRFProtection::validate_post_token('login')) {
            $this->error('Invalid security token. Please try again.', 403);
            return;
        }
        
        // SECURITY FIX: Check rate limiting
        if (!RateLimiter::check_rate_limit('login', 5, 300)) {
            $remaining = RateLimiter::get_reset_time('login', 300);
            $this->error('Too many login attempts. Please try again in ' . $remaining . ' seconds.', 429);
            return;
        }
        
        RateLimiter::record_attempt('login');
        
        // Get raw input first for debugging
        $raw_username = $_POST['username'] ?? '';
        $raw_password = $_POST['password'] ?? '';
        
        // SECURITY FIX: Use InputValidator for sanitization
        $user_data = array(
            'username' => InputValidator::getInput('username', INPUT_POST, 'string'),
            'password' => InputValidator::getInput('password', INPUT_POST, 'string'),
        );
        
        // Debug logging (remove in production)
        if (defined('WPSM_DEBUG') && WPSM_DEBUG) {
            error_log('Login attempt - Username: ' . (empty($user_data['username']) ? 'EMPTY' : 'SET') . ', Password: ' . (empty($user_data['password']) ? 'EMPTY' : 'SET'));
        }
        
        // Use output buffering to catch any HTML output from DashboardModel
        ob_start();
        try {
            $dashboard_model = new DashboardModel();
            $login = $dashboard_model->get_login();
        } catch (Throwable $e) {
            ob_end_clean();
            error_log('Login Database Error: ' . $e->getMessage());
            $this->error('Database error occurred. Please try again.', 500);
            return;
        }
        // Discard any output from DashboardModel
        ob_end_clean();
        
        if (empty($login)) {
            $this->error('Login is not configured. Please set your login credentials in Global Settings.', 400);
            return;
        }
        
        $error = false;
        $error_message = '';
        
        // Validate input
        if (empty($user_data['password']) || trim($user_data['password']) === '') {
            $error = true;
            $error_message = 'Password field cannot be empty.';
        }
        if (empty($user_data['username']) || trim($user_data['username']) === '') {
            $error = true;
            $error_message = 'Username/Email field cannot be empty.';
        }
        
        if ($error) {
            $this->error($error_message, 400);
            return;
        }
        
        if (!empty($login) && is_array($login)) {
            // Check username/email - Fixed logic for PHP 8.0+
            $is_email = filter_var($user_data['username'], FILTER_VALIDATE_EMAIL) !== false;
            
            if ($is_email) {
                // User entered email
                if (!isset($login['email']) || $login['email'] !== $user_data['username']) {
                    $error = true;
                }
            } else {
                // User entered username
                if (!isset($login['username']) || $login['username'] !== $user_data['username']) {
                    $error = true;
                }
            }
            
            // Check password
            if (!$error) {
                try {
                    include_once('ext/PasswordHash.php');
                    $t_hasher = new PasswordHash(8, FALSE);
                    $hash = $login['password'] ?? '';
                    $check_hash = $user_data['password'];
                    
                    if (empty($hash)) {
                        $error = true;
                    } else {
                        $check = $t_hasher->CheckPassword($check_hash, $hash);
                        if (!$check) {
                            $error = true;
                        }
                    }
                } catch (Throwable $e) {
                    error_log('Password check error: ' . $e->getMessage());
                    $error = true;
                }
            }
            
            if ($error) {
                $this->error('Wrong email/username and/or password', 401);
                return;
            } else {
                // SECURITY FIX: Reset rate limit on successful login
                RateLimiter::reset_rate_limit('login');
                $this->set_session_var('login', true);
                $this->success('You have been successfully logged in.', null, array('view' => 'info'));
                return;
            }
        }
        
        $this->error('Login failed', 500);
    }
    
    /**
     * Handle CSRF token requests
     */
    private function handle_csrf() {
        // PHP 8.0+ compatible
        $form_name = filter_input(INPUT_GET, 'form', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'default';
        $token = CSRFProtection::get_token($form_name);
        $this->success('Token generated', array('token' => $token));
    }
    
    /**
     * Handle data requests
     */
    private function handle_data() {
        // PHP 8.0+ compatible
        $data_type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if (!$data_type) {
            $this->error('Data type not specified');
            return;
        }
        
        $dashboard = $this->getDashboardInstance();
        
        $data = null;
        
        switch ($data_type) {
            case 'info':
                $data = array(
                    'core_info' => $dashboard->get_wordpress_core_info(),
                    'plugins_info' => $dashboard->get_plugins_info(),
                    'themes_info' => $dashboard->get_themes_info(),
                    'php_info' => $dashboard->get_php_info(),
                    'server_info' => $dashboard->get_server_info()
                );
                break;
            case 'plugins':
                $active_plugins = $dashboard->dashboard_model->get_active_plugins();
                // Convert PHP serialized data to JSON for JavaScript
                if ($active_plugins && isset($active_plugins['option_value'])) {
                    $unserialized = @unserialize($active_plugins['option_value']);
                    if ($unserialized !== false) {
                        $active_plugins['option_value'] = json_encode($unserialized);
                    }
                }
                $data = array(
                    'active_plugins' => $active_plugins,
                    'all_plugins' => $dashboard->dashboard_model->scan_plugins_directory($dashboard->wp_dir)
                );
                break;
            case 'themes':
                
                $data = array(
                    'themes' => $dashboard->dashboard_model->scan_themes_directory($dashboard->wp_dir)
                );
                break;
            case 'backup':
                // PHP 8.0+ compatible
                $backup_type = filter_input(INPUT_GET, 'backup_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                if ($backup_type == 'database') {
                    $data = array(
                        'backups' => $dashboard->dashboard_model->get_database_backups()
                    );
                } else {
                    $data = array(
                        'backups' => $dashboard->dashboard_model->get_file_backups()
                    );
                }
                break;
            default:
                $this->error('Unknown data type: ' . $data_type);
                return;
        }
        
        $this->success('Data retrieved', $data);
    }
    
    /**
     * Handle system health data request
     */
    private function handle_system_health() {
        $health_service = new SystemHealthService();
        $metrics = $health_service->getHealthMetrics();
        $this->success('Health metrics retrieved', $metrics);
    }
    
    /**
     * Handle file manager operations
     */
    private function handle_file_manager() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'list';
        $file_manager = new FileManagerService();
        
        try {
            switch ($action) {
                case 'list':
                    $path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
                    $files = $file_manager->listDirectory($path);
                    $this->success('Files retrieved', array('files' => $files, 'path' => $path));
                    break;
                    
                case 'read':
                    $path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if (empty($path)) {
                        $this->error('Path is required');
                        return;
                    }
                    $content = $file_manager->readFile($path);
                    $this->success('File read', array('content' => $content, 'path' => $path));
                    break;
                    
                case 'write':
                    $path = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $content = $_POST['content'] ?? '';
                    if (empty($path)) {
                        $this->error('Path is required');
                        return;
                    }
                    $result = $file_manager->writeFile($path, $content);
                    if ($result) {
                        $this->success('File saved', array('path' => $path));
                    } else {
                        $this->error('Failed to save file');
                    }
                    break;
                    
                case 'delete':
                    $path = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if (empty($path)) {
                        $this->error('Path is required');
                        return;
                    }
                    $result = $file_manager->delete($path);
                    if ($result) {
                        $this->success('File deleted', array('path' => $path));
                    } else {
                        $this->error('Failed to delete file');
                    }
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('File manager error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle user management operations
     */
    private function handle_users() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'list';
        $user_service = new UserManagementService();
        
        try {
            switch ($action) {
                case 'list':
                    $users = $user_service->getUsers();
                    $this->success('Users retrieved', array('users' => $users));
                    break;
                    
                case 'get':
                    $user_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($user_id)) {
                        $this->error('User ID is required');
                        return;
                    }
                    $user = $user_service->getUser((int)$user_id);
                    if ($user) {
                        $this->success('User retrieved', array('user' => $user));
                    } else {
                        $this->error('User not found');
                    }
                    break;
                    
                case 'create':
                    $user_data = array(
                        'user_login' => InputValidator::getInput('user_login', INPUT_POST, 'string'),
                        'user_email' => InputValidator::getInput('user_email', INPUT_POST, 'string'),
                        'user_pass' => InputValidator::getInput('user_pass', INPUT_POST, 'string'),
                        'display_name' => InputValidator::getInput('display_name', INPUT_POST, 'string'),
                        'role' => InputValidator::getInput('role', INPUT_POST, 'string')
                    );
                    $result = $user_service->createUser($user_data);
                    if ($result['success']) {
                        $this->success('User created', $result);
                    } else {
                        $this->error($result['message'] ?? 'Failed to create user');
                    }
                    break;
                    
                case 'update':
                    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($user_id)) {
                        $this->error('User ID is required');
                        return;
                    }
                    $user_data = array();
                    if (isset($_POST['user_email'])) {
                        $user_data['user_email'] = InputValidator::getInput('user_email', INPUT_POST, 'string');
                    }
                    if (isset($_POST['user_pass']) && !empty($_POST['user_pass'])) {
                        $user_data['user_pass'] = InputValidator::getInput('user_pass', INPUT_POST, 'string');
                    }
                    if (isset($_POST['display_name'])) {
                        $user_data['display_name'] = InputValidator::getInput('display_name', INPUT_POST, 'string');
                    }
                    if (isset($_POST['role'])) {
                        $user_data['role'] = InputValidator::getInput('role', INPUT_POST, 'string');
                    }
                    $result = $user_service->updateUser((int)$user_id, $user_data);
                    if ($result) {
                        $this->success('User updated');
                    } else {
                        $this->error('Failed to update user');
                    }
                    break;
                    
                case 'delete':
                    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($user_id)) {
                        $this->error('User ID is required');
                        return;
                    }
                    $reassign = filter_input(INPUT_POST, 'reassign', FILTER_VALIDATE_BOOLEAN);
                    $reassign_to = filter_input(INPUT_POST, 'reassign_to', FILTER_SANITIZE_NUMBER_INT);
                    $result = $user_service->deleteUser((int)$user_id, $reassign, $reassign_to ?: null);
                    if ($result) {
                        $this->success('User deleted');
                    } else {
                        $this->error('Failed to delete user');
                    }
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('User management error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle cron operations
     */
    private function handle_cron() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'list';
        $cron_service = new CronService();
        
        try {
            switch ($action) {
                case 'list':
                    $jobs = $cron_service->getCronJobs();
                    $this->success('Cron jobs retrieved', array('jobs' => $jobs));
                    break;
                    
                case 'run':
                    $hook = filter_input(INPUT_POST, 'hook', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if (empty($hook)) {
                        $this->error('Hook is required');
                        return;
                    }
                    $result = $cron_service->runCronJob($hook);
                    if ($result['success']) {
                        $this->success($result['message']);
                    } else {
                        $this->error($result['message']);
                    }
                    break;
                    
                case 'delete':
                    $hook = filter_input(INPUT_POST, 'hook', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $timestamp = filter_input(INPUT_POST, 'timestamp', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($hook) || empty($timestamp)) {
                        $this->error('Hook and timestamp are required');
                        return;
                    }
                    $result = $cron_service->deleteCronJob($hook, (int)$timestamp);
                    if ($result) {
                        $this->success('Cron job deleted');
                    } else {
                        $this->error('Failed to delete cron job');
                    }
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Cron error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle activity log operations
     */
    private function handle_activity_log() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'list';
        $activity_service = new ActivityLogService();
        
        try {
            switch ($action) {
                case 'list':
                    $limit = filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT) ?: 100;
                    $filter_action = filter_input(INPUT_GET, 'filter_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $filter_user = filter_input(INPUT_GET, 'filter_user', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $logs = $activity_service->getLogs($limit, $filter_action, $filter_user);
                    $this->success('Activity logs retrieved', array('logs' => $logs));
                    break;
                    
                case 'statistics':
                    $stats = $activity_service->getStatistics();
                    $this->success('Statistics retrieved', array('statistics' => $stats));
                    break;
                    
                case 'clear':
                    $days = filter_input(INPUT_POST, 'days', FILTER_SANITIZE_NUMBER_INT) ?: 30;
                    $result = $activity_service->clearOldLogs($days);
                    if ($result) {
                        $this->success('Old logs cleared');
                    } else {
                        $this->error('Failed to clear logs');
                    }
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Activity log error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle email operations
     */
    private function handle_email() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'info';
        $email_service = new EmailService();
        
        try {
            switch ($action) {
                case 'info':
                    $info = $email_service->getEmailInfo();
                    $this->success('Email info retrieved', array('info' => $info));
                    break;
                    
                case 'test':
                    $to = InputValidator::getInput('to', INPUT_POST, 'email');
                    $subject = InputValidator::getInput('subject', INPUT_POST, 'string');
                    $message = InputValidator::getInput('message', INPUT_POST, 'string');
                    
                    if (empty($to)) {
                        $this->error('Email address is required');
                        return;
                    }
                    
                    $result = $email_service->testEmail($to, $subject, $message);
                    if ($result['success']) {
                        $this->success($result['message']);
                    } else {
                        $this->error($result['message']);
                    }
                    break;
                    
                case 'test_php':
                    $to = InputValidator::getInput('to', INPUT_POST, 'email');
                    $subject = InputValidator::getInput('subject', INPUT_POST, 'string') ?: 'Test Email';
                    $message = InputValidator::getInput('message', INPUT_POST, 'string') ?: 'This is a test email.';
                    
                    if (empty($to)) {
                        $this->error('Email address is required');
                        return;
                    }
                    
                    $result = $email_service->testPhpMail($to, $subject, $message);
                    if ($result['success']) {
                        $this->success($result['message']);
                    } else {
                        $this->error($result['message']);
                    }
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Email error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle security scanner operations
     */
    private function handle_security_scanner() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'scan';
        $scanner_service = new SecurityScannerService();
        
        try {
            switch ($action) {
                case 'scan':
                    $results = $scanner_service->scan();
                    $this->success('Security scan completed', array('results' => $results));
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Security scanner error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle performance profiler operations
     */
    private function handle_performance() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'metrics';
        $profiler_service = new PerformanceProfilerService();
        
        try {
            switch ($action) {
                case 'metrics':
                    $metrics = $profiler_service->getMetrics();
                    $this->success('Performance metrics retrieved', array('metrics' => $metrics));
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Performance profiler error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle media library operations
     */
    private function handle_media() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'list';
        $media_service = new MediaLibraryService();
        
        try {
            switch ($action) {
                case 'list':
                    $limit = filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT) ?: 50;
                    $offset = filter_input(INPUT_GET, 'offset', FILTER_SANITIZE_NUMBER_INT) ?: 0;
                    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
                    $result = $media_service->getMediaFiles($limit, $offset, $search);
                    $this->success('Media files retrieved', $result);
                    break;
                    
                case 'statistics':
                    $stats = $media_service->getStatistics();
                    $this->success('Media statistics retrieved', array('statistics' => $stats));
                    break;
                    
                case 'delete':
                    $file_id = filter_input(INPUT_POST, 'file_id', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($file_id)) {
                        $this->error('File ID is required');
                        return;
                    }
                    $result = $media_service->deleteFile($file_id);
                    if ($result['success']) {
                        $this->success($result['message']);
                    } else {
                        $this->error($result['message']);
                    }
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Media library error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Handle database optimizer operations
     */
    private function handle_database_optimizer() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'analyze';
        $optimizer_service = new DatabaseOptimizerService();
        
        try {
            switch ($action) {
                case 'analyze':
                    $analysis = $optimizer_service->analyze();
                    $this->success('Database analysis completed', array('analysis' => $analysis));
                    break;
                    
                case 'optimize':
                    $result = $optimizer_service->optimizeAllTables();
                    $this->success('Tables optimized', $result);
                    break;
                    
                case 'clean_orphaned':
                    $result = $optimizer_service->cleanOrphaned();
                    $this->success('Orphaned data cleaned', $result);
                    break;
                    
                case 'clean_revisions':
                    $keep = filter_input(INPUT_POST, 'keep', FILTER_SANITIZE_NUMBER_INT) ?: 3;
                    $result = $optimizer_service->cleanRevisions($keep);
                    $this->success('Revisions cleaned', $result);
                    break;
                    
                case 'clean_transients':
                    $result = $optimizer_service->cleanTransients();
                    $this->success('Transients cleaned', $result);
                    break;
                    
                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Database optimizer error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    
    /**
     * Send success response
     * 
     * @param string $message Success message
     * @param mixed $data Response data
     * @param array|null $redirect Redirect information
     * @return void
     */
    private function success(string $message, $data = null, ?array $redirect = null): void {
        Response::jsonSuccess($message, $data, $redirect);
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return void
     */
    private function error(string $message, int $code = 400): void {
        Response::jsonError($message, $code);
    }
}
