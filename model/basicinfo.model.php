<?php

	class BasicInfoModel extends DashboardModel {
		
		//scan_plugins_directory  postoji i u plugins.model isto tako i za get_all_themes u themes.model da li da ih ubacujemo i ovde?
		public function __construct(){
	        parent::__construct();       
    	}
    	
    	/**
	    * Retrieves information from version.php file stored in wp-includes directory of WordPress site 
	    * 
	    * @return array|boolean list of items from version.php in array format or false if file doesn\'t exist
	    */   
	    function get_core_info(){
	   	$versions_file =   $this->wp_dir . 'wp-includes/version.php';
	   	
	   	if(file_exists($versions_file)){
			include_once $versions_file;	
			$versions = array();
	        $versions['wp_version'] = array(
	                                        'name'=>'WordPress Core version', 
	                                        'description'=> 'The WordPress version of your site',
	                                        'variable_name'=> '$wp_version',
	                                        'version' => $wp_version, 
	                                         );
	        $versions['wp_db_version'] = array(
	                                        'name'=>'WordPress DB revision version', 
	                                        'description'=> 'Holds the WordPress DB revision, increments when changes are made to the WordPress DB schema.',
	                                        'variable_name'=> '$wp_db_version',
	                                        'version' => $wp_db_version, 
	                                         );
	       
	        $versions['tinymce_version'] = array(
	                                        'name'=>'TinyMCE version', 
	                                        'description'=> '',
	                                        'variable_name'=> '$tinymce_version',
	                                        'version' => $tinymce_version, 
	                                         );         
	        $versions['required_php_version'] = array(
	                                        'name'=>'Required PHP version', 
	                                        'description'=> 'Minimum version of PHP so WordPress can function properly',
	                                        'variable_name'=> '$required_php_version',
	                                        'version' => $required_php_version, 
	                                         );                         
	        $versions['required_mysql_version'] = array(
	                                        'name'=>'Required MySQL version', 
	                                        'description'=> 'Minimum version of MySQL so WordPress can function properly',
	                                        'variable_name'=> '$required_mysql_version',
	                                        'version' => $required_mysql_version, 
	                                         );                                         
	        return $versions;
			}
			
			return false;
		
	    }
	   
	   
	    /**
		* Retrieves vars from php.ini and returns them in array form, each holds 'description' and 'value' indexes, while main key for array item holds actual
		* var name 
		* 
		* @return array $php_ini_vars array of available php_ini variables set in php.ini file
		*/
		public function get_php_ini_vars(){
				$php_ini_vars  = array();
				$php_ini_vars['display_errors'] = array(
					'description'=>"This determines whether errors should be printed to the screen as part of the output or if they should be hidden from the user",
				);
				$php_ini_vars['display_startup_errors'] = array(
					'description' =>"Even when display_errors is on, errors that occur during PHP's startup sequence are not displayed. It's strongly recommended to keep display_startup_errors off, except for debugging."
				);
				$php_ini_vars['log_errors'] = array(
					'description'=>"Tells whether script error messages should be logged to the server's error log or error_log. This option is thus server-specific.",
				);
				$php_ini_vars['error_reporting'] = array(
					'description'=>"Set the error reporting level. The parameter is either an integer representing a bit field, or named constants. The error_reporting levels and constants are described in Predefined Constants, and in php.ini",
				);
				$php_ini_vars['error_log'] = array(
					'description'=>"Name of the file where script errors should be logged. The file should be writable by the web server's user. If the special value syslog is used, the errors are sent to the system logger instead. ",
				);
				$php_ini_vars['register_globals'] = array(
					'description'=>"Whether or not to register the EGPCS (Environment, GET, POST, Cookie, Server) variables as global variables. ",
				);
				$php_ini_vars['file_uploads'] = array(
					'description'=>"Whether or not to allow HTTP file uploads. ",
				);
				$php_ini_vars['upload_tmp_dir'] = array(
					'description'=>"The temporary directory used for storing files when doing file upload. Must be writable by whatever user PHP is running as. If not specified PHP will use the system's default.",
				);
				$php_ini_vars['post_max_size'] = array(
					'description'=>"Sets max size of post data allowed. This setting also affects file upload. To upload large files, this value must be larger than upload_max_filesize.   Generally speaking, memory_limit should be larger than post_max_size.",
				);
				$php_ini_vars['upload_max_filesize'] = array(
					'description'=>"The maximum size of an uploaded file.",
				);
				$php_ini_vars['max_file_uploads'] = array(
					'description'=>"The maximum number of files allowed to be uploaded simultaneously. Starting with PHP 5.3.4, upload fields left blank on submission do not count towards this limit.",
				);
				$php_ini_vars['max_execution_time'] = array(
					'description'=>"This sets the maximum time in seconds a script is allowed to run before it is terminated by the parser. This helps prevent poorly written scripts from tying up the server. The default setting is 30.",
				);
				$php_ini_vars['max_input_vars'] = array(
					'description'=>"How many input variables may be accepted (limit is applied to \$_GET, \$_POST and \$_COOKIE superglobal separately). Use of this directive mitigates the possibility of denial of service attacks which use hash collisions. If there are more input variables than specified by this directive, an E_WARNING is issued, and further input variables are truncated from the request.",
				);
				$php_ini_vars['max_input_time'] = array(
					'description'=>"This sets the maximum time in seconds a script is allowed to parse input data, like POST and GET. Timing begins at the moment PHP is invoked at the server and ends when execution begins. ",
				);
				$php_ini_vars['memory_limit'] = array(
					'description'=>"This sets the maximum amount of memory in bytes that a script is allowed to allocate. This helps prevent poorly written scripts for eating up all available memory on a server.",
				);
				$php_ini_vars['auto_prepend_file'] = array(
					'description'=>"Specifies the name of a file that is automatically parsed before the main file. The file is included as if it was called with the require function, so include_path is used.",
				);
				$php_ini_vars['default_mimetype'] = array(
					'description'=>"By default, PHP will output a character encoding using the Content-Type header. To disable sending of the charset, simply set it to be empty.",
				);
				$php_ini_vars['include_path'] = array(
					'description'=>"Specifies a list of directories where the require, include, fopen(), file(), readfile() and file_get_contents() functions look for files.",
				);
				$php_ini_vars['allow_url_fopen'] = array(
					'description'=>"This option enables the URL-aware fopen wrappers that enable accessing URL object like files. ",
				);
				$php_ini_vars['allow_url_include'] = array(
					'description'=>"This option allows the use of URL-aware fopen wrappers with the following functions: include, include_once, require, require_once. ",
				);
				//iterate through $php_ini_vars array and assign value index with values from php.ini file using ini_get() function
				foreach($php_ini_vars as $key => $php_var){    
				    $php_ini_vars[$key]['value'] = ini_get($key);			    
				}
				
				return $php_ini_vars;
			}
		  /**
		  * 
		  * Retrieves variables from server array along with their description 
		  * 
		  * @return array holding variables from $_SERVER array 
		  */
		  public function get_server_options(){
			$server_vars = array();
			$server_vars['PHP_SELF'] = array('description' => "Returns the filename of the currently executing script",);
			$server_vars['GATEWAY_INTERFACE'] = array('description' => "Returns the version of the Common Gateway Interface (CGI) the server is using",);
			$server_vars['SERVER_ADDR'] = array('description' => "Returns the IP address of the host server",);
			$server_vars['SERVER_NAME'] = array('description' => "Returns the name of the host server",);
			$server_vars['SERVER_SOFTWARE'] = array('description' => "Returns the server identification string",);
			$server_vars['SERVER_PROTOCOL'] = array('description' => "Returns the name and revision of the information protocol ",);
			$server_vars['REQUEST_METHOD'] = array('description' => "Returns the request method used to access the page",);
			$server_vars['REQUEST_TIME'] = array('description' => "Returns the timestamp of the start of the request",);
			$server_vars['QUERY_STRING'] = array('description' => "Returns the query string if the page is accessed via a query string",);
			$server_vars['HTTP_ACCEPT'] = array('description' => "Returns the Accept header from the current request",);
			//$server_vars['HTTP_ACCEPT_CHARSET'] = array('description' => "Returns the Accept_Charset header from the current request",);
			$server_vars['HTTP_HOST'] = array('description' => "Returns the Host header from the current request",);
			//$server_vars['HTTP_REFERER'] = array('description' => "Returns the complete URL of the current page",);
			//$server_vars['HTTPS'] = array('description' => "Is the script queried through a secure HTTP protocol",);
			$server_vars['REMOTE_ADDR'] = array('description' => "Returns the IP address from where the user is viewing the current page",);
			//$server_vars['REMOTE_HOST'] = array('description' => "Returns the Host name from where the user is viewing the current page",);
			$server_vars['REMOTE_PORT'] = array('description' => "Returns the port being used on the user's machine to communicate with the web server",);
			$server_vars['SCRIPT_FILENAME'] = array('description' => "Returns the absolute pathname of the currently executing script",);
			$server_vars['SERVER_ADMIN'] = array('description' => "Returns the value given to the SERVER_ADMIN directive in the web server configuration file (if your script runs on a virtual host, it will be the value defined for that virtual host)",);
			$server_vars['SERVER_PORT'] = array('description' => "Returns the port on the server machine being used by the web server for communication",);
			$server_vars['SERVER_SIGNATURE'] = array('description' => "Returns the server version and virtual host name which are added to server-generated pages",);
			$server_vars['PATH_TRANSLATED'] = array('description' => "Returns the file system based path to the current script",);
			$server_vars['SCRIPT_NAME'] = array('description' => "Returns the path of the current script",);
			//$server_vars['SCRIPT_URI'] = array('description' => "Returns the URI of the current page",);
			
			foreach($server_vars as $key => $server_var){    
				    $server_vars[$key]['value'] = $_SERVER[$key];			    
				}
				
			return $server_vars;
		}
	}
?>