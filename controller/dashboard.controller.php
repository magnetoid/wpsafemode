<?php



/**
* 
*/
class DashboardController extends MainController {
    
    protected $current_page;
    protected $dirs;
    
    /**
	* 
	* 
	* @return
	*/
    function __construct(){
    	
        parent::__construct();   
      
       
        $this->dashboard_model = new DashboardModel;        
       
        $this->init_data();    
        $this->actions();      
        $this->submit();
        $this->view();
      

    }
    /**
	* Sets initial data in $data variable 
	* 
	* @return void 
	*/
    function init_data(){
		$this->data['result'] = array();
        $this->data['script_url'] = $this->get_script_url();       
        $this->data['current_page'] = $this->current_page;        
        $this->view_url = $this->settings['view_url'];     
	}
   
   /**
   * Triggers action methods if condition is fulfilled. Action method can be triggered if action exists 
   * in query string, or if autoload is set to true 
   * 
   * @return void 
   */
   function actions(){ 	
       $actions = array(
       'login' => array('autoload' => true),
       'logout' => array('action'=>'logout'), 
       'maintenance' => array('autoload' => true),
       'backup' => array('autoload' => true), 
       'optimize_tables'  => array('action'=>'optimize_tables'), 
       'core_scan'  => array('action'=>'core_scan'), 
       'delete_revisions' => array('action'=>'delete_revisions'), 
       'delete_spam_comments' => array('action'=>'delete_spam_comments'), 
       'delete_unapproved_comments' => array('action'=>'delete_unapproved_comments'), 
       'check_maintenance' => array('autoload' => true), 
       'autobackup' => array('autoload'=> true), 
       'download' => array('autoload' => true), 
       'message' => array('autoload' => true),      
       );     	   
     
       
       foreach($actions as $key => $action){  	
       $skip = false;
       $callback = 	array($this, 'action_' . $key);
       if(isset($action['action']) && (empty($this->action) || $this->action != $action['action'])){
	   	$skip = true;
	   }
		   if($skip == false){
					if(is_callable($callback)){	
					if($key!='message' && $key!='logout' && $key!='login' && isset($this->settings['demo']) && $this->settings['demo'] == true){
					if(!isset($action['autoload']) && $key!='maintenance')
							$this->set_message('quick actions disabled in demo mode');			
					}else{
					call_user_func($callback);		
					}							
								
					}	   	
		   }
		
		}		
		return;  
      


   	   
   }
    

    /**
	* Calls get_message() method. 
	* 
	* @return void 
	*/
    function action_message(){
		$this->get_message();
	}

    /**
	* 
	* handles the submit upon post requests 
	* 
	* @return
	*/
    function submit(){


        $submits = array(
        'submit_plugins'=> array('callback'=> array($this , 'submit_plugins')),
        'submit_themes'=> array('callback' =>  array($this , 'submit_themes')),
        'submit_backup_database'=>array('callback' => array($this ,  'submit_backup_database')),
        'submit_backup_files'=>array('callback'=> array($this , 'submit_backup_files')),
        'submit_search_replace'=>array('callback'=> array($this , 'submit_search_replace')),
        'saveconfig'=>array('callback'=> array($this , 'submit_wpconfig')),
        'saveconfig_advanced'=>array( 'callback'=> array($this , 'submit_wpconfig_advanced')),
        'save_htaccess'=>array('callback'=> array($this , 'submit_htaccess')),
        'save_revert'=>array('callback'=> array($this , 'submit_htaccess_to_revert')),
        'submit_autobackup'=>array('callback'=> array($this , 'submit_autobackup')),
        'save_htaccess_backup'=>array('callback'=> array($this , 'save_htaccess_backup')),        
        'submit_site_url'=>array('callback'=> array($this , 'submit_site_url')),        
        'save_robots'=>array('callback'=> array($this , 'submit_robots')),
        'create_robots_file' =>array('callback'=>array($this, 'create_robots_file')),
        'submit_global_settings' =>array('callback'=>array($this, 'submit_global_settings')),
        'submit_login' =>array('callback'=>array($this, 'submit_login')),
        );
        
        
            
        foreach($submits as $submit_key=>$submit){
          	$submit_input = filter_input(INPUT_POST,$submit_key);
			if(!empty($submit_input)){
				if(is_callable($submit['callback'])){
				if($submit_key!='submit_login' && isset($this->settings['demo']) && $this->settings['demo'] == true){
					$this->set_message('Saving settings and submission is disabled in demo mode');
				}
				else{
				call_user_func($submit['callback']);		
				}
				
				 return;
				}
				
			}
		}
		
		return;
    }

