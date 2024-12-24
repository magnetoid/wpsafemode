<?php

	class BaseInfoController extends DashboardController {
		
		function __construct(){    	
	        parent::__construct();         
	     }
		
		 /**
		* Calls method from model to get WordPress core info 
		* 
		* @return boolean|array false if method from model returns empty results, or list of core info upon success 
		*/
	    public function get_wordpress_core_info(){
			
			$versions = $this->dashboard_model->get_core_info();
			if($versions){
			
				return $versions;
			}
			return false;
		}
		
		/**
		* Calls method from model to get all plugins data 
		* 
		* @return boolean|array false if method from model returns empty results, or list of plugins info upon success
		*/
		public function get_plugins_info(){
			 
			$plugins = $this->dashboard_model->scan_plugins_directory($this->wp_dir);
			if($plugins){
				return $plugins;
			}
			return false;
		}
		
		/**
		* Calls method from model to get all themes data 
		* 
		* @return boolean|array false if method from model returns empty results, or list of themes info upon success
		*/	
		public function get_themes_info(){
			 
			$versions = $this->dashboard_model->get_all_themes($this->wp_dir);
			if($versions){
				return $versions;
			}
			return false;
		}
		
		/**
		* Calls method from model to get php vars information 
		* 
		* @return array php info data with description
		*/
		function get_php_info(){
		   $description = $this->dashboard_model->get_php_ini_vars();
		   return $description;
	    }
		
		/**
		* Calls method from model to get information from $_SERVER 
		* 
		* @return array set of data from $_SERVER along with description 
		*/
		function get_server_info(){
		   $server_option = $this->dashboard_model->get_server_options();
		   return $server_option;
	    }
	    
	    
	}
?>