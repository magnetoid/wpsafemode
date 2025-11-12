<?php
/**
 * Media Library Service
 * Manages WordPress media files
 */
class MediaLibraryService {
    
    private $config;
    private $wp_dir;
    private $upload_dir;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->wp_dir = $this->config->get('wp_dir', '../');
        $this->upload_dir = $this->wp_dir . 'wp-content/uploads/';
    }
    
    /**
     * Get media files
     * 
     * @param int $limit Number of files to return
     * @param int $offset Offset for pagination
     * @param string $search Search term
     * @return array
     */
    public function getMediaFiles(int $limit = 50, int $offset = 0, string $search = ''): array {
        try {
            $db_model = new DBModel();
            $db_prefix = $this->config->get('wp_db_prefix', 'wp_');
            
            $query = "SELECT p.ID, p.post_title, p.post_name, p.post_mime_type, p.post_date, 
                             pm1.meta_value as file_path, pm2.meta_value as file_size
                      FROM {$db_prefix}posts p
                      LEFT JOIN {$db_prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_wp_attached_file'
                      LEFT JOIN {$db_prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_wp_attachment_metadata'
                      WHERE p.post_type = 'attachment'";
            
            $params = array();
            
            if (!empty($search)) {
                $query .= " AND (p.post_title LIKE ? OR p.post_name LIKE ?)";
                $search_term = '%' . $search . '%';
                $params[] = $search_term;
                $params[] = $search_term;
            }
            
            $query .= " ORDER BY p.post_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $results = $db_model->query($query, $params);
            
            $files = array();
            foreach ($results as $row) {
                $file_info = $this->getFileInfo($row);
                $files[] = $file_info;
            }
            
            return array(
                'files' => $files,
                'total' => $this->getTotalCount($search),
                'limit' => $limit,
                'offset' => $offset
            );
        } catch (Throwable $e) {
            error_log('Media Library Error: ' . $e->getMessage());
            return array(
                'files' => array(),
                'total' => 0,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get file information
     * 
     * @param array $row Database row
     * @return array
     */
    private function getFileInfo(array $row): array {
        $file_path = $row['file_path'] ?? '';
        $full_path = $this->upload_dir . $file_path;
        
        $info = array(
            'id' => $row['ID'],
            'title' => $row['post_title'],
            'filename' => $row['post_name'],
            'mime_type' => $row['post_mime_type'],
            'date' => $row['post_date'],
            'file_path' => $file_path,
            'url' => $this->getFileUrl($file_path),
            'size' => file_exists($full_path) ? filesize($full_path) : 0,
            'exists' => file_exists($full_path),
            'type' => $this->getFileType($row['post_mime_type'])
        );
        
        // Parse metadata for images
        if (!empty($row['file_size'])) {
            $metadata = $row['file_size'];
            // Try to unserialize if it's serialized
            if (is_string($metadata) && (substr($metadata, 0, 2) === 'a:' || substr($metadata, 0, 2) === 'O:')) {
                $metadata = @unserialize($metadata);
            }
            if (is_array($metadata) && isset($metadata['width'], $metadata['height'])) {
                $info['width'] = $metadata['width'];
                $info['height'] = $metadata['height'];
            }
        }
        
        return $info;
    }
    
    /**
     * Get file URL
     * 
     * @param string $file_path
     * @return string
     */
    private function getFileUrl(string $file_path): string {
        $site_url = '';
        
        // Try WordPress function first
        if (function_exists('get_option')) {
            $site_url = get_option('siteurl') ?? '';
        }
        
        // Fallback to database
        if (empty($site_url)) {
            try {
                $db_model = new DBModel();
                $db_prefix = $this->config->get('wp_db_prefix', 'wp_');
                $result = $db_model->query("SELECT option_value FROM {$db_prefix}options WHERE option_name = 'siteurl' LIMIT 1");
                if ($result && isset($result[0]['option_value'])) {
                    $site_url = $result[0]['option_value'];
                }
            } catch (Throwable $e) {
                // Ignore
            }
        }
        
        // Final fallback
        if (empty($site_url)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $site_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        
        return rtrim($site_url, '/') . '/wp-content/uploads/' . ltrim($file_path, '/');
    }
    
    /**
     * Get file type from MIME type
     * 
     * @param string $mime_type
     * @return string
     */
    private function getFileType(?string $mime_type): string {
        if (strpos($mime_type, 'image/') === 0) {
            return 'image';
        } elseif (strpos($mime_type, 'video/') === 0) {
            return 'video';
        } elseif (strpos($mime_type, 'audio/') === 0) {
            return 'audio';
        } elseif (strpos($mime_type, 'application/pdf') === 0) {
            return 'pdf';
        } else {
            return 'other';
        }
    }
    
    /**
     * Get total count of media files
     * 
     * @param string $search
     * @return int
     */
    private function getTotalCount(string $search = ''): int {
        try {
            $db_model = new DBModel();
            $db_prefix = $this->config->get('wp_db_prefix', 'wp_');
            
            $query = "SELECT COUNT(*) as count FROM {$db_prefix}posts WHERE post_type = 'attachment'";
            $params = array();
            
            if (!empty($search)) {
                $query .= " AND (post_title LIKE ? OR post_name LIKE ?)";
                $search_term = '%' . $search . '%';
                $params[] = $search_term;
                $params[] = $search_term;
            }
            
            $result = $db_model->query($query, $params);
            if ($result && isset($result[0]['count'])) {
                return intval($result[0]['count']);
            }
        } catch (Throwable $e) {
            // Ignore
        }
        
        return 0;
    }
    
    /**
     * Delete media file
     * 
     * @param int $file_id
     * @return array
     */
    public function deleteFile(int $file_id): array {
        try {
            $db_model = new DBModel();
            $db_prefix = $this->config->get('wp_db_prefix', 'wp_');
            
            // Get file info
            $file = $db_model->query(
                "SELECT p.ID, pm.meta_value as file_path 
                 FROM {$db_prefix}posts p
                 LEFT JOIN {$db_prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                 WHERE p.ID = ? AND p.post_type = 'attachment'",
                array($file_id)
            );
            
            if (!$file || !isset($file[0])) {
                return array('success' => false, 'message' => 'File not found');
            }
            
            $file_path = $file[0]['file_path'];
            $full_path = $this->upload_dir . $file_path;
            
            // Delete physical file
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
            
            // Delete from database
            $db_model->query("DELETE FROM {$db_prefix}posts WHERE ID = ?", array($file_id));
            $db_model->query("DELETE FROM {$db_prefix}postmeta WHERE post_id = ?", array($file_id));
            
            return array('success' => true, 'message' => 'File deleted successfully');
        } catch (Throwable $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Get media statistics
     * 
     * @return array
     */
    public function getStatistics(): array {
        try {
            $db_model = new DBModel();
            $db_prefix = $this->config->get('wp_db_prefix', 'wp_');
            
            $stats = array(
                'total_files' => 0,
                'total_size' => 0,
                'by_type' => array(),
                'recent_uploads' => 0
            );
            
            // Total count
            $total = $db_model->query("SELECT COUNT(*) as count FROM {$db_prefix}posts WHERE post_type = 'attachment'");
            if ($total && isset($total[0]['count'])) {
                $stats['total_files'] = intval($total[0]['count']);
            }
            
            // Count by type
            $by_type = $db_model->query(
                "SELECT post_mime_type, COUNT(*) as count 
                 FROM {$db_prefix}posts 
                 WHERE post_type = 'attachment' 
                 GROUP BY post_mime_type"
            );
            
            if ($by_type) {
                foreach ($by_type as $row) {
                    $type = $this->get_file_type($row['post_mime_type']);
                    if (!isset($stats['by_type'][$type])) {
                        $stats['by_type'][$type] = 0;
                    }
                    $stats['by_type'][$type] += intval($row['count']);
                }
            }
            
            // Recent uploads (last 7 days)
            $recent = $db_model->query(
                "SELECT COUNT(*) as count FROM {$db_prefix}posts 
                 WHERE post_type = 'attachment' 
                 AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            if ($recent && isset($recent[0]['count'])) {
                $stats['recent_uploads'] = intval($recent[0]['count']);
            }
            
            return $stats;
        } catch (Throwable $e) {
            return array('error' => $e->getMessage());
        }
    }
}

