<?php
	class AutobackupController extends DashboardController {
		
		function __construct(){    	
			parent::__construct();         
		}
		
		/**
	   * Renders autobackup settings section 
	   * 
	   * @return void 
	   */
	   function view_autobackup(){
	   		$autobackup_settings_file = $this->settings['sfstore'].'autobackup_settings.json';
			if(file_exists($autobackup_settings_file)){			
				$autobackup_settings = DashboardHelpers::get_data($autobackup_settings_file , true);
				$this->data['autobackup_settings'] = $autobackup_settings;
			}
			$default_settings = array(
				'enable_autobackup' => '',
				'full_backup' => '',
		        'files_backup' => '',
		        'htaccess_backup' => '',
		        'wp_config_backup' => '',
		        'prefix' => '',
		        'interval' => '5 hours',		         
			);		
			foreach($autobackup_settings as $key=>$value){
				if($key!='interval' && $key!='prefix'){
					if(isset($default_settings[$key])){
						if($value == 1){
							$default_settings[$key] = 'checked="checked"';
						}
					}
				}else{
					if(isset($default_settings[$key])){
						$default_settings[$key] = $value;
					}
				}
			}
			$this->data['default_settings'] = $default_settings;
	   		$this->render( $this->view_url.'autobackup', $this->data );
	   }	
	   
	   	/**
		* Triggers automatic backup of all site data, depending on settings in autobackup section 
		* 
		* @return void 
		*/
		function action_autobackup(){
			  set_time_limit(0);
			  $default_interval = '6 hours';
		
			
			  $autobackup_settings_file = $this->settings['sfstore'].'autobackup_settings.json';
			  if(file_exists($autobackup_settings_file)){
			  	$autobackup_settings = DashboardHelpers::get_data($autobackup_settings_file , true);
			  
			  
			  if($autobackup_settings == false || !is_array($autobackup_settings)){		  	
			 	return;				
			  }
			  if(!isset($autobackup_settings['last_autobackup']))
			  return;
			  
			  if(!isset($autobackup_settings['enable_autobackup']) || $autobackup_settings['enable_autobackup']!=1){
			  
			  	return;
			  }
			  
			  
			 
			  if(!isset($autobackup_settings['interval'])){
			   $interval = $default_interval;
			  }else{
			   $interval = $autobackup_settings['interval'];
			  }

			  $current_time = strtotime('now - '.$interval);
	          $last_cron = $autobackup_settings['last_autobackup'];
			 
			  if($current_time < $last_cron){	
			   return; 
			  }
			  
			  
			   //do the magic 
			    
	       	if(isset($autobackup_settings['prefix'])){
				$prefix = $autobackup_settings['prefix'];
			}else{
				$prefix = '';
			}
			$prefix.= 'autobackup_';
			 $date = date('d-m-Y--H-i-s');
		       	if(isset($autobackup_settings['htaccess_backup']) && $autobackup_settings['htaccess_backup']==1){
					$file_to_backup = $this->wp_dir.'.htaccess';
		   			$sourcedir =  $this->settings['safemode_dir'].$this->settings['sfstore'].'/htaccess_backup/'.$prefix .$date. '_htaccess';
					$source = file_get_contents($file_to_backup);
					file_put_contents($sourcedir, $source);
					$this->set_message("Htaccess backup is successfully stored at " . $sourcedir);
				}
				//wp config file backup 
				if(isset($autobackup_settings['wp_config_backup']) && $autobackup_settings['wp_config_backup']==1){
					$file_to_backup = $this->wp_dir.'wp-config.php';
		   			$sourcedir =  $this->settings['safemode_dir'].$this->settings['sfstore'].'/wp_config_backup/'.$prefix .$date. '_wp-config.php';
		   			$source = file_get_contents($file_to_backup);
					file_put_contents($sourcedir, $source);
					$this->set_message("WP Config file backup is successfully stored at " . $sourcedir);
				}
				 //files backup 
				 if(isset($autobackup_settings['files_backup']) && $autobackup_settings['files_backup']==1){
			        $this->wp_dir = $this->settings['wp_dir'];	       
			        $file = $this->settings['safemode_dir'].$this->settings['sfstore'].'/file_backup/full/'.$prefix.$date.'.zip';
			       
			        if(DashboardHelpers::zip_all_data($this->wp_dir, $file)){
						$this->set_message('All site files successfully archived in ' . $file );
				    }
				 }

					//database backup 
				 if(isset($autobackup_settings['full_backup']) && $autobackup_settings['full_backup']==1){	
				 				
						$this->dashboard_model->backup_tables('' , true, true , $prefix . $date );
						$this->set_message("Full database backup is successfully stored");
				 }
			    $autobackup_settings['last_autobackup'] = strtotime('now');
			    DashboardHelpers::put_data($autobackup_settings_file , $autobackup_settings , true );
			    $this->set_message('Autobackup done');
			    $this->redirect(); 
			}
			
		}
		
		/**
		* Handless submission from autobackup settings section 
		* 
		* @return void 
		*/
		function submit_autobackup(){
			 $default_interval = '6 hours';
			$submit_autobackup = filter_input(INPUT_POST, 'submit_autobackup');
		
			//$autobackup_settings = 
		    if(!isset($submit_autobackup) || empty($submit_autobackup) ){
				return;
			}
		    $autobackup_settings_file = $this->settings['sfstore'].'autobackup_settings.json';
		 
			$autobackup_settings = DashboardHelpers::get_data($autobackup_settings_file , true);
			if($autobackup_settings == false){
				$autobackup_settings = array();
			}
		   
		  
			$autobackup_inputs = array();
			$autobackup_inputs['enable_autobackup'] = 'enable_autobackup';
			$autobackup_inputs['full_backup'] = 'full_backup';
			$autobackup_inputs['files_backup'] = 'files_backup';
			$autobackup_inputs['htaccess_backup'] = 'htaccess_backup';
			$autobackup_inputs['wp_config_backup'] = 'wp_config_backup';
			$autobackup_inputs['prefix'] = 'prefix';
			$autobackup_inputs['interval'] = 'interval';
			$reset_interval = filter_input(INPUT_POST , 'reset_interval');
	        
			// $autobackup_settings_new = array();
			foreach($autobackup_inputs as $autobackup_key => $autobackup_input){
				$input = filter_input(INPUT_POST , $autobackup_key);
				if(!empty($input)){
				   if($autobackup_key!='interval' && $autobackup_key!='prefix'){
				   //	$autobackup_settings_new = ''
				   $autobackup_settings[$autobackup_key] = 1;
				   }else{
				   	if($autobackup_key=='interval' || $autobackup_key=='prefix'){
						 $autobackup_settings[$autobackup_key]  = $input;
					}
					
				   }
				}else{
					if(isset($autobackup_settings[$autobackup_key])){
						unset($autobackup_settings[$autobackup_key]);
					}
				}
			}
			if(!isset($autobackup_settings['interval'])){
				$autobackup_settings['interval'] = $default_interval;
			}
			if(!isset($autobackup_settings['last_autobackup'])){		
				$autobackup_settings['last_autobackup'] = strtotime('now - ' . $autobackup_settings['interval']);
			}
			if( !empty($reset_interval)){
				$autobackup_settings['last_autobackup'] = strtotime('now - ' . $autobackup_settings['interval']);
			}
		

			

	        DashboardHelpers::put_data($autobackup_settings_file , $autobackup_settings , true );
	        $this->set_message('Autobackup settings saved');
	        $this->redirect(); 
	        return;
	       
	      }
	}
?>