<?php
/**
 * AI Controller - Handles AI-powered features
 */

class AIController extends MainController
{

    private $ai_service;

    function __construct()
    {
        parent::__construct();
        include_once('services/AIService.php');
        $this->ai_service = new AIService();
    }

    /**
     * Handle AI API requests
     */
    function handle()
    {
        // PHP 8.0+ compatible: FILTER_SANITIZE_STRING is deprecated
        if (PHP_VERSION_ID >= 80100) {
            $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
        }
        $method = $_SERVER['REQUEST_METHOD'];

        // Clear any output buffers
        OutputBuffer::cleanAll();

        header('Content-Type: application/json', true);

        try {
            switch ($action) {
                case 'analyze_error_log':
                    $this->analyze_error_log();
                    break;
                case 'detect_conflicts':
                    $this->detect_conflicts();
                    break;
                case 'security_analysis':
                    $this->security_analysis();
                    break;
                case 'performance_optimization':
                    $this->performance_optimization();
                    break;
                case 'chat':
                    $this->chat();
                    break;
                case 'explain_error':
                    $this->explain_error();
                    break;
                case 'suggest_code':
                    $this->suggest_code();
                    break;
                case 'check_config':
                    $this->check_config();
                    break;
                default:
                    $this->error('Invalid action', 400);
            }
        } catch (Throwable $e) {
            // PHP 8.0+ compatible error handling
            error_log('AI Controller Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Check if AI is configured
     */
    private function check_config()
    {
        $this->success(array(
            'configured' => $this->ai_service->is_configured(),
            'message' => $this->ai_service->is_configured()
                ? 'AI service is configured and ready'
                : 'Please configure OpenAI API key in settings'
        ));
    }

    /**
     * Analyze error log
     */
    private function analyze_error_log()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // SECURITY FIX: Validate CSRF token
        if (!CSRFProtection::validate_post_token('ai_action')) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $error_log = InputValidator::getInput('error_log', INPUT_POST, 'string');

        if (empty($error_log)) {
            $this->error('Error log content is required', 400);
            return;
        }

        $analysis = $this->ai_service->analyze_error_log($error_log);

        $this->success(array(
            'analysis' => $analysis,
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Detect plugin conflicts
     */
    private function detect_conflicts()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // SECURITY FIX: Validate CSRF token
        if (!CSRFProtection::validate_post_token('ai_action')) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $plugins_raw = filter_input(INPUT_POST, 'plugins', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $error_log = InputValidator::getInput('error_log', INPUT_POST, 'string');

        $plugins = array();
        if (is_array($plugins_raw)) {
            foreach ($plugins_raw as $plugin) {
                // PHP 8.0+ compatible
                if (PHP_VERSION_ID >= 80100) {
                    $plugins[] = filter_var($plugin, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                } else {
                    $plugins[] = filter_var($plugin, FILTER_SANITIZE_STRING);
                }
            }
        }

        if (empty($plugins)) {
            $this->error('Plugins list is required', 400);
            return;
        }

        $analysis = $this->ai_service->detect_plugin_conflicts($plugins, $error_log);

        $this->success(array(
            'analysis' => $analysis,
            'plugins_analyzed' => count($plugins),
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Security analysis
     */
    private function security_analysis()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // SECURITY FIX: Validate CSRF token
        if (!CSRFProtection::validate_post_token('ai_action')) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $this->dashboard_model = new DashboardModel();
        $site_info = $this->get_site_info();

        $analysis = $this->ai_service->analyze_security($site_info);

        $this->success(array(
            'analysis' => $analysis,
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Performance optimization
     */
    private function performance_optimization()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // SECURITY FIX: Validate CSRF token
        if (!CSRFProtection::validate_post_token('ai_action')) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $this->dashboard_model = new DashboardModel();
        $site_info = $this->get_site_info();

        $analysis = $this->ai_service->optimize_performance($site_info);

        $this->success(array(
            'analysis' => $analysis,
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Chat assistant
     */
    private function chat()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // SECURITY FIX: Validate CSRF token
        if (!CSRFProtection::validate_post_token('ai_action')) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $message = InputValidator::getInput('message', INPUT_POST, 'string');
        $history_raw = filter_input(INPUT_POST, 'history', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $context_raw = filter_input(INPUT_POST, 'context', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

        $history = is_array($history_raw) ? $history_raw : array();
        $context = is_array($context_raw) ? $context_raw : array();

        if (empty($message)) {
            $this->error('Message is required', 400);
            return;
        }

        $chat_context = array();
        if (!empty($context)) {
            $chat_context = $context;
        }

        if (!empty($history)) {
            $chat_context['history'] = $history;
        }

        $response = $this->ai_service->chat($message, $chat_context);

        $this->success(array(
            'response' => $response,
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Explain error
     */
    private function explain_error()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // SECURITY FIX: Validate CSRF token
        if (!CSRFProtection::validate_post_token('ai_action')) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $error = InputValidator::getInput('error', INPUT_POST, 'string');

        if (empty($error)) {
            $this->error('Error message is required', 400);
            return;
        }

        $explanation = $this->ai_service->explain_error($error);

        $this->success(array(
            'explanation' => $explanation,
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Suggest code
     */
    private function suggest_code()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        // SECURITY FIX: Validate CSRF token
        if (!CSRFProtection::validate_post_token('ai_action')) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $description = InputValidator::getInput('description', INPUT_POST, 'string');
        $context = InputValidator::getInput('context', INPUT_POST, 'string');

        if (empty($description)) {
            $this->error('Description is required', 400);
            return;
        }

        $code = $this->ai_service->suggest_code($description, $context);

        $this->success(array(
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Get site information for analysis
     */
    private function get_site_info()
    {
        $this->dashboard_model = new DashboardModel();
        $info_model = new BasicInfoModel();

        $info = $info_model->get_info();
        $plugins = $this->dashboard_model->get_plugins();
        $themes = $this->dashboard_model->get_themes();

        return array(
            'wp_version' => isset($info['wp_version']) ? $info['wp_version'] : 'Unknown',
            'php_version' => phpversion(),
            'mysql_version' => $this->get_mysql_version(),
            'active_plugins' => isset($plugins['active']) ? count($plugins['active']) : 0,
            'inactive_plugins' => isset($plugins['inactive']) ? count($plugins['inactive']) : 0,
            'active_theme' => isset($themes['active']) ? $themes['active'] : 'Unknown',
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        );
    }

    /**
     * Get MySQL version
     */
    private function get_mysql_version()
    {
        try {
            $db_model = new DBModel();
            $result = $db_model->query("SELECT VERSION() as version");
            if ($result && isset($result[0]['version'])) {
                return $result[0]['version'];
            }
        } catch (Throwable $e) {
            // PHP 8.0+ compatible error handling
            // Ignore - return 'Unknown' below
        }
        return 'Unknown';
    }

    /**
     * Send success response
     */
    private function success($data)
    {
        echo json_encode(array(
            'success' => true,
            'data' => $data
        ));
        exit;
    }

    /**
     * Send error response
     */
    private function error($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(array(
            'success' => false,
            'error' => $message
        ));
        exit;
    }
}

