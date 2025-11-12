<?php
	class BackupDatabaseModel extends DashboardModel {
		
		public function __construct(){
	        parent::__construct();       
    	}
    	
    	/**
		* Scans through local backup storage directories in wpsafemode and returns array, a list of paths to backup files for backedup tables, 
		* csv files and full databases 
		* 
		* @return array list of filepaths 
		*/
	    public function get_database_backups(){
	    	$backups = array();
	    	$backups['csv'] = array();
	    	$backups['tables'] = array();
	    	$backups['database'] = array();
	    	
	    	$sourcedir_tables_csv =  $this->settings['safemode_dir'].$this->settings['sfstore'].'db_backup/csv/';
	    	$sourcedir_tables_database =  $this->settings['safemode_dir'].$this->settings['sfstore'].'db_backup/database/';
	    	$sourcedir_tables_tables =  $this->settings['safemode_dir'].$this->settings['sfstore'].'db_backup/tables/';
			 foreach(glob($sourcedir_tables_tables.'*') as $dir) {
			 	if(!is_dir($dir)){
				$backups['tables'][] = $dir;	
				}
			 	
			 }
			 foreach(glob($sourcedir_tables_csv.'*') as $dir) {
			 	if(!is_dir($dir)){
				$backups['csv'][] = $dir;	
				}
			 }
			 foreach(glob($sourcedir_tables_database.'*') as $dir) {
			 	if(!is_dir($dir)){
				$backups['database'][] = $dir;	
				}
			 }
			 return $backups;
		}
		
		/**
		* Creates backup of tables in csv format
		* 
		* @param empty|array $allowed_tables list of tables to be included in backup, if empty it does backup of all tables in active database 
		* @param boolean $archive if true, it will pack in zip archive backup
		* 
		* @return void|false 
		*/
	    public function backup_tables_csv($allowed_tables = '' , $archive = false){
	    	set_time_limit(0);
			 $tables = $this->show_tables();
			  $sourcedir_tables_csv =  $this->settings['safemode_dir'].$this->settings['sfstore'].'db_backup/csv/';
			  if(!empty($allowed_tables)){
				$tables = $this->db_allowed_tables_filter($tables, $allowed_tables);
			   }
			   $date = date('d-m-Y--H-i-s');
			    $backup_file_csv_zip = $sourcedir_tables_csv.'tables_database_'.DB_NAME.'-'.$date.'.zip';
			   $backup_files_csv = array();
			   foreach($tables as $table){
			   	   // SECURITY FIX: Validate table name before use
			   	   $validated_table = $this->validate_table_name($table);
			   	   if (!$validated_table) {
			   	       error_log('WP Safe Mode: Invalid table name for CSV export: ' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8'));
			   	       continue;
			   	   }
			   	   
			   	   $backup_file_csv = $sourcedir_tables_csv.$validated_table.'-'.$date.'.csv';
			   	   // SECURITY FIX: Validate file path to prevent directory traversal
			   	   $backup_file_csv = str_replace('..', '', $backup_file_csv);
			   	   $backup_file_csv = realpath(dirname($backup_file_csv)) . '/' . basename($backup_file_csv);
			   	   
			       $backup_files_csv[] =  $backup_file_csv;
			       // Note: INTO OUTFILE requires FILE privilege and cannot use parameter binding
			       // Table name is validated, file path is sanitized
			       try {
			           $q = $this->query("SELECT * INTO OUTFILE '". addslashes($backup_file_csv) . "' FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'  FROM `".DB_NAME."`.`" . $validated_table . "`");
			       } catch (PDOException $e) {
			           error_log('WP Safe Mode: CSV export failed for table ' . $validated_table . ': ' . $e->getMessage());
			           continue;
			       }
			   	}
			   	
			   	 if($archive == false){
				 	return $backup_files_csv;		 	
				 }else{
				 	if(DashboardHelpers::zip_data($backup_files_csv,$backup_file_csv_zip,$sourcedir_tables_csv)){
			 	     foreach($backup_files_csv as $table_file){
					  unlink($table_file);	
					 }
			 	    
				    return $backup_file_csv_zip;	
				 }
		   }
		}
	    /**
		* Creates backup of selected tables or full database  in .sql format, optionally backup can be archived in zip format 
		* 
		* @param null|array $allowed_tables list of tables that will be included in backup process. if empty it will be skipped 
		* @param boolean $full_backup if true, $allowed_tables will be skipped, and backup will include whole active database 
		* @param boolean $archive if true it archives backup in zip format 
		* @param string $wp_prefix if empty, it will take default db prefix 
		* 
		* @return
		*/
	    public function backup_tables($allowed_tables = '' , $full_backup = true, $archive = false , $wp_prefix =''){
	    	set_time_limit(0);
	        $tables = $this->show_tables();
	        
	        $output = '';
	        $submit_autobackup = filter_input(INPUT_POST, 'submit_autobackup');
	   
	        $backup_files_sql = array();
	        $date = date('d-m-Y--H-i-s');
	        //if is autobackup submit 
	        if($submit_autobackup){
				$sourcedir_master_sql =  $this->settings['safemode_dir'].$this->settings['sfstore'];
		        $sourcedir_tables_sql =  $this->settings['safemode_dir'].$this->settings['sfstore'];
		       
		        $backup_file_master_sql = $sourcedir_master_sql.'database_'.DB_NAME.$wp_prefix.'.sql';
		        $backup_file_master_zip =  $sourcedir_master_sql.'database_'.DB_NAME.$wp_prefix.'.zip';
		        $backup_file_tables_zip = $sourcedir_tables_sql.'tables_database_'.DB_NAME.$wp_prefix.'.zip';
			}else{			
		        $sourcedir_master_sql =  $this->settings['safemode_dir'].$this->settings['sfstore'].'db_backup/database/';
		        $sourcedir_tables_sql =  $this->settings['safemode_dir'].$this->settings['sfstore'].'db_backup/tables/';
		       
		        $backup_file_master_sql = $sourcedir_master_sql.'database_'.DB_NAME.'-'.$date.'.sql';
		        $backup_file_master_zip =  $sourcedir_master_sql.'database_'.DB_NAME.'-'.$date.'.zip';
		        $backup_file_tables_zip = $sourcedir_tables_sql.'tables_database_'.DB_NAME.'-'.$date.'.zip';
			}
	        
	        if(!empty($allowed_tables)){
				$tables = $this->db_allowed_tables_filter($tables, $allowed_tables);
			}
	        foreach($tables as $table){
	        	
					
	        	$create_table = $this->db_build_create_table($table);
	        
	            $backup_file = $sourcedir_tables_sql.'table_'.$table.'-'.$date.'.sql';
	    
	            $backup_files_sql[] = $backup_file;
	            $backup_file_csv = $this->settings['safemode_dir'].$this->settings['sfstore'].'db_backup/csv/'.$table.'-'.$date.'.csv';
	            if(file_exists($backup_file)){
					unlink($backup_file);
				}
				if(file_exists($backup_file_csv)){
					unlink($backup_file_csv);
				}
				
				$table_records = $this->db_build_insert_records($table);
				$content = $create_table.$table_records;
			
				DashboardHelpers::put_data($backup_file , $content );
		

	            try{

	            }catch(PDOException $ex) {
	                error_log('WP Safe Mode Database Error: ' . $ex->getMessage());
	                // Don't output in API context
	                if (!defined('WPSM_API')) {
	                    echo '<p style="color:red">Error: </p>'. $ex->getMessage();
	                }
	                return false;
	            }
	            
	        }
	        if($full_backup == true){
			 $full_path = DashboardHelpers::merge_files($backup_files_sql,$backup_file_master_sql,true);	
			 if($archive == false){
			 	return $full_path;
			 }else{
			 	if(DashboardHelpers::zip_data(array($full_path),$backup_file_master_zip, $sourcedir_master_sql)){
			 	unlink($full_path);
				return $backup_file_master_zip;	
				}
			 	
			 }
			}else{
				 if($archive == false){
				 	return $backup_files_sql;		 	
				 }else{
				 	if(DashboardHelpers::zip_data($backup_files_sql,$backup_file_tables_zip,$sourcedir_tables_sql)){
			 	     foreach($backup_files_sql as $table_file){
					  unlink($table_file);	
					 }
			 	    
				    return $backup_file_tables_zip;	
				 }
			//	$backup_file_tables_zip
			}
	            
	       }
	    }
	    
	    /**
	    * 
		* Retrieves all data from table and builds INSERT query for all data from table 
		* 
		* @param string $table name of current table to be archived 
		* 
		* @return string $output created INSERT MySQL command to insert data extracted from database 
		*/
	    function db_build_insert_records( $table = '' ){
	    	//TODO backup in chunks, due to different server max_execution_time limitation 
			if(empty($table)){
				return;
			}
			$search   = array( '\x00', '\x0a', '\x0d', '\x1a' ); 
		    $replace  = array( '\0', '\n', '\r', '\Z' );
			$q = $this->prepare( 'SELECT * FROM ' . $table );
			$q->execute();
			$output = '';
			$output.= '--' . PHP_EOL . '-- Dumping data for table '. $table . PHP_EOL . '--' . PHP_EOL;
			$output.= 'INSERT INTO '.$table.' VALUES '. PHP_EOL;
			$rows_output = '';
			while( $row = $q->fetch(PDO::FETCH_ASSOC)){
			  $num_fields = count($row);
			  $j=0;
			  $rows_output.= "(";
			  foreach($row as $field){
			  		  //  $field = addslashes($field);
					$field = str_replace("\n","\\n",$field);
					$field = str_replace("\r","\\r",$field);
					$field = str_replace("'","''",$field);
					//$rows_output.="'" . str_replace( $search, $replace, DashboardHelpers::wp_addslashes( $field ) ) . "'";
					$rows_output.= $this->quote($field);
					//	$rows_output.= "'".$field."'" ; 
							
			  if ($j<($num_fields-1)) { 
				$rows_output.= ','; 
			  }
			  	$j++;
			  }
	           $rows_output.= ")," . PHP_EOL;
			  
			}
			if(!empty($rows_output)){
				$rows_output = stripslashes($rows_output);
			$output.= DashboardHelpers::str_lreplace(',',';',$rows_output);	
			$output.= "\n\n\n" . PHP_EOL;
			return $output;
			}

			
		}
		
	    /**
		* 
		* Builds mysql query for given table from active database
		* 
		* @param string $table name of table from active database 
		* 
		* @return string $output build CREATE query for given table name 
		*/
	    function db_build_create_table( $table = ''){

				$table_keys = $this->db_show_keys( $table );
				$table_columns = $this->db_show_columns( $table );
			    $table_info = $this->db_show_table_info( $table );
			    $charset = explode('_',$table_info['Collation']);
			    $table_info['Charset'] = $charset[0];

			
			$output = '';
			$output.= '--' . PHP_EOL . '-- Table structure for table '. $table . PHP_EOL . '--' . PHP_EOL;
	        $output.= 'CREATE TABLE IF NOT EXISTS ' . $table . ' (' . PHP_EOL;
	        
	        $count = 0;
	        $columns_count = count($table_columns);
	        //$primary_field = '';
			foreach($table_columns as $column){
			if($column['Key'] == 'PRI'){
			 $primary_field = $column;	
			}
		
		    $count++;
			$column_output = "";
			$column_output.= $column['Field'] . " " . $column['Type'];
			if(!empty($column['Collation'])){
						$column_output.= " COLLATE " . $column['Collation'];
			}
			if(!empty($column['Null']) && $column['Null'] == 'NO'){
						$column_output.= " NOT NULL ";
			}
			if($column['Null'] == 'YES' && empty($column['Default']) && strstr($column['Type'],'varchar')){
				$column_output.= " DEFAULT NULL";
			}elseif($column['Key']!='PRI'){
				$column_output.= " DEFAULT '".$column['Default']."'";
			}
			//if()
			if($count < $columns_count){
			 $column_output.= ',' ;	
			}
			$column_output.= PHP_EOL;

	        $output.= $column_output;

			}
	          $output.= ')';        
			  $output.= ' ENGINE=' . $table_info['Engine'];	
			  $output.= ' DEFAULT ';	
			  $output.= ' CHARSET=' . $table_info['Charset'];	
			  $output.= ' COLLATE=' . $table_info['Collation'];	
			  $output.= ';' . PHP_EOL . PHP_EOL;

			  $keys_output = '--' . PHP_EOL . '-- Indexes for table '. $table . PHP_EOL . '--' . PHP_EOL;
		      $keys_output.= 'ALTER TABLE '. $table . PHP_EOL;
		      $count = 0;
		      $keys_count = count($table_keys);
		        //look for unique joined keys 
		          $unique = array();
		          $primary = array();
		          $regular = array();
		         foreach($table_keys as $key=>$table_key){
		         		if($table_key['Key_name'] != $table_key['Column_name'] && $table_key['Key_name'] != 'PRIMARY'){
		         			if(!isset($unique[$table_key['Key_name']])){
								$unique[$table_key['Key_name']] = 0;
							}
		         		$unique[$table_key['Key_name']]+=1;	         			
		         		}
		         		if($table_key['Key_name'] == 'PRIMARY'){
		         			if(!isset($primary[$table_key['Key_name']])){
								$primary[$table_key['Key_name']] = 0;
							}
		         		   $primary[$table_key['Key_name']]+=1;	  	         			
		         			
		         		}
		         	
		         }
		         //echo '<pre>'.print_r($unique,true).'</pre>';
		        foreach($table_keys as $key=>$table_key){
		        	$count++;
		        	$sub_part = ($table_key['Sub_part'])?'('.$table_key['Sub_part'].')':'';
					if($table_key['Key_name'] == 'PRIMARY'){
						if($table_key['Seq_in_index'] == 1){
						$keys_output.= "\t".'ADD PRIMARY KEY (' .  $table_key['Column_name'] . $sub_part;
						}else{
						 $keys_output.= $table_key['Column_name'] . $sub_part;
						}
						if($table_key['Seq_in_index'] == $primary[$table_key['Key_name']]){
						 if($count < $keys_count){
						  $keys_output.= '),'. PHP_EOL; 	
						  }else{
						  $keys_output.= ');'. PHP_EOL; 	
						  }
						}else{
						$keys_output.= ',';	
						}
					}
					if($table_key['Key_name'] == $table_key['Column_name']){
						if($table_key['Non_unique'] == 1){
					 $keys_output.= "\t".'ADD KEY '.$table_key['Key_name'].' ('.$table_key['Column_name']. $sub_part .')' ;	
						}else{
					 $keys_output.= "\t".'ADD UNIQUE KEY '.$table_key['Key_name'].' ('.$table_key['Column_name']. $sub_part .')';	
						}
					}
					//check for combined unique 
					if($table_key['Key_name'] != $table_key['Column_name'] && $table_key['Key_name'] != 'PRIMARY'){
						if($table_key['Seq_in_index'] == 1){
					      if($table_key['Non_unique'] == 1){
					      $keys_output.= "\t".'ADD KEY '.$table_key['Key_name'].' ('.$table_key['Column_name']. $sub_part .'';	
					      	if($table_key['Seq_in_index'] != $unique[$table_key['Key_name']]){
							$keys_output.=',';	
							}
					      	if($table_key['Seq_in_index'] == $unique[$table_key['Key_name']]){
							if($count < $keys_count){
							 $keys_output.=  '),' . PHP_EOL; 	
							 }else{
							  $keys_output.=  ');' . PHP_EOL; 		
							 }
							}
						  }else{
					       $keys_output.= "\t".'ADD UNIQUE KEY '.$table_key['Key_name'].' ('.$table_key['Column_name']. $sub_part .',';	
						  }
						}else{
							if($table_key['Seq_in_index'] == $unique[$table_key['Key_name']]){
							if($count < $keys_count){
							 $keys_output.= $table_key['Column_name']. $sub_part . '),' . PHP_EOL; 	
							 }else{
							  $keys_output.= $table_key['Column_name']. $sub_part . ');' . PHP_EOL; 		
							 }
							}else{
							$keys_output.= $table_key['Column_name']. $sub_part . ','; 		
							}
						}
						
					}
				if( !isset($unique[$table_key['Key_name']]) && $table_key['Key_name'] != 'PRIMARY'){
				if($count < $keys_count){
						 $keys_output.= ',' ;	
					}else{
						$keys_output.= ';' ;	
					}
				
					$keys_output.= PHP_EOL;				
				}

				 }
	        	
			  $output.= $keys_output;
	          if(isset($primary_field) && strstr($primary_field['Extra'],'auto_increment')){
			  $autoincrement_output = '--' . PHP_EOL . '-- AUTO_INCREMENT for table '. $table . PHP_EOL . '--' . PHP_EOL;
		      $autoincrement_output.= 'ALTER TABLE '. $table . PHP_EOL;		  	
		      $autoincrement_output.= "\t".' MODIFY '.$primary_field['Field'].' '.$primary_field['Type'].' NOT NULL AUTO_INCREMENT;' . PHP_EOL;	
		      $output.= $autoincrement_output;	 
		      unset($primary_field); 	
			  }

			  return $output;
		}
		
		function mysqldump(){}
		
		/**
		* Filters table list, removes tables that are not in $allowed_tables list 
		* 
		* @param array $tables all tables from current active database 
		* @param array $allowed_tables tables to be kept while filtering 
		* 
		* @return array filtered table names list 
		*/
		function db_allowed_tables_filter($tables = '', $allowed_tables = ''){
			if(empty($allowed_tables)){
				return $tables;
			}else{
				$new_tables = array();
				
				foreach($tables as $table){
					if(in_array($table,$allowed_tables)){
						$new_tables[] = $table;
					}
				}
				return $new_tables;
			}
		}
	}
?>