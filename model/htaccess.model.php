<?php
	class HtaccessModel extends DashboardModel{
		
		public function __construct(){
	        parent::__construct();       
    	}
    	
	    /**
		* Returns htaccess options for .htaccess section in array format. Each item holds several keys so we can use to build a form.  
		* 
		* @return array $htaccess_opts that holds array of htaccess items available to setup in .htaccess section
		* 
		*/
		public function get_htaccess_options(){
			
			$htaccess_opts = array();
			$htaccess_opts['block_ips'] = array(
				'name' => 'block_ips',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Block IPs',
				'input_key'=> 'block_ips', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$htaccess_opts['bad_ips'] = array(
				'name' => 'bad_ips',
				'description' => 'Block IPs, Comma separated list',
				'type' => 'variable',
				'input_label'=> 'Bad IPs',
				'input_key'=> 'bad_ips', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'block_ips',
			); 
			$htaccess_opts['block_bots'] = array(
				'name' => 'block_bots',
				'description' => 'Block bots from your website',
				'type' => 'constant',
				'input_label'=> 'Block bots',
				'input_key'=> 'block_bots', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',	
			);
			$htaccess_opts['block_hidden'] = array(
				'name' => 'block_hidden',
				'description' => 'Block access to hidden files & directories',
				'type' => 'constant',
				'input_label'=> 'Block access to hidden files & directories',
				'input_key'=> 'block_hidden', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',	
			);
			$htaccess_opts['block_source'] = array(
				'name' => 'block_source',
				'description' => 'Block access to source files',
				'type' => 'constant',
				'input_label'=> 'Block access to source files',
				'input_key'=> 'block_source', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$htaccess_opts['old_domain'] = array(
				'name' => 'old_domain',
				'description' => 'Using 301 redirects for whole site',
				'type' => 'constant',
				'input_label'=> 'Redirect from one domain to another',
				'input_key'=> 'old_domain', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',	
			);
			$htaccess_opts['new_domain'] = array(
				'name' => 'new_domain',
				'description' => 'New domain',
				'type' => 'variable',
				'input_label'=> 'New domain',
				'input_key'=> 'new_domain', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string',
				'condition' => 'old_domain', 
			);
			$htaccess_opts['deny_referrer'] = array(
				'name' => 'deny_referrer',
				'description' => 'Deny visitors by referrer',
				'type' => 'constant',
				'input_label'=> 'Deny visitors by referrer',
				'input_key'=> 'deny_referrer', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);	
			$htaccess_opts['referrer'] = array(
				'name' => 'referrer',
				'description' => 'Referrer domain',
				'type' => 'variable',
				'input_label'=> 'Referrer domain',
				'input_key'=> 'referrer', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'deny_referrer', 
			);
			$htaccess_opts['media_download'] = array(
				'name' => 'media_download',
				'description' => 'It is possible to ensure that .zip, .mp3, .mp4 are treated as a download, rather than to be played by the browser.',
				'type' => 'constant',
				'input_label'=> 'Ensuring media files are downloaded instead of played',
				'input_key'=> 'media_download', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);	
			
			$htaccess_opts['redirect_www'] = array(
				'name' => 'redirect_www',
				'description' => 'Redirects example.com to www.example.com',
				'type' => 'constant',
				'input_label'=> 'Rewrite www to non www or vice versa',
				'input_key'=> 'redirect_www', 
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['canonical_url'] = array(
				'name' => 'canonical_url',
				'description' => 'Setting canonical url manually',
				'type' => 'constant',
				'input_label'=> 'Setting canonical url manually',
				'input_key'=> 'canonical_url', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	  
			);
			$htaccess_opts['trailing_slash'] = array(
				'name' => 'trailing_slash',
				'description' => 'From http://www.example.com to http://www.example.com/',
				'type' => 'constant',
				'input_label'=> 'Add Trailing Slash to URL',
				'input_key'=> 'trailing_slash', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	  
			);
			$htaccess_opts['pass_single_file'] = array(
				'name' => 'pass_single_file',
				'description' => 'A simple way to password protect single file',
				'type' => 'constant',
				'input_label'=> 'Password protected single file',
				'input_key'=> 'pass_single_file', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['single_file_name'] = array(
				'name' => 'single_file_name',
				'description' => 'File Name',
				'type' => 'variable',
				'input_label'=> 'File Name',
				'input_key'=> 'single_file_name', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'pass_single_file', 
			);
			$htaccess_opts['pass_directory'] = array(
				'name' => 'pass_directory',
				'description' => 'A simple way to password the directory in which this htaccess rule resides',
				'type' => 'constant',
				'input_label'=> 'Password protect directory',
				'input_key'=> 'pass_directory', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['directory_browsing'] = array(
				'name' => 'directory_browsing',
				'description' => 'This disallows anyone to easily sniff around the wp-content/uploads folder or any other directory which doesn’t have the default index.php file',
				'type' => 'constant',
				'input_label'=> 'Disable Directory Browsing',
				'input_key'=> 'directory_browsing', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	
			);
			$htaccess_opts['server_signature'] = array(
				'name' => 'server_signature',
				'description' => 'Here we are disabling the digital signature that would otherwise identify the server',
				'type' => 'constant',
				'input_label'=> 'Disable the Server Signature',
				'input_key'=> 'server_signature', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	  
			);
			$htaccess_opts['disable_hotlinking'] = array(
				'name' => 'disable_hotlinking',
				'description' => 'Other people can slow down your website and steal your bandwidth by hotlinking images from your website. Prevent image hotlinking',
				'type' => 'constant',
				'input_label'=> 'Disable Hotlinking',
				'input_key'=> 'disable_hotlinking', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	 
			);
			$htaccess_opts['disable_trace'] = array(
				'name' => 'disable_trace',
				'description' => 'With these rules in place, your site is protected against one more potential security vulnerability',
				'type' => 'constant',
				'input_label'=> 'Disable HTTP Trace',
				'input_key'=> 'disable_trace', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['restrict_wpincludes'] = array(
				'name' => 'restrict_wpincludes',
				'description' => 'No visitor (including you) should require access to content of the wp-include folder',
				'type' => 'constant',
				'input_label'=> 'Restrict All Access to wp-includes',
				'input_key'=> 'restrict_wpincludes', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		 
			);
			$htaccess_opts['development_redirect'] = array(
				'name' => 'development_redirect',
				'description' => 'Redirect visitors to a temporary site during site development',
				'type' => 'constant',
				'input_label'=> 'Redirect visitors to a temporary site during site development',
				'input_key'=> 'development_redirect', 	
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['redirect_url'] = array(
				'name' => 'redirect_url',
				'description' => 'Site Redirect URL',
				'type' => 'variable',
				'input_label'=> 'Site Redirect URL',
				'input_key'=> 'redirect_url', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'development_redirect', 
			);
			$htaccess_opts['allow_wpadmin'] = array(
				'name' => 'allow_wpadmin',
				'description' => 'You can allow the IPs of the people who need access to the WordPress dashboard – editors, contributors and other admins.',
				'type' => 'constant',
				'input_label'=> 'Allow only Selected IP Addresses to Access wp-admin',
				'input_key'=> 'allow_wpadmin', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['allow_wpadmin_ip'] = array(
				'name' => 'allow_wpadmin_ip',
				'description' => 'Block IP addresses - comma separated list',
				'type' => 'variable',
				'input_label'=> 'Block IP addresses - comma separated list',
				'input_key'=> 'allow_wpadmin_ip', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'allow_wpadmin', 
			);
			$htaccess_opts['protected_config'] = array(
				'name' => 'protected_config',
				'description' => 'Disable access to wp-config.php',
				'type' => 'constant',
				'input_label'=> 'Protect wp-config.php from everyone',
				'input_key'=> 'protected_config', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['protected_htaccess'] = array(
				'name' => 'protected_htaccess',
				'description' => 'Disable access to .htaccess',
				'type' => 'constant',
				'input_label'=> 'Protect .htaccess from everyone',
				'input_key'=> 'protected_htaccess', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['protect_from_xmlrpc'] = array(
				'name' => 'protect_from_xmlrpc',
				'description' => 'Restrict access to xmlrpc.php',
				'type' => 'constant',
				'input_label'=> 'Restrict access to xmlrpc.php',
				'input_key'=> 'protect_from_xmlrpc', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['caching'] = array(
				'name' => 'caching',
				'description' => 'Enable the recommended browser caching options for your WordPress site',
				'type' => 'constant',
				'input_label'=> 'Enable Browser Caching',
				'input_key'=> 'caching', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['default_page'] = array(
				'name' => 'default_page',
				'description' => 'Change your default directory page',
				'type' => 'constant',
				'input_label'=> 'Change your default directory page',
				'input_key'=> 'default_page', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	  
			);
			$htaccess_opts['default_file_name'] = array(
				'name' => 'default_file_name',
				'description' => 'Default file name',
				'type' => 'variable',
				'input_label'=> 'Default file name',
				'input_key'=> 'default_file_name', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'default_page', 
			);
			$htaccess_opts['set_charset'] = array(
				'name' => 'set_charset',
				'description' => 'Set default charset',
				'type' => 'constant',
				'input_label'=> 'Set default charset',
				'input_key'=> 'set_charset', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',		
			);
			$htaccess_opts['charset_value'] = array(
				'name' => 'charset_value',
				'description' => 'Default charset value',
				'type' => 'variable',
				'input_label'=> 'Default charset value',
				'input_key'=> 'charset_value', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'charset_value', 
			);
			$htaccess_opts['set_language'] = array(
				'name' => 'set_language',
				'description' => 'Set default language',
				'type' => 'constant',
				'input_label'=> 'Set default language',
				'input_key'=> 'set_language', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	
			);
			$htaccess_opts['language_value'] = array(
				'name' => 'language_value',
				'description' => 'Set default language',
				'type' => 'variable',
				'input_label'=> 'Set default language',
				'input_key'=> 'language_value', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'set_language', 
			);
			$htaccess_opts['error_page'] = array(
				'name' => 'error_page',
				'description' => 'Add name of .html error page for 404, 403, 500 errors',
				'type' => 'constant',
				'input_label'=> 'Custom error pages',
				'input_key'=> 'error_page', 		
				'input_type'=> 'checkbox',
				'default_value' => '0',	
			);
			$htaccess_opts['error_400'] = array(
				'name' => 'error_400',
				'description' => 'Error 400 Page File Name (Bad Request)',
				'type' => 'variable',
				'input_label'=> 'Error 400 Page File Name (Bad Request)',
				'input_key'=> 'error_400', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'error_page', 
			);
			$htaccess_opts['error_401'] = array(
				'name' => 'error_401',
				'description' => 'Error 401 Page File Name(Auth Required)',
				'type' => 'variable',
				'input_label'=> 'Error 401 Page File Name (Auth Required)',
				'input_key'=> 'error_401', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'error_page', 
			);
			$htaccess_opts['error_403'] = array(
				'name' => 'error_403',
				'description' => 'Error 403 Page File Name (Forbidden)',
				'type' => 'variable',
				'input_label'=> 'Error 403 Page File Name (Forbidden)',
				'input_key'=> 'error_403', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'error_page', 
			);
			$htaccess_opts['error_404'] = array(
				'name' => 'error_404',
				'description' => 'Error 404 Page File Name (Not Found)',
				'type' => 'variable',
				'input_label'=> 'Error 404 Page File Name (Not Found)',
				'input_key'=> 'error_404', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'error_page', 
			);
			$htaccess_opts['error_500'] = array(
				'name' => 'error_500',
				'description' => 'Error 500 Page File Name (Internal Server Error)',
				'type' => 'variable',
				'input_label'=> 'Error 500 Page File Name (Internal Server Error)',
				'input_key'=> 'error_500', 		
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'error_page', 
			);
			
			
			if(isset($inputs['block_ips']['value']) && $inputs['block_ips']['value'] == 1){
				$inputs['block_ips']['checked'] = 'checked="checked"';
			}else{
				$inputs['block_ips']['checked'] = '';
			}
			
			//populate array items with proper values if exists 
	 		foreach( $htaccess_opts as $key => $option ){
				$htaccess_opts[$key]['value'] = $this->get_wp_config_option( $option['name'] , $option['type'] );
			}
			
			return $htaccess_opts;
		}
			
			 /**
			* Returns content from site's main .htaccess file
			* 
			* @param boolean $clean_comments if set to true, strips out comments from file
			* 
			* @return string raw content from main site's .htaccess file
			*/
		    public function get_htaccess($clean_comments = false){		
			
				$source = DashboardHelpers::get_data($this->htaccess_path);
				
				$this->backup_htaccess($source); 		
				
				if($clean_comments == true){
					return DashboardHelpers::strip_comments($source);
				}
				return $source;
			}
			
			/**
			* Creates backup copy file of site's main .htaccess file 
			* 
			* @param string $source content from .htaccess file
			* 
			* @return void|boolean 
			*/
			public function backup_htaccess($source , $overwrite = false){
			 	if(empty($source)){
					return;
				}
				return DashboardHelpers::put_data($this->htaccess_backup_path , $source , false , $overwrite );
			}
			
			/**
			* Returns .htaccess content in array format 
			* 
			* @return array .htaccess content exploed into array 
			*/
			public function get_htaccess_array(){	       
		       	return DashboardHelpers::file_array( $this->htaccess_path );
			}
			
		    /**
		    * 
		    * Reverts .htaccess to previous state 
			* 
			* @param boolean $clean_comments
			* 
			* @return string
			*/
			public function get_htaccess_revert($clean_comments = false){
				
				$source = file_get_contents($this->wp_dir . '.htaccess');
				$json_file = $this->settings['sfstore'].'htaccess_revision_last.json';
				$json_content = file_get_contents($json_file);
				$json_revision = $this->settings['sfstore'].'htaccess_revision_backup.json';
				
				$this->backup_htaccess($source);
				DashboardHelpers::put_data($json_revision , $json_content);

				if($clean_comments == true){
					return DashboardHelpers::strip_comments($source);
				}
				return $source;
			}
			/**
			* Retrieves filepath to htaccess backup file
			*  
			* @return string full path to htaccess backup file
			*/
			public function get_htaccess_backups(){
				return $this->htaccess_backup_path;
			}
			
			/**
			* Saves new .htaccess file in main WordPress directory 
			* 
			* @param string $data regenerated .htaccess directives submitted from .htaccess section 
			* 
			* @return void
			*/
			public function save_htaccess_file($data = ''){
				if(empty($data))
				return;
				DashboardHelpers::put_data($this->htaccess_path , $data );
			}
			
			public function save_htaccess_revision($data = ''){
			//TODO add to convert array into json object and put into sfstore/htaccess_revisions
			
			}
	
	}
		
?>