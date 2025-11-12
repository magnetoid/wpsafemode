<?php
/**
 * File Manager Service
 * Handles file operations securely
 */
class FileManagerService {
    
    private $config;
    private $base_path;
    
    /**
     * Constructor
     * 
     * @param Config|null $config Configuration instance
     */
    public function __construct(?Config $config = null) {
        $this->config = $config ?? Config::getInstance();
        $this->base_path = realpath($this->config->get('wp_dir', '../'));
    }
    
    /**
     * List directory contents
     * 
     * @param string $path Directory path (relative to WordPress root)
     * @return array
     */
    public function listDirectory(string $path = ''): array {
        $full_path = $this->validatePath($path);
        if (!$full_path) {
            throw new InvalidArgumentException('Invalid path');
        }
        
        if (!is_dir($full_path)) {
            throw new RuntimeException('Path is not a directory');
        }
        
        $items = array();
        $files = scandir($full_path);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $full_path . '/' . $file;
            $relative_path = str_replace($this->base_path . '/', '', $file_path);
            
            $items[] = array(
                'name' => $file,
                'path' => $relative_path,
                'type' => is_dir($file_path) ? 'directory' : 'file',
                'size' => is_file($file_path) ? filesize($file_path) : 0,
                'size_formatted' => is_file($file_path) ? $this->formatBytes(filesize($file_path)) : '-',
                'modified' => filemtime($file_path),
                'modified_formatted' => date('Y-m-d H:i:s', filemtime($file_path)),
                'permissions' => substr(sprintf('%o', fileperms($file_path)), -4),
                'readable' => is_readable($file_path),
                'writable' => is_writable($file_path)
            );
        }
        
        // Sort: directories first, then by name
        usort($items, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcmp($a['name'], $b['name']);
        });
        
        return $items;
    }
    
    /**
     * Read file contents
     * 
     * @param string $path File path (relative to WordPress root)
     * @return string
     */
    public function readFile(string $path): string {
        $full_path = $this->validatePath($path);
        if (!$full_path || !is_file($full_path)) {
            throw new RuntimeException('File not found or invalid');
        }
        
        if (!is_readable($full_path)) {
            throw new RuntimeException('File is not readable');
        }
        
        // Check file size (max 10MB)
        if (filesize($full_path) > 10 * 1024 * 1024) {
            throw new RuntimeException('File too large to read (max 10MB)');
        }
        
        return file_get_contents($full_path);
    }
    
    /**
     * Write file contents
     * 
     * @param string $path File path (relative to WordPress root)
     * @param string $content File content
     * @return bool
     */
    public function writeFile(string $path, string $content): bool {
        $full_path = $this->validatePath($path);
        if (!$full_path) {
            throw new InvalidArgumentException('Invalid path');
        }
        
        // Create directory if it doesn't exist
        $dir = dirname($full_path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException('Failed to create directory');
            }
        }
        
        return file_put_contents($full_path, $content) !== false;
    }
    
    /**
     * Delete file or directory
     * 
     * @param string $path Path (relative to WordPress root)
     * @return bool
     */
    public function delete(string $path): bool {
        $full_path = $this->validatePath($path);
        if (!$full_path) {
            throw new InvalidArgumentException('Invalid path');
        }
        
        // Prevent deletion of critical files
        $critical_files = array('wp-config.php', 'index.php', '.htaccess');
        $basename = basename($full_path);
        if (in_array($basename, $critical_files) && strpos($full_path, $this->base_path) === 0) {
            throw new RuntimeException('Cannot delete critical files');
        }
        
        if (is_dir($full_path)) {
            return $this->deleteDirectory($full_path);
        } else {
            return unlink($full_path);
        }
    }
    
    /**
     * Create directory
     * 
     * @param string $path Directory path (relative to WordPress root)
     * @return bool
     */
    public function createDirectory(string $path): bool {
        $full_path = $this->validatePath($path);
        if (!$full_path) {
            throw new InvalidArgumentException('Invalid path');
        }
        
        if (is_dir($full_path)) {
            return true;
        }
        
        return mkdir($full_path, 0755, true);
    }
    
    /**
     * Upload file
     * 
     * @param array $file $_FILES array element
     * @param string $destination Destination path (relative to WordPress root)
     * @return array Result with success status and file path
     */
    public function uploadFile(array $file, string $destination): array {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid file upload');
        }
        
        $full_path = $this->validatePath($destination);
        if (!$full_path) {
            throw new InvalidArgumentException('Invalid destination path');
        }
        
        // Check file size (max 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            throw new RuntimeException('File too large (max 50MB)');
        }
        
        // Create directory if needed
        $dir = dirname($full_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            return array(
                'success' => true,
                'path' => $destination,
                'size' => $file['size']
            );
        }
        
        throw new RuntimeException('Failed to upload file');
    }
    
    /**
     * Validate and sanitize path
     * 
     * @param string $path Path to validate
     * @return string|false Full path or false if invalid
     */
    private function validatePath(string $path): string|false {
        if (empty($path)) {
            return $this->base_path;
        }
        
        // Remove any directory traversal attempts
        $path = str_replace('..', '', $path);
        $path = ltrim($path, '/');
        
        $full_path = $this->base_path . '/' . $path;
        $full_path = realpath(dirname($full_path)) . '/' . basename($path);
        
        // Ensure path is within base path
        if (strpos($full_path, $this->base_path) !== 0) {
            return false;
        }
        
        return $full_path;
    }
    
    /**
     * Delete directory recursively
     * 
     * @param string $dir Directory path
     * @return bool
     */
    private function deleteDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $file_path = $dir . '/' . $file;
            if (is_dir($file_path)) {
                $this->deleteDirectory($file_path);
            } else {
                unlink($file_path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Format bytes to human readable
     * 
     * @param int $bytes Bytes
     * @return string
     */
    private function formatBytes(int $bytes): string {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}


