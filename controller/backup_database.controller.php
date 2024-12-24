<?php

	class BackupDatabaseController extends DashboardController {
		
		function __construct(){    	
	        parent::__construct();         
	     }
	     
	     /**
		* Renders backup database section 
		* 
		* @return void 
		*/
	    function view_backup_database() {  

	        $this->data['tables'] = $this->dashboard_model->show_tables();
	        $this->data['backups'] = $this->dashboard_model->get_database_backups();        
	        $this->render(  $this->view_url .'db_backup', $this->data );
	    }
	     
	     /**
		* Triggers method in model to backup full WordPress database or partial only selected tables 
		* 
		* @return void 
		*/
	    function backup_database(){
	        
	        $this->data['mysql']['tables'] = $this->dashboard_model->backup_tables();
	     
	        $this->render( $this->view_url .'mysqlbackup', $this->data);
	    }
	    
	    /**
		* Handles submission from backup database section 
		* 
		* @return void
		*/
		function submit_backup_database(){
			
			 set_time_limit(0);
			$backup_tables_list = filter_input(INPUT_POST,'backup_tables_list',FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
			$backup_tables_type = filter_input(INPUT_POST,'backup_tables_type',FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
			$backup_database_type = filter_input(INPUT_POST,'backup_database_type');
			$backup_archive = filter_input(INPUT_POST,'backup_archive');
				if(!empty($backup_archive) && $backup_archive == '1'){
					$archive = true;
				}else{
					$archive = false;
				}		
			if(!empty($backup_database_type) && $backup_database_type == 'full'){

				$this->dashboard_model->backup_tables('' , true, $archive);
			}
			if(!empty($backup_database_type) && $backup_database_type == 'partial' && !empty($backup_tables_type) && in_array('sql',$backup_tables_type)){

				if($tables_backup_result = $this->dashboard_model->backup_tables($backup_tables_list , false, $archive)){
					$backup_tables_list_string = implode(', ',$backup_tables_list);
				  	if(is_array($tables_backup_result)){
						$tables_backup_result = implode('<br/>',$tables_backup_result);
						
						$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following files: <br/>' . $tables_backup_result);
					}else{
						$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following file: <br/>' . $tables_backup_result);
					}
					
				}
			}
			if(!empty($backup_database_type) && $backup_database_type == 'partial' && !empty($backup_tables_type) && in_array('csv',$backup_tables_type)){
				if($csv_backup_result = $this->dashboard_model->backup_tables_csv($backup_tables_list , $archive)){
					$backup_tables_list_string = implode(', ',$backup_tables_list);
				  	if(is_array($csv_backup_result)){
						$csv_backup_result = implode('<br/>',$csv_backup_result);
						
						$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following files: <br/>' . $csv_backup_result);
					}else{
						$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following file: <br/>' . $csv_backup_result);
					}
				}
				
			}
			  $this->redirect('?view='.$this->current_page);
			 
		}
		
		
	    
	    
	    
	}
?>