    /**
	* renders main pages and triggers callbacks for main pages 
	* 
	* @return void 
	*/ 
    function view(){    	      

         
        #--validate--#
        $pages = array();
        $pages['login'] = array(
        'slug'=>'login',
        'callback'=> array( $this,'view_login'),         
        );
        $pages['plugins'] = array(
        'slug'=>'plugins',
        'callback'=> array( $this,'view_plugins'),         
        );
        $pages['themes'] = array(
        'slug'=>'themes', 
        'callback'=> array( $this, 'view_themes'),        
        );
        $pages['wpconfig'] = array(
        'slug'=>'wpconfig',
        'callback'=> array( $this , 'view_wpconfig' ),
        );
        $pages['wpconfig_advanced'] = array(
        'slug'=>'wpconfig_advanced',
        'callback'=> array($this , 'view_wpconfig_advanced'),
        );
        $pages['backup_database'] = array(
        'slug'=>'backup_database',
        'callback'=> array($this , 'view_backup_database'),
        );
        $pages['backup_files'] = array(
        'slug'=>'backup_files',
        'callback'=> array($this , 'view_backup_files'),
        );
        $pages['htaccess'] = array(
        'slug'=>'htaccess',
        'callback'=> array($this , 'view_htaccess'),
        );
         $pages['robots'] = array(
        'slug'=>'robots',
        'callback'=> array($this , 'view_robots'),
        );
        $pages['error_log'] = array(
        'slug'=>'error_log',
        'callback'=> array($this , 'view_error_log'),
        );
        
        $pages['core_scan'] = array(
        'slug'=>'core_scan',
        'callback'=> array($this , 'core_scan'),
        );       
        
        $pages['global_settings'] = array(
        'slug'=>'global_settings',
        'callback'=> array($this , 'global_settings'),
        );
        
       
      
        $this->data['menu_items'] = $this->dashboard_model->get_main_menu_items();
        $this->data['htaccess_items'] = $this->dashboard_model->get_htaccess_options();
        $this->data['robots_items'] = $this->dashboard_model->get_robots_options();
        //call header template 
        $this->header();
  
  

        $page_found = false;
          foreach($pages as $page_key=>$page){
			if($this->current_page == $page['slug']){
				if(is_callable($page['callback'])){
				call_user_func($page['callback']);	
				 $page_found  = true;
				}
				
			}
		}
		if($page_found == false){
			$wild_page = array($this, 'view_' . str_replace('-', '_', $this->current_page));
			if(is_callable($wild_page)){
				call_user_func($wild_page);	
			     $page_found  = true;
				}else{
					$this->redirect('?view=info'); 					
				}
		
		}
				
	
    	//call footer template 
       $this->footer();
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
   * Retrieves info for wordpress core, plugins, themes, php, server and calls info template 
   * 
   * @return
   */
   function view_info(){
   	
   	if(!isset($this->data['info'])){
		$this->data['info'] = array();
	}
   	 $this->data['info']['core_info'] = $this->get_wordpress_core_info(); //TODO rename to core_info 
   	 $this->data['info']['plugins_info'] = $this->get_plugins_info(); //TODO rename to plugins_info 
   	 $this->data['info']['themes_versions'] =  $this->get_themes_info(); //TODO renam to themes_info 
   	 $this->data['info']['php_info'] =  $this->get_php_info();
   	 $this->data['info']['server'] =  $this->get_server_info();
   	 //some php + mysql info 
   	 //count files info 
   	  $this->render( $this->view_url .'info', $this->data );
   	
   }
   /**
   * Renders autobackup settings section 
   * 
   * @return void 
   */
   function view_autobackup(){
   		$autobackup_settings_file = $this->settings['sfstore'].'autobackup_settings.json';
   		$default_settings = array(
			'enable_autobackup' => '',
			'full_backup' => '',
	        'files_backup' => '',
	        'htaccess_backup' => '',
	        'wp_config_backup' => '',
	        'prefix' => '',
	        'interval' => '5 hours',		         
		);	
		if(file_exists($autobackup_settings_file)){			
			$autobackup_settings = DashboardHelpers::get_data($autobackup_settings_file , true);
			$this->data['autobackup_settings'] = $autobackup_settings;
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
		}
		else{
			foreach($default_settings as $key=>$value){
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
		}
			
		
		$this->data['default_settings'] = $default_settings;
   		$this->render( $this->view_url.'autobackup', $this->data );
   		
   }


    /**
	* Renders plugins section. 
	* 
	* @return void 
	*/
    function view_plugins(){
       
        $sfstore = $this->settings['sfstore'];
        $this->data['plugins']['active_plugins'] = $this->dashboard_model->get_active_plugins();
        $this->data['plugins']['all_plugins'] =    $this->dashboard_model->scan_plugins_directory($this->wp_dir);

        if (!file_exists($sfstore.'active_plugins.txt')) {
           $this->dashboard_model->backup_active_plugins_list();
        }

        $this->data['plugins']['active_plugins'] = unserialize($this->data['plugins']['active_plugins']['option_value']);
        $this->render( $this->view_url.'plugins', $this->data );
    }
    
    /**
	* Handles submission from plugins section. Filters submitted data and calls methods from controller and model 
	* 
	* @return void 
	*/
    function submit_plugins(){
        $rebuild_plugins_backup = filter_input(INPUT_POST,'rebuild_plugins_backup');
        $submit_plugins_action = filter_input(INPUT_POST,'submit_plugins_action');

        if(!empty($rebuild_plugins_backup) && $rebuild_plugins_backup == 'rebuild'){
			 $this->dashboard_model->backup_active_plugins_list();
			 $this->set_message('Plugins backup file has been rebuild');
		}
       if(!empty($submit_plugins_action) && $submit_plugins_action!= 'revert'){
       
            $this->enable_selected_plugins();

        }
       

        if(!empty($submit_plugins_action) &&  $submit_plugins_action == 'revert'){
            $this->revert_plugins();
        }
        
        
    }
    
    /**
	* To set all present plugins in WordPress plugins directory as active and redirects to current page 
	* 
	* @return void
	*/
    function enable_all_plugins(){
    $this->redirect('?view='.$this->current_page);
    }

    /**
	* Deactivates all active plugins in current WordPress instance  and redirects to current page 
	* 
	* @return void 
	*/
    function disable_all_plugins(){
        $this->dashboard_model->disable_all_plugins();
        $this->redirect('?view='.$this->current_page);
    }

    /**
	* Activates all plugins selected from plugins section. It serializes array and calls method from model to save active plugins. 
	* 
	* @return void 
	*/
    function enable_selected_plugins(){
        $selected_plugins = filter_input(INPUT_POST,'plugins',FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $selected_plugins = serialize($selected_plugins);
        $this->dashboard_model->save_plugins($selected_plugins);
        $this->set_message('Selected plugins have been enabled');
        $this->redirect('?view='.$this->current_page);
        
    }

    /**
	* 
	* Reverts active plugins to initial state. Calls method in model to save initial active plugins 
	* 
	* @return void 
	*/
    function revert_plugins(){
     
        
        $revert = $this->dashboard_model->save_revert_plugins();
       
        if($revert){
        	 $this->set_message('Plugins reverted to initial state');
			 $this->redirect('?view='.$this->current_page);
		}
  
      
     }


    /**
	* 
	* Renders themes section 
	* 
	* @return void 
	*/
    function view_themes(){

        $sfstore = $this->dashboard_model->settings['sfstore'];
        $this->data['themes']['active_theme'] = $this->dashboard_model->get_active_themes();
        $this->data['themes']['all_themes'] =  $this->dashboard_model->get_all_themes($this->wp_dir);

        $this->render( $this->view_url.'themes', $this->data );
    }

    /**
	* Handles submission from themes section 
	* 
	* @return void 
	*/
    function submit_themes(){
       $set_active_theme = filter_input(INPUT_POST, 'active_theme');
       $all_themes =  $this->dashboard_model->get_all_themes($this->wp_dir);
       if(!empty($set_active_theme) && $set_active_theme == 'downloadsafe'){
       	
            $this->dashboard_model->safemode_download_theme();
            
            $theme = array(
                'template'=> 'twentyfifteen',
                'stylesheet'=> 'twentyfifteen',
                'current_theme'=> 'twentyfifteen',
            );
            $this->dashboard_model->set_active_theme($theme);
               $this->redirect('?view='.$this->current_page);
            return;
       }
       foreach($all_themes as $key=>$value){
           if($set_active_theme == $key){
             
               if(isset($value['theme_parent'])){
                   $theme = array(
                       'template'=> $value['theme_parent'],
                       'stylesheet'=> $key,
                       'current_theme'=> $value['theme_name'],
                   );
               }else{
                   $theme = array(
                    'template'=> $key,
                    'stylesheet'=> $key,
                    'current_theme'=> $value['theme_name'],
                   );
               }
               $this->dashboard_model->set_active_theme($theme);
               $this->redirect('?view='.$this->current_page);
               return;
           }
       }
    }


    /**
	* Renders basic wp configuration section 
	* 
	* @return void 
 	*/
    function view_wpconfig(){
         $this->data['wpconfig']['config'] = $this->dashboard_model->get_wp_config();
        $this->data['wpconfig']['array'] = $this->dashboard_model->get_wp_config_array();
        $this->render( $this->view_url .'wpconfig', $this->data );
    }
    
    /**
	* Renders php error_log section 
	* 
	* @return void 
	*/
    function view_error_log(){
       
       $page = filter_input(INPUT_GET , 'page');
       $lines = filter_input(INPUT_GET , 'lines');
       $search = filter_input(INPUT_GET , 'search'); //sanitize
      
       if(empty($lines)){
	   	$lines = 20;
	   }
	   if(empty($page)){
	   	$page = 1;
	   }
       $this->data['results'] = $this->dashboard_model->get_error_log( $page , $lines , $search);
       
       if(!is_array($this->data['results'])){
            
      
	   	$this->set_message($this->data['results']);
	   }
	  $this->data['results']['page'] = $page;
      $this->data['results']['lines'] = $lines; 
	  $this->data['results']['search'] = $search; 
	  
        $this->render( $this->view_url .'error_log', $this->data );
    }
    
    /**
	* Renders advanced wp configuration section 
	* 
	* @return void 
	*/
    function view_wpconfig_advanced(){
	    $this->data['wpconfig_options'] = $this->dashboard_model->get_wp_config_options();	    
	    $this->render( $this->view_url .'wpconfig_advanced', $this->data );   
    }
    
    /**
	* Updates option (constant or variable) from wp-config.php with new assigned value set from wp configuration section 
	* 
	* @param array $wpconfig_option set of wp config option data 
	* @param array $wpconfig_array list from wp-config.php data split into array 
	* 
	* @return array $wp_config_array updated with new value for given wp config option 
	*/
    function update_wp_config_value($wpconfig_option , $wpconfig_array){
    
    		if($wpconfig_option['value_type'] == 'boolean'){
    			if(!empty($wpconfig_option['new_value'])){
					$wpconfig_option['new_value'] = 'true';
				}else{
					$wpconfig_option['new_value'] = 'false';
				}   
				if($wpconfig_option['value'] == '1' || $wpconfig_option['value'] == 'on'){
					$wpconfig_option['value'] = 'true';
				}else{
					$wpconfig_option['value'] = 'false';
				}		
    		}
    	
        if($wpconfig_option['new_value'] == $wpconfig_option['value']){
    	
    
    	return $wpconfig_array;			
		}

    	
    	$found_line = false;
    	if($wpconfig_option['type'] == 'constant'){
    		if($wpconfig_option['value_type'] == 'boolean' ){
				$new_line = "define('".$wpconfig_option['name']."', ".$wpconfig_option['new_value'].");";
			}else{
				
				$new_line = "define('".$wpconfig_option['name']."', '".$wpconfig_option['new_value']."');";
				
			}
			
		}
		if($wpconfig_option['type'] == 'variable'){
			$new_line = "$".$wpconfig_option['name']." = '".$wpconfig_option['new_value']."';";
		}
		$new_line.= "\n\n";
    	$found_abspath = false;
	   
		foreach($wpconfig_array as $key => $wpconfig_line){
			
			if( strstr($wpconfig_line, 'ABSPATH' )){
				$found_abspath = true;
			}
			if(empty($wpconfig_line) && $found_abspath == false){
				$editing_end = $key;
			}
		    if(strstr($wpconfig_line , "Happy blogging")){
				
				$editing_end = $key;
			}
		
		
			if( strstr( $wpconfig_line , $wpconfig_option['name'] ) && (($wpconfig_option['type'] == 'constant' && strstr( $wpconfig_line , 'define(' )) || ($wpconfig_option['type'] == 'variable' && strstr( $wpconfig_line , '$'. $wpconfig_option['name'] ) ))  && (strstr($wpconfig_line , $wpconfig_option['value']) || empty($wpconfig_option['value'])) &&  strstr( $this->wpconfig_clean_comments_source , $wpconfig_line ) ){
				
				
				$wpconfig_array[$key] = $new_line;
					
				return $wpconfig_array;
			}
		}
		
		$insert = array($new_line);
	    array_splice( $wpconfig_array, ($editing_end - 1), 0, $insert );
		return $wpconfig_array;
		
		
	}
    
    /**
	* Handles submission from wp config advanced section. Calls method from model to save wp-config.php with updated data 
	* 
	* @return void 
	*/
    function submit_wpconfig_advanced(){

          $wpconfig_options = $this->dashboard_model->get_wp_config_options();
          $wpconfig_array = $this->dashboard_model->get_wp_config_array();
       

          $this->wpconfig_clean_comments_source = $this->dashboard_model->get_wp_config( true );
          foreach( $wpconfig_options as $key => $wpconfig_option ){
          	
		   $wpconfig_post_value = '';

		   $wpconfig_post_value = filter_input(INPUT_POST, $wpconfig_option['input_key'] );

		   
		   	$wpconfig_option['new_value'] = trim($wpconfig_post_value);	
		   	if($wpconfig_option['new_value'] == 'on'){
				$wpconfig_option['new_value'] = 1;
			}	
		   	if($wpconfig_option['new_value']!=$wpconfig_option['value']){
			 	$wpconfig_array = $this->update_wp_config_value($wpconfig_option , $wpconfig_array);
			 		
			} 
			if(isset($wpconfig_array[0])){
				$wpconfig_array_temp = $wpconfig_array;
			}
		  }            	
             $wpconfig_source = '';

            foreach($wpconfig_array_temp as $wpconfig_option_new ){
				$wpconfig_source.= $wpconfig_option_new;
			
			}

       
            $this->dashboard_model->save_wpconfig( $wpconfig_source );

     
          $this->redirect();
	}
    
    /**
	* Handles submission from basic wp configuration section 
	* 
	* @return void 
	* 
	*/
    function submit_wpconfig(){
        $saveconfig = filter_input(INPUT_POST,'saveconfig');
        $wpdebug = filter_input(INPUT_POST,'wpdebug');
        $automatic_updater = filter_input(INPUT_POST,'automatic_updater');
        $automatic_updater_core = filter_input(INPUT_POST,'automatic_updater_core');

        if(!empty($saveconfig)){
            $fileStr = $this->dashboard_model->get_wp_config();
            $ini_array = $this->dashboard_model->get_wp_config_array();
            //wp debug on/off
            $found_line = false;
            foreach($ini_array as $key=>$value){
                if(!empty($value)){
                   
                    if(stristr($value,"WP_DEBUG")){
                      
                        if(!empty($wpdebug) && $wpdebug == 'on'){
                            $new_value = str_replace("false","true",$value);
                        }else{
                            $new_value = str_replace("true","false",$value);
                        }

                        $fileStr  = str_replace($value,$new_value,$fileStr);
                        $found_line = true;
                    }
                }
            }
            if($found_line == false){
                if(!empty($wpdebug) && $wpdebug == 'on'){
                    $add_line = "\n\n"."define('WP_DEBUG', true);\n\n";
                    $fileStr  = str_replace("/* That's all, stop editing! Happy blogging. */",$add_line."/* That's all, stop editing! Happy blogging. */",$fileStr);
                   
                }
            }
            //automatic updater off/on
            $found_line = false;
            foreach($ini_array as $key=>$value){
                if(!empty($value)){
                  
                    if(stristr($value,"AUTOMATIC_UPDATER_DISABLED")){
                       
                        $new_value = $value;
                        if(!empty($automatic_updater) || $automatic_updater == 'on'){
                            $new_value = str_replace("false","true",$value);

                        }else{
                            $new_value = str_replace("true","false",$value);
                        }
                        $fileStr  = str_replace($value,$new_value,$fileStr);
                        $found_line = true;
                    }
                }
            }
            if($found_line == false){
                $add_line = '';
                if(!empty($automatic_updater) || $automatic_updater == 'on'){
                    $add_line = "\n\n"."define('AUTOMATIC_UPDATER_DISABLED', true);\n";
                    $fileStr  = str_replace("/* That's all, stop editing! Happy blogging. */",$add_line."/* That's all, stop editing! Happy blogging. */",$fileStr);
                  
                }

            }
            //wp autoupdate core on/off
            $found_line = false;
            foreach($ini_array as $key=>$value){
                if(!empty($value)){
                  
                    $new_value = $value;
                    if(stristr($value,"WP_AUTO_UPDATE_CORE")){
                       
                        if(!empty($automatic_updater_core) && $automatic_updater_core == 'on'){
                            $new_value = str_replace("false","true",$value);
                        }else{
                            $new_value = str_replace("true","false",$value);
                        }

                        $fileStr  = str_replace($value,$new_value,$fileStr);
                        $found_line = true;
                    }

                }
            }
            if($found_line == false){
                $add_line = '';
                if(!empty($automatic_updater_core) || $automatic_updater_core == 'on'){
                    $add_line = "\n\n"."define('WP_AUTO_UPDATE_CORE', true);\n";
                    $fileStr  = str_replace("/* That's all, stop editing! Happy blogging. */",$add_line."/* That's all, stop editing! Happy blogging. */",$fileStr);


                }

            }
            $found_line = false;


           
            $pos=strpos($fileStr, ' ?>');
            $fileStr = substr($fileStr, 0, $pos)."\r\n".substr($fileStr, $pos);
            $this->dashboard_model->save_wpconfig($fileStr);

        }
          $this->redirect('?view='.$this->current_page);
    }

    /**
	* Triggers download of files stored in WP Safe Mode storage 
	* 
	* @return void 
	*/
    function action_download(){
    	// SECURITY FIX: Use InputValidator for sanitization
    	$download = InputValidator::getInput('download', INPUT_GET, 'string');
    	$filename = InputValidator::getInput('filename', INPUT_GET, 'string');
    	
    	// SECURITY FIX: Validate filename format
    	if (!InputValidator::validate($filename, 'filename')) {
    		$this->set_message('Invalid filename');
    		$this->redirect();
    		return;
    	}
    	
    	if($download == 'database'){
    		$base_directory = $this->settings['sfstore'] . 'db_backup/';
    		
    		// SECURITY FIX: Validate file path
    		$filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
    		if ($filepath === false || !file_exists($filepath)) {
    			$this->set_message('File not found');
    			$this->redirect();
    			return;
    		}
    		
    		SecureFileOperations::secure_download_file($filename, $base_directory);
    		exit;
    	}
    	
    	if($download == 'sitefiles'){
    		$base_directory = $this->settings['sfstore'] . 'file_backup/';
    		
    		// SECURITY FIX: Validate file path
    		$filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
    		if ($filepath === false || !file_exists($filepath)) {
    			$this->set_message('File not found');
    			$this->redirect();
    			return;
    		}
    		
    		SecureFileOperations::secure_download_file($filename, $base_directory);
    		exit;
    	}
    	
    	//download htaccess backup file
    	if($download == 'htaccess'){
    		$base_directory = $this->settings['sfstore'] . 'htaccess_backup/';
    		
    		// SECURITY FIX: Validate file path
    		$filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
    		if ($filepath === false || !file_exists($filepath)) {
    			$this->set_message('File not found');
    			$this->redirect();
    			return;
    		}
    		
    		SecureFileOperations::secure_download_file($filename, $base_directory);
    		exit;
    	}
    	
    	if($download == 'error_log'){
    		$error_file = ini_get('error_log');
    		if ($error_file && file_exists($error_file)) {
    			// SECURITY FIX: Validate error log file path
    			$filepath = realpath($error_file);
    			if ($filepath && is_file($filepath)) {
    				SecureFileOperations::secure_download_file(basename($error_file), dirname($filepath));
    				exit;
    			}
    		}
    		$this->set_message('Error log file not found');
    		$this->redirect();
    	}
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
	* Renders backup files section 
	* 
	* @return void 
	*/
    function view_backup_files(){
    	 $this->data['backups'] = $this->dashboard_model->get_file_backups();
	     $this->render(  $this->view_url .'files_backup', $this->data );
	}
	

   /**
   * Renders robots.txt editing section 
   * 
   * @return void 
   */
   function view_robots(){
   		$robots_settings_file = $this->settings['sfstore'].'robots_revision_last.json';
		$robots_settings = DashboardHelpers::get_data($robots_settings_file , true);
		if(empty($robots_settings) || !is_array($robots_settings)){
            $robots_settings = array();
        }
		
		$this->data['robots_settings'] = $robots_settings;
		$default_settings = array(
			'cgi_bin' => '',
			'wp_admin' => '',
			'archives' => '',
			'replytocom' => '',
			'wp_includes' => '',
			'wp_content_plugins' => '',
			'wp_content_cache' => '',
			'wp_content_themes' => '',
			'user_agent_allow' => '',
			'mediapartners_google' => '',
			'googlebot_image' => '',
			'adsbot_google' => '',
			'googlebot_mobile' => '',
			'sitemap' => '',
			'sitemap_urls' => '',
		);
		
		foreach($robots_settings as $key => $value){
			if($key!='sitemap_urls'){
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
		
   	 	$this->data['robots_file'] = $this->wp_dir . 'robots.txt';
   		$this->data['robots'] = $this->dashboard_model->get_robots();  
   		$this->data['default_settings'] = $default_settings;
   		$this->render( $this->view_url.'robots', $this->data );
   }
   
   /**
   * Handles submission from robots.txt edit section 
   * 
   * @return void 
   */
   function submit_robots(){
   		$robots_action = filter_input(INPUT_POST, 'save_robots');
   		$file = $this->wp_dir . "robots.txt";
   		$robots_content = file_get_contents($file);
   		$robots_settings_file = $this->settings['sfstore'].'robots_revision_last.json';
   		$robots_settings = DashboardHelpers::get_data($robots_settings_file , true);
   		$string = "User-agent: *\nAllow: /";
   		
   		$output = "";
   		$robots_revision = array(); 
   		$robots_revision['cgi_bin'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /cgi-bin/\n",
   		);
   		$robots_revision['wp_admin'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-admin/\n",
   		);
   		$robots_revision['archives'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /archives/\n",
   		);
   		$robots_revision['replytocom'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: *?replytocom\n",
   		);
   		$robots_revision['wp_includes'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-includes\n",
   		);
   		$robots_revision['wp_content_plugins'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-content/plugins\n",
   		);
   		$robots_revision['wp_content_cache'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-content/cache\n",
   		);
   		$robots_revision['wp_content_themes'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-content/themes\n",
   		);
   		$robots_revision['user_agent_allow'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: *\nAllow: /\n",
   		);
   		$robots_revision['mediapartners_google'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Mediapartners-Google*\nAllow: /\n",
   		);
   		$robots_revision['googlebot_image'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Googlebot-Image\nAllow: /wp-content/uploads/\n",
   		);
   		$robots_revision['adsbot_google'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Adsbot-Google\nAllow: /\n",
   		);
   		$robots_revision['googlebot_mobile'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Googlebot-Mobile\nAllow: /\n",
   		);
   		$robots_revision['sitemap'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nSitemap: %s\n",
   		);
   		$robots_revision['sitemap_urls'] = array(
   			'type' => 'string',
   		);
   		
   		 foreach($robots_revision as $revisio_key => $revision_value){ 
		 	$input = filter_input(INPUT_POST , $revisio_key);
		
		  if(!empty($input)){
		 		if($revision_value['type'] == 'boolean'){				
					$robots_settings[$revisio_key] = 1;
				}else{
					 $robots_settings[$revisio_key]  = $input;
				}
				
				if($robots_settings == false || empty($robots_settings)){
					$robots_settings = array();
				}
				else{
					if($revisio_key != 'sitemap'){
						$output .= $revision_value['unformatted_value'];
					}else{						
						if($revisio_key == 'sitemap'){
							$sitemaps = filter_input(INPUT_POST, 'sitemap_urls');
							$sitemaps_urls = explode(",",$sitemaps);
							foreach($sitemaps_urls as $sitemaps_url){
								$url .= "Sitemap: " . $sitemaps_url . "\n";
							}
							$output .= sprintf($revision_value['unformatted_value'], $url);
						}
					
					}
				}
				
			} else{
				if(isset($robots_settings[$revisio_key])){
					unset($robots_settings[$revisio_key]);
				}		
			}
		 }	
		 
		  //save revision by date
		 $robots_revision_json = json_encode($robots_revision);
		 $robots_revision_json_filename =  $this->settings['sfstore'].'robots_revision_'.date('Y-m-d--H-i-s').'.json';
		 //save last revision
		 $robots_revision_json_last_filename =  $this->settings['sfstore'].'robots_revision_last.json';
		 $robots_revision_json_last_content = file_get_contents($robots_revision_json_last_filename);
		 file_put_contents($robots_revision_json_filename, $robots_revision_json_last_content);
		 file_put_contents($robots_revision_json_last_filename, $robots_revision_json);
		 
		 $new_robots = $string . $output;
		 $this->dashboard_model->save_robots_file( $new_robots ); 
		 
		 DashboardHelpers::put_data($robots_settings_file , $robots_settings , true );
         $this->set_message('robots.txt has been saved');
         $this->redirect();
         return;
   }
	
	

	/**
	* Renders .htaccess section 
	* 
	* @return void 
	*/
    function view_htaccess(){
		$htaccess_settings_file = $this->settings['sfstore'].'htaccess_revision_last.json';
		$htaccess_settings = DashboardHelpers::get_data($htaccess_settings_file , true);
		if(empty($htaccess_settings) || !is_array($htaccess_settings)){
            $htaccess_settings = array();
        }
		
		$this->data['htaccess_settings'] = $htaccess_settings;
		$default_settings = array(
			'bad_ips' => '',
			'block_ips' => '',
		 	'block_bots' => '',
		 	'block_hidden' => '',
		 	'block_source' => '',
		 	'old_domain' => '',
		 	'new_domain' => '',
		 	'deny_referrer' => '',
		 	'referrer' => '',
		 	'media_download' => '',
		 	'redirect_www' => '',
		 	'canonical_url' => '',
		 	'trailing_slash' => '',
		 	'pass_single_file' => '',
		 	'single_file_name' => '',
		 	'pass_directory' => '',
		 	'directory_browsing' => '',	 
		 	'server_signature' => '',
		 	'disable_hotlinking' => '',
		 	'disable_trace' => '',
		 	'restrict_wpincludes' => '',
		 	'development_redirect' => '',
		 	'redirect_url' => '',
		 	'protected_config' => '',
		 	'protected_htaccess' => '',
		 	'protect_from_xmlrpc' => '',
		 	'caching' => '',
		 	'default_page' => '',
		 	'default_file_name' => '',
		 	'set_language' => '',
		 	'language_value' => '',
		 	'set_charset' => '',
		 	'charset_value' => '',
		 	'error_page' => '',
		 	'error_400' => '',
		 	'error_401' => '',
		 	'error_403' => '',
		 	'error_404' => '',
		 	'error_500' => '',
		 	'allow_wpadmin' => '',
		 	'allow_wpadmin_ip' => '',
		);
		foreach($htaccess_settings as $key => $value){
			if($key!='bad_ips' && $key!='new_domain' && $key!='referrer' && $key!='single_file_name' && $key!='redirect_url' && $key!='allow_wpadmin_ip' && $key!='default_file_name' && $key!='charset_value' && $key!='language_value' && $key!='error_400' && $key!='error_401' && $key!='error_403' && $key!='error_404' && $key!='error_500'){
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
		$file = $this->wp_dir . ".htaccess";
		//if in initial state doesn't exists htaccess file create one
		if(!file_exists($file)){
			$fo = fopen($file, "w+") or die("Cannot create file");
		 	fwrite($fo, "# BEGIN WordPress-SafeMode\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n\n</IfModule>\n# END WordPress-SafeMode");
		 	file_put_contents($wp_dir . 'htaccess.safemode.backup', $source);
		 } 
		 
		 $pass_file = $this->wp_dir . ".htpasswd";	
		 if(!file_exists($pass_file)){
		 	$fopen = fopen($pass_file, "w+") or die("Cannot create file");
		 }	 
    	 $this->data['backups'] = $this->dashboard_model->get_htaccess_backups();
    	 $this->data['htaccess'] = $this->wp_dir . '.htaccess';
		 $this->data['default_settings'] = $default_settings;
   		 $this->render( $this->view_url.'htaccess', $this->data );
	}
	
    /**
	* 
	* Handles submission of htaccess settings from .htaccess section 
	* 
	* @return void
	*/
	function submit_htaccess(){
         $htaccess_action = filter_input(INPUT_POST, 'save_htaccess');
         
         $file = $this->wp_dir . ".htaccess";
		 $domain_name = $_SERVER['SERVER_NAME'];	
         $uri = $_SERVER['REQUEST_URI'];
		 $pass_file = $this->settings['wp_dir'].'.htpasswd';
		 $pass_file = realpath($pass_file); 
		 		
         $htaccess_content = $this->dashboard_model->get_htaccess();
         $htaccess_settings_file = $this->settings['sfstore'].'htaccess_revision_last.json';
	 	 $htaccess_settings = DashboardHelpers::get_data($htaccess_settings_file , true); 	 
         preg_match('/\#\sBEGIN\sWordPress[\s\S]+?\#\sEND\sWordPress/', $htaccess_content , $matches);
         preg_match('/\#\sBEGIN\sWPSM-MAINTENANCE[\s\S]+?\#\sEND\sWPSM-MAINTENANCE/', $htaccess_content , $wpsm_matches);
         if($matches[0] != ''){		 	
         	$htaccess_wordpress = $matches[0];
		 }else{
		 	$htaccess_wordpress = $wpsm_matches[0];
		 }
         
		 $output = "";
		 
		 $htacess_revision = array(); 
		 
		 $htacess_revision['bad_ips'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['block_ips'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Block IPs\norder allow,deny %s \nallow from all\n#End Block IPs\n",
		 );
		 $htacess_revision['block_bots'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "#WPSM Block bots\nRewriteBase /\nRewriteCond %{HTTP_USER_AGENT} ^Anarchie [OR]\nRewriteCond %{HTTP_USER_AGENT} ^ASPSeek [OR]\nRewriteCond %{HTTP_USER_AGENT} ^attach [OR]\nRewriteCond %{HTTP_USER_AGENT} ^autoemailspider [OR]\nRewriteCond %{HTTP_USER_AGENT} ^Xaldon\ WebSpider [OR]\nRewriteCond %{HTTP_USER_AGENT} ^Xenu [OR]\nRewriteCond %{HTTP_USER_AGENT} ^Zeus.*Webster [OR]\nRewriteCond %{HTTP_USER_AGENT} ^Zeus\nRewriteRule ^.* - [F,L]\n",
		 );
		 $htacess_revision['block_hidden'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Block access to hidden files & directories\nRewriteCond %{SCRIPT_FILENAME} -d [OR]\nRewriteCond %{SCRIPT_FILENAME} -f\nRewriteRule \"(^|/)\.\" - [F]\n#End Block access to hidden files & directories\n",
		 );
		 $htacess_revision['block_source'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Block access to source files\n<FilesMatch \"(^#.*#|\.(bak|config|dist|fla|inc|ini|log|psd|sh|sql|sw[op])|~)$\">\nOrder allow,deny\nDeny from all\nSatisfy All\n</FilesMatch>\n#End Block access to source files\n",
		 );
		 $htacess_revision['old_domain'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Redirect\nRedirect 301 / %s \n# End redirect",
		 );
		 $htacess_revision['new_domain'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['deny_referrer'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "#WPSM deny by referrer\nRewriteEngine on\n#Options +FollowSymlinks %s",
		 );
		 $htacess_revision['referrer'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['media_download'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM media files download\nAddType application/octet-stream .zip .mp3 .mp4\n#End media files download",
		 );
		 $htacess_revision['redirect_www'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => array(
		 		'add' => "#WPSM non www to www\nRewriteCond %{HTTP_HOST} !^www\..+$ [NC]\nRewriteRule ^ http://www.%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n### Redirect away from /index.php to clear path\nRewriteCond %{THE_REQUEST} ^.*\/index.php\nRewriteRule ^(.*)index.php\$ http://www.%{HTTP_HOST}%{REQUEST_URI}$1 [R=301,L]\n#END non www to www\n",
		 		'remove' => "#WPSM remove www\nRewriteCond %{HTTP_HOST} ^www\.(.+)$\nRewriteRule ^(.*)\$ http://%1/$1 [R=301,L]\n#End remove www\n",)
		 		);
 		 $htacess_revision['canonical_url'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM canonical URL\nRewriteCond %{HTTP_HOST} !^www\..+$ [NC]\nRewriteRule ^ http://www.%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n#End canonical ULR\n",
		 );
		 $htacess_revision['trailing_slash'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "#WPSM add slah on the end of url\nRewriteBase /\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_URI} !#\nRewriteCond %{REQUEST_URI} !(.*)/$\nRewriteRule ^(.*)\$ %{HTTP_HOST}/$1/ [L,R=301]\n#End adding slash\n",
		 );
		 $htacess_revision['pass_single_file'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM single file password protection\n<FilesMatch \" %s \">\nAuthName \"Username and password required\"\nAuthType Basic\nAuthUserFile \" %s \"\nRequire valid-user\n</FilesMatch>\n#End single file pass\n",
		 );
		 $htacess_revision['single_file_name'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['pass_directory'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM password protect directory\nAuthType Basic\nAuthName \"Password Protected Area\"\nAuthUserFile %s \nRequire valid-user\n#ENd password protect directory\n",
		 );
		 $htacess_revision['directory_browsing'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Disable Directory Browsing \nOptions All -Indexes\n# End of Disable Directory Browsing ",
		 );		 
		 $htacess_revision['server_signature'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM disable the server signature\nServerSignature Off\n#End of disable the server signature",
		 );
		 $htacess_revision['disable_hotlinking'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM disable hotinking\nRewriteCond %%{HTTP_REFERER} !^$\nRewriteCond %%{HTTP_REFERER} !^http://(www\.)?%s.*$ [NC]\nRewriteRule \.(gif|jpg|js|css|jpeg|png)$ - [F]\n#END of disable hotlinking",
		 );
		 $htacess_revision['disable_trace'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Disable HTTP Trace\nRewriteEngine On\nRewriteCond %{REQUEST_METHOD} ^TRACE\nRewriteRule .* - [F]\n#End disable trace\n",
		 );
		 $htacess_revision['restrict_wpincludes'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPS restrict wp-includes \nRewriteRule ^wp-admin/includes/ - [F,L]\nRewriteRule !^wp-includes/ - [S=3]\nRewriteRule ^wp-includes/[^/]+\.php$ - [F,L]\nRewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]\nRewriteRule ^wp-includes/theme-compat/ - [F,L]\n#End restrict wp-includes\n",
		 );
		 $htacess_revision['development_redirect'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM redirect all visitors to alternate site but retain full access for you\nErrorDocument 403 %s \nOrder deny,allow\nDeny from all\n#End redirect",
		 );
		 $htacess_revision['redirect_url'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['protected_config'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Protect wp-config.php \n<Files wp-config.php>\norder allow,deny\ndeny from all\n</Files>\n# End Protect wp-config.php ",
		 );
		 $htacess_revision['protected_htaccess'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Protect .htaccess \n<Files ~ \"^.*\.([Hh][Tt][Aa])\">\norder allow,deny\ndeny from all\n</Files>\n# End Protect htaccess ",
		 );
		 $htacess_revision['protect_from_xmlrpc'] = array(
			 'type' => 'boolean',
			 'unformatted_value' => "\n#WPSM Protect from xmlrpc \n<Files xmlrpc.php>\nOrder allow,deny\nDeny from all\n</Files>\n#END Protect from xmlrpc\n",
		);
		 $htacess_revision['caching'] =array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "#WPSM enable cach\nExpiresActive On\nExpiresDefault A86400\nExpiresByType image/x-icon A2592000\nExpiresByType application/x-javascript A2592000\nExpiresByType text/css A2592000\nExpiresByType image/gif A604800\nExpiresByType image/png A604800\nExpiresByType image/jpeg A604800\nExpiresByType text/plain A604800\nExpiresByType application/x-shockwave-flash A604800\nExpiresByType video/x-flv A604800\nExpiresByType application/pdf A604800\nExpiresByType text/html A900\n#End caching\n",
		 );
		 $htacess_revision['default_page'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM Default directory page\nDirectoryIndex %s \n#End defult directory page",
		 );		 
		 $htacess_revision['default_file_name'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['set_language'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM set the default language\nDefaultLanguage %s \n#End language",
		 );
		 $htacess_revision['language_value'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['set_charset'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM set the default character set\nAddDefaultCharset %s \n# End charset",
		 );
		 $htacess_revision['charset_value'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['error_page'] = array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM set the default character set\nAddDefaultCharset %s \n# End charset" ,
		 );
		 $htacess_revision['error_400'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['error_401'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['error_403'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['error_404'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['error_500'] = array(
		 	'type' => 'string',
		 );
		 $htacess_revision['allow_wpadmin'] =  array(
		 	'type' => 'boolean',
		 	'unformatted_value' => "\n#WPSM protect wp-admin\n<Files \"wp-login.php\">\nOrder allow,deny %s \ndeny from all\n</Files>\n#End protect wp-admin\n",
		 );
		 $htacess_revision['allow_wpadmin_ip'] = array(
		 	'type' => 'string',
		 );
		 
		 foreach($htacess_revision as $revisio_key => $revision_value){ 
		 	$input = filter_input(INPUT_POST , $revisio_key);
		
		  if(!empty($input)){
		 		if($revision_value['type'] == 'boolean'){				
					$htaccess_settings[$revisio_key] = 1;
				}else{
					 $htaccess_settings[$revisio_key]  = $input;
				}
				
				if($htaccess_settings == false || empty($htaccess_settings)){
					$htaccess_settings = array();
				}
				else{
					if($revisio_key == 'block_bots' || $revisio_key == 'block_hidden'|| $revisio_key == 'block_source' || $revisio_key == 'media_download' || $revisio_key == 'redirect_www' || $revisio_key == 'canonical_url' || $revisio_key == 'trailing_slash' || $revisio_key == 'directory_browsing' || $revisio_key == 'server_signature' || $revisio_key == 'disable_trace' || $revisio_key == 'restrict_wpincludes' || $revisio_key == 'protected_config' || $revisio_key == 'protected_htaccess' || $revisio_key == 'protect_from_xmlrpc' || $revisio_key == 'caching'){
						$output .= $revision_value['unformatted_value'];
					}else{
						
						if($revisio_key == 'block_ips'){
							$user_addresses = filter_input(INPUT_POST, 'bad_ips');
							$user_ips = explode(",",$user_addresses);
							foreach($user_ips as $ip){
								$valid = filter_var($ip, FILTER_VALIDATE_IP);
								if(!empty($valid)){
									$user .= "\ndeny from " .$valid;
								}else{
									$user .= "\n#". $ip . ". IP adress is not valid";
								}
							}
							$output .= sprintf($revision_value['unformatted_value'], $user);
						}
						if($revisio_key == 'old_domain'){				
							$new_domain = filter_input(INPUT_POST , 'new_domain');
							$output .= sprintf($revision_value['unformatted_value'], $new_domain);
						}
						if($revisio_key == 'deny_referrer'){
							$referrers = filter_input(INPUT_POST, 'referrer');
							$refs = explode(",", $referrers);
							$i=0;
							foreach($refs as $ref){
								$num_of_ref = count($refs);
								$i++;
								$ref = str_replace(".", "\.", $ref);
								$ref_output .= "\nRewriteCond %{HTTP_REFERER} " . $ref;
								if($i < $num_of_ref){
									$ref_output .= " [NC,OR]";
								}else{
									$ref_output .= " [NC]";
								}
							}		
							$ref_output .= " \nRewriteRule .* - [F]\n#End denu referrer\n";
							$output .= sprintf($revision_value['unformatted_value'], $ref_output);					
						}
						if($revisio_key == 'pass_single_file'){
							$file_name = filter_input(INPUT_POST, 'single_file_name');
							$output .= sprintf($revision_value['unformatted_value'], $file_name, $pass_file);
						}
						if($revisio_key == 'pass_directory'){
							$output .= sprintf($revision_value['unformatted_value'], $pass_file);
						}
						//proveri zasto ovo ne radi
						if($revisio_key == 'disable_hotlinking'){
							$output .= sprintf($revision_value['unformatted_value'], str_replace('.', '\.', $domain_name));
						}
						if($revisio_key == 'development_redirect'){
							$url_redirect = filter_input(INPUT_POST, 'redirect_url');
							$output .= sprintf($revision_value['unformatted_value'], $url_redirect	);
						}
						if($revisio_key == 'allow_wpadmin'){
							$admin_addresses = filter_input(INPUT_POST, 'allow_wpadmin_ip');
							$admin_ips = explode(",",$admin_addresses);
							foreach($admin_ips as $admin_ip){
								$valid_user = filter_var($admin_ip, FILTER_VALIDATE_IP);
								if(!empty($valid_user)){
									$use_ips .= "\nallow from " .$valid_user;
								}else{
									$use_ips .= "\n#". $admin_ip . ". IP adress is not valid";
								}
							}
							$output .= sprintf($revision_value['unformatted_value'], $use_ips);
						}
						if($revisio_key == 'default_page'){
							$def_page = filter_input(INPUT_POST, 'default_file_name');
							$output .= sprintf($revision_value['unformatted_value'], $def_page);
						}
						if($revisio_key == 'set_language'){
							$lang = filter_input(INPUT_POST, 'language_value');
							$output .= sprintf($revision_value['unformatted_value'], $lang);
						}
						if($revisio_key == 'set_charset'){
							$charset = filter_input(INPUT_POST, 'charset_value');
							$output .= sprintf($revision_value['unformatted_value'], $charset);
						}
						if($revisio_key == 'error_page'){
							$error_400 = filter_input(INPUT_POST, 'error_400');
							$error_401 = filter_input(INPUT_POST, 'error_401');
							$error_403 = filter_input(INPUT_POST, 'error_403');
							$error_404 = filter_input(INPUT_POST, 'error_404');
							$error_500 = filter_input(INPUT_POST, 'error_500');
							
							if($error_400 != ''){
								$output .= sprintf($revision_value['unformatted_value'], $error_400);
							}
							if($error_401 != ''){
								$output .= sprintf($revision_value['unformatted_value'], $error_401);
							}
							if($error_403 != ''){
								$output .= sprintf($revision_value['unformatted_value'], $error_403);
							}
							if($error_404 != ''){
								$output .= sprintf($revision_value['unformatted_value'], $error_404);
							}
							if($error_500 != ''){
								$output .= sprintf($revision_value['unformatted_value'], $error_500);
							}
						}
					
					}
				}
				
			}
			
			else{
				if(isset($htaccess_settings[$revisio_key])){
					unset($htaccess_settings[$revisio_key]);
				}
			}					
			
		 }	
         
		 //save revision by date
		 $htaccess_revision_json = json_encode($htacess_revision);
		 $htaccess_revision_json_filename =  $this->settings['sfstore'].'htaccess_revision_'.date('Y-m-d--H-i-s').'.json';
		 //save last revision
		 $htaccess_revision_json_last_filename =  $this->settings['sfstore'].'htaccess_revision_last.json';
		 $htaccess_revision_json_last_content = file_get_contents($htaccess_revision_json_last_filename);
		 file_put_contents($htaccess_revision_json_filename, $htaccess_revision_json_last_content);
		 file_put_contents($htaccess_revision_json_last_filename, $htaccess_revision_json);
		 
		 
		
         $htaccess_new = $htaccess_wordpress . "\n# BEGIN WordPress-SafeMode \n" . $output . "\n# END Wordpress-Safemode \n";
		 $this->dashboard_model->save_htaccess_file( $htaccess_new ); 
		 
		 DashboardHelpers::put_data($htaccess_settings_file , $htaccess_settings , true );
         $this->set_message('.htaccess has been saved');
         $this->redirect();
         return;
    }
    
    /**
    * 
	* Revert htacces file from last backup
	* 
	* @return
	*/
    function submit_htaccess_to_revert(){
         $htaccess_revert= filter_input(INPUT_POST,'save_revert');
         $dir = $this->settings['wp_dir'] . "htaccess.safemode.backup";
         $htaccess_revision_json_last_filename =  $this->settings['sfstore'].'htaccess_revision_last.json';
		 $json_backup = $this->settings['sfstore'].'htaccess_revision_backup.json';
		 $json_backup_content = file_get_contents($json_backup);
         if(isset($htaccess_revert) && file_exists($dir)){
         	$htaccess_bacukup = file_get_contents($dir);
         	file_put_contents($htaccess_revision_json_last_filename, $json_backup_content);
         	$this->dashboard_model->save_htaccess_file($htaccess_bacukup);
		}
		$this->set_message('.htaccess revert');
        $this->redirect();
    }
	
	/**
	* Saves backup file of htaccess 
	* 
	* @return void
	*/
	function save_htaccess_backup(){
		$save_backup = filter_input(INPUT_POST, 'save_htaccess_backup');
		$file = $this->dashboard_model->htaccess_path;
		$file_backup = $this->dashboard_model->htaccess_backup_path;
		$this->dashboard_model->get_htaccess_revert();
		$this->set_message(".htaccess backup has been saved at " . realpath($file_backup));
		$this->redirect(); 
		return;
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
	* Renders search and replace section 
	* 
	* @return void 
	*/
    function view_search_replace(){
    	
           $this->data['tables'] = $this->dashboard_model->show_tables();	
           $this->render( $this->view_url .'search_replace', $this->data);
	}

    /**
	* Handles submission from search and replace 
	* 
	* @return array|void|mixed  
	*/
    function submit_search_replace(){
		 $allowed_criteria_term = array('contains','exact','any');
		 $allowed_criteria_db = array('full','partial');
		 
		 $search_term = filter_input(INPUT_POST,'term');
		 $search_criteria_term = filter_input(INPUT_POST,'search_criteria_term');		 
		 $search_criteria_db = filter_input(INPUT_POST,'search_criteria_db');
		 $search_criteria_tables = filter_input(INPUT_POST,'search_tables_list',FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		 
		 $args = array();
		 
		 $args['criteria']['term'] = (!empty($search_criteria_term) && in_array($search_criteria_term, $allowed_criteria_term))? $search_criteria_term : 'contains';
		 $args['criteria']['db'] = (!empty($search_criteria_db) && in_array($search_criteria_db, $allowed_criteria_db))? $search_criteria_db : 'full';
		 if(!empty($search_criteria_tables) && $search_criteria_db == 'partial'){
		 	 $args['criteria']['tables'] = $search_criteria_tables;
		 }
		  
		 
		 if(!empty($search_term)){
		   $this->data['search_results'] = $this->dashboard_model->db_search($search_term, $args );	
		 }
		 
		 
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
	public function help_info_file( $page = '' ){    	    
        $store = $this->dashboard_model->settings['sfstore'];   
		$page = filter_input(INPUT_GET, 'view');
		$file_name = $store . $page . ".txt";
		if(file_exists($file_name)){			
			$text = file_get_contents($file_name);
			return $text;
		}
		
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
	* Resets .htaccess to initial state 
	* 
	* @return void 
	*/
	function reset_htaccess(){
		return;
	}
	
	/**
	* Handles submission of new site url 
	* 
	* @return void 
	*/
	function submit_site_url(){
		$siteurl = filter_input(INPUT_POST,'site_url');
		$home = filter_input(INPUT_POST,'home_url');
		
	   if(empty($home)){
	   	$this->set_message('Home URL cannot be empty');
		return;
	   }
	   if(empty($siteurl)){
	     $this->set_message('Site URL cannot be empty');
		return;
	   }
	   $this->dashboard_model->update_site_url($home , $siteurl );
	   $this->set_message('Home and SiteUrl have been changed');
	   $this->redirect();
	}
	
	
	function create_robots_file(){
		$file = $this->dashboard_model->settings['wp_dir'].'robots.txt';
		$content =  "User-agent: *\nDisallow: /";
		file_put_contents($file, $content);
		$this->set_message("File is successfully created");
		$this->redirect(); 
		return;
	}
	


}
global $dashboard;

//define var for class use - not global variable
$dashboard = new DashboardController;