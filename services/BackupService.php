<?php
/**
 * Backup Service
 * Handles backup operations
 */
class BackupService {
    
    private $model;
    private $config;
    private $logger;
    
    /**
     * Constructor
     * 
     * @param DashboardModel $model Dashboard model instance
     * @param Config|null $config Configuration instance
     * @param Logger|null $logger Logger instance
     */
    public function __construct(DashboardModel $model, ?Config $config = null, ?Logger $logger = null) {
        $this->model = $model;
        $this->config = $config ?? Config::getInstance();
        $this->logger = $logger ?? Logger::getInstance();
    }
    
    /**
     * Create database backup
     * 
     * @param string $type Backup type (full, tables, csv)
     * @return array Result with success status and file path
     */
    public function createDatabaseBackup(string $type = 'full'): array {
        try {
            $this->logger->info('Starting database backup', ['type' => $type]);
            
            switch ($type) {
                case 'full':
                    $result = $this->model->db_build_sql_backup();
                    break;
                case 'tables':
                    $result = $this->model->db_build_tables_backup();
                    break;
                case 'csv':
                    $result = $this->model->db_build_csv_backup();
                    break;
                default:
                    throw new InvalidArgumentException('Invalid backup type: ' . $type);
            }
            
            if ($result) {
                $this->logger->info('Database backup completed', ['type' => $type]);
                return ['success' => true, 'message' => 'Backup created successfully', 'files' => $result];
            }
            
            throw new RuntimeException('Backup creation failed');
        } catch (Throwable $e) {
            $this->logger->error('Database backup failed', ['type' => $type, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create file backup
     * 
     * @param string $type Backup type (full, partial)
     * @param array|null $files Specific files to backup (for partial)
     * @return array Result with success status and file path
     */
    public function createFileBackup(string $type = 'full', ?array $files = null): array {
        try {
            $this->logger->info('Starting file backup', ['type' => $type]);
            
            if ($type === 'partial' && !empty($files)) {
                $result = $this->model->backup_selected_files($files);
            } else {
                $result = $this->model->backup_all_files();
            }
            
            if ($result) {
                $this->logger->info('File backup completed', ['type' => $type]);
                return ['success' => true, 'message' => 'Backup created successfully', 'file' => $result];
            }
            
            throw new RuntimeException('Backup creation failed');
        } catch (Throwable $e) {
            $this->logger->error('File backup failed', ['type' => $type, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()];
        }
    }
}

