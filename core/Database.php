<?php
/**
 * Database Connection Manager
 * Singleton pattern for database connections
 */
class Database {
    
    private static $instance = null;
    private $connection = null;
    
    /**
     * Get singleton instance
     * 
     * @return Database
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - establish connection
     */
    private function __construct() {
        if (!defined('DB_NAME') || !defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASSWORD')) {
            throw new RuntimeException('Database credentials not defined');
        }
        
        $config = Config::getInstance();
        $engine = 'mysql';
        $host = DB_HOST;
        $database = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASSWORD;
        
        $dsn = $engine . ':dbname=' . $database . ';host=' . $host;
        
        try {
            $this->connection = new PDO($dsn, $user, $pass);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed', 0, $e);
        }
    }
    
    /**
     * Get PDO connection
     * 
     * @return PDO
     */
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new RuntimeException("Cannot unserialize singleton");
    }
}

