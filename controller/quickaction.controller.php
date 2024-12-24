<?php

	class QuickAcrionController extends DashboardController {
		 
		   function __construct(){    	
	          parent::__construct();         
	       }
	     
	       /**
		   * Renders quick actions section 
		   * 
		   * @return void 
		   */
		   function view_quick_actions(){
			   	//turn on off maintenance 
			   	$check_maintenance = $this->action_check_maintenance(false);
			   	$quick_actions = array(
				   	'maintenance_enable' => array('action' => 'maintenance_enable' , 'text' => 'Enable Maintenance Mode'),
				   	'maintenance_disable' => array('action' => 'maintenance_disable' , 'text' => 'Disable Maintenance Mode'),
				   	'optimize_tables' => array('action' => 'optimize_tables' , 'text' => 'Optimize Database Tables'),
				   	'delete_revisions' => array('action' => 'delete_revisions' , 'text' => 'Delete Post Revisions'),   	
				   	'delete_spam_comments' => array('action' => 'delete_spam_comments' , 'text' => 'Delete All Spam Comments'),   	
				   	'delete_unapproved_comments' => array('action' => 'delete_unapproved_comments' , 'text' => 'Delete All Unapproved Comments'),   	
				   	'core_scan' => array('action' => 'core_scan' , 'text' => 'Scan WordPress Core'),   	
			   	);
		   	
			   	foreach($quick_actions as $key => $quick_action ){
			   		$skip = false;
					if($key == 'maintenance_enable'){
						if($check_maintenance){
							$skip = true;
						}			
					}
					if($key == 'maintenance_disable'){
						if(!$check_maintenance){
							$skip = true;
						}
					}
					if($skip == false){
						 $this->data['quick_actions']['links'][$key] = array(
						 'link' => DashboardHelpers::build_url('',array('view'=>'quick_actions' , 'action' => $quick_action['action'])), 
						 'text' => $quick_action['text'],
						 );
					}
				}

				 $this->data['quick_actions']['data']['homeurl'] = $this->dashboard_model->get_home_url();
				 $this->data['quick_actions']['data']['siteurl'] = $this->dashboard_model->get_site_url();
				 
			   	$this->render('', $this->data);
		   	
		  	}
	     
		     /**
			 * Checks if maintenances for site is active or not 
			 * 
			 * @param boolean $set_message if set to false it will not leave a message on dashboard 
			 * 
			 * @return boolean depends on if maintenance is active or not 
			 */
			 function action_check_maintenance($set_message = true){
			
				
				$htaccess_content = DashboardHelpers::get_data($this->htaccess_path);
			
				if(!empty($htaccess_content) && $htaccess_content){
					if(strstr($htaccess_content , 'WPSM-MAINTENANCE')){
					  	if($set_message == true){
								$this->set_message('Wordpress site is in maintenance mode. To disable it, go to Quick Actions.');
						}
					  
					
						return true;
					}
				}else{
					
				}
				
				return false;
			}
			
			/**
			* Triggers methods to disable or enable maintenance mode 
			* 
			* @return void 
			*/
			function action_maintenance(){
			
				if(!empty($this->action)){
					if($this->action == 'maintenance_enable'){
					  $this->maintenance_mode_on();
					
					 
					}elseif($this->action == 'maintenance_disable'){
						$this->maintenance_mode_off();
						 $this->set_message('Maintenance mode disabled');	
					}
				}
			}
			
			
			/**
			* Disables maintenance mode 
			* 
			* @return void
			*/
			function maintenance_mode_off(){
				 
				 $htaccess_content = file_get_contents($this->wp_dir . '.htaccess');
				  if(file_exists($this->wp_dir . 'maintenance.html')){
				 	unlink($this->wp_dir . 'maintenance.html');
				 	 if(strstr($htaccess_content , '# BEGIN WPSM-MAINTENANCE')){
				 	 	$htaccess_content = preg_replace('/\#\sBEGIN\sWPSM-MAINTENANCE[\s\S]+?\#\sEND\sWPSM-MAINTENANCE/', '', $htaccess_content);
				 	 	
				 	 }
				 }
				 
				  file_put_contents($this->wp_dir . '.htaccess' , $htaccess_content . $htaccess_maintenance);
			}
			
			/**
			* Enables maintenance mode. Creates directives in .htaccess to redirect to maintenance.html, 
			* and also creates maintenance.html file in main WordPress directory if it doesn't exist
			* //TODO add wpsafemode directory as exception, so user can still have access. 
			* 
			* @return void 
			*/
		    function maintenance_mode_on(){		
				 
				 $htaccess_content = DashboardHelpers::get_data($this->wp_dir . '.htaccess');
				$maintenance_data = 'Website is under maintenance, please check back soon.';
				 	DashboardHelpers::put_data($this->wp_dir . 'maintenance.html' , $maintenance_data , false , false );
				 
				 
				 if(strstr($htaccess_content , '# BEGIN WPSM-MAINTENANCE')){
				 	return; 
				 }
				 $htaccess_maintenance =  '# BEGIN WPSM-MAINTENANCE' . "\n";
				 if(!strstr($htaccess_content, 'RewriteEngine') && !strstr($htaccess_content, 'RewriteBase')){
				  $htaccess_maintenance.= ' RewriteEngine on ' . "\n";		
				 }
			
				
				 $htaccess_maintenance.= 'RewriteCond %{REQUEST_URI} !./' . basename($this->settings['safemode_dir']) . '($|/)' . "\n";	
				 
		         $htaccess_maintenance.= 'RewriteCond %{REQUEST_URI} !./maintenance.html$' . "\n";
		         $htaccess_maintenance.= 'RewriteCond %{REMOTE_ADDR} !^123\.123\.123\.123' . "\n";
		         $htaccess_maintenance.= 'RewriteRule .? ./maintenance.html [R=302,L]' . "\n";
		         $htaccess_maintenance.= '# END WPSM-MAINTENANCE' . "\n";
		                                  
		         
				
				 DashboardHelpers::put_data($this->wp_dir . '.htaccess' , $htaccess_content . $htaccess_maintenance , false, true );
			}
			
			/**
			* Scans recursivelly through WordPress core files and compares with clean copy of WordPress files from wordpress.org. 
			* 
			* @return array 
			*/
			public function action_core_scan(){
				 set_time_limit(0);
				 $time_start = microtime(true); 
				 $versions = $this->dashboard_model->get_core_info();
				 if($versions && isset($versions['wp_version'])){
				 	$wp_version = $versions['wp_version']['version'];
				 	$remote_file = 'https://wordpress.org/wordpress-' . $wp_version . '.zip';
				 	
				 	$local_file = $this->settings['sfstore'].'temp/wordpress-' . $wp_version . '.zip';
				 	$remote_file_download = true;
				 	if(file_exists($local_file)){
				 		$md5_remote = 'https://wordpress.org/wordpress-' . $wp_version . '.zip.md5';
						$md5_remote = file_get_contents($md5_remote);
						$md5_local = md5_file($local_file);
						if($md5_remote === $md5_local){
						//	echo 'files identical';
							$remote_file_download = false;
						}
					}			
				 	//https://wordpress.org/wordpress-4.3.1.zip.md5
				 	if($remote_file_download == true){
					DashboardHelpers::remote_download($remote_file , $local_file);	
					}
				 	
				 	DashboardHelpers::unzip_data($local_file);
				 	$original_wp_dir = $this->settings['sfstore'].'temp/wordpress/';
				 	$original_wp_files = DashboardHelpers::scan_directory_recursive($original_wp_dir);
				 	$site_wp_files = DashboardHelpers::scan_directory_recursive($this->wp_dir);
				 	//echo '<pre>'.print_r($site_wp_files,true).'</pre>';
				 	$files_compared = 0;
				 	$files_missing_core = array();
				 	$files_different = array();
				 	foreach($original_wp_files as $file_path => $file){
						if(isset($site_wp_files[$file_path])){
							$files_compared ++;
							if($site_wp_files[$file_path]['md5'] != $file['md5']){
								if(!strstr($file_path , 'wp-content') && !strstr($file_path , 'wp-content/themes') && !strstr($file_path , 'wp-content/plugins')){
								$this->set_message('files are different: ' . $file_path );
								$files_different[] = $file_path;
								}
							}
						}else{
							if(!strstr($file_path , 'wp-content') && !strstr($file_path , 'wp-content/themes') && !strstr($file_path , 'wp-content/plugins')){
								$files_missing_core[] = $file_path;
								
							}
							
						}
					}
					
					$this->set_message('total files compared ' . $files_compared);
					$this->set_message('skipped folders and files from wp-content');
					if(count($files_missing_core) > 0){
						$this->set_message('files missing: <pre>' . print_r($files_missing_core,true).'</pre>');
					}
					//if(count($files_different) > 0){
						$this->set_message('total different files found: ' . count($files_different));
					//}
					
				 	
				 }
				 
				 $time_end = microtime(true);
				 		//dividing with 60 will give the execution time in minutes other wise seconds
				$execution_time = ($time_end - $time_start);

				//execution time of the script
				$this->set_message('<b>Total Scan Time:</b> '.$execution_time.' Seconds' );
				$this->redirect();
			
			}
			
			
			
			/**
			* Calls method from model to optimize tables from active database 
			* 
			* @return void 
			*/
			function action_optimize_tables(){		
						$this->dashboard_model->optimize_tables();
						$this->set_message('All database tables have been optimized');
					    $this->redirect();	
			}
			
			/**
			* Calls method from model to delete all post revisions
			* 
			* @return void 
			*/
			function action_delete_revisions(){		
				  		$this->dashboard_model->delete_revisions();
				  		$this->set_message('All post revisions have been removed');
					    $this->redirect(); 	  	
			}
			
			/**
			* Calls method from model to delete all comments from db marked as spam 
			* 
			* @return void 
			*/
		    function action_delete_spam_comments(){
					    $this->dashboard_model->delete_spam_comments();
				  		$this->set_message('All spam comments have been removed');
					    $this->redirect(); 	  	
			}
			
			/**
			* Calls method from model to delete all comments from db marked as unapproved 
			* 
			* @return
			*/
			function action_delete_unapproved_comments(){
				       	$this->dashboard_model->delete_unapproved_comments();
				  		$this->set_message('All unapproved comments have been removed');
					    $this->redirect(); 	  	
			}
			
			/**
			* Creates quick backup of all files and calls method in model to create full active database backup 
			* 
			* @return void 
			*/
			function action_backup(){
				$manual_backup = filter_input(INPUT_GET , 'backup');
				if(!empty($manual_backup) && $manual_backup == 'quick'){
					   $prefix.= 'quickbackup_';
				      $date = date('d-m-Y--H-i-s');
					    $this->wp_dir = $this->settings['wp_dir'];	       
				        $file = $this->settings['safemode_dir'].$this->settings['sfstore'].'/file_backup/full/'.$prefix.$date.'.zip';
				       
				        if(DashboardHelpers::zip_all_data($this->wp_dir, $file)){
							$this->set_message('Quick backup of all site files successfully archived in ' . $file );
					    }
					    
					    $this->dashboard_model->backup_tables('' , true, true , $prefix . $date );
						$this->set_message("Full database quick backup is successfully stored");
					
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