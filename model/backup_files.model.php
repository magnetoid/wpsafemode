<?php

	class BackupFilesModel extends DashboardModel {
		
		public function __construct(){
	        parent::__construct();       
    	}
    	
    	/**
		* Scans through local backups storage and returns list of backup files, backups of site files 
		* 
		* @return array list with filepaths to backup files 
		*/
		public function get_file_backups(){
	        $backups = array();
	    	$backups['full'] = array();
	    	$backups['partial'] = array();
		  
			$sourcedir_full =  $this->settings['safemode_dir'].$this->settings['sfstore'].'file_backup/full/';
			$sourcedir_partial =  $this->settings['safemode_dir'].$this->settings['sfstore'].'file_backup/partial/';
			
			foreach(glob($sourcedir_full.'*') as $dir) {
			 	if(!is_dir($dir)){
				$backups['full'][] = $dir;	
				}
			 	
			 }
			
			foreach(glob($sourcedir_partial.'*') as $dir) {
			 	if(!is_dir($dir)){
				$backups['partial'][] = $dir;	
				}
			 	
			 }	
			 
			 return $backups;	 
		}
		
		
	}

?>