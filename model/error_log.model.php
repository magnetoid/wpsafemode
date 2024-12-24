<?php

	class ErrorLogModel extends DashboardModel {
		
		public function __construct(){
	        parent::__construct();       
    	}
    	
    	/**
		* Retrieves a part of php error_log file 
		* 
		* @param integer $page number of current page
		* @param integer $lines number of lines/records shown per page
		* @param string $search search string to look up in error_log file
		* 
		* @return array formatted error_log lines with headers 
		*/
	    public function get_error_log( $page = 1 , $lines = 20 , $search = ''){
	       $error_file = ini_get('error_log');
	 
	       if( $page == 1 ){
		   	$seek = 0;
		   }else{
		   	 $seek = (( $page * $lines ) - ($lines + 1));
		   }
		   $headers = array('Date' , 'Type' , 'Message'); 
	       $rows = array();
	       if(file_exists($error_file)){
	       	 $number_lines = DashboardHelpers::number_lines_file($error_file , $search ); //TODO set offset page show only 20 page links 
	          	$file = new SplFileObject($error_file);
				$file->seek( $seek );    
				$i = 0;
				while($line = $file->fgets()){
					if($i < $lines ){
					 if((!empty($search) && stristr( $line , $search )) || empty($search)){			 	
					 
					   $row = array();
					   $date_array = explode("]", $line);
	                   $date = str_replace("[", '', $date_array);
	                   $row[] =  $date[0];
	                   $type = explode(":  ", $date[1]);
	                   $row[] = $type[0];
	                   $message = $type[1];
	                   $row[] = $message;
	                   
	                   $rows[] = $row; 
					$i++;	
					}
					}else{
						break;
					}
					
				}                     
	                $results = array('headers' => $headers , 'rows' => $rows , 'number_lines' => $number_lines);
	                return $results; 
	           }
	      
	       return "Error log is empty";
	    }
	    
	    
	}

?>