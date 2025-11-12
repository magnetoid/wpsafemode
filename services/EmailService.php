<?php
/**
 * Email Service
 * Test email functionality and SMTP configuration
 */
class EmailService {
    
    private $config;
    
    public function __construct() {
        $this->config = Config::getInstance();
    }
    
    /**
     * Test email sending
     * 
     * @param string $to Email address to send to
     * @param string $subject Email subject
     * @param string $message Email message
     * @return array Result with success status and message
     */
    public function test_email($to, $subject = null, $message = null) {
        if ($subject === null) {
            $subject = 'WP Safe Mode - Email Test';
        }
        
        if ($message === null) {
            $message = "This is a test email from WP Safe Mode.\n\n";
            $message .= "If you received this email, your WordPress email configuration is working correctly.\n\n";
            $message .= "Sent at: " . date('Y-m-d H:i:s');
        }
        
        $headers = array(
            'From: WP Safe Mode <noreply@' . $_SERVER['HTTP_HOST'] . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: WP Safe Mode'
        );
        
        try {
            // Try WordPress wp_mail if available
            if (function_exists('wp_mail')) {
                $result = wp_mail($to, $subject, $message, $headers);
                
                if ($result) {
                    return array(
                        'success' => true,
                        'message' => 'Email sent successfully to ' . $to
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => 'WordPress wp_mail() function returned false. Check your email configuration.'
                    );
                }
            } else {
                // Fallback to PHP mail()
                return $this->test_php_mail($to, $subject, $message);
            }
        } catch (Throwable $e) {
            return array(
                'success' => false,
                'message' => 'Error sending email: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get email configuration info
     * 
     * @return array
     */
    public function get_email_info() {
        $info = array(
            'php_mail' => function_exists('mail'),
            'wp_mail' => function_exists('wp_mail'),
            'smtp_configured' => false,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_ssl' => null,
            'smtp_auth' => false,
            'from_email' => function_exists('get_option') ? get_option('admin_email') : null,
            'from_name' => function_exists('get_option') ? get_option('blogname') : null,
            'mail_function' => ini_get('sendmail_path'),
            'php_ini_smtp' => ini_get('SMTP'),
            'php_ini_smtp_port' => ini_get('smtp_port')
        );
        
        // Check for SMTP plugins
        if (defined('WPMS_ON') && WPMS_ON) {
            $info['smtp_configured'] = true;
            $info['smtp_host'] = defined('WPMS_SMTP_HOST') ? WPMS_SMTP_HOST : null;
            $info['smtp_port'] = defined('WPMS_SMTP_PORT') ? WPMS_SMTP_PORT : null;
            $info['smtp_ssl'] = defined('WPMS_SSL') ? WPMS_SSL : null;
            $info['smtp_auth'] = defined('WPMS_SMTP_AUTH') && WPMS_SMTP_AUTH;
        }
        
        // Check wp-config.php for SMTP settings
        $wp_config = $this->get_wp_config();
        if (isset($wp_config['SMTP_HOST'])) {
            $info['smtp_configured'] = true;
            $info['smtp_host'] = $wp_config['SMTP_HOST'];
            $info['smtp_port'] = $wp_config['SMTP_PORT'] ?? 25;
        }
        
        return $info;
    }
    
    /**
     * Get WordPress configuration
     * 
     * @return array
     */
    private function get_wp_config() {
        $config = Config::getInstance();
        $wp_dir = $config->get('wp_dir', '../');
        $wp_config_path = $wp_dir . 'wp-config.php';
        
        if (!file_exists($wp_config_path)) {
            return array();
        }
        
        $wp_config = array();
        $content = file_get_contents($wp_config_path);
        
        // Extract SMTP settings
        if (preg_match("/define\s*\(\s*['\"]SMTP_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]/i", $content, $matches)) {
            $wp_config['SMTP_HOST'] = $matches[1];
        }
        if (preg_match("/define\s*\(\s*['\"]SMTP_PORT['\"]\s*,\s*['\"]?(\d+)['\"]?/i", $content, $matches)) {
            $wp_config['SMTP_PORT'] = $matches[1];
        }
        
        return $wp_config;
    }
    
    /**
     * Send test email using native PHP mail
     * 
     * @param string $to Email address
     * @param string $subject Subject
     * @param string $message Message
     * @return array
     */
    public function test_php_mail($to, $subject, $message) {
        $headers = "From: WP Safe Mode <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: WP Safe Mode\r\n";
        
        try {
            $result = @mail($to, $subject, $message, $headers);
            
            if ($result) {
                return array(
                    'success' => true,
                    'message' => 'PHP mail() function executed successfully'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'PHP mail() function returned false. Check server mail configuration.'
                );
            }
        } catch (Throwable $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
}

