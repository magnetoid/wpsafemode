<?php
/**
 * Response Handler
 * Centralized response formatting
 */
class Response {
    
    /**
     * Send JSON success response
     * 
     * @param string $message Success message
     * @param mixed $data Response data
     * @param array|null $redirect Redirect information
     * @return void
     */
    public static function jsonSuccess(string $message, $data = null, ?array $redirect = null): void {
        self::clearOutputBuffers();
        header('Content-Type: application/json', true);
        
        $response = array(
            'success' => true,
            'message' => $message,
            'data' => $data
        );
        
        if ($redirect !== null) {
            $response['redirect'] = $redirect;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send JSON error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return void
     */
    public static function jsonError(string $message, int $code = 400): void {
        self::clearOutputBuffers();
        header('Content-Type: application/json', true);
        http_response_code($code);
        
        $response = array(
            'success' => false,
            'message' => $message,
            'data' => null
        );
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Clear all output buffers
     * 
     * @return void
     */
    private static function clearOutputBuffers(): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    /**
     * Send HTML response
     * 
     * @param string $html HTML content
     * @param int $code HTTP status code
     * @return void
     */
    public static function html(string $html, int $code = 200): void {
        self::clearOutputBuffers();
        header('Content-Type: text/html; charset=UTF-8', true);
        http_response_code($code);
        echo $html;
        exit;
    }
    
    /**
     * Send redirect response
     * 
     * @param string $url Redirect URL
     * @param int $code HTTP status code
     * @return void
     */
    public static function redirect(string $url, int $code = 302): void {
        self::clearOutputBuffers();
        header('Location: ' . $url, true, $code);
        exit;
    }
}

