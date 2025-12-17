<?php
/**
 * Auth Service
 * Handles authentication related operations including magic links
 */
class AuthService
{
    private $db;
    private $wp_dir;

    public function __construct()
    {
        $config = Config::getInstance();
        $this->wp_dir = $config->get('wp_dir');
        $this->db = DashboardModel::get_db_instance();
    }

    /**
     * Generate a magic login link for an administrator
     * 
     * @param int $user_id Optional user ID, otherwise finds first admin
     * @return string|false Magic link URL or false on failure
     */
    public function generateMagicLink($user_id = null)
    {
        // If no user ID provided, find the first admin user
        if (!$user_id) {
            $user = $this->getFirstAdmin();
            if (!$user) {
                throw new Exception('No administrator user found.');
            }
            $user_id = $user['ID'];
        }

        // Generate a secure token
        $token = bin2hex(random_bytes(32));
        $expiry = time() + 3600; // 1 hour expiration

        // Store token in a temporary file (since we don't want to modify WP tables for this if possible, 
        // to keep Safe Mode isolated. But storing in file means we need writable dir.
        // SFSTORE should be writable)
        if (!$this->storeToken($token, $user_id, $expiry)) {
            throw new Exception('Failed to store authentication token.');
        }

        // Build the link
        // Current URL without query params + ?action=magic_login&token=...
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        // Simple reconstruction, might need refinement based on actual setup
        $current_url = $protocol . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]";

        // If we are in /wpsafemode/index.php, we want the link to point to index.php with action
        return $current_url . '?action=magic_login&token=' . $token;
    }

    /**
     * Validate a magic link token
     * 
     * @param string $token
     * @return int|false User ID if valid, false otherwise
     */
    public function validateMagicLink($token)
    {
        $data = $this->getTokenData($token);

        if (!$data) {
            return false;
        }

        if ($data['expiry'] < time()) {
            $this->deleteToken($token);
            return false;
        }

        // Token is valid, return user ID and cleanup
        $this->deleteToken($token);
        return $data['user_id'];
    }

    /**
     * Log the user into WordPress
     * 
     * @param int $user_id
     */
    public function loginUser($user_id)
    {
        if (!file_exists($this->wp_dir . 'wp-load.php')) {
            throw new Exception('WordPress not found at ' . $this->wp_dir);
        }

        // We need to load WordPress environment to set cookies
        // Use output buffering to catch any stray output from WP
        ob_start();
        define('WP_USE_THEMES', false);
        require_once($this->wp_dir . 'wp-load.php');
        ob_end_clean();

        if (is_user_logged_in()) {
            wp_logout();
        }

        $user = get_user_by('id', $user_id);
        if ($user) {
            wp_set_current_user($user_id, $user->user_login);
            wp_set_auth_cookie($user_id);
            do_action('wp_login', $user->user_login, $user);
            return admin_url();
        }

        return false;
    }

    private function getFirstAdmin()
    {
        // Try to find admin via DB directly to avoid loading WP if possible, or use standard WP tables
        // Prefix is in dbModel
        // For simplicity and robustness, let's query the DB directly using DatabaseService/Model logic
        // But we are in AuthService.

        // We know wp_users and wp_usermeta tables.
        // Administrator capability is in wp_usermeta -> wp_capabilities

        // Get prefix from config/settings if possible, or assume from DashboardModel
        $config = Config::getInstance();
        $settings = $config->all();
        $prefix = $settings['wp_db_prefix'] ?? 'wp_'; // This might be risky if not set correctly

        // Better: Query for user with meta_key = prefix . 'capabilities' AND meta_value LIKE '%administrator%'
        // Wait, $this->db is PDO (dbModel).

        try {
            // Find ID of admin
            // This is a simplified query. It assumes standard prefix+capabilities key.
            // A robust way is to look for 'administrator' in the value of the capabilities meta key.
            // The meta_key usually matches $table_prefix . 'capabilities'

            $stmt = $this->db->prepare("
                SELECT u.ID, u.user_login 
                FROM `{$prefix}users` u
                JOIN `{$prefix}usermeta` m ON u.ID = m.user_id
                WHERE m.meta_key = :cap_key AND m.meta_value LIKE :role_pattern
                LIMIT 1
            ");

            $stmt->execute([
                ':cap_key' => $prefix . 'capabilities',
                ':role_pattern' => '%administrator%'
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            // Fallback or error logging
            error_log('AuthService: Failed to find admin: ' . $e->getMessage());
            return false;
        }
    }

    private function getTokenFilePath($token)
    {
        $config = Config::getInstance();
        $sfstore = $config->get('sfstore');
        // Sanitize token just in case
        $token = preg_replace('/[^a-f0-9]/', '', $token);
        return $config->get('safemode_dir') . $sfstore . 'tokens/' . $token . '.json';
    }

    private function storeToken($token, $user_id, $expiry)
    {
        $file = $this->getTokenFilePath($token);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'user_id' => $user_id,
            'expiry' => $expiry,
            'created' => time()
        ];

        return file_put_contents($file, json_encode($data)) !== false;
    }

    private function getTokenData($token)
    {
        $file = $this->getTokenFilePath($token);
        if (!file_exists($file)) {
            return false;
        }

        $content = file_get_contents($file);
        return json_decode($content, true);
    }

    private function deleteToken($token)
    {
        $file = $this->getTokenFilePath($token);
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
