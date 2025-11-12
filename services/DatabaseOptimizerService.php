<?php
/**
 * Database Optimizer Service
 * Advanced database optimization and maintenance tools
 */
class DatabaseOptimizerService {
    
    private $config;
    private $db_model;
    private $db_prefix;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->db_model = new DBModel();
        $this->db_prefix = $this->config->get('wp_db_prefix', 'wp_');
    }
    
    /**
     * Get database analysis
     * 
     * @return array
     */
    public function analyze() {
        $analysis = array(
            'tables' => $this->analyze_tables(),
            'orphaned_data' => $this->find_orphaned_data(),
            'duplicate_data' => $this->find_duplicates(),
            'unused_data' => $this->find_unused_data(),
            'recommendations' => array()
        );
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generate_recommendations($analysis);
        
        return $analysis;
    }
    
    /**
     * Analyze all tables
     * 
     * @return array
     */
    private function analyze_tables() {
        $tables = array();
        $all_tables = $this->db_model->show_tables();
        
        foreach ($all_tables as $table) {
            $info = $this->db_model->db_show_table_info($table);
            if ($info) {
                $tables[] = array(
                    'name' => $table,
                    'rows' => intval($info['Rows'] ?? 0),
                    'data_length' => intval($info['Data_length'] ?? 0),
                    'index_length' => intval($info['Index_length'] ?? 0),
                    'data_free' => intval($info['Data_free'] ?? 0),
                    'engine' => $info['Engine'] ?? 'Unknown',
                    'needs_optimization' => intval($info['Data_free'] ?? 0) > 0
                );
            }
        }
        
        return $tables;
    }
    
    /**
     * Find orphaned data
     * 
     * @return array
     */
    private function find_orphaned_data() {
        $orphaned = array(
            'postmeta' => 0,
            'commentmeta' => 0,
            'term_relationships' => 0
        );
        
        try {
            // Orphaned postmeta
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM {$this->db_prefix}postmeta pm
                 LEFT JOIN {$this->db_prefix}posts p ON pm.post_id = p.ID
                 WHERE p.ID IS NULL"
            );
            if ($result && isset($result[0]['count'])) {
                $orphaned['postmeta'] = intval($result[0]['count']);
            }
            
            // Orphaned commentmeta
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM {$this->db_prefix}commentmeta cm
                 LEFT JOIN {$this->db_prefix}comments c ON cm.comment_id = c.comment_ID
                 WHERE c.comment_ID IS NULL"
            );
            if ($result && isset($result[0]['count'])) {
                $orphaned['commentmeta'] = intval($result[0]['count']);
            }
            
            // Orphaned term relationships
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM {$this->db_prefix}term_relationships tr
                 LEFT JOIN {$this->db_prefix}posts p ON tr.object_id = p.ID
                 WHERE p.ID IS NULL"
            );
            if ($result && isset($result[0]['count'])) {
                $orphaned['term_relationships'] = intval($result[0]['count']);
            }
        } catch (Throwable $e) {
            error_log('Database Optimizer Error: ' . $e->getMessage());
        }
        
        return $orphaned;
    }
    
    /**
     * Find duplicate data
     * 
     * @return array
     */
    private function find_duplicates() {
        $duplicates = array(
            'postmeta' => 0,
            'options' => 0
        );
        
        try {
            // Duplicate postmeta
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM (
                    SELECT post_id, meta_key, COUNT(*) as cnt
                    FROM {$this->db_prefix}postmeta
                    GROUP BY post_id, meta_key
                    HAVING cnt > 1
                ) as duplicates"
            );
            if ($result && isset($result[0]['count'])) {
                $duplicates['postmeta'] = intval($result[0]['count']);
            }
            
            // Duplicate options (shouldn't happen, but check anyway)
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM (
                    SELECT option_name, COUNT(*) as cnt
                    FROM {$this->db_prefix}options
                    GROUP BY option_name
                    HAVING cnt > 1
                ) as duplicates"
            );
            if ($result && isset($result[0]['count'])) {
                $duplicates['options'] = intval($result[0]['count']);
            }
        } catch (Throwable $e) {
            error_log('Database Optimizer Error: ' . $e->getMessage());
        }
        
        return $duplicates;
    }
    
    /**
     * Find unused data
     * 
     * @return array
     */
    private function find_unused_data() {
        $unused = array(
            'revisions' => 0,
            'spam_comments' => 0,
            'trashed_posts' => 0,
            'expired_transients' => 0
        );
        
        try {
            // Post revisions
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM {$this->db_prefix}posts WHERE post_type = 'revision'"
            );
            if ($result && isset($result[0]['count'])) {
                $unused['revisions'] = intval($result[0]['count']);
            }
            
            // Spam comments
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM {$this->db_prefix}comments WHERE comment_approved = 'spam'"
            );
            if ($result && isset($result[0]['count'])) {
                $unused['spam_comments'] = intval($result[0]['count']);
            }
            
            // Trashed posts
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM {$this->db_prefix}posts WHERE post_status = 'trash'"
            );
            if ($result && isset($result[0]['count'])) {
                $unused['trashed_posts'] = intval($result[0]['count']);
            }
            
            // Expired transients (approximate - check old ones)
            $result = $this->db_model->query(
                "SELECT COUNT(*) as count FROM {$this->db_prefix}options 
                 WHERE option_name LIKE '_transient_timeout_%' 
                 AND option_value < UNIX_TIMESTAMP()"
            );
            if ($result && isset($result[0]['count'])) {
                $unused['expired_transients'] = intval($result[0]['count']);
            }
        } catch (Throwable $e) {
            error_log('Database Optimizer Error: ' . $e->getMessage());
        }
        
        return $unused;
    }
    
    /**
     * Generate recommendations
     * 
     * @param array $analysis
     * @return array
     */
    private function generate_recommendations($analysis) {
        $recommendations = array();
        
        // Check for tables needing optimization
        $needs_opt = 0;
        foreach ($analysis['tables'] as $table) {
            if ($table['needs_optimization']) {
                $needs_opt++;
            }
        }
        
        if ($needs_opt > 0) {
            $recommendations[] = array(
                'priority' => 'high',
                'title' => 'Optimize database tables',
                'description' => "{$needs_opt} tables need optimization",
                'action' => 'optimize_tables'
            );
        }
        
        // Check for orphaned data
        $total_orphaned = array_sum($analysis['orphaned_data']);
        if ($total_orphaned > 0) {
            $recommendations[] = array(
                'priority' => 'medium',
                'title' => 'Clean up orphaned data',
                'description' => "Found {$total_orphaned} orphaned records",
                'action' => 'clean_orphaned'
            );
        }
        
        // Check for revisions
        if ($analysis['unused_data']['revisions'] > 50) {
            $recommendations[] = array(
                'priority' => 'medium',
                'title' => 'Clean up post revisions',
                'description' => "Found {$analysis['unused_data']['revisions']} post revisions",
                'action' => 'clean_revisions'
            );
        }
        
        // Check for expired transients
        if ($analysis['unused_data']['expired_transients'] > 100) {
            $recommendations[] = array(
                'priority' => 'low',
                'title' => 'Clean up expired transients',
                'description' => "Found {$analysis['unused_data']['expired_transients']} expired transients",
                'action' => 'clean_transients'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Optimize all tables
     * 
     * @return array
     */
    public function optimize_all_tables() {
        $results = array('optimized' => 0, 'errors' => 0);
        
        try {
            $tables = $this->db_model->show_tables();
            foreach ($tables as $table) {
                if ($this->db_model->validate_table_name($table)) {
                    try {
                        $this->db_model->query("OPTIMIZE TABLE `{$table}`");
                        $results['optimized']++;
                    } catch (Throwable $e) {
                        $results['errors']++;
                        error_log("Error optimizing table {$table}: " . $e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Database Optimizer Error: ' . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Clean orphaned data
     * 
     * @return array
     */
    public function clean_orphaned() {
        $results = array('cleaned' => 0, 'errors' => 0);
        
        try {
            // Clean orphaned postmeta
            $stmt1 = $this->db_model->prepare(
                "DELETE pm FROM {$this->db_prefix}postmeta pm
                 LEFT JOIN {$this->db_prefix}posts p ON pm.post_id = p.ID
                 WHERE p.ID IS NULL"
            );
            $stmt1->execute();
            $results['cleaned'] += $stmt1->rowCount();
            
            // Clean orphaned commentmeta
            $stmt2 = $this->db_model->prepare(
                "DELETE cm FROM {$this->db_prefix}commentmeta cm
                 LEFT JOIN {$this->db_prefix}comments c ON cm.comment_id = c.comment_ID
                 WHERE c.comment_ID IS NULL"
            );
            $stmt2->execute();
            $results['cleaned'] += $stmt2->rowCount();
            
            // Clean orphaned term relationships
            $stmt3 = $this->db_model->prepare(
                "DELETE tr FROM {$this->db_prefix}term_relationships tr
                 LEFT JOIN {$this->db_prefix}posts p ON tr.object_id = p.ID
                 WHERE p.ID IS NULL"
            );
            $stmt3->execute();
            $results['cleaned'] += $stmt3->rowCount();
        } catch (Throwable $e) {
            $results['errors']++;
            error_log('Database Optimizer Error: ' . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Clean post revisions
     * 
     * @param int $keep Number of revisions to keep per post
     * @return array
     */
    public function clean_revisions($keep = 3) {
        $results = array('cleaned' => 0, 'errors' => 0);
        
        try {
            // Delete old revisions, keeping only the most recent ones
            $stmt = $this->db_model->prepare(
                "DELETE FROM {$this->db_prefix}posts 
                 WHERE post_type = 'revision' 
                 AND ID NOT IN (
                     SELECT * FROM (
                         SELECT ID FROM {$this->db_prefix}posts 
                         WHERE post_type = 'revision' 
                         ORDER BY post_date DESC 
                         LIMIT 1000
                     ) as keep_revisions
                 )"
            );
            $stmt->execute();
            $results['cleaned'] = $stmt->rowCount();
        } catch (Throwable $e) {
            $results['errors']++;
            error_log('Database Optimizer Error: ' . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Clean expired transients
     * 
     * @return array
     */
    public function clean_transients() {
        $results = array('cleaned' => 0, 'errors' => 0);
        
        try {
            // Delete expired transients
            $stmt1 = $this->db_model->prepare(
                "DELETE FROM {$this->db_prefix}options 
                 WHERE option_name LIKE '_transient_timeout_%' 
                 AND option_value < UNIX_TIMESTAMP()"
            );
            $stmt1->execute();
            $cleaned1 = $stmt1->rowCount();
            
            // Delete transient values
            $stmt2 = $this->db_model->prepare(
                "DELETE o1 FROM {$this->db_prefix}options o1
                 LEFT JOIN {$this->db_prefix}options o2 ON o1.option_name = REPLACE(o2.option_name, '_transient_timeout_', '_transient_')
                 WHERE o1.option_name LIKE '_transient_%' 
                 AND o1.option_name NOT LIKE '_transient_timeout_%'
                 AND o2.option_name IS NULL"
            );
            $stmt2->execute();
            $cleaned2 = $stmt2->rowCount();
            
            $results['cleaned'] = $cleaned1 + $cleaned2;
        } catch (Throwable $e) {
            $results['errors']++;
            error_log('Database Optimizer Error: ' . $e->getMessage());
        }
        
        return $results;
    }
}

