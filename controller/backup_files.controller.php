<?php
	class BackupFilesController extends DashboardController {
		
		function __construct(){    	
	        parent::__construct();         
	     }
		
		/**
		* Renders backup files section 
		* 
		* @return void 
		*/
	    function view_backup_files(){
	    	 $this->data['backups'] = $this->dashboard_model->get_file_backups();
		     $this->render(  $this->view_url .'files_backup', $this->data );
		}
		
		/**
		* Handles submission from backup files section 
		* 
		* @return void 
		*/
	    function submit_backup_files(){ 
	            $this->backup_all_files(); 
	    }
	    

	    /**
		* Creates recursivelly  backup of all files stored in main WordPress directory
		* 
		* @return void 
		*/
	    function backup_all_files() {
	    	 set_time_limit(0);
	        $view_url = $this->dashboard_model->settings['view_url'];
	       
	        $wp_base_name = basename($this->wp_dir);
	        $sfstore = $this->settings['sfstore'];
	        $date = date('d-m-Y--H-i-s');
	        $file = $sfstore.'file_backup/full/filesbackup_'.$date.'.zip';

	        if(DashboardHelpers::zip_all_data($this->wp_dir, $file)){
				$this->set_message('All site files successfully archived in ' . $file );
			}
			  $this->redirect('?view='.$this->current_page);

	    }
	    
	    
	}
?>