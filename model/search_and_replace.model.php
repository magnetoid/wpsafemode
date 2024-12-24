<?php
	class SearchAndReplaceModel extends DashboardModel {
		
		function __construct(){    	
	        parent::__construct();         
	    }
	    
	   /**
	   * Searches through database for given $term string 
	   * 
	   * @param string $term term to be found in database
	   * @param array|mixed $args various arguments to specify more precise search in db 
	   * 
	   * @return array results from db query 
	   */
	   function db_search( $term = '' , $args = array()){
	   	set_time_limit(0);
	   	if(empty($term))
	   	return; 
	   	 $tables = $this->show_tables();
	   	if(isset($args['criteria']['tables']) && !empty($args['criteria']['tables']) && $args['criteria']['db'] == 'partial'){
		$tables = $this->db_allowed_tables_filter($tables, $args['criteria']['tables']);
		}
	  
	   	$results = array();
	   	
	   	 foreach($tables as $table){
	   	 	$this->condition = '';
	   	 	$results[$table] = array();
	   	 	$results[$table]['table_name'] = $table;
	   	 	
		   		$table_columns = $this->db_show_columns( $table );
		   		
		   		$results[$table]['table_columns'] = array();
		   		foreach($table_columns as $column){
		   		 $results[$table]['table_columns'][] = $column['Field'];	
		   	     $options = array('condition'=>'OR','operator'=> $this->get_operator($column['Type']) ,'exact'=> false );
				 $this->add_condition( $column['Field'] , $term ,  $options );
				}
				$query = 'SELECT DISTINCT * FROM ' . $table . ' ' . $this->condition;
		   		
		   		
		   		if(!empty($this->condition)){				
				
		   		$q = $this->prepare( $query );
			    $q->execute();
		       
		       	$results[$table]['table_results'] = '';
			while( $row = $q->fetch(PDO::FETCH_ASSOC)){
				   foreach($row as &$item){
				   	if(stristr($item, $term)){
				   		$item = htmlentities($item);
					   $item = str_ireplace( $term ,   '<span class="highlight">' .$term.'</span>' ,  $item);
					}
				//	$item = htmlentities($item);
				   }
					$results[$table]['table_results'][] = $row;
			}
			}
			if(empty($results[$table]['table_results']) || !isset($results[$table]['table_results'])){
				unset($results[$table]); //flush if no results for table
			}
			
		 
		   		
		 }
		 return $results;
	   	
	   }
	     
	}
?>