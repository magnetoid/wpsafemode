<?php
/**
 * API Controller for JSON responses
 * Handles AJAX requests and returns JSON data
 */

class ApiController extends MainController {
    
    private $response = array(
        'success' => false,
        'message' => '',
        'data' => null
    );
    
    function __construct() {
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
        
        // Validate CSRF token for POST/PUT/DELETE
        if (in_array($method, array('POST', 'PUT', 'DELETE'))) {
            if (!$this->validate_csrf()) {
                $this->error('Invalid CSRF token', 403);
                return;
            }
        }
        
        // Route to appropriate handler
        switch ($endpoint) {
            case 'view':
                $this->handle_view();
                break;
            case 'action':
                $this->handle_action();
                break;
            case 'submit':
                $this->handle_submit();
                break;
            case 'data':
                $this->handle_data();
                break;
            case 'csrf':
                $this->handle_csrf();
                break;
            default:
                $this->error('Invalid endpoint', 404);
        }
    }
    
    /**
     * Get API endpoint from request
     */
    private function get_endpoint() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove base path
        $base_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($base_path !== '/') {
            $path = str_replace($base_path, '', $path);
        }
        
        // Extract endpoint from /api/endpoint
        if (preg_match('#/api/([^/?]+)#', $path, $matches)) {
            return $matches[1];
        }
        
