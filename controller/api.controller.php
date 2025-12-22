<?php
/**
 * API Controller for JSON responses
 * Handles AJAX requests and returns JSON data
 * PHP 8.0+ Compatible
 */

class ApiController extends MainController
{

    private $response = array(
        'success' => false,
        'message' => '',
        'data' => null
    );

    function __construct()
    {
        // Define API context to prevent HTML output in models
        if (!defined('WPSM_API')) {
            define('WPSM_API', true);
        }

        // Clear any output that might have been sent
        OutputBuffer::clean();

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
    function handle()
    {
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
                case 'database-query':
                    $this->handle_database_query();
                    break;
                case 'database':
                    $this->handle_database();
                    break;
                case 'malware-scanner':
                    $this->handle_malware_scanner();
                    break;
                case 'error-log':
                    $this->handle_error_log();
                    break;
                default:
                    $this->error('Invalid endpoint', 404);
            }
        } catch (Throwable $e) {
            // PHP 8.0+ compatible error handling
            error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->error('Internal server error', Constants::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Get endpoint from request
     * 
     * @return string Endpoint name
     */
    private function get_endpoint(): string
    {
        // PHP 8.0+ compatible: FILTER_SANITIZE_STRING is deprecated, use FILTER_SANITIZE_FULL_SPECIAL_CHARS
        // First check GET parameter (for index.php?endpoint=view format)
        $endpoint = filter_input(INPUT_GET, 'endpoint', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($endpoint)) {
            // Try to extract from URL (handles both /api/endpoint and /wpsm/api/endpoint)
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            // Match /api/endpoint or /wpsm/api/endpoint pattern
            if (preg_match('#/api/([^/?]+)#', $uri, $matches)) {
                $endpoint = $matches[1];
            } else {
                // Default to 'view' if no endpoint found
                $endpoint = 'view';
            }
        }
        return $endpoint ?: 'view';
    }

    /**
     * Validate CSRF token
     * 
     * @return bool True if valid
     */
    private function validate_csrf(): bool
    {
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
    private function handle_view()
    {
        try {
            // PHP 8.0+ compatible
            $view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'info';
            $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Set current page
            $this->current_page = $view;

            // Get view HTML
            try {
                $html = OutputBuffer::capture(function () use ($view, $action) {
                    $this->load_view($view, $action);
                });
            } catch (Throwable $e) {
                error_log('Error loading view: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $this->error('Error loading view: ' . $e->getMessage(), Constants::HTTP_INTERNAL_ERROR);
                return;
            }

            $this->success('View loaded', array('html' => $html, 'view' => $view));
        } catch (Throwable $e) {
            error_log('Error in handle_view: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->error('Internal server error', Constants::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Get dashboard controller instance (optimized)
     * Note: DashboardController constructor has side effects, so we can't cache it
     * but we can optimize the instantiation
     * 
     * @return DashboardController Dashboard instance
     * @throws Throwable If instantiation fails
     */
    private function getDashboardInstance(): DashboardController
    {
        try {
            // Suppress output from DashboardController constructor
            $dashboard = null;
            OutputBuffer::suppress(function () use (&$dashboard) {
                $dashboard = new DashboardController();
            });
            return $dashboard;
        } catch (Throwable $e) {
            error_log('Error creating DashboardController: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    /**
     * Load view content for API response
     * 
     * @param string $view View name
     * @param string|null $action Optional action
     * @return void
     * @throws Throwable If view loading fails
     */
    private function load_view(string $view, ?string $action = null): void
    {
        try {
            $dashboard = $this->getDashboardInstance();

            if ($action) {
                $dashboard->action = $action;
            }

            // Set current page using the setter method
            // Temporarily set $_GET['view'] so set_current_page() works correctly
            $original_view = $_GET['view'] ?? null;
            $_GET['view'] = $view;
            $dashboard->set_current_page();
            // Restore original if it was different
            if ($original_view !== $view) {
                if ($original_view === null) {
                    unset($_GET['view']);
                } else {
                    $_GET['view'] = $original_view;
                }
            }

            // Initialize data - this may call view-specific methods
            $dashboard->init_data();

            // If view has a specific method, call it to ensure data is loaded
            $view_method = 'view_' . str_replace('-', '_', $view);
            if (method_exists($dashboard, $view_method) && is_callable(array($dashboard, $view_method))) {
                // Suppress output from view method (it might call render)
                OutputBuffer::suppress(function () use ($dashboard, $view_method) {
                    call_user_func(array($dashboard, $view_method));
                });
            }

            $dashboard->get_message();

            // Normalize view_url once
            $view_url = rtrim($this->settings['view_url'] ?? 'view/', '/\\') . '/';

            // Try admin view first, then regular view
            $admin_template = $view_url . $view . '-admin.php';
            $regular_template = $view_url . $view . '.php';

            $admin_path = $this->resolveTemplatePath($admin_template);
            $regular_path = $this->resolveTemplatePath($regular_template);

            // Use cached file existence checks
            // Access parent class's template_cache using reflection since it's private
            $file_to_load = null;
            if ($admin_path) {
                $cache_value = $this->getTemplateCacheValue($admin_path);
                if ($cache_value === null) {
                    $exists = file_exists($admin_path);
                    $this->setTemplateCacheValue($admin_path, $exists);
                    $cache_value = $exists;
                }
                if ($cache_value) {
                    $file_to_load = $admin_path;
                }
            }

            if (!$file_to_load && $regular_path) {
                $cache_value = $this->getTemplateCacheValue($regular_path);
                if ($cache_value === null) {
                    $exists = file_exists($regular_path);
                    $this->setTemplateCacheValue($regular_path, $exists);
                    $cache_value = $exists;
                }
                if ($cache_value) {
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
        } catch (Throwable $e) {
            error_log('Error in load_view: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            echo '<div class="alert alert-danger">Error loading view: ' . htmlspecialchars($e->getMessage()) . '</div>';
            throw $e;
        }
    }

    /**
     * Get template cache value (helper to access parent's private static property)
     * 
     * @param string $key Cache key
     * @return bool|null Cached value or null
     */
    private function getTemplateCacheValue(string $key): ?bool
    {
        $reflection = new ReflectionClass('MainController');
        $property = $reflection->getProperty('template_cache');
        $property->setAccessible(true);
        // For static properties, pass null as the object
        $cache = $property->getValue(null);
        return isset($cache[$key]) ? $cache[$key] : null;
    }

    /**
     * Set template cache value (helper to access parent's private static property)
     * 
     * @param string $key Cache key
     * @param bool $value Cache value
     * @return void
     */
    private function setTemplateCacheValue(string $key, bool $value): void
    {
        $reflection = new ReflectionClass('MainController');
        $property = $reflection->getProperty('template_cache');
        $property->setAccessible(true);
        // For static properties, pass null as the object
        $cache = $property->getValue(null);
        $cache[$key] = $value;
        $property->setValue(null, $cache);
    }

    /**
     * Handle action requests
     */
    private function handle_action()
    {
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
    private function handle_submit()
    {

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
        if (method_exists($dashboard, 'get_current_page')) {
            $redirect = array('view' => $dashboard->get_current_page());
        }

        $this->success($message, null, $redirect);
    }

    /**
     * Handle login submission (without redirects)
     */
    private function handle_login()
    {
        // SECURITY FIX: Validate CSRF token first
        if (!CSRFProtection::validate_post_token('login')) {
            $this->error('Invalid security token. Please try again.', Constants::HTTP_FORBIDDEN);
            return;
        }

        // SECURITY FIX: Check rate limiting
        if (!RateLimiter::check_rate_limit('login', Constants::RATE_LIMIT_LOGIN_ATTEMPTS, Constants::RATE_LIMIT_LOGIN_WINDOW)) {
            $remaining = RateLimiter::get_reset_time('login', Constants::RATE_LIMIT_LOGIN_WINDOW);
            $this->error('Too many login attempts. Please try again in ' . $remaining . ' seconds.', Constants::HTTP_TOO_MANY_REQUESTS);
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
        try {
            OutputBuffer::suppress(function () use (&$login) {
                $dashboard_model = new DashboardModel();
                $login = $dashboard_model->get_login();
            });
        } catch (Throwable $e) {
            error_log('Login Database Error: ' . $e->getMessage());
            $this->error('Database error occurred. Please try again.', Constants::HTTP_INTERNAL_ERROR);
            return;
        }

        if (empty($login)) {
            $this->error('Login is not configured. Please set your login credentials in Global Settings.', Constants::HTTP_BAD_REQUEST);
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

        $this->error('Login failed', Constants::HTTP_INTERNAL_ERROR);
    }

    /**
     * Handle CSRF token requests
     */
    private function handle_csrf()
    {
        // PHP 8.0+ compatible
        $form_name = filter_input(INPUT_GET, 'form', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'default';
        $token = CSRFProtection::get_token($form_name);
        $this->success('Token generated', array('token' => $token));
    }

    /**
     * Handle data requests
     */
    private function handle_data()
    {
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
                    'themes' => $dashboard->dashboard_model->get_all_themes($dashboard->wp_dir)
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
    private function handle_system_health()
    {
        $health_service = new SystemHealthService();
        $metrics = $health_service->getHealthMetrics();
        $this->success('Health metrics retrieved', $metrics);
    }

    /**
     * Handle file manager operations
     */
    private function handle_file_manager()
    {
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
                    $result = $file_manager->readFile($path);
                    $this->success('File read', array(
                        'content' => $result['content'],
                        'is_binary' => $result['is_binary'],
                        'path' => $path
                    ));
                    break;

                case 'write':
                    $path = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $content = $_POST['content'] ?? '';
                    $is_base64 = filter_input(INPUT_POST, 'is_base64', FILTER_VALIDATE_BOOLEAN) ?? false;

                    if (empty($path)) {
                        $this->error('Path is required');
                        return;
                    }

                    $result = $file_manager->writeFile($path, $content, $is_base64);
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

                case 'zip':
                    $path = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $destination = filter_input(INPUT_POST, 'destination', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                    if (empty($path) || empty($destination)) {
                        $this->error('Path and destination are required');
                        return;
                    }

                    if ($file_manager->zipPath($path, $destination)) {
                        $this->success('Zip created successfully');
                    } else {
                        $this->error('Failed to create zip file');
                    }
                    break;

                case 'unzip':
                    $path = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $destination = filter_input(INPUT_POST, 'destination', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                    if (empty($path) || empty($destination)) {
                        $this->error('Path and destination are required');
                        return;
                    }

                    if ($file_manager->unzipFile($path, $destination)) {
                        $this->success('File unzipped successfully');
                    } else {
                        $this->error('Failed to unzip file');
                    }
                    break;

                case 'upload':
                    $destination = filter_input(INPUT_POST, 'destination', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if (empty($destination)) {
                        $this->error('Destination is required');
                        return;
                    }

                    if (!isset($_FILES['file'])) {
                        $this->error('No file uploaded');
                        return;
                    }

                    try {
                        // Create directory if needed
                        if (!is_dir($file_manager->validatePath($destination))) {
                            $file_manager->createDirectory($destination);
                        }

                        $result = $file_manager->uploadFile($_FILES['file'], $destination . '/' . $_FILES['file']['name']);
                        $this->success('File uploaded successfully', $result);
                    } catch (Throwable $e) {
                        $this->error($e->getMessage());
                    }
                    break;

                case 'fix_permissions':
                    $path = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if ($path === null)
                        $path = ''; // Allow empty path for root

                    try {
                        $result = $file_manager->fixPermissions($path);
                        $this->success('Permissions fixed', $result);
                    } catch (Throwable $e) {
                        $this->error('Failed to fix permissions: ' . $e->getMessage());
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
    private function handle_users()
    {
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
                    $user = $user_service->getUser((int) $user_id);
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
                    $result = $user_service->updateUser((int) $user_id, $user_data);
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
                    $result = $user_service->deleteUser((int) $user_id, $reassign, $reassign_to ?: null);
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
    private function handle_cron()
    {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'list';
        $cron_service = new CronService();

        try {
            switch ($action) {
                case 'list':
                    $jobs = $cron_service->getCronJobs();
                    $this->success('Cron jobs retrieved', array('jobs' => $jobs));
                    break;

                case 'run':
                    // Read JSON input from request body
                    $input = json_decode(file_get_contents('php://input'), true);
                    $hook = $input['hook'] ?? '';
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
                    // Read JSON input from request body
                    $input = json_decode(file_get_contents('php://input'), true);
                    $hook = $input['hook'] ?? '';
                    $timestamp = $input['timestamp'] ?? '';
                    if (empty($hook) || empty($timestamp)) {
                        $this->error('Hook and timestamp are required');
                        return;
                    }
                    $result = $cron_service->deleteCronJob($hook, (int) $timestamp);
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
    private function handle_activity_log()
    {
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
    private function handle_email()
    {
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
    private function handle_security_scanner()
    {
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
    private function handle_performance()
    {
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
    private function handle_media()
    {
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
    private function handle_database_optimizer()
    {
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
    private function success(string $message, $data = null, ?array $redirect = null): void
    {
        Response::jsonSuccess($message, $data, $redirect);
    }

    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return void
     */
    private function error(string $message, int $code = 400): void
    {
        Response::jsonError($message, $code);
    }
    /**
     * Handle database query execution
     */
    private function handle_database_query()
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // Read JSON input from request body
        $input = json_decode(file_get_contents('php://input'), true);
        $query = $input['query'] ?? '';

        if (empty($query)) {
            $this->error('Query is required');
            return;
        }

        try {
            $dashboard = $this->getDashboardInstance();
            $db = $dashboard->dashboard_model;

            // Execute query
            // We use the underlying PDO connection from dbModel
            $stmt = $db->query($query);

            $results = [];
            $columns = [];

            // If it's a SELECT/SHOW/DESCRIBE query, fetch results
            if ($stmt->columnCount() > 0) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get column names from the first row if available
                if (!empty($results)) {
                    $columns = array_keys($results[0]);
                } else {
                    // If no rows, try to get column metadata
                    for ($i = 0; $i < $stmt->columnCount(); $i++) {
                        $meta = $stmt->getColumnMeta($i);
                        $columns[] = $meta['name'];
                    }
                }

                $this->success('Query executed successfully', [
                    'results' => $results,
                    'columns' => $columns,
                    'count' => count($results)
                ]);
            } else {
                // For INSERT/UPDATE/DELETE, return affected rows
                $this->success('Query executed successfully', [
                    'affected_rows' => $stmt->rowCount()
                ]);
            }

        } catch (PDOException $e) {
            $this->error('Database Error: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle error log operations
     */
    private function handle_error_log()
    {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'list';
        $error_log_service = new ErrorLogService();

        try {
            switch ($action) {
                case 'list':
                    $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?: 1;
                    $lines = filter_input(INPUT_GET, 'lines', FILTER_SANITIZE_NUMBER_INT) ?: 20;
                    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
                    $date_from = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
                    $date_to = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

                    $result = $error_log_service->getErrorLog($page, $lines, $search, $date_from, $date_to);
                    $this->success('Error log retrieved', $result);
                    break;

                case 'stats':
                    $stats = $error_log_service->getStats();
                    $this->success('Error log statistics retrieved', ['stats' => $stats]);
                    break;

                case 'download':
                    $path = $error_log_service->getErrorLogPath();
                    if (!$path || !file_exists($path)) {
                        $this->error('Error log file not found');
                        return;
                    }

                    // For download, we don't return JSON
                    // We need to bypass the JSON header set in constructor
                    // But since constructor already ran, we can just output file

                    // Ideally we should have a separate download handler, but for now:
                    header('Content-Type: text/plain');
                    header('Content-Disposition: attachment; filename="error_log.txt"');
                    header('Content-Length: ' . filesize($path));
                    readfile($path);
                    exit;
                    break;

                case 'clear':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        $this->error('Method not allowed', 405);
                        return;
                    }

                    if ($error_log_service->clearErrorLog()) {
                        $this->success('Error log cleared successfully');
                    } else {
                        $this->error('Failed to clear error log');
                    }
                    break;

                case 'archive':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        $this->error('Method not allowed', 405);
                        return;
                    }

                    $archive_path = $error_log_service->archiveErrorLog();
                    if ($archive_path) {
                        $this->success('Error log archived successfully', ['path' => basename($archive_path)]);
                    } else {
                        $this->error('Failed to archive error log');
                    }
                    break;

                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Error log error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    /**
     * Handle database inspector operations
     */
    private function handle_database()
    {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'tables';
        $db_service = new DatabaseService();

        try {
            switch ($action) {
                case 'tables':
                    $tables = $db_service->getTables();
                    $this->success('Tables retrieved', array('tables' => $tables));
                    break;

                case 'schema':
                    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if (empty($table)) {
                        $this->error('Table name is required');
                        return;
                    }
                    $schema = $db_service->getTableSchema($table);
                    $this->success('Table schema retrieved', $schema);
                    break;

                case 'data':
                    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $limit = filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT) ?: 50;
                    $offset = filter_input(INPUT_GET, 'offset', FILTER_SANITIZE_NUMBER_INT) ?: 0;

                    if (empty($table)) {
                        $this->error('Table name is required');
                        return;
                    }

                    $data = $db_service->getTableData($table, $limit, $offset);
                    $this->success('Table data retrieved', $data);
                    break;

                case 'query':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        $this->error('Method not allowed', 405);
                        return;
                    }
                    $input = json_decode(file_get_contents('php://input'), true);
                    $query = $input['query'] ?? '';
                    if (empty($query)) {
                        $this->error('Query is required');
                        return;
                    }
                    $result = $db_service->executeQuery($query);
                    $this->success('Query executed', $result);
                    break;

                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Database inspector error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
    /**
     * Handle malware scanner operations
     */
    private function handle_malware_scanner()
    {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'scan';
        $scanner = new MalwareScannerService();

        try {
            switch ($action) {
                case 'scan':
                    $results = $scanner->scan();
                    $this->success('Scan completed', array('results' => $results));
                    break;

                default:
                    $this->error('Invalid action');
            }
        } catch (Throwable $e) {
            Logger::error('Malware scanner error', ['action' => $action, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
        }
    }
}
