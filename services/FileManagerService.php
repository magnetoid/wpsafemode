<?php
/**
 * File Manager Service
 * Handles file operations securely
 */
class FileManagerService
{

    private $config;
    private $base_path;

    /**
     * Constructor
     * 
     * @param Config|null $config Configuration instance
     */
    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
        $this->base_path = realpath($this->config->get('wp_dir', '../'));
    }

    /**
     * List directory contents
     * 
     * @param string $path Directory path (relative to WordPress root)
     * @return array
     */
    public function listDirectory(string $path = ''): array
    {
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
            if ($file === '.' || $file === '..')
                continue;

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
        usort($items, function ($a, $b) {
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
     * @return array array('content' => string, 'is_binary' => bool)
     */
    public function readFile(string $path): array
    {
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

        $content = file_get_contents($full_path);
        $is_binary = false;

        // Simple binary detection (check for null byte)
        if (strpos($content, "\0") !== false) {
            $is_binary = true;
            $content = base64_encode($content);
        }

        return array('content' => $content, 'is_binary' => $is_binary);
    }

    /**
     * Zip directory or file
     * 
     * @param string $path Source path
     * @param string $destination Destination zip path
     * @return bool
     */
    public function zipPath(string $path, string $destination): bool
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is not available');
        }

        $full_path = $this->validatePath($path);
        $full_dest = $this->validatePath($destination);

        if (!$full_path || !$full_dest) {
            throw new InvalidArgumentException('Invalid path');
        }

        $zip = new ZipArchive();
        if ($zip->open($full_dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create zip file');
        }

        if (is_dir($full_path)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($full_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $relative_path = substr($file_path, strlen($full_path) + 1);
                    $zip->addFile($file_path, $relative_path);
                }
            }
        } else {
            $zip->addFile($full_path, basename($full_path));
        }

        return $zip->close();
    }

    /**
     * Unzip archive
     * 
     * @param string $path Zip file path
     * @param string $destination Destination directory
     * @return bool
     */
    public function unzipFile(string $path, string $destination): bool
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is not available');
        }

        $full_path = $this->validatePath($path);
        $full_dest = $this->validatePath($destination);

        if (!$full_path || !is_file($full_path)) {
            throw new InvalidArgumentException('Invalid zip file');
        }

        // Ensure destination exists
        if (!is_dir($full_dest)) {
            if (!mkdir($full_dest, 0755, true)) {
                throw new RuntimeException('Failed to create destination directory');
            }
        }

        $zip = new ZipArchive();
        if ($zip->open($full_path) === true) {
            $zip->extractTo($full_dest);
            $zip->close();
            return true;
        }

        return false;
    }

    /**
     * Write file contents
     * 
     * @param string $path File path (relative to WordPress root)
     * @param string $content File content
     * @param bool $is_base64 Is content base64 encoded
     * @return bool
     */
    public function writeFile(string $path, string $content, bool $is_base64 = false): bool
    {
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

        if ($is_base64) {
            $content = base64_decode($content);
        }

        return file_put_contents($full_path, $content) !== false;
    }

    /**
     * Delete file or directory
     * 
     * @param string $path Path (relative to WordPress root)
     * @return bool
     */
    public function delete(string $path): bool
    {
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
    public function createDirectory(string $path): bool
    {
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
    public function uploadFile(array $file, string $destination): array
    {
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
    private function validatePath(string $path): string|false
    {
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
    private function deleteDirectory(string $dir): bool
    {
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
    private function formatBytes(int $bytes): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    /**
     * Fix permissions for file or directory
     * Directories: 0755, Files: 0644
     * 
     * @param string $path Path to fix
     * @return array Result count of fixed items
     */
    public function fixPermissions(string $path): array
    {
        $full_path = $this->validatePath($path);
        if (!$full_path) {
            throw new InvalidArgumentException('Invalid path');
        }

        $count = ['dirs' => 0, 'files' => 0];

        if (is_file($full_path)) {
            if (chmod($full_path, 0644)) {
                $count['files']++;
            }
            return $count;
        }

        // It's a directory
        if (!is_dir($full_path)) {
            throw new RuntimeException('Path not found');
        }

        // Fix the directory itself
        if (chmod($full_path, 0755)) {
            $count['dirs']++;
        }

        // Recursive fix
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($full_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (chmod($item->getRealPath(), 0755)) {
                    $count['dirs']++;
                }
            } else {
                if (chmod($item->getRealPath(), 0644)) {
                    $count['files']++;
                }
            }
        }

        return $count;
    }
}