        // Fallback to query parameter
        return filter_input(INPUT_GET, 'endpoint', FILTER_SANITIZE_STRING) ?: 'view';
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
        $form_name = filter_input(INPUT_GET, 'form', FILTER_SANITIZE_STRING) ?: 'default';
        return CSRFProtection::validate_token($token, $form_name);
    }
    
    /**
     * Handle view requests
     */
    private function handle_view() {
        $view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_STRING) ?: 'info';
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
        
        // Set current page
        $this->current_page = $view;
        
        // Get view HTML
        ob_start();
        $this->load_view($view, $action);
        $html = ob_get_clean();
        
        $this->success('View loaded', array('html' => $html, 'view' => $view));
    }
    
    /**
     * Load view content
     */
    private function load_view($view, $action = null) {
        // Use output buffering to prevent DashboardController from outputting HTML
        ob_start();
        // Initialize dashboard controller to get data
        $dashboard = new DashboardController();
        // Discard any HTML output from constructor
        ob_end_clean();
        
        // Set action if provided
        if ($action) {
            $dashboard->action = $action;
        }
        
        // Get view data
        $dashboard->init_data();
        $dashboard->get_message();
        
        // Try admin view first, fallback to regular view
        $admin_view_file = $this->settings['view_url'] . $view . '-admin.php';
        $view_file = $this->settings['view_url'] . $view . '.php';
        
        $file_to_load = null;
        if (file_exists($admin_view_file)) {
            $file_to_load = $admin_view_file;
        } elseif (file_exists($view_file)) {
            $file_to_load = $view_file;
        }
        
        if ($file_to_load) {
            // Set data for view
            $data = $dashboard->data;
            include $file_to_load;
        } else {
            echo '<div class="alert alert-danger">View not found: ' . htmlspecialchars($view) . '</div>';
        }
    }
    
    /**
     * Handle action requests
     */
    private function handle_action() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
        
        if (!$action) {
            $this->error('Action not specified');
            return;
        }
        
        // Use output buffering to prevent DashboardController from outputting HTML
        ob_start();
        $dashboard = new DashboardController();
        // Discard any HTML output from constructor
        ob_end_clean();
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
        $form_type = filter_input(INPUT_GET, 'form', FILTER_SANITIZE_STRING);
        
        if (!$form_type) {
            // Try to detect form type from POST data
            $form_type = $this->detect_form_type();
        }
        
        if (!$form_type) {
            $this->error('Form type not specified');
            return;
        }
        
        // Handle login separately (doesn't need DashboardController)
        if ($form_type === 'login') {
            $this->handle_login();
            return; // Return early to prevent default handling
        }
        
        // Use output buffering to prevent DashboardController from outputting HTML
        ob_start();
        $dashboard = new DashboardController();
        // Discard any HTML output from constructor
        ob_end_clean();
        
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
            case 'site_url':
                $dashboard->submit_site_url();
                break;
            case 'backup_database':
                $dashboard->submit_backup_database();
                break;
            case 'backup_files':
                $dashboard->submit_backup_files();
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
        
        // SECURITY FIX: Use SecureInput for sanitization
        $user_data = array(
            'username' => SecureInput::get_input('username', INPUT_POST, 'string'),
            'password' => SecureInput::get_input('password', INPUT_POST, 'string'),
        );
        
        $dashboard_model = new DashboardModel();
        $login = $dashboard_model->get_login();
        
        if (empty($login)) {
            $this->error('Login is not configured. Please set your login credentials in Global Settings.', 400);
            return;
        }
        
        $error = false;
        $error_message = '';
        
        if (empty($user_data['password'])) {
            $error = true;
            $error_message = 'Password field cannot be empty.';
        }
        if (empty($user_data['username'])) {
            $error = true;
            $error_message = 'Username/Email field cannot be empty.';
        }
        
        if ($error) {
            $this->error($error_message, 400);
            return;
        }
        
        if (!empty($login) && is_array($login)) {
            // Check username/email
            if (!filter_var($user_data['username'], FILTER_VALIDATE_EMAIL) === false) {
                if ($login['email'] != $user_data['username']) {
                    $error = true;
                }
            } elseif ($login['username'] != $user_data['username']) {
                $error = true;
            }
            
            // Check password
            if (!$error) {
                include_once('ext/PasswordHash.php');
                $t_hasher = new PasswordHash(8, FALSE);
                $hash = $login['password'];
                $check_hash = $user_data['password'];
                $check = $t_hasher->CheckPassword($check_hash, $hash);
                if (!$check) {
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
     * Detect form type from POST data
     */
    private function detect_form_type() {
        $form_keys = array(
            'submit_login' => 'login',
            'submit_plugins' => 'plugins',
            'submit_themes' => 'themes',
            'saveconfig' => 'wpconfig',
            'saveconfig_advanced' => 'wpconfig_advanced',
            'save_htaccess' => 'htaccess',
            'save_robots' => 'robots',
            'submit_autobackup' => 'autobackup',
            'submit_global_settings' => 'global_settings',
            'submit_site_url' => 'site_url',
            'submit_backup_database' => 'backup_database',
            'submit_backup_files' => 'backup_files'
        );
        
        foreach ($form_keys as $key => $type) {
            if (isset($_POST[$key])) {
                return $type;
            }
        }
        
        return null;
    }
    
    /**
     * Handle CSRF token requests
     */
    private function handle_csrf() {
        $form_name = filter_input(INPUT_GET, 'form', FILTER_SANITIZE_STRING) ?: 'default';
        $token = CSRFProtection::get_token($form_name);
        
        $this->success('CSRF token generated', array('token' => $token));
    }
    
    /**
     * Handle data requests
     */
    private function handle_data() {
        $data_type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
        
        if (!$data_type) {
            $this->error('Data type not specified');
            return;
        }
        
        // Use output buffering to prevent DashboardController from outputting HTML
        ob_start();
        $dashboard = new DashboardController();
        // Discard any HTML output from constructor
        ob_end_clean();
        
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
                    'active_theme' => $dashboard->dashboard_model->get_active_themes(),
                    'all_themes' => $dashboard->dashboard_model->get_all_themes($dashboard->wp_dir)
                );
                break;
            case 'tables':
                $data = $dashboard->dashboard_model->show_tables();
                break;
            case 'backups':
                $backup_type = filter_input(INPUT_GET, 'backup_type', FILTER_SANITIZE_STRING);
                if ($backup_type === 'database') {
                    $data = $dashboard->dashboard_model->get_database_backups();
                } elseif ($backup_type === 'files') {
                    $data = $dashboard->dashboard_model->get_file_backups();
                }
                break;
            default:
                $this->error('Unknown data type: ' . $data_type);
                return;
        }
        
        $this->success('Data retrieved', $data);
    }
    
    /**
     * Send success response
     */
    private function success($message, $data = null, $redirect = null) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Ensure JSON header is set
        header('Content-Type: application/json', true);
        
        $this->response = array(
            'success' => true,
            'message' => $message,
            'data' => $data
        );
        
        if ($redirect) {
            $this->response['redirect'] = $redirect;
        }
        
        echo json_encode($this->response);
        exit;
    }
    
    /**
     * Send error response
     */
    private function error($message, $code = 400) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Ensure JSON header is set
        header('Content-Type: application/json', true);
        http_response_code($code);
        
        $this->response = array(
            'success' => false,
            'message' => $message,
            'data' => null
        );
        
        echo json_encode($this->response);
        exit;
    }
}

