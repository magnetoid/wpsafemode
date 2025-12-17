<?php
/**
 * AI Service - Handles OpenAI API integration
 * Provides AI-powered diagnostics, suggestions, and chat assistance
 */

class AIService
{

    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $model = 'gpt-4';
    private $max_tokens = 2000;
    private $temperature = 0.7;

    public function __construct()
    {
        // Get API key from global settings, settings.php, or environment
        $this->api_key = $this->get_api_key();

        // Allow model override
        global $settings;
        if (isset($settings['openai_model'])) {
            $this->model = $settings['openai_model'];
        }
    }

    /**
     * Get OpenAI API key from various sources
     */
    private function get_api_key()
    {
        // First try global settings (user-configured)
        $dashboard_model = new DashboardModel();
        $global_settings = $dashboard_model->get_global_settings();
        if (!empty($global_settings) && isset($global_settings['openai_api_key'])) {
            return $global_settings['openai_api_key'];
        }

        // Then try settings.php
        global $settings;
        if (isset($settings['openai_api_key'])) {
            return $settings['openai_api_key'];
        }

        // Finally try environment variable
        return getenv('OPENAI_API_KEY') ?: '';
    }

    /**
     * Check if AI is configured
     */
    public function is_configured()
    {
        return !empty($this->api_key);
    }

    /**
     * Make API request to OpenAI
     */
    private function make_request($messages, $options = array())
    {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI API key not configured. Please set OPENAI_API_KEY in settings.');
        }

        $data = array(
            'model' => isset($options['model']) ? $options['model'] : $this->model,
            'messages' => $messages,
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : $this->max_tokens,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : $this->temperature,
        );

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }

        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error']['message'])
                ? $error_data['error']['message']
                : 'API request failed with status ' . $http_code;
            throw new Exception($error_message);
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response format');
        }

        return $result['choices'][0]['message']['content'];
    }

    /**
     * Analyze error log and provide suggestions
     */
    public function analyze_error_log($error_log_content)
    {
        $system_prompt = "You are a WordPress expert troubleshooting assistant. Analyze PHP error logs and provide:
1. A summary of the main issues
2. Specific error explanations
3. Step-by-step solutions
4. Prevention tips
Format your response in clear sections with actionable advice.";

        $user_prompt = "Analyze this PHP error log and provide troubleshooting suggestions:\n\n" .
            substr($error_log_content, 0, 8000); // Limit to avoid token limits

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt)
        );

        return $this->make_request($messages, array('max_tokens' => 3000));
    }

    /**
     * Detect plugin conflicts
     */
    public function detect_plugin_conflicts($plugins_list, $error_log = '', $simulation_mode = false)
    {
        $system_prompt = "You are a WordPress plugin expert. Analyze a list of plugins and identify potential conflicts, compatibility issues, or known problems. Provide:
1. Potential conflicts between plugins
2. Compatibility warnings
3. Performance concerns
4. Recommended actions";

        if ($simulation_mode) {
            $system_prompt .= "\n5. A step-by-step 'Simulation Plan' (Binary Search method) to isolate the problematic plugin, assuming user can disable plugins.";
        }

        $user_prompt = "Analyze these WordPress plugins for conflicts:\n\n" .
            "Plugins: " . implode(', ', $plugins_list) . "\n\n";

        if (!empty($error_log)) {
            $user_prompt .= "Recent errors:\n" . substr($error_log, 0, 2000);
        }

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt)
        );

        return $this->make_request($messages);
    }

    /**
     * Run automated security scan and save report
     */
    public function run_automated_security_scan()
    {
        // Collect metrics
        $health_service = new SystemHealthService();
        $metrics = $health_service->getHealthMetrics();

        // Analyze
        $analysis = $this->analyze_security($metrics);

        // Create Report
        $report = array(
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'metrics' => $metrics['security'],
            'ai_analysis' => $analysis
        );

        // Save Report
        $dashboard_model = new DashboardModel();
        $settings = $dashboard_model->get_settings();
        $store_dir = $settings['sfstore'];
        $report_file = $store_dir . 'security_scan_last.json';

        if (file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT))) {
            return $report;
        }

        return false;
    }

    /**
     * Provide security analysis
     */
    public function analyze_security($site_info)
    {
        $system_prompt = "You are a WordPress security expert. Analyze WordPress site information and provide:
1. Security vulnerabilities identified
2. Risk assessment
3. Immediate action items
4. Long-term security recommendations
Format as a security audit report.";

        $user_prompt = "Analyze this WordPress site for security issues:\n\n" .
            json_encode($site_info, JSON_PRETTY_PRINT);

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt)
        );

        return $this->make_request($messages, array('max_tokens' => 3000));
    }

    /**
     * Performance optimization suggestions
     */
    public function optimize_performance($site_info)
    {
        $system_prompt = "You are a WordPress performance optimization expert. Analyze site information and provide:
1. Performance bottlenecks
2. Optimization recommendations
3. Plugin/theme suggestions
4. Server configuration tips
Provide actionable, prioritized recommendations.";

        $user_prompt = "Analyze this WordPress site for performance optimization:\n\n" .
            json_encode($site_info, JSON_PRETTY_PRINT);

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt)
        );

        return $this->make_request($messages, array('max_tokens' => 3000));
    }

    /**
     * Chat assistant for troubleshooting
     */
    public function chat($user_message, $context = array())
    {
        $system_prompt = "You are a helpful WordPress troubleshooting assistant. You help users diagnose and fix WordPress issues. 
Be concise, practical, and provide step-by-step solutions. If you need more information, ask specific questions.";

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt)
        );

        // Add context if provided
        if (!empty($context)) {
            $context_message = "Context about the WordPress site:\n" . json_encode($context, JSON_PRETTY_PRINT);
            $messages[] = array('role' => 'system', 'content' => $context_message);
        }

        // Add conversation history if provided
        if (isset($context['history']) && is_array($context['history'])) {
            foreach ($context['history'] as $msg) {
                $messages[] = $msg;
            }
        }

        $messages[] = array('role' => 'user', 'content' => $user_message);

        return $this->make_request($messages, array('temperature' => 0.8));
    }

    /**
     * Generate code suggestions
     */
    public function suggest_code($description, $context = '')
    {
        $system_prompt = "You are a WordPress developer expert. Generate secure, best-practice PHP code for WordPress. 
Always include proper sanitization, validation, and security measures. Provide code with comments explaining key parts.";

        $user_prompt = "Generate WordPress code for: " . $description;
        if (!empty($context)) {
            $user_prompt .= "\n\nContext:\n" . $context;
        }

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt)
        );

        return $this->make_request($messages, array('temperature' => 0.5));
    }

    /**
     * Explain WordPress error
     */
    public function explain_error($error_message)
    {
        $system_prompt = "You are a WordPress debugging expert. Explain PHP/WordPress errors in simple terms and provide solutions.";

        $user_prompt = "Explain this error and how to fix it:\n\n" . $error_message;

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt)
        );

        return $this->make_request($messages);
    }
}

