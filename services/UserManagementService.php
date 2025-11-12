<?php
/**
 * User Management Service
 * Handles WordPress user operations
 */
class UserManagementService {
    
    private $model;
    private $db_prefix;
    
    /**
     * Constructor
     * 
     * @param DashboardModel|null $model Dashboard model instance
     */
    public function __construct(?DashboardModel $model = null) {
        $this->model = $model ?? new DashboardModel();
        $this->db_prefix = $this->model->db_prefix ?? 'wp_';
    }
    
    /**
     * Get all users
     * 
     * @param int $limit Limit results
     * @param int $offset Offset
     * @return array
     */
    public function getUsers(int $limit = 50, int $offset = 0): array {
        try {
            $q = $this->model->prepare("SELECT * FROM {$this->db_prefix}users ORDER BY user_registered DESC LIMIT :limit OFFSET :offset");
            $q->bindValue(':limit', $limit, PDO::PARAM_INT);
            $q->bindValue(':offset', $offset, PDO::PARAM_INT);
            $q->execute();
            $users = $q->fetchAll(PDO::FETCH_ASSOC);
            
            // Get user meta
            foreach ($users as &$user) {
                $user['meta'] = $this->getUserMeta($user['ID']);
                $user['roles'] = $this->getUserRoles($user['ID']);
            }
            
            return $users;
        } catch (PDOException $e) {
            Logger::error('Error getting users', ['error' => $e->getMessage()]);
            return array();
        }
    }
    
