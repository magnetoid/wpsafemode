<?php
/**
 * Cache Manager
 * Simple in-memory cache for frequently accessed data
 */
class Cache {
    
    private static $instance = null;
    private $cache = array();
    private $ttl = array();
    private $defaultTtl = 3600; // 1 hour
    
    /**
     * Get singleton instance
     * 
     * @return Cache
     */
    public static function getInstance(): Cache {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public function get(string $key) {
        if (!isset($this->cache[$key])) {
            return null;
        }
        
        // Check if expired
        if (isset($this->ttl[$key]) && $this->ttl[$key] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $this->cache[$key];
    }
    
    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = default)
     * @return void
     */
    public function set(string $key, $value, ?int $ttl = null): void {
        $this->cache[$key] = $value;
        $this->ttl[$key] = time() + ($ttl ?? $this->defaultTtl);
    }
    
    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return void
     */
    public function delete(string $key): void {
        unset($this->cache[$key]);
        unset($this->ttl[$key]);
    }
    
    /**
     * Clear all cache
     * 
     * @return void
     */
    public function clear(): void {
        $this->cache = array();
        $this->ttl = array();
    }
    
    /**
     * Check if key exists and is valid
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        // Check if expired
        if (isset($this->ttl[$key]) && $this->ttl[$key] < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get or set cached value (lazy loading)
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to generate value if not cached
     * @param int|null $ttl Time to live in seconds
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null) {
        if ($this->has($key)) {
            return $this->get($key);
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}

