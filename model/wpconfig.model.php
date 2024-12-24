<?php

	class WpConfigModel extends DashboardModel {
		
		function __construct(){
			parent::__construct();
		}
		
		/**
		* returns variable and constants data from wp-config.php all in one array. Each item holds several indexes 
		* 		example: 
		* 		$wpconfig_opts['wp_debug'] = array(
		* 		'name' => 'WP_DEBUG', //name of constant/variable, that is actual name stored in wp-config.php 
		* 		'description' => 'WordPress debug mode for developers', //description for constant/variable 
		* 		'type' => 'constant', //type of item can be constant or variable 
		* 		'input_label'=> 'WP Debug', //label that will be shown in form 
		* 		'input_key'=> 'wp_debug', //form input name 
		* 		'allowed_values'=> array( true , false ), //allowed values, constraints
		* 		'input_type'=> 'switch', //form input type 
		* 		'default_value' => true, //default value if actual value doesn't exist		
		* 		'value_type' => 'boolean', //type of value - can be boolean, string, integer  ... 
	 	*	);	
		* 
		* @return array $wp_config_opts with items from wp-config.php 
		*/
	    public function get_wp_config_options(){
			$wpconfig_opts = array();
	        //to do, add group, fieldset 
			$wpconfig_opts['wp_debug'] = array(
				'name' => 'WP_DEBUG',
				'description' => 'WordPress debug mode for developers',
				'type' => 'constant',
				'input_label'=> 'WP Debug',
				'input_key'=> 'wp_debug', 		
				'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => true,		
				'value_type' => 'boolean', 
	 		);		
	 		$wpconfig_opts['automatic_updater_disabled'] = array(
				'name' => 'AUTOMATIC_UPDATER_DISABLED',
				'description' => 'Disable automatic updater for plugins and themes',
				'type' => 'constant',
				'input_label'=> 'Disable Automatic Updater',
				'input_key'=> 'automatic_updater_disabled', 		
				'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => false,		
				'value_type' => 'boolean', 
	 		);	
	 		$wpconfig_opts['wp_auto_update_core'] = array(
				'name' => 'WP_AUTO_UPDATE_CORE',
				'description' => 'Enable WP core auto update',
				'type' => 'constant',
				'input_label'=> 'WP Auto Update Core',
				'input_key'=> 'wp_auto_update_core', 		
				'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => false,		
				'value_type' => 'boolean', 
	 		);
			$wpconfig_opts['db_name'] = array(
				'name' => 'DB_NAME',
				'description' => 'The name of the database for WordPress',
				'type' => 'constant',
				'input_label'=> 'DB Name',
				'input_key'=> 'db_name', 					
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
	 		); 		
	 		$wpconfig_opts['table_prefix'] = array(
		 		'name' => 'table_prefix',
		 		'description'=> 'Prefix for your wordpress table', 
		 		'type' => 'variable', 
		 		'input_label' => 'DB table prefix', 
		 		'input_key' => 'table_prefix', 
		 		'input_type'=> 'text',
		 		'default_value' => 'wp_' , 
		 		'value_type' => 'string' ,	
	 		);
	 		$wpconfig_opts['backuped_table_prefix'] = array(
		 		'name' => 'backuped_table_prefix',
		 		'description'=> 'Prefix for your backuped wordpress table', 
		 		'type' => 'variable', 
		 		'input_label' => 'DB backuped table prefix', 
		 		'input_key' => 'backuped_table_prefix', 
		 		'input_type'=> 'text',
		 		'default_value' => 'bck_' , 
		 		'value_type' => 'string' ,	
	 		);
	 		$wpconfig_opts['db_host'] = array(
	 			'name' => 'DB_HOST',
	 			'description' => 'MySQL Database Host',
	 			'type' => 'constant',
	 			'input_label' => 'DB Host',
	 			'input_key' => 'db_host',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['db_user'] = array(
	 			'name' => 'DB_USER',
	 			'description' => 'MySQL Database User',
	 			'type' => 'constant',
	 			'input_label' => 'DB User',
	 			'input_key' => 'db_user',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['db_password'] = array(
	 			'name' => 'DB_PASSWORD',
	 			'description' => 'MySQL Database Password',
	 			'type' => 'constant',
	 			'input_label' => 'DB Password',
	 			'input_key' => 'db_password',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['db_charset'] = array(
	 			'name' => 'DB_CHARSET',
	 			'description' => 'MySQL Database Charset',
	 			'type' => 'constant',
	 			'input_label' => 'DB Charset',
	 			'input_key' => 'db_charset',
	 			'input_type' => 'text',
	 			'default_value' => 'utf8',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['db_collate'] = array(
	 			'name' => 'DB_COLLATE',
	 			'description' => 'MySQL Database Collate',
	 			'type' => 'constant',
	 			'input_label' => 'DB Collate',
	 			'input_key' => 'db_collate',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['security_keys'] = array(
	 			'name' => 'security_keys',
	 			'description' => 'Define new security keys',
	 			'type' => 'constant',
	 			'input_label' => 'Security Keys',
	 			'input_key' => 'security_keys',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_siteurl'] = array(
	 			'name' => 'WP_SITEURL',
	 			'description' => 'Define site url',
	 			'type' => 'constant',
	 			'input_label' => 'Site URL',
	 			'input_key' => 'site_url',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_home'] = array(
	 			'name' => 'WP_HOME',
	 			'description' => 'Define home url',
	 			'type' => 'constant',
	 			'input_label' => 'Home URL',
	 			'input_key' => 'home_url',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_content_dir'] = array(
	 			'name' => 'WP_CONTENT_DIR',
	 			'description' => 'Define Content Directory',
	 			'type' => 'constant',
	 			'input_label' => 'Content DIR',
	 			'input_key' => 'content_dir',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_plugin_dir'] = array(
	 			'name' => 'WP_PLUGIN_DIR',
	 			'description' => 'Define Plugin Directory',
	 			'type' => 'constant',
	 			'input_label' => 'Plugin DIR',
	 			'input_key' => 'plugin_dir',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['theme_root'] = array(
	 			'name' => 'theme_root',
	 			'description' => 'Define and register new theme dir',
	 			'type' => 'variable',
	 			'input_label' => 'Theme Directory',
	 			'input_key' => 'theme_root',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['uploads'] = array(
	 			'name' => 'UPLOADS',
	 			'description' => 'Define new uploads dir',
	 			'type' => 'constant',
	 			'input_label' => 'Uploads Directory',
	 			'input_key' => 'uploads_dir',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_post_revision'] = array(
	 			'name' => 'WP_POST_REVISIONS',
	 			'description' => 'Disable/Enable post revision',
	 			'type' => 'constant',
	 			'input_label' => 'Post revision',
	 			'input_key' => 'post_revision',
	 			'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => false,		
				'value_type' => 'boolean',
	 		);
	 		$wpconfig_opts['cookie_domain'] = array(
	 			'name' => 'COOKIE_DOMAIN',
	 			'description' => 'Define cookie domain',
	 			'type' => 'constant',
	 			'input_label' => 'Cookie domain',
	 			'input_key' => 'cookie_domain',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_allow_multisite'] = array(
	 			'name' => 'WP_ALLOW_MULTISITE',
	 			'description' => 'Disable/Enable multisite',
	 			'type' => 'constant',
	 			'input_label' => 'Multisite',
	 			'input_key' => 'multisite',
	 			'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => false,		
				'value_type' => 'boolean',
	 		);
	 		$wpconfig_opts['noblogredirect'] = array(
	 			'name' => 'NOBLOGREDIRECT',
	 			'description' => 'Define no blog redirect url',
	 			'type' => 'constant',
	 			'input_label' => 'No blog redirect',
	 			'input_key' => 'no_blog_redirect',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['script_debug'] = array(
	 			'name' => 'SCRIPT_DEBUG',
	 			'description' => 'Enable/Disable script debug',
	 			'type' => 'constant',
	 			'input_label' => 'Script debug',
	 			'input_key' => 'script_debug',
	 			'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => true,		
				'value_type' => 'boolean',
	 		);
	 		$wpconfig_opts['wp_debug_log'] = array(
	 			'name' => 'WP_DEBUG_LOG',
	 			'description' => 'Enable Debug logging to the /wp-content/debug.log file',
	 			'type' => 'constant',
	 			'input_label' => 'Logging to log file',
	 			'input_key' => 'debug_log',
	 			'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => true,		
				'value_type' => 'boolean',
	 		);
	 		$wpconfig_opts['wp_debug_display'] = array(
	 			'name' => 'WP_DEBUG_DISPLAY',
	 			'description' => 'Disable display of errors and warnings',
	 			'type' => 'constant',
	 			'input_label' => 'Display of errors and warnings',
	 			'input_key' => 'debug_display',
	 			'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => false,		
				'value_type' => 'boolean',
	 		);
	 		$wpconfig_opts['concatenate_scripts'] = array(
	 			'name' => 'CONCATENATE_SCRIPTS',
	 			'description' => 'Enable/Disable JS Concatenation',
	 			'type' => 'constant',
	 			'input_label' => 'JS Concatenation',
	 			'input_key' => 'concatenate_scripts',
	 			'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => false,		
				'value_type' => 'boolean',
	 		);
	 		$wpconfig_opts['wp_memory_limit'] = array(
	 			'name' => 'WP_MEMORY_LIMIT',
	 			'description' => 'Define WP Memory Limit',
	 			'type' => 'constant',
	 			'input_label' => 'Memory limit',
	 			'input_key' => 'memory_limit',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_max_memory_limit'] = array(
	 			'name' => 'WP_MAX_MEMORY_LIMIT',
	 			'description' => 'Define WP max memory limit',
	 			'type' => 'constant',
	 			'input_label' => 'Max memory limit',
	 			'input_key' => 'max_memory_limit',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_cache'] = array(
	 			'name' => 'WP_CACHE',
	 			'description' => 'Enable Disable WP Cache',
	 			'type' => 'constant',
	 			'input_label' => 'WP Cache',
	 			'input_key' => 'wp_cache',
	 			'allowed_values'=> array( true , false ),
				'input_type'=> 'switch',
				'default_value' => true,		
				'value_type' => 'boolean',
	 		);
	 		$wpconfig_opts['custom_user_table'] = array(
	 			'name' => 'CUSTOM_USER_TABLE',
	 			'description' => 'Define custom user table',
	 			'type' => 'constant',
	 			'input_label' => 'Custom User Table',
	 			'input_key' => 'custom_user_table',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['custom_user_meta_table'] = array(
	 			'name' => 'CUSTOM_USER_META_TABLE',
	 			'description' => 'Define custom user meta table',
	 			'type' => 'constant',
	 			'input_label' => 'Custom User Meta Table',
	 			'input_key' => 'custom_user_meta_table',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wplang'] = array(
	 			'name' => 'WPLANG',
	 			'description' => 'Define wp language',
	 			'type' => 'constant',
	 			'input_label' => 'Language',
	 			'input_key' => 'wplang',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['wp_lang_dir'] = array(
	 			'name' => 'WP_LANG_DIR',
	 			'description' => 'Define wp language directory',
	 			'type' => 'constant',
	 			'input_label' => 'Language Directory',
	 			'input_key' => 'wp_lang_dir',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		$wpconfig_opts['autosave_interval'] = array(
	 			'name' => 'AUTOSAVE_INTERVAL',
	 			'description' => 'Define autosave interval in seconds',
	 			'type' => 'constant',
	 			'input_label' => 'Autosave interval',
	 			'input_key' => 'autosave_interval',
	 			'input_type' => 'text',
	 			'default_value' => '',	
	 			'value_type' => 'string', 
	 		);
	 		
	 		//populate array items with proper values if exists 
	 		foreach( $wpconfig_opts as $key => $option ){
				$wpconfig_opts[$key]['value'] = $this->get_wp_config_option( $option['name'] , $option['type'] );
			}
			
			return $wpconfig_opts;
			
		}
		/**
		* 
		* Returns value for given name of constant or variable in current wp-config.php file
		* 
		* @param string $name
		* @param string $type default is 'constant'
		* 
		* @return string value for given constant name or given variable name
		*/
	    public function get_wp_config_option( $name = '' , $type = 'constant'){
	    	
	    
			if( empty( $name ) ){
				return;
			}
			
			
			if( $type == 'constant' ){
				if( defined( $name ) ){
				return constant( $name );
				}
			}
			if( $type == 'variable' ){
				global ${$name}; 	
				
				if(isset( ${$name} )){ 
				return ${$name};	
				}
				
			}
		}
		
	    /**
		* Retrieves raw content from wp-config.php. Can return with or without php comments 
		* 
		* @param boolean $clean_comments if set to true, it will remove all comments from wp-config.php content. default is false 
		* 
		* @return string $source raw content from wp-config.php file 
		*/
	    public function get_wp_config( $clean_comments = false ){
	      
	        
	        $source = DashboardHelpers::get_data( $this->wp_config_path );
	        
	        $this->backup_wp_config( $source );
	       
	        if($clean_comments == true){
			return	DashboardHelpers::strip_comments( $source );
			}
	        return $source;
	    }
	    
	    /**
		* Stores a copy of current wp-config.php in root folder of main site files
		* 
		* @param string $source raw content from wp-config.php file
		* 
		* @return boolean depending on success or fail to backup wp-config.php it returns true or false
		*/
	    public function backup_wp_config( $source = '' ){
			if(empty($source))
			return false;
			
			return DashboardHelpers::put_data($this->wp_config_backup_path , $source , false , false);
		}
	    
	    /**
		* Returns content from wp-config.php with lines converted in array items
		* 
		* @return array 
		*/
	    function get_wp_config_array(){  
	       
	       	return DashboardHelpers::file_array( $this->wp_config_path ); 	 
	    	
	    }
	    
	    /**
	    * 
		* Writes new rebuilt content in wp-config.php file 
		* 
		* @param string $data rebuilt wp-config.php content 
		* 
		* @return void
		*/
	    function save_wpconfig($data = ''){
	        if(empty($data))
	        return false;
	        
	        return DashboardHelpers::put_data( $this->wp_config_path , $data );
	    }
	    
	   
	}
?>