<?php
/**
 * Database Service
 * Handles database inspection and query operations
 */
class DatabaseService
{
    private $db;

    public function __construct()
    {
        $this->db = DashboardModel::get_db_instance();
    }

    /**
     * Get list of tables
     * @return array
     */
    public function getTables()
    {
        $tables = array();
        // PDO query returns PDOStatement
        $result = $this->db->query("SHOW TABLE STATUS");
        if ($result) {
            $tables = $result->fetchAll(PDO::FETCH_ASSOC);
        }
        return $tables;
    }

    /**
     * Get table schema
     * @param string $table
     * @return array
     */
    public function getTableSchema($table)
    {
        $columns = array();
        $indexes = array();

        // Validate table name (simple check)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return array('columns' => [], 'indexes' => []);
        }

        // Get columns
        $result = $this->db->query("SHOW FULL COLUMNS FROM `$table`");
        if ($result) {
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        }

        // Get indexes
        $result = $this->db->query("SHOW INDEX FROM `$table`");
        if ($result) {
            $indexes = $result->fetchAll(PDO::FETCH_ASSOC);
        }

        return array('columns' => $columns, 'indexes' => $indexes);
    }

    /**
     * Get table data (records)
     * @param string $table
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @param string $orderDir
     * @return array
     */
    public function getTableData($table, $limit = 50, $offset = 0, $orderBy = '', $orderDir = 'ASC')
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return array('rows' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset);
        }

        $limit = (int) $limit;
        $offset = (int) $offset;

        $sql = "SELECT * FROM `$table`";

        if (!empty($orderBy) && preg_match('/^[a-zA-Z0-9_]+$/', $orderBy)) {
            $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `$orderBy` $orderDir";
        }

        $sql .= " LIMIT $limit OFFSET $offset";

        $rows = array();
        $result = $this->db->query($sql);
        if ($result) {
            $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        }

        // Get total count
        $countResult = $this->db->query("SELECT COUNT(*) as count FROM `$table`");
        $total = 0;
        if ($countResult) {
            $row = $countResult->fetch(PDO::FETCH_ASSOC);
            $total = (int) $row['count'];
        }

        return array('rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset);
    }

    /**
     * Execute specific SQL query
     * @param string $query
     * @return array|bool
     */
    public function executeQuery($query)
    {
        try {
            $stmt = $this->db->query($query);

            if ($stmt->columnCount() > 0) {
                // It's a SELECT/SHOW etc
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return ['success' => true, 'rows' => $rows];
            } else {
                // INSERT/UPDATE/DELETE
                return ['success' => true, 'affected_rows' => $stmt->rowCount()];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