    /**
     * Get user by ID
     * 
     * @param int $user_id User ID
     * @return array|null
     */
    public function getUser(int $user_id): ?array {
        try {
            $q = $this->model->prepare("SELECT * FROM {$this->db_prefix}users WHERE ID = :id");
            $q->bindValue(':id', $user_id, PDO::PARAM_INT);
            $q->execute();
            $user = $q->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $user['meta'] = $this->getUserMeta($user_id);
                $user['roles'] = $this->getUserRoles($user_id);
            }
            
            return $user ?: null;
        } catch (PDOException $e) {
            Logger::error('Error getting user', ['user_id' => $user_id, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Create new user
     * 
     * @param array $user_data User data
     * @return array Result with success status and user ID
     */
    public function createUser(array $user_data): array {
        try {
            // Validate required fields
            if (empty($user_data['user_login']) || empty($user_data['user_email'])) {
                throw new InvalidArgumentException('Username and email are required');
            }
            
            // Validate email
            if (!InputValidator::validateEmail($user_data['user_email'])) {
                throw new InvalidArgumentException('Invalid email address');
            }
            
            // Check if user exists
            $existing = $this->model->prepare("SELECT ID FROM {$this->db_prefix}users WHERE user_login = :login OR user_email = :email");
            $existing->bindValue(':login', $user_data['user_login'], PDO::PARAM_STR);
            $existing->bindValue(':email', $user_data['user_email'], PDO::PARAM_STR);
            $existing->execute();
            if ($existing->fetch()) {
                throw new RuntimeException('User already exists');
            }
            
            // Hash password
            if (empty($user_data['user_pass'])) {
                $user_data['user_pass'] = wp_generate_password(12);
            }
            $hashed_password = wp_hash_password($user_data['user_pass']);
            
            // Insert user
            $q = $this->model->prepare("INSERT INTO {$this->db_prefix}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) VALUES (:login, :pass, :nicename, :email, NOW(), 0, :display)");
            $q->bindValue(':login', $user_data['user_login'], PDO::PARAM_STR);
            $q->bindValue(':pass', $hashed_password, PDO::PARAM_STR);
            $q->bindValue(':nicename', $this->sanitizeTitle($user_data['user_login']), PDO::PARAM_STR);
            $q->bindValue(':email', $user_data['user_email'], PDO::PARAM_STR);
            $q->bindValue(':display', $user_data['display_name'] ?? $user_data['user_login'], PDO::PARAM_STR);
            $q->execute();
            
            $user_id = $this->model->lastInsertId();
            
            // Set user role
            if (!empty($user_data['role'])) {
                $this->setUserRole($user_id, $user_data['role']);
            }
            
            Logger::info('User created', ['user_id' => $user_id, 'login' => $user_data['user_login']]);
            
            return array('success' => true, 'user_id' => $user_id);
        } catch (Throwable $e) {
            Logger::error('Error creating user', ['error' => $e->getMessage()]);
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Update user
     * 
     * @param int $user_id User ID
     * @param array $user_data User data
     * @return bool
     */
    public function updateUser(int $user_id, array $user_data): bool {
        try {
            $updates = array();
            $params = array(':id' => $user_id);
            
            if (isset($user_data['user_email'])) {
                if (!InputValidator::validateEmail($user_data['user_email'])) {
                    throw new InvalidArgumentException('Invalid email address');
                }
                $updates[] = 'user_email = :email';
                $params[':email'] = $user_data['user_email'];
            }
            
            if (isset($user_data['user_pass']) && !empty($user_data['user_pass'])) {
                $updates[] = 'user_pass = :pass';
                $params[':pass'] = $this->hashPassword($user_data['user_pass']);
            }
            
            if (isset($user_data['display_name'])) {
                $updates[] = 'display_name = :display';
                $params[':display'] = $user_data['display_name'];
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $sql = "UPDATE {$this->db_prefix}users SET " . implode(', ', $updates) . " WHERE ID = :id";
            $q = $this->model->prepare($sql);
            foreach ($params as $key => $value) {
                $q->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $q->execute();
            
            // Update role if provided
            if (isset($user_data['role'])) {
                $this->setUserRole($user_id, $user_data['role']);
            }
            
            Logger::info('User updated', ['user_id' => $user_id]);
            return true;
        } catch (Throwable $e) {
            Logger::error('Error updating user', ['user_id' => $user_id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Delete user
     * 
     * @param int $user_id User ID
     * @param bool $reassign Reassign content to another user
     * @param int|null $reassign_to User ID to reassign to
     * @return bool
     */
    public function deleteUser(int $user_id, bool $reassign = true, ?int $reassign_to = null): bool {
        try {
            if ($reassign && $reassign_to) {
                // Reassign posts
                $this->model->prepare("UPDATE {$this->db_prefix}posts SET post_author = :reassign WHERE post_author = :id")->execute([':reassign' => $reassign_to, ':id' => $user_id]);
            }
            
            // Delete user meta
            $this->model->prepare("DELETE FROM {$this->db_prefix}usermeta WHERE user_id = :id")->execute([':id' => $user_id]);
            
            // Delete user
            $this->model->prepare("DELETE FROM {$this->db_prefix}users WHERE ID = :id")->execute([':id' => $user_id]);
            
            Logger::info('User deleted', ['user_id' => $user_id]);
            return true;
        } catch (Throwable $e) {
            Logger::error('Error deleting user', ['user_id' => $user_id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get user meta
     * 
     * @param int $user_id User ID
     * @return array
     */
    private function getUserMeta(int $user_id): array {
        try {
            $q = $this->model->prepare("SELECT meta_key, meta_value FROM {$this->db_prefix}usermeta WHERE user_id = :id");
            $q->bindValue(':id', $user_id, PDO::PARAM_INT);
            $q->execute();
            $meta = array();
            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $meta[$row['meta_key']] = $row['meta_value'];
            }
            return $meta;
        } catch (PDOException $e) {
            return array();
        }
    }
    
    /**
     * Get user roles
     * 
     * @param int $user_id User ID
     * @return array
     */
    private function getUserRoles(int $user_id): array {
        $meta = $this->getUserMeta($user_id);
        $capabilities = unserialize($meta['wp_capabilities'] ?? '');
        return $capabilities ? array_keys($capabilities) : array('subscriber');
    }
    
    /**
     * Set user role
     * 
     * @param int $user_id User ID
     * @param string $role Role name
     * @return bool
     */
    private function setUserRole(int $user_id, string $role): bool {
        try {
            $capabilities = array($role => true);
            $q = $this->model->prepare("INSERT INTO {$this->db_prefix}usermeta (user_id, meta_key, meta_value) VALUES (:id, 'wp_capabilities', :caps) ON DUPLICATE KEY UPDATE meta_value = :caps");
            $q->bindValue(':id', $user_id, PDO::PARAM_INT);
            $q->bindValue(':caps', serialize($capabilities), PDO::PARAM_STR);
            $q->execute();
            return true;
        } catch (PDOException $e) {
            Logger::error('Error setting user role', ['user_id' => $user_id, 'role' => $role, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Hash password using WordPress method
     * 
     * @param string $password Plain password
     * @return string Hashed password
     */
    private function hashPassword(string $password): string {
        if (file_exists(__DIR__ . '/../ext/PasswordHash.php')) {
            include_once(__DIR__ . '/../ext/PasswordHash.php');
            $hasher = new PasswordHash(8, false);
            return $hasher->HashPassword($password);
        }
        // Fallback to PHP password_hash
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Generate random password
     * 
     * @param int $length Password length
     * @return string Generated password
     */
    private function generatePassword(int $length = 12): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
    }
    
    /**
     * Sanitize title
     * 
     * @param string $title Title to sanitize
     * @return string Sanitized title
     */
    private function sanitizeTitle(string $title): string {
        return strtolower(preg_replace('/[^a-z0-9-]+/', '-', $title));
    }
}
