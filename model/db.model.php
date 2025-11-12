<?php
/**
 * Database Model
 * Base database model extending PDO with security enhancements
 */
class dbModel extends PDO {

    private $config;
    public $condition;
    private $condition_params = array(); // For parameter binding
    private $safemode_url;
    
    /**
     * Constructor - establish database connection
     * 
     * @param Config|null $config Configuration instance
     * @throws RuntimeException If database connection fails
     */
    public function __construct($config = null) {
        $this->config = $config ?? Config::getInstance();
        
        if (!defined('DB_NAME')) {
            error_log('WP Safe Mode: Database parameters not set');
            if (!defined('WPSM_API')) {
                echo 'no database parameters set!';
                exit;
            }
            throw new RuntimeException('Database parameters not set');
        }
        
        $this->wp_options = array();
        $this->safemode_url = $this->config->get('safemode_url', '');
        $this->condition = '';
        
        $engine = 'mysql';
        $host = DB_HOST;
        $database = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASSWORD;
        $dsn = $engine . ':dbname=' . $database . ';host=' . $host;
        
        try {
            parent::__construct($dsn, $user, $pass);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $ex) {
            error_log('Database connection error: ' . $ex->getMessage());
            if (!defined('WPSM_API')) {
                echo '<p style="color:red">Database connection error. Please contact administrator.</p>';
            }
            throw new RuntimeException('Database connection failed', 0, $ex);
        }
    }
     
     
     function add_condition( $field, $value = '', $options = array('condition'=>'AND','operator'=>'=','exact'=> true )){
     	// SECURITY FIX: Whitelist allowed field names to prevent SQL injection
     	$allowed_fields = array('option_name', 'option_value', 'post_type', 'comment_approved', 'post_status', 'post_title', 'post_content');
     	if (!in_array($field, $allowed_fields)) {
     		throw new InvalidArgumentException("Field name not allowed: " . htmlspecialchars($field, ENT_QUOTES, 'UTF-8'));
     	}
     	
     	// SECURITY FIX: Whitelist allowed operators
     	$allowed_operators = array('=', 'LIKE', '!=', '<', '>', '<=', '>=');
     	if (!in_array($options['operator'], $allowed_operators)) {
     		throw new InvalidArgumentException("Operator not allowed: " . htmlspecialchars($options['operator'], ENT_QUOTES, 'UTF-8'));
     	}
     	
        if(empty($this->condition)){
			$this->condition = ' WHERE ';
		}else{
			$this->condition.= ' ' . $options['condition'] . ' ';
		}
		if($options['operator'] == 'LIKE' && $options['exact'] == false){
			$value = '%'. $value . '%';
		}
	
		// SECURITY FIX: Use parameter binding instead of string concatenation
		$param_name = ':param_' . count($this->condition_params);
		$this->condition .= $field . ' ' . $options['operator'] . ' ' . $param_name;
		$this->condition_params[$param_name] = $value;
	 }
	 
	 /**
	 * Get condition parameters for binding
	 * 
	 * @return array
	 */
	 function get_condition_params() {
	 	return $this->condition_params;
	 }
	 
	 /**
	 * Reset condition and parameters
	 * 
	 * @return void
	 */
	 function reset_condition() {
	 	$this->condition = '';
	 	$this->condition_params = array();
	 }
		
	 /**
	 * Validate table name to prevent SQL injection
	 * 
	 * @param string $table Table name
	 * @return string|false Validated table name or false
	 */
	 function validate_table_name($table) {
	 	// Only allow alphanumeric, underscore, and dash
	 	if (!preg_match('/^[a-zA-Z0-9_-]+$/', $table)) {
	 		return false;
	 	}
	 	
	 	// Get list of valid tables
	 	$valid_tables = $this->show_tables();
	 	if (!in_array($table, $valid_tables)) {
	 		return false;
	 	}
	 	
	 	return $table;
	 }
	 
	/**
	* Shows list of tables in active database in array format 
	*  
	* @return array list of tables in given database 
	*/
    public function show_tables(){
        try{
            // Note: SHOW TABLES doesn't support parameter binding, but DB_NAME is from config, not user input
            $q = $this->query("SHOW TABLES FROM `" . DB_NAME . "`");
            return $q->fetchAll(PDO::FETCH_COLUMN);
        }catch(PDOException $ex) {
            error_log('WP Safe Mode Database Error (show_tables): ' . $ex->getMessage());
            // Don't output in API context
            if (!defined('WPSM_API')) {
                echo '<p style="color:red">Error: </p>'. $ex->getMessage();
            }
            return array();
        }      
    }
    
	    function db_show_columns( $table = ''){
		if(empty($table)){
			return false;
		}
		
		// SECURITY FIX: Validate table name
		$validated_table = $this->validate_table_name($table);
		if (!$validated_table) {
			throw new InvalidArgumentException('Invalid table name: ' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8'));
		}
		
		// Note: SHOW commands don't support parameter binding, but we've validated the table name
		$q = $this->prepare("SHOW FULL COLUMNS FROM `" . $validated_table . "`");
		$q->execute();
		return $q->fetchAll(PDO::FETCH_ASSOC);
	}
	function db_show_keys( $table = ''){
		if(empty($table)){
			return false;
		}
		
		// SECURITY FIX: Validate table name
		$validated_table = $this->validate_table_name($table);
		if (!$validated_table) {
			throw new InvalidArgumentException('Invalid table name: ' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8'));
		}
		
		$q = $this->prepare("SHOW KEYS FROM `" . $validated_table . "`");
		$q->execute();
		return $q->fetchAll(PDO::FETCH_ASSOC);
	}
	function db_show_table_info( $table = '' ){
		if(empty($table)){
			return false;
		}
		
		// SECURITY FIX: Validate table name
		$validated_table = $this->validate_table_name($table);
		if (!$validated_table) {
			throw new InvalidArgumentException('Invalid table name: ' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8'));
		}
		
		// SECURITY FIX: Use parameter binding
		$q = $this->prepare("SHOW TABLE STATUS FROM `" . DB_NAME . "` WHERE Name = :table_name");
		$q->bindValue(':table_name', $validated_table, PDO::PARAM_STR);
		$q->execute();
		return $q->fetch( PDO::FETCH_ASSOC );
	}
	
   /**
   * 
   * 
   * @return
   */
   function optimize_tables(){
   	$tables  = $this->show_tables();
   	foreach($tables as $table){
		// SECURITY FIX: Validate table name before use
		$validated_table = $this->validate_table_name($table);
		if ($validated_table) {
			// Table names in OPTIMIZE cannot be parameterized, but we've validated them
			$query = $this->query('OPTIMIZE TABLE `' . $validated_table . '`');
			$query->execute();
		}
	}
	} 
	 function get_field_type( $field){
	 	
	 }
	 function check_value_type( $value = ''){
	 	if($this->isInteger($value) || is_float($value)){
			return 'number';
		}else{
			return 'string';
		}
	 }
	 function isInteger($input){
	    return(ctype_digit(strval($input)));
	}
	 function get_operator($type = 'integer'){
	 	if($type == 'integer' || strstr( $type , 'int' ) || strstr( $type , 'float' )){
			return '=';
		}
		if( $type == 'text' || strstr( $type , 'varchar' ) || strstr( $type , 'text' ) ){
		return 'LIKE';	
		}
		return '=';
	 }
	 function check_type( $field ){
	 	if(isset($field)){
			
		}
	 }

 }

global $dbModel;
$dbModel = new dbModel;
$dbModel->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);