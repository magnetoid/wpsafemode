<?php
/**
* 
* WP Safe Mode v1.1 
* @author CloudIndustry - http://cloud-industry.com 
* @author and contributors Nikola Kirincic, Marko Tiosavljevic, Daliborka Ciric, Luka Cvetinovic , Nikola Stojanovic
* @see For more information about installation, usage, licensing and other notes see README 
  @author
*/

define('WPSM', true);

// Start output buffering early for API routes to prevent any output before headers
// Check for API routes (handles both root and subdirectory installations)
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
// Check for endpoint parameter (for index.php?endpoint=view format)
$has_endpoint_param = isset($_GET['endpoint']) || filter_input(INPUT_GET, 'endpoint', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
// Check for /api/ or /ai/ in URL
$is_api_request = (strpos($request_uri, '/api/') !== false || strpos($request_uri, '/ai/') !== false || $has_endpoint_param);

if ($is_api_request) {
    // Suppress error output for API requests
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    // Start output buffering
    ob_start();
}

include_once('autoload.php');

// Handle AI API requests
if (strpos($request_uri, '/ai/') !== false) {
    // Define API context
    define('WPSM_API', true);
    // Clear any buffered output
    if (ob_get_level())
        ob_clean();
    include_once('controller/ai.controller.php');
    $ai = new AIController();
    $ai->handle();
    exit;
}

// Handle API requests first (before normal page rendering)
// Check for /api/ in URL OR endpoint parameter
if (strpos($request_uri, '/api/') !== false || $has_endpoint_param) {
    // Define API context (will be redefined in ApiController, but set early for includes)
    if (!defined('WPSM_API')) {
        define('WPSM_API', true);
    }
    // Clear any buffered output
    if (ob_get_level())
        ob_clean();
    include_once('controller/api.controller.php');
    $api = new ApiController();
    $api->handle();
    exit;

}

// Handle Magic Login
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ($action === 'magic_login') {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($token) {
        $auth = new AuthService();
        $user_id = $auth->validateMagicLink($token);
        if ($user_id) {
            $redirect_url = $auth->loginUser($user_id);
            if ($redirect_url) {
                header('Location: ' . $redirect_url);
                exit;
            } else {
                echo 'Failed to login user.';
            }
        } else {
            echo 'Invalid or expired magic link.';
        }
        exit;
    }
}

// Handle regular page requests - instantiate DashboardController
// This will render the full page with header, content, and footer
include_once('controller/dashboard.controller.php');
$dashboard = new DashboardController();