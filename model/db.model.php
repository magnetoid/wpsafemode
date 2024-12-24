<?php


include_once('settings.php');
 class dbModel extends PDO {

     private $engine;
     private $host;
     private $database;
     private $user;
     private $pass;
     
     public $condition;
     //public  $wp_options;
     private $safemode_url; //change config to pull from db or external source
     public function __construct(){
         //   echo dirname(__FILE__);
         global $settings;
            

         if(!defined('DB_NAME')){
             echo 'no database parameters set!';
             exit;
         }
         $this->wp_options = array();
    
         $this->safemode_url = $settings['safemode_url'];
         $this->engine = 'mysql';
         $this->host = DB_HOST;
         $this->database = DB_NAME;
         $this->user = DB_USER;
         $this->pass = DB_PASSWORD;
         $this->condition = '';
         $dns = $this->engine.':dbname='.$this->database.";host=".$this->host;
         try{
             parent::__construct( $dns, $this->user, $this->pass );
         }catch(PDOException $ex) {

             //echo "An Error occured!"; //user friendly message
             echo '<p style="color:red">Error: </p>'. $ex->getMessage();
             //die('<p style="color:red">Error: </p>'.$ex);
             return false;
         }

     }
     
     
     function add_condition( $field, $value = '', $options = array('condition'=>'AND','operator'=>'=','exact'=> true )){
     	if( $this->check_value_type($value) == 'string' &&  $options['operator'] == '='){
     		return;	     			
     	}
        if(empty($this->condition)){
			$this->condition = ' WHERE ';
		}else{
			
			$this->condition.= ' ' . $options['condition'] . ' ';
		}
		if($options['operator'] == 'LIKE' && $options['exact'] == false){
			$value = '%'. $value . '%';
		}
	
			
		 
		// $this->condition.=  ' '.  $field .' '. $options['operator'] .' '.  " '" . $value . "' ";	
		
		//if( $this->check_value_type($value) == 'string'  &&  $options['operator']!= '='){
		 $this->condition.=  ' '.  $field .' '. $options['operator'] .' '.  " '" . $value . "' ";	
		//}
	   
	//	$this->condition.= $field . ' ' . 
		
		
	 }
	 
	/**
	* Shows list of tables in active database in array format 
	*  
	* @return array list of tables in given database 
	*/
    public function show_tables(){
        try{
            $q = $this->query("SHOW TABLES FROM " . DB_NAME . "");
            $q->execute();
        }catch(PDOException $ex) {
            echo '<p style="color:red">Error: </p>'. $ex->getMessage();
            return false;
        }      
        return $q->fetchAll(PDO::FETCH_COLUMN);
    
    }
    
	    function db_show_columns( $table = ''){
		if(!empty($table)){
			
			$q = $this->prepare("SHOW FULL COLUMNS FROM ".$table);
			$q->execute();
			return $q->fetchAll(PDO::FETCH_ASSOC);
			
		}
		
	}
	function db_show_keys( $table = ''){
		    $q = $this->prepare("SHOW KEYS FROM ".$table);
			$q->execute();
			return $q->fetchAll(PDO::FETCH_ASSOC);
		
	}
	function db_show_table_info( $table = '' ){
		  $q = $this->prepare("SHOW TABLE STATUS FROM " . DB_NAME . " WHERE Name = '" . $table . "'");
		  $q->execute();
		  return $q->fetch( PDO::FETCH_ASSOC );
		//SHOW TABLE STATUS WHERE Name = 'xxx'
	}
	
   /**
   * 
   * 
   * @return
   */
   function optimize_tables(){
   	$tables  = $this->show_tables();
   	foreach($tables as $table){
		$query = $this->query('OPTIMIZE TABLE '.$table);
		$query->execute();
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