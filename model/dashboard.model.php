<?php
/**
 * 
 */


/**
 * 
 */
class DashboardModel extends dbModel
{

	private $config;
	protected $settings;
	protected $db_prefix;
	protected $wp_dir;
	protected $wp_config_path;
	protected $wp_config_backup_path;
	protected $robots_path;
	protected $wp_config;
	protected $htaccess_path;
	protected $htaccess_backup_path;
	protected $htaccess;
	protected $wp_config_array;
	protected $default_themes;

	/**
	 * Constructor - initialize dashboard model
	 * 
	 * @param Config|null $config Configuration instance
	 */
	public function __construct($config = null)
	{
		$this->config = $config ?? Config::getInstance();
		parent::__construct($this->config);

		$this->settings = $this->config->all();
		$this->db_prefix = $this->settings['wp_db_prefix'] ?? 'wp_';
		$this->wp_dir = $this->settings['wp_dir'] ?? '../';
		$this->wp_config_path = $this->wp_dir . "wp-config.php";
		$this->wp_config_backup_path = $this->settings['sfstore'] . 'wp-config-safemode-backup.php';

		$this->robots_path = $this->wp_dir . "robots.txt";
		$this->wp_config = $this->get_wp_config();
		$this->htaccess_path = $this->wp_dir . '.htaccess';
		$this->htaccess_backup_path = $this->settings['sfstore'] . '.htaccess.safemode.backup';
		$this->htaccess = $this->get_htaccess();
		$this->wp_config_array = $this->get_wp_config_array();
		$this->default_themes = array(
			'twentyfifteen' => 'Twenty Fifteen',
			'twentyfourteen' => 'Twenty Fourteen',
			'twentythirteen' => 'Twenty Thirteen',
			'twentytwelve' => 'Twenty Twelve',
		);
	}
	/**
	 * Returns htaccess options for .htaccess section in array format. Each item holds several keys so we can use to build a form.  
	 * 
	 * @return array $htaccess_opts that holds array of htaccess items available to setup in .htaccess section
	 * 
	 */
	public function get_htaccess_options()
	{

		$htaccess_opts = array();
		$htaccess_opts['block_ips'] = array(
			'name' => 'block_ips',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Block IPs',
			'input_key' => 'block_ips',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$htaccess_opts['bad_ips'] = array(
			'name' => 'bad_ips',
			'description' => 'Block IPs, Comma separated list',
			'type' => 'variable',
			'input_label' => 'Bad IPs',
			'input_key' => 'bad_ips',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'block_ips',
		);
		$htaccess_opts['block_bots'] = array(
			'name' => 'block_bots',
			'description' => 'Block bots from your website',
			'type' => 'constant',
			'input_label' => 'Block bots',
			'input_key' => 'block_bots',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$htaccess_opts['block_hidden'] = array(
			'name' => 'block_hidden',
			'description' => 'Block access to hidden files & directories',
			'type' => 'constant',
			'input_label' => 'Block access to hidden files & directories',
			'input_key' => 'block_hidden',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$htaccess_opts['block_source'] = array(
			'name' => 'block_source',
			'description' => 'Block access to source files',
			'type' => 'constant',
			'input_label' => 'Block access to source files',
			'input_key' => 'block_source',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$htaccess_opts['old_domain'] = array(
			'name' => 'old_domain',
			'description' => 'Using 301 redirects for whole site',
			'type' => 'constant',
			'input_label' => 'Redirect from one domain to another',
			'input_key' => 'old_domain',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$htaccess_opts['new_domain'] = array(
			'name' => 'new_domain',
			'description' => 'New domain',
			'type' => 'variable',
			'input_label' => 'New domain',
			'input_key' => 'new_domain',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'old_domain',
		);
		$htaccess_opts['deny_referrer'] = array(
			'name' => 'deny_referrer',
			'description' => 'Deny visitors by referrer',
			'type' => 'constant',
			'input_label' => 'Deny visitors by referrer',
			'input_key' => 'deny_referrer',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$htaccess_opts['referrer'] = array(
			'name' => 'referrer',
			'description' => 'Referrer domain',
			'type' => 'variable',
			'input_label' => 'Referrer domain',
			'input_key' => 'referrer',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'deny_referrer',
		);
		$htaccess_opts['media_download'] = array(
			'name' => 'media_download',
			'description' => 'It is possible to ensure that .zip, .mp3, .mp4 are treated as a download, rather than to be played by the browser.',
			'type' => 'constant',
			'input_label' => 'Ensuring media files are downloaded instead of played',
			'input_key' => 'media_download',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);

		$htaccess_opts['redirect_www'] = array(
			'name' => 'redirect_www',
			'description' => 'Redirects example.com to www.example.com',
			'type' => 'constant',
			'input_label' => 'Rewrite www to non www or vice versa',
			'input_key' => 'redirect_www',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['canonical_url'] = array(
			'name' => 'canonical_url',
			'description' => 'Setting canonical url manually',
			'type' => 'constant',
			'input_label' => 'Setting canonical url manually',
			'input_key' => 'canonical_url',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['trailing_slash'] = array(
			'name' => 'trailing_slash',
			'description' => 'From http://www.example.com to http://www.example.com/',
			'type' => 'constant',
			'input_label' => 'Add Trailing Slash to URL',
			'input_key' => 'trailing_slash',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['pass_single_file'] = array(
			'name' => 'pass_single_file',
			'description' => 'A simple way to password protect single file',
			'type' => 'constant',
			'input_label' => 'Password protected single file',
			'input_key' => 'pass_single_file',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['single_file_name'] = array(
			'name' => 'single_file_name',
			'description' => 'File Name',
			'type' => 'variable',
			'input_label' => 'File Name',
			'input_key' => 'single_file_name',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'pass_single_file',
		);
		$htaccess_opts['pass_directory'] = array(
			'name' => 'pass_directory',
			'description' => 'A simple way to password the directory in which this htaccess rule resides',
			'type' => 'constant',
			'input_label' => 'Password protect directory',
			'input_key' => 'pass_directory',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['directory_browsing'] = array(
			'name' => 'directory_browsing',
			'description' => 'This disallows anyone to easily sniff around the wp-content/uploads folder or any other directory which doesn’t have the default index.php file',
			'type' => 'constant',
			'input_label' => 'Disable Directory Browsing',
			'input_key' => 'directory_browsing',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['server_signature'] = array(
			'name' => 'server_signature',
			'description' => 'Here we are disabling the digital signature that would otherwise identify the server',
			'type' => 'constant',
			'input_label' => 'Disable the Server Signature',
			'input_key' => 'server_signature',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['disable_hotlinking'] = array(
			'name' => 'disable_hotlinking',
			'description' => 'Other people can slow down your website and steal your bandwidth by hotlinking images from your website. Prevent image hotlinking',
			'type' => 'constant',
			'input_label' => 'Disable Hotlinking',
			'input_key' => 'disable_hotlinking',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['disable_trace'] = array(
			'name' => 'disable_trace',
			'description' => 'With these rules in place, your site is protected against one more potential security vulnerability',
			'type' => 'constant',
			'input_label' => 'Disable HTTP Trace',
			'input_key' => 'disable_trace',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['restrict_wpincludes'] = array(
			'name' => 'restrict_wpincludes',
			'description' => 'No visitor (including you) should require access to content of the wp-include folder',
			'type' => 'constant',
			'input_label' => 'Restrict All Access to wp-includes',
			'input_key' => 'restrict_wpincludes',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['development_redirect'] = array(
			'name' => 'development_redirect',
			'description' => 'Redirect visitors to a temporary site during site development',
			'type' => 'constant',
			'input_label' => 'Redirect visitors to a temporary site during site development',
			'input_key' => 'development_redirect',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['redirect_url'] = array(
			'name' => 'redirect_url',
			'description' => 'Site Redirect URL',
			'type' => 'variable',
			'input_label' => 'Site Redirect URL',
			'input_key' => 'redirect_url',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'development_redirect',
		);
		$htaccess_opts['allow_wpadmin'] = array(
			'name' => 'allow_wpadmin',
			'description' => 'You can allow the IPs of the people who need access to the WordPress dashboard – editors, contributors and other admins.',
			'type' => 'constant',
			'input_label' => 'Allow only Selected IP Addresses to Access wp-admin',
			'input_key' => 'allow_wpadmin',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['allow_wpadmin_ip'] = array(
			'name' => 'allow_wpadmin_ip',
			'description' => 'Block IP addresses - comma separated list',
			'type' => 'variable',
			'input_label' => 'Block IP addresses - comma separated list',
			'input_key' => 'allow_wpadmin_ip',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'allow_wpadmin',
		);
		$htaccess_opts['protected_config'] = array(
			'name' => 'protected_config',
			'description' => 'Disable access to wp-config.php',
			'type' => 'constant',
			'input_label' => 'Protect wp-config.php from everyone',
			'input_key' => 'protected_config',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['protected_htaccess'] = array(
			'name' => 'protected_htaccess',
			'description' => 'Disable access to .htaccess',
			'type' => 'constant',
			'input_label' => 'Protect .htaccess from everyone',
			'input_key' => 'protected_htaccess',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['protect_from_xmlrpc'] = array(
			'name' => 'protect_from_xmlrpc',
			'description' => 'Restrict access to xmlrpc.php',
			'type' => 'constant',
			'input_label' => 'Restrict access to xmlrpc.php',
			'input_key' => 'protect_from_xmlrpc',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['caching'] = array(
			'name' => 'caching',
			'description' => 'Enable the recommended browser caching options for your WordPress site',
			'type' => 'constant',
			'input_label' => 'Enable Browser Caching',
			'input_key' => 'caching',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['default_page'] = array(
			'name' => 'default_page',
			'description' => 'Change your default directory page',
			'type' => 'constant',
			'input_label' => 'Change your default directory page',
			'input_key' => 'default_page',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['default_file_name'] = array(
			'name' => 'default_file_name',
			'description' => 'Default file name',
			'type' => 'variable',
			'input_label' => 'Default file name',
			'input_key' => 'default_file_name',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'default_page',
		);
		$htaccess_opts['set_charset'] = array(
			'name' => 'set_charset',
			'description' => 'Set default charset',
			'type' => 'constant',
			'input_label' => 'Set default charset',
			'input_key' => 'set_charset',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['charset_value'] = array(
			'name' => 'charset_value',
			'description' => 'Default charset value',
			'type' => 'variable',
			'input_label' => 'Default charset value',
			'input_key' => 'charset_value',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'charset_value',
		);
		$htaccess_opts['set_language'] = array(
			'name' => 'set_language',
			'description' => 'Set default language',
			'type' => 'constant',
			'input_label' => 'Set default language',
			'input_key' => 'set_language',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['language_value'] = array(
			'name' => 'language_value',
			'description' => 'Set default language',
			'type' => 'variable',
			'input_label' => 'Set default language',
			'input_key' => 'language_value',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'set_language',
		);
		$htaccess_opts['error_page'] = array(
			'name' => 'error_page',
			'description' => 'Add name of .html error page for 404, 403, 500 errors',
			'type' => 'constant',
			'input_label' => 'Custom error pages',
			'input_key' => 'error_page',
			'input_type' => 'checkbox',
			'default_value' => '0',
		);
		$htaccess_opts['error_400'] = array(
			'name' => 'error_400',
			'description' => 'Error 400 Page File Name (Bad Request)',
			'type' => 'variable',
			'input_label' => 'Error 400 Page File Name (Bad Request)',
			'input_key' => 'error_400',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'error_page',
		);
		$htaccess_opts['error_401'] = array(
			'name' => 'error_401',
			'description' => 'Error 401 Page File Name(Auth Required)',
			'type' => 'variable',
			'input_label' => 'Error 401 Page File Name (Auth Required)',
			'input_key' => 'error_401',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'error_page',
		);
		$htaccess_opts['error_403'] = array(
			'name' => 'error_403',
			'description' => 'Error 403 Page File Name (Forbidden)',
			'type' => 'variable',
			'input_label' => 'Error 403 Page File Name (Forbidden)',
			'input_key' => 'error_403',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'error_page',
		);
		$htaccess_opts['error_404'] = array(
			'name' => 'error_404',
			'description' => 'Error 404 Page File Name (Not Found)',
			'type' => 'variable',
			'input_label' => 'Error 404 Page File Name (Not Found)',
			'input_key' => 'error_404',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'error_page',
		);
		$htaccess_opts['error_500'] = array(
			'name' => 'error_500',
			'description' => 'Error 500 Page File Name (Internal Server Error)',
			'type' => 'variable',
			'input_label' => 'Error 500 Page File Name (Internal Server Error)',
			'input_key' => 'error_500',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'error_page',
		);


		if (isset($inputs['block_ips']['value']) && $inputs['block_ips']['value'] == 1) {
			$inputs['block_ips']['checked'] = 'checked="checked"';
		} else {
			$inputs['block_ips']['checked'] = '';
		}

		//populate array items with proper values if exists 
		foreach ($htaccess_opts as $key => $option) {
			$htaccess_opts[$key]['value'] = $this->get_wp_config_option($option['name'], $option['type']);
		}

		return $htaccess_opts;
	}
	/**
	 * Retrieves vars from php.ini and returns them in array form, each holds 'description' and 'value' indexes, while main key for array item holds actual
	 * var name 
	 * 
	 * @return array $php_ini_vars array of available php_ini variables set in php.ini file
	 */
	public function get_php_ini_vars()
	{
		$php_ini_vars = array();
		$php_ini_vars['display_errors'] = array(
			'description' => "This determines whether errors should be printed to the screen as part of the output or if they should be hidden from the user",
		);
		$php_ini_vars['display_startup_errors'] = array(
			'description' => "Even when display_errors is on, errors that occur during PHP's startup sequence are not displayed. It's strongly recommended to keep display_startup_errors off, except for debugging."
		);
		$php_ini_vars['log_errors'] = array(
			'description' => "Tells whether script error messages should be logged to the server's error log or error_log. This option is thus server-specific.",
		);
		$php_ini_vars['error_reporting'] = array(
			'description' => "Set the error reporting level. The parameter is either an integer representing a bit field, or named constants. The error_reporting levels and constants are described in Predefined Constants, and in php.ini",
		);
		$php_ini_vars['error_log'] = array(
			'description' => "Name of the file where script errors should be logged. The file should be writable by the web server's user. If the special value syslog is used, the errors are sent to the system logger instead. ",
		);
		$php_ini_vars['register_globals'] = array(
			'description' => "Whether or not to register the EGPCS (Environment, GET, POST, Cookie, Server) variables as global variables. ",
		);
		$php_ini_vars['file_uploads'] = array(
			'description' => "Whether or not to allow HTTP file uploads. ",
		);
		$php_ini_vars['upload_tmp_dir'] = array(
			'description' => "The temporary directory used for storing files when doing file upload. Must be writable by whatever user PHP is running as. If not specified PHP will use the system's default.",
		);
		$php_ini_vars['post_max_size'] = array(
			'description' => "Sets max size of post data allowed. This setting also affects file upload. To upload large files, this value must be larger than upload_max_filesize.   Generally speaking, memory_limit should be larger than post_max_size.",
		);
		$php_ini_vars['upload_max_filesize'] = array(
			'description' => "The maximum size of an uploaded file.",
		);
		$php_ini_vars['max_file_uploads'] = array(
			'description' => "The maximum number of files allowed to be uploaded simultaneously. Starting with PHP 5.3.4, upload fields left blank on submission do not count towards this limit.",
		);
		$php_ini_vars['max_execution_time'] = array(
			'description' => "This sets the maximum time in seconds a script is allowed to run before it is terminated by the parser. This helps prevent poorly written scripts from tying up the server. The default setting is 30.",
		);
		$php_ini_vars['max_input_vars'] = array(
			'description' => "How many input variables may be accepted (limit is applied to \$_GET, \$_POST and \$_COOKIE superglobal separately). Use of this directive mitigates the possibility of denial of service attacks which use hash collisions. If there are more input variables than specified by this directive, an E_WARNING is issued, and further input variables are truncated from the request.",
		);
		$php_ini_vars['max_input_time'] = array(
			'description' => "This sets the maximum time in seconds a script is allowed to parse input data, like POST and GET. Timing begins at the moment PHP is invoked at the server and ends when execution begins. ",
		);
		$php_ini_vars['memory_limit'] = array(
			'description' => "This sets the maximum amount of memory in bytes that a script is allowed to allocate. This helps prevent poorly written scripts for eating up all available memory on a server.",
		);
		$php_ini_vars['auto_prepend_file'] = array(
			'description' => "Specifies the name of a file that is automatically parsed before the main file. The file is included as if it was called with the require function, so include_path is used.",
		);
		$php_ini_vars['default_mimetype'] = array(
			'description' => "By default, PHP will output a character encoding using the Content-Type header. To disable sending of the charset, simply set it to be empty.",
		);
		$php_ini_vars['include_path'] = array(
			'description' => "Specifies a list of directories where the require, include, fopen(), file(), readfile() and file_get_contents() functions look for files.",
		);
		$php_ini_vars['allow_url_fopen'] = array(
			'description' => "This option enables the URL-aware fopen wrappers that enable accessing URL object like files. ",
		);
		$php_ini_vars['allow_url_include'] = array(
			'description' => "This option allows the use of URL-aware fopen wrappers with the following functions: include, include_once, require, require_once. ",
		);
		//iterate through $php_ini_vars array and assign value index with values from php.ini file using ini_get() function
		foreach ($php_ini_vars as $key => $php_var) {
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
	public function get_server_options()
	{
		$server_vars = array();
		$server_vars['PHP_SELF'] = array('description' => "Returns the filename of the currently executing script", );
		$server_vars['GATEWAY_INTERFACE'] = array('description' => "Returns the version of the Common Gateway Interface (CGI) the server is using", );
		$server_vars['SERVER_ADDR'] = array('description' => "Returns the IP address of the host server", );
		$server_vars['SERVER_NAME'] = array('description' => "Returns the name of the host server", );
		$server_vars['SERVER_SOFTWARE'] = array('description' => "Returns the server identification string", );
		$server_vars['SERVER_PROTOCOL'] = array('description' => "Returns the name and revision of the information protocol ", );
		$server_vars['REQUEST_METHOD'] = array('description' => "Returns the request method used to access the page", );
		$server_vars['REQUEST_TIME'] = array('description' => "Returns the timestamp of the start of the request", );
		$server_vars['QUERY_STRING'] = array('description' => "Returns the query string if the page is accessed via a query string", );
		$server_vars['HTTP_ACCEPT'] = array('description' => "Returns the Accept header from the current request", );
		//$server_vars['HTTP_ACCEPT_CHARSET'] = array('description' => "Returns the Accept_Charset header from the current request",);
		$server_vars['HTTP_HOST'] = array('description' => "Returns the Host header from the current request", );
		//$server_vars['HTTP_REFERER'] = array('description' => "Returns the complete URL of the current page",);
		//$server_vars['HTTPS'] = array('description' => "Is the script queried through a secure HTTP protocol",);
		$server_vars['REMOTE_ADDR'] = array('description' => "Returns the IP address from where the user is viewing the current page", );
		//$server_vars['REMOTE_HOST'] = array('description' => "Returns the Host name from where the user is viewing the current page",);
		$server_vars['REMOTE_PORT'] = array('description' => "Returns the port being used on the user's machine to communicate with the web server", );
		$server_vars['SCRIPT_FILENAME'] = array('description' => "Returns the absolute pathname of the currently executing script", );
		$server_vars['SERVER_ADMIN'] = array('description' => "Returns the value given to the SERVER_ADMIN directive in the web server configuration file (if your script runs on a virtual host, it will be the value defined for that virtual host)", );
		$server_vars['SERVER_PORT'] = array('description' => "Returns the port on the server machine being used by the web server for communication", );
		$server_vars['SERVER_SIGNATURE'] = array('description' => "Returns the server version and virtual host name which are added to server-generated pages", );
		$server_vars['PATH_TRANSLATED'] = array('description' => "Returns the file system based path to the current script", );
		$server_vars['SCRIPT_NAME'] = array('description' => "Returns the path of the current script", );
		//$server_vars['SCRIPT_URI'] = array('description' => "Returns the URI of the current page",);

		foreach ($server_vars as $key => $server_var) {
			$server_vars[$key]['value'] = $_SERVER[$key];
		}

		return $server_vars;
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
	public function get_wp_config_options()
	{
		$wpconfig_opts = array();
		//to do, add group, fieldset 
		$wpconfig_opts['wp_debug'] = array(
			'name' => 'WP_DEBUG',
			'description' => 'WordPress debug mode for developers',
			'type' => 'constant',
			'input_label' => 'WP Debug',
			'input_key' => 'wp_debug',
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
			'default_value' => true,
			'value_type' => 'boolean',
		);
		$wpconfig_opts['automatic_updater_disabled'] = array(
			'name' => 'AUTOMATIC_UPDATER_DISABLED',
			'description' => 'Disable automatic updater for plugins and themes',
			'type' => 'constant',
			'input_label' => 'Disable Automatic Updater',
			'input_key' => 'automatic_updater_disabled',
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
			'default_value' => false,
			'value_type' => 'boolean',
		);
		$wpconfig_opts['wp_auto_update_core'] = array(
			'name' => 'WP_AUTO_UPDATE_CORE',
			'description' => 'Enable WP core auto update',
			'type' => 'constant',
			'input_label' => 'WP Auto Update Core',
			'input_key' => 'wp_auto_update_core',
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
			'default_value' => false,
			'value_type' => 'boolean',
		);
		$wpconfig_opts['db_name'] = array(
			'name' => 'DB_NAME',
			'description' => 'The name of the database for WordPress',
			'type' => 'constant',
			'input_label' => 'DB Name',
			'input_key' => 'db_name',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
		);
		$wpconfig_opts['table_prefix'] = array(
			'name' => 'table_prefix',
			'description' => 'Prefix for your wordpress table',
			'type' => 'variable',
			'input_label' => 'DB table prefix',
			'input_key' => 'table_prefix',
			'input_type' => 'text',
			'default_value' => 'wp_',
			'value_type' => 'string',
		);
		$wpconfig_opts['backuped_table_prefix'] = array(
			'name' => 'backuped_table_prefix',
			'description' => 'Prefix for your backuped wordpress table',
			'type' => 'variable',
			'input_label' => 'DB backuped table prefix',
			'input_key' => 'backuped_table_prefix',
			'input_type' => 'text',
			'default_value' => 'bck_',
			'value_type' => 'string',
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
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
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
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
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
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
			'default_value' => true,
			'value_type' => 'boolean',
		);
		$wpconfig_opts['wp_debug_log'] = array(
			'name' => 'WP_DEBUG_LOG',
			'description' => 'Enable Debug logging to the /wp-content/debug.log file',
			'type' => 'constant',
			'input_label' => 'Logging to log file',
			'input_key' => 'debug_log',
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
			'default_value' => true,
			'value_type' => 'boolean',
		);
		$wpconfig_opts['wp_debug_display'] = array(
			'name' => 'WP_DEBUG_DISPLAY',
			'description' => 'Disable display of errors and warnings',
			'type' => 'constant',
			'input_label' => 'Display of errors and warnings',
			'input_key' => 'debug_display',
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
			'default_value' => false,
			'value_type' => 'boolean',
		);
		$wpconfig_opts['concatenate_scripts'] = array(
			'name' => 'CONCATENATE_SCRIPTS',
			'description' => 'Enable/Disable JS Concatenation',
			'type' => 'constant',
			'input_label' => 'JS Concatenation',
			'input_key' => 'concatenate_scripts',
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
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
			'allowed_values' => array(true, false),
			'input_type' => 'switch',
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
		foreach ($wpconfig_opts as $key => $option) {
			$wpconfig_opts[$key]['value'] = $this->get_wp_config_option($option['name'], $option['type']);
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
	public function get_wp_config_option($name = '', $type = 'constant')
	{


		if (empty($name)) {
			return;
		}


		if ($type == 'constant') {
			if (defined($name)) {
				return constant($name);
			}
		}
		if ($type == 'variable') {
			global ${$name};

			if (isset(${$name})) {
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
	public function get_wp_config($clean_comments = false)
	{


		$source = DashboardHelpers::get_data($this->wp_config_path);

		$this->backup_wp_config($source);

		if ($clean_comments == true) {
			return DashboardHelpers::strip_comments($source);
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
	public function backup_wp_config($source = '')
	{
		if (empty($source))
			return false;

		return DashboardHelpers::put_data($this->wp_config_backup_path, $source, false, false);
	}

	/**
	 * Returns content from wp-config.php with lines converted in array items
	 * 
	 * @return array 
	 */
	function get_wp_config_array()
	{

		return DashboardHelpers::file_array($this->wp_config_path);

	}

	/**
	 * 
	 * Writes new rebuilt content in wp-config.php file 
	 * 
	 * @param string $data rebuilt wp-config.php content 
	 * 
	 * @return void
	 */
	function save_wpconfig($data = '')
	{
		if (empty($data))
			return false;

		return DashboardHelpers::put_data($this->wp_config_path, $data);
	}


	/**
	 * Retrieves a part of php error_log file 
	 * 
	 * @param integer $page number of current page
	 * @param integer $lines number of lines/records shown per page
	 * @param string $search search string to look up in error_log file
	 * 
	 * @return array formatted error_log lines with headers 
	 */
	public function get_error_log($page = 1, $lines = 20, $search = '')
	{
		$error_file = ini_get('error_log');

		if ($page == 1) {
			$seek = 0;
		} else {
			$seek = (($page * $lines) - ($lines + 1));
		}
		$headers = array('Date', 'Type', 'Message');
		$rows = array();
		if (file_exists($error_file)) {
			$number_lines = DashboardHelpers::number_lines_file($error_file, $search); //TODO set offset page show only 20 page links 
			$file = new SplFileObject($error_file);
			$file->seek($seek);
			$i = 0;
			while ($line = $file->fgets()) {
				if ($i < $lines) {
					if ((!empty($search) && stristr($line, $search)) || empty($search)) {

						$row = array();
						$date_array = explode("]", $line);
						$date = str_replace("[", '', $date_array);
						$row[] = $date[0];
						$type = explode(":  ", $date[1]);
						$row[] = $type[0];
						$message = $type[1];
						$row[] = $message;

						$rows[] = $row;
						$i++;
					}
				} else {
					break;
				}

			}
			$results = array('headers' => $headers, 'rows' => $rows, 'number_lines' => $number_lines);
			return $results;
		}

		return "Error log is empty";
	}

	/**
	 * Returns content from site's main .htaccess file
	 * 
	 * @param boolean $clean_comments if set to true, strips out comments from file
	 * 
	 * @return string raw content from main site's .htaccess file
	 */
	public function get_htaccess($clean_comments = false)
	{

		$source = DashboardHelpers::get_data($this->htaccess_path);

		$this->backup_htaccess($source);

		if ($clean_comments == true) {
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
	public function backup_htaccess($source, $overwrite = false)
	{
		if (empty($source)) {
			return;
		}
		return DashboardHelpers::put_data($this->htaccess_backup_path, $source, false, $overwrite);
	}

	/**
	 * Returns .htaccess content in array format 
	 * 
	 * @return array .htaccess content exploed into array 
	 */
	public function get_htaccess_array()
	{
		return DashboardHelpers::file_array($this->htaccess_path);
	}

	/**
	 * 
	 * Reverts .htaccess to previous state 
	 * 
	 * @param boolean $clean_comments
	 * 
	 * @return string
	 */
	public function get_htaccess_revert($clean_comments = false)
	{

		$source = file_get_contents($this->wp_dir . '.htaccess');
		$json_file = $this->settings['sfstore'] . 'htaccess_revision_last.json';
		$json_content = file_get_contents($json_file);
		$json_revision = $this->settings['sfstore'] . 'htaccess_revision_backup.json';

		$this->backup_htaccess($source);
		DashboardHelpers::put_data($json_revision, $json_content);

		if ($clean_comments == true) {
			return DashboardHelpers::strip_comments($source);
		}
		return $source;
	}



	/**
	 * 
	 * Returns active plugins data in serialized format
	 * 
	 * @return string value from option_value for active_plugins option
	 */
	/**
	 * Get active plugins with caching
	 * 
	 * @return array
	 */
	public function get_active_plugins()
	{
		$cache = Cache::getInstance();
		return $cache->remember('active_plugins', function () {
			return $this->get_active_plugins_uncached();
		}, 300); // Cache for 5 minutes
	}

	/**
	 * Get active plugins (uncached)
	 * 
	 * @return array
	 */
	private function get_active_plugins_uncached(): array
	{
		// Use secure select helper
		$results = $this->select($this->db_prefix . 'options', ['option_name' => 'active_plugins']);

		if (empty($results)) {
			return array();
		}

		$result = $results[0];
		$plugins = unserialize($result['option_value']);
		if (empty($plugins) || !is_array($plugins)) {
			return array();
		}
		return $plugins;
	}

	/**
	 * 
	 * Reverts active plugins using active_plugins.txt if exist 
	 * 
	 * @return boolean
	 */
	public function save_revert_plugins()
	{
		$plugins_file = $this->settings['sfstore'] . 'active_plugins.txt';


		$file_contents = file_get_contents($plugins_file);
		$file_contents = DashboardHelpers::get_data($plugins_file);
		if ($file_contents && !empty($file_contents)) {
			$this->save_plugins($file_contents);
			return true;
		}
		return false;


	}

	/**
	 * 
	 * Creates backup of an initial list of active plugins in serialized format 
	 * 
	 * @return boolean|void
	 */
	public function backup_active_plugins_list()
	{
		$sfstore = $this->settings['sfstore'];
		$file_backup = $sfstore . 'active_plugins.txt';
		$active_plugins = $this->get_active_plugins();
		if ($active_plugins) {
			return DashboardHelpers::put_data($file_backup, $active_plugins['option_value'], false, true);

		}
	}
	/**
	 * Does the cleanup of plugin information comment block being hold in index.php file of each plugin 
	 * 
	 * @param string $info comment that holds plugin information
	 * 
	 * @return string cleaned plugin information 
	 */
	function plugin_info_cleanup($info = '')
	{
		$info = strip_tags($info);
		$info = str_replace(array('\/*', '*\/', '"', "'"), '', $info);
		$info = $info;
		return $info;
	}

	/**
	 * Scans main site directory and looks up for plugins retrieves data information from its index files and returns an array of all plugins that 
	 * exist in WordPress site dir 
	 * 
	 * @param string $wordpress_dir path to main WordPress site directory
	 * 
	 * @return array list of plugins with following data in array format 
	 */
	function scan_plugins_directory($wordpress_dir = '')
	{

		//todo checkout _transient_plugin_slugs in wp_options, maybe it is faster , maybe first try to read that option, and then do the scan if empty
		$count = 0;
		$all_plugins_arr = array();
		foreach (glob($wordpress_dir . 'wp-content/plugins/*') as $dir) {
			if (is_file($dir) && strstr($dir, 'hello')) {
				$plugin_dir = str_replace($wordpress_dir . 'wp-content/plugins/', '', $dir);
				$plugin_path = $plugin_dir;
				$all_plugins_arr[$plugin_path]['name'] = 'Hello Dolly';
				$filecontents = file_get_contents($dir);
				preg_match_all('/\/\*(.*)\*\//sU', $filecontents, $filecontents_arr);
				$found_plugin_info = false;
				foreach ($filecontents_arr as $filecontent) {
					foreach ($filecontent as $filecontent_data) {
						if (strstr($filecontent_data, 'Plugin Name:') && strstr($filecontent_data, 'Version:') && $found_plugin_info == false) {
							$file_info = $filecontent_data;
							$all_plugins_arr[$plugin_path]['info'] = $this->plugin_info_cleanup($file_info);
							$found_plugin_info = true;
						}
					}
				}
			} elseif (is_dir($dir)) {
				$found_plugin_info = false;
				$plugin_dir = str_replace($wordpress_dir . 'wp-content/plugins/', '', $dir);
				foreach (glob($dir . '/*.php') as $filename) {
					if ($found_plugin_info == false) {
						$filecontents = file_get_contents($filename);
						if (strstr($filecontents, 'Plugin Name:') && strstr($filecontents, 'Version:')) {
							$plugin_main_file = str_replace($wordpress_dir . 'wp-content/plugins/', '', $filename);
							$count++;
							preg_match_all('/\/\*(.*)\*\//sU', $filecontents, $filecontents_arr);
							//echo '<pre>'.print_r($filecontents_arr,true).'</pre>'."<br>";
							foreach ($filecontents_arr as $filecontent) {
								foreach ($filecontent as $filecontent_data) {
									if (strstr($filecontent_data, 'Plugin Name:') && strstr($filecontent_data, 'Version:') && $found_plugin_info == false) {
										$file_info = $filecontent_data;
										$filecontent_data = str_replace(array("\*\/", "\/\*"), '', $filecontent_data);
										$filecontent_data = str_replace(array("\n", '#', '*'), PHP_EOL, $filecontent_data);
										$plugin_data_arr = explode(PHP_EOL, $filecontent_data);
										foreach ($plugin_data_arr as $plugin_data_line) {
											if (strstr($plugin_data_line, 'Plugin Name:')) {

												$plugin_name = str_replace('Plugin Name: ', '', $plugin_data_line);
												$plugin_name = trim($plugin_name);
											}
											//  echo $plugin_data_line . "<br/>\n";
										}
										foreach ($plugin_data_arr as $plugin_data_line) {
											if (strstr($plugin_data_line, 'Version:')) {

												$plugin_version = str_replace('Version: ', '', $plugin_data_line);
												$plugin_version = trim($plugin_version);
											}
											//  echo $plugin_data_line . "<br/>\n";
										}
										$found_plugin_info = true;
									}
								}
							}
						}
					}


				}
				$plugin_path = $plugin_main_file;
				$all_plugins_arr[$plugin_path]['name'] = $plugin_name;
				$all_plugins_arr[$plugin_path]['version'] = $plugin_version;
				$all_plugins_arr[$plugin_path]['info'] = $this->plugin_info_cleanup($file_info);


			}
		}

		return $all_plugins_arr;
	}

	/**
	 * 
	 * Updates active_plugins option in options table with new value that hols new list of active plugins 
	 * 
	 * @param string $option_value
	 * @param boolean $serialize default false
	 * 
	 * @return void
	 */
	/**
	 * Save plugins and clear cache
	 * 
	 * @param string|array $option_value Plugin data
	 * @param bool $serialize Whether to serialize
	 * @return bool
	 */
	public function save_plugins($option_value = '', $serialize = false): bool
	{
		if (empty($option_value)) {
			return false;
		}
		if (is_array($option_value)) {
			if ($serialize == false) {
				$option_value = serialize($option_value);
			} else {
				$option_value = json_encode($option_value);
			}
		}
		// SECURITY FIX: Use parameter binding to prevent SQL injection
		$q = $this->prepare("UPDATE `" . $this->db_prefix . "options` SET option_value = :option_value WHERE option_name = 'active_plugins'");
		$q->bindParam(':option_value', $option_value, PDO::PARAM_STR);
		$q->execute();

		// Clear cache after saving
		$cache = Cache::getInstance();
		$cache->delete('active_plugins');

		return true;
	}

	/**
	 * 
	 * Empties option_value for active_plugins option in options table. This disables all active plugins at once 
	 * 
	 * @return void
	 */
	public function disable_all_plugins()
	{
		$q = $this->prepare("UPDATE " . $this->db_prefix . "options SET option_value = '' WHERE option_name = 'active_plugins';");
		$q->execute();
	}








	/**
	 * Returns information about active theme 
	 * 	* 
	 * @return array information about current template theme, stylesheet and current_theme 
	 */
	public function get_active_themes()
	{

		$q = $this->prepare("SELECT * FROM  " . $this->db_prefix . "options WHERE option_name IN ( 'template', 'stylesheet' , 'current_theme' );");

		$q->execute();
		return $q->fetchAll();

	}

	/**
	 * 
	 * Updates all relevant options related with them to set current theme 
	 * @param array $theme
	 * 
	 * @return void
	 */
	function set_active_theme($theme = '')
	{
		if (!empty($theme)) {
			foreach ($theme as $key => $value) {
				$this->update_option_data($key, $value);
			}

		}
	}

	/**
	 * Scans themes directory in WordPress site formats and returns relevant information about every theme in array.
	 * 
	 * @param string $wordpress_dir path to wordpress directory 
	 * 
	 * @return array data of all present themes 
	 */
	function get_all_themes($wordpress_dir = '')
	{
		$count = 0;
		$themes_data = array();
		foreach (glob($wordpress_dir . 'wp-content/themes/*') as $dir) {
			if (is_dir($dir)) {
				$found_plugin_info = false;
				$theme_slug = str_replace($wordpress_dir . 'wp-content/themes/', '', $dir);
				$filename = $dir . '/style.css';
				$filecontents = file_get_contents($filename);
				$theme_data = array();
				$theme_main_file = str_replace($wordpress_dir . 'wp-content/themes/', '', $filename);
				$count++;
				preg_match_all('/\/\*(.*)\*\//sU', $filecontents, $filecontents_arr);
				//echo '<pre>'.print_r($filecontents_arr,true).'</pre>'."<br>";
				foreach ($filecontents_arr as $filecontent) {
					foreach ($filecontent as $filecontent_data) {
						$filecontent_data = str_replace(array("\*\/", "\/\*"), '', $filecontent_data);
						$filecontent_data = str_replace(array("\n", '#', '*'), PHP_EOL, $filecontent_data);
						$theme_data_arr = explode(PHP_EOL, $filecontent_data);
						foreach ($theme_data_arr as $theme_data_line) {
							if (strstr($theme_data_line, 'Theme Name:')) {
								$theme_name = str_replace('Theme Name: ', '', $theme_data_line);
								$theme_data['theme_name'] = trim($theme_name);
							}
							if (strstr($theme_data_line, 'Version:')) {
								$theme_version = str_replace('Version: ', '', $theme_data_line);
								$theme_data['theme_version'] = trim($theme_version);
							}
							if (strstr($theme_data_line, 'Template:')) {
								$template = str_replace('Template: ', '', $theme_data_line);
								$template = trim($template);
								if ($theme_slug != $template) {
									$theme_data['theme_parent'] = $template;
								}

							}
						}
					}
				}
				$themes_data[$theme_slug] = $theme_data;
			}
		}

		return $themes_data;
	}






	/**
	 * Downloads theme with given slug from wordpress.org, and  unpacks it in themes folder
	 * 
	 * @param string $theme default is 'twentyfifteen'
	 * 
	 * @return boolean 
	 */
	public function safemode_download_theme($theme = 'twentyfifteen')
	{
		$download_url = 'http://downloads.wordpress.org/theme/';

		$default_themes = $this->default_themes;
		foreach ($default_themes as $available_theme => $theme_name) {
			if ($available_theme == $theme) {

				$url = $download_url . $theme . '.zip';
				$filename = $this->wp_dir . 'wp-content/themes/' . $theme . '.zip';
				DashboardHelpers::remote_download($url, $filename);
				if (file_exists($filename)) {
					DashboardHelpers::unzip_data($filename, '', true);
				} else {
					return false;
				}

			}
		}
	}
	/**
	 * Scans through local backup storage directories in wpsafemode and returns array, a list of paths to backup files for backedup tables, 
	 * csv files and full databases 
	 * 
	 * @return array list of filepaths 
	 */
	public function get_database_backups()
	{
		$backups = array();
		$backups['csv'] = array();
		$backups['tables'] = array();
		$backups['database'] = array();

		$sourcedir_tables_csv = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'db_backup/csv/';
		$sourcedir_tables_database = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'db_backup/database/';
		$sourcedir_tables_tables = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'db_backup/tables/';
		foreach (glob($sourcedir_tables_tables . '*') as $dir) {
			if (!is_dir($dir)) {
				$backups['tables'][] = $dir;
			}

		}
		foreach (glob($sourcedir_tables_csv . '*') as $dir) {
			if (!is_dir($dir)) {
				$backups['csv'][] = $dir;
			}
		}
		foreach (glob($sourcedir_tables_database . '*') as $dir) {
			if (!is_dir($dir)) {
				$backups['database'][] = $dir;
			}
		}
		return $backups;
	}

	/**
	 * Scans through local backups storage and returns list of backup files, backups of site files 
	 * 
	 * @return array list with filepaths to backup files 
	 */
	public function get_file_backups()
	{
		$backups = array();
		$backups['full'] = array();
		$backups['partial'] = array();

		$sourcedir_full = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'file_backup/full/';
		$sourcedir_partial = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'file_backup/partial/';

		foreach (glob($sourcedir_full . '*') as $dir) {
			if (!is_dir($dir)) {
				$backups['full'][] = $dir;
			}

		}

		foreach (glob($sourcedir_partial . '*') as $dir) {
			if (!is_dir($dir)) {
				$backups['partial'][] = $dir;
			}

		}

		return $backups;
	}
	/**
	 * Retrieves filepath to htaccess backup file
	 *  
	 * @return string full path to htaccess backup file
	 */
	public function get_htaccess_backups()
	{
		return $this->htaccess_backup_path;
	}

	/**
	 * Saves new .htaccess file in main WordPress directory 
	 * 
	 * @param string $data regenerated .htaccess directives submitted from .htaccess section 
	 * 
	 * @return void
	 */
	public function save_htaccess_file($data = '')
	{
		if (empty($data))
			return;
		DashboardHelpers::put_data($this->htaccess_path, $data);
	}

	public function save_htaccess_revision($data = '')
	{
		//TODO add to convert array into json object and put into sfstore/htaccess_revisions

	}
	/**
	 * Saves new robots.txt file in main WordPress directory 
	 * 
	 * @param string $data regenerated robots directives submitted from robots section 
	 * 
	 * @return void
	 */
	public function save_robots_file($data = '')
	{
		if (empty($data))
			return;
		DashboardHelpers::put_data($this->robots_path, $data);
	}
	/**
	 * Creates backup of tables in csv format
	 * 
	 * @param empty|array $allowed_tables list of tables to be included in backup, if empty it does backup of all tables in active database 
	 * @param boolean $archive if true, it will pack in zip archive backup
	 * 
	 * @return void|false 
	 */
	public function backup_tables_csv($allowed_tables = '', $archive = false)
	{
		set_time_limit(0);
		$tables = $this->show_tables();
		$sourcedir_tables_csv = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'db_backup/csv/';
		if (!empty($allowed_tables)) {
			$tables = $this->db_allowed_tables_filter($tables, $allowed_tables);
		}
		$date = date('d-m-Y--H-i-s');
		$backup_file_csv_zip = $sourcedir_tables_csv . 'tables_database_' . DB_NAME . '-' . $date . '.zip';
		$backup_files_csv = array();
		foreach ($tables as $table) {
			// SECURITY FIX: Validate table name before use
			$validated_table = $this->validate_table_name($table);
			if (!$validated_table) {
				error_log('WP Safe Mode: Invalid table name for CSV export: ' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8'));
				continue;
			}

			$backup_file_csv = $sourcedir_tables_csv . $validated_table . '-' . $date . '.csv';
			// SECURITY FIX: Validate file path to prevent directory traversal
			$backup_file_csv = str_replace('..', '', $backup_file_csv);
			$backup_file_csv = realpath(dirname($backup_file_csv)) . '/' . basename($backup_file_csv);

			$backup_files_csv[] = $backup_file_csv;
			// Note: INTO OUTFILE requires FILE privilege and cannot use parameter binding
			// Table name is validated, file path is sanitized
			try {
				$q = $this->query("SELECT * INTO OUTFILE '" . addslashes($backup_file_csv) . "' FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'  FROM `" . DB_NAME . "`.`" . $validated_table . "`");
			} catch (PDOException $e) {
				error_log('WP Safe Mode: CSV export failed for table ' . $validated_table . ': ' . $e->getMessage());
				continue;
			}
		}

		if ($archive == false) {
			return $backup_files_csv;
		} else {
			if (DashboardHelpers::zip_data($backup_files_csv, $backup_file_csv_zip, $sourcedir_tables_csv)) {
				foreach ($backup_files_csv as $table_file) {
					unlink($table_file);
				}

				return $backup_file_csv_zip;
			}
		}
	}
	/**
	 * Creates backup of selected tables or full database  in .sql format, optionally backup can be archived in zip format 
	 * 
	 * @param null|array $allowed_tables list of tables that will be included in backup process. if empty it will be skipped 
	 * @param boolean $full_backup if true, $allowed_tables will be skipped, and backup will include whole active database 
	 * @param boolean $archive if true it archives backup in zip format 
	 * @param string $wp_prefix if empty, it will take default db prefix 
	 * 
	 * @return
	 */
	public function backup_tables($allowed_tables = '', $full_backup = true, $archive = false, $wp_prefix = '')
	{
		set_time_limit(0);
		$tables = $this->show_tables();

		$output = '';
		$submit_autobackup = filter_input(INPUT_POST, 'submit_autobackup');

		$backup_files_sql = array();
		$date = date('d-m-Y--H-i-s');
		//if is autobackup submit 
		if ($submit_autobackup) {
			$sourcedir_master_sql = $this->settings['safemode_dir'] . $this->settings['sfstore'];
			$sourcedir_tables_sql = $this->settings['safemode_dir'] . $this->settings['sfstore'];

			$backup_file_master_sql = $sourcedir_master_sql . 'database_' . DB_NAME . $wp_prefix . '.sql';
			$backup_file_master_zip = $sourcedir_master_sql . 'database_' . DB_NAME . $wp_prefix . '.zip';
			$backup_file_tables_zip = $sourcedir_tables_sql . 'tables_database_' . DB_NAME . $wp_prefix . '.zip';
		} else {
			$sourcedir_master_sql = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'db_backup/database/';
			$sourcedir_tables_sql = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'db_backup/tables/';

			$backup_file_master_sql = $sourcedir_master_sql . 'database_' . DB_NAME . '-' . $date . '.sql';
			$backup_file_master_zip = $sourcedir_master_sql . 'database_' . DB_NAME . '-' . $date . '.zip';
			$backup_file_tables_zip = $sourcedir_tables_sql . 'tables_database_' . DB_NAME . '-' . $date . '.zip';
		}

		if (!empty($allowed_tables)) {
			$tables = $this->db_allowed_tables_filter($tables, $allowed_tables);
		}
		foreach ($tables as $table) {


			$create_table = $this->db_build_create_table($table);

			$backup_file = $sourcedir_tables_sql . 'table_' . $table . '-' . $date . '.sql';

			$backup_files_sql[] = $backup_file;
			$backup_file_csv = $this->settings['safemode_dir'] . $this->settings['sfstore'] . 'db_backup/csv/' . $table . '-' . $date . '.csv';
			if (file_exists($backup_file)) {
				unlink($backup_file);
			}
			if (file_exists($backup_file_csv)) {
				unlink($backup_file_csv);
			}

			$table_records = $this->db_build_insert_records($table);
			$content = $create_table . $table_records;

			DashboardHelpers::put_data($backup_file, $content);


			try {

			} catch (PDOException $ex) {
				error_log('WP Safe Mode Database Error: ' . $ex->getMessage());
				// Don't output in API context
				if (!defined('WPSM_API')) {
					echo '<p style="color:red">Error: </p>' . $ex->getMessage();
				}
				return false;
			}

		}
		if ($full_backup == true) {
			$full_path = DashboardHelpers::merge_files($backup_files_sql, $backup_file_master_sql, true);
			if ($archive == false) {
				return $full_path;
			} else {
				if (DashboardHelpers::zip_data(array($full_path), $backup_file_master_zip, $sourcedir_master_sql)) {
					unlink($full_path);
					return $backup_file_master_zip;
				}

			}
		} else {
			if ($archive == false) {
				return $backup_files_sql;
			} else {
				if (DashboardHelpers::zip_data($backup_files_sql, $backup_file_tables_zip, $sourcedir_tables_sql)) {
					foreach ($backup_files_sql as $table_file) {
						unlink($table_file);
					}

					return $backup_file_tables_zip;
				}
				//	$backup_file_tables_zip
			}

		}
	}

	/**
	 * 
	 * Retrieves all data from table and builds INSERT query for all data from table 
	 * 
	 * @param string $table name of current table to be archived 
	 * 
	 * @return string $output created INSERT MySQL command to insert data extracted from database 
	 */
	function db_build_insert_records($table = '')
	{
		//TODO backup in chunks, due to different server max_execution_time limitation 
		if (empty($table)) {
			return;
		}
		$search = array('\x00', '\x0a', '\x0d', '\x1a');
		$replace = array('\0', '\n', '\r', '\Z');
		$q = $this->prepare('SELECT * FROM ' . $table);
		$q->execute();
		$output = '';
		$output .= '--' . PHP_EOL . '-- Dumping data for table ' . $table . PHP_EOL . '--' . PHP_EOL;
		$output .= 'INSERT INTO ' . $table . ' VALUES ' . PHP_EOL;
		$rows_output = '';
		while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
			$num_fields = count($row);
			$j = 0;
			$rows_output .= "(";
			foreach ($row as $field) {
				//  $field = addslashes($field);
				$field = str_replace("\n", "\\n", $field);
				$field = str_replace("\r", "\\r", $field);
				$field = str_replace("'", "''", $field);
				//$rows_output.="'" . str_replace( $search, $replace, DashboardHelpers::wp_addslashes( $field ) ) . "'";
				$rows_output .= $this->quote($field);
				//	$rows_output.= "'".$field."'" ; 

				if ($j < ($num_fields - 1)) {
					$rows_output .= ',';
				}
				$j++;
			}
			$rows_output .= ")," . PHP_EOL;

		}
		if (!empty($rows_output)) {
			$rows_output = stripslashes($rows_output);
			$output .= DashboardHelpers::str_lreplace(',', ';', $rows_output);
			$output .= "\n\n\n" . PHP_EOL;
			return $output;
		}


	}

	/**
	 * 
	 * Builds mysql query for given table from active database
	 * 
	 * @param string $table name of table from active database 
	 * 
	 * @return string $output build CREATE query for given table name 
	 */
	function db_build_create_table($table = '')
	{

		$table_keys = $this->db_show_keys($table);
		$table_columns = $this->db_show_columns($table);
		$table_info = $this->db_show_table_info($table);
		$charset = explode('_', $table_info['Collation']);
		$table_info['Charset'] = $charset[0];


		$output = '';
		$output .= '--' . PHP_EOL . '-- Table structure for table ' . $table . PHP_EOL . '--' . PHP_EOL;
		$output .= 'CREATE TABLE IF NOT EXISTS ' . $table . ' (' . PHP_EOL;

		$count = 0;
		$columns_count = count($table_columns);
		//$primary_field = '';
		foreach ($table_columns as $column) {
			if ($column['Key'] == 'PRI') {
				$primary_field = $column;
			}

			$count++;
			$column_output = "";
			$column_output .= $column['Field'] . " " . $column['Type'];
			if (!empty($column['Collation'])) {
				$column_output .= " COLLATE " . $column['Collation'];
			}
			if (!empty($column['Null']) && $column['Null'] == 'NO') {
				$column_output .= " NOT NULL ";
			}
			if ($column['Null'] == 'YES' && empty($column['Default']) && strstr($column['Type'], 'varchar')) {
				$column_output .= " DEFAULT NULL";
			} elseif ($column['Key'] != 'PRI') {
				$column_output .= " DEFAULT '" . $column['Default'] . "'";
			}
			//if()
			if ($count < $columns_count) {
				$column_output .= ',';
			}
			$column_output .= PHP_EOL;

			$output .= $column_output;

		}
		$output .= ')';
		$output .= ' ENGINE=' . $table_info['Engine'];
		$output .= ' DEFAULT ';
		$output .= ' CHARSET=' . $table_info['Charset'];
		$output .= ' COLLATE=' . $table_info['Collation'];
		$output .= ';' . PHP_EOL . PHP_EOL;

		$keys_output = '--' . PHP_EOL . '-- Indexes for table ' . $table . PHP_EOL . '--' . PHP_EOL;
		$keys_output .= 'ALTER TABLE ' . $table . PHP_EOL;
		$count = 0;
		$keys_count = count($table_keys);
		//look for unique joined keys 
		$unique = array();
		$primary = array();
		$regular = array();
		foreach ($table_keys as $key => $table_key) {
			if ($table_key['Key_name'] != $table_key['Column_name'] && $table_key['Key_name'] != 'PRIMARY') {
				if (!isset($unique[$table_key['Key_name']])) {
					$unique[$table_key['Key_name']] = 0;
				}
				$unique[$table_key['Key_name']] += 1;
			}
			if ($table_key['Key_name'] == 'PRIMARY') {
				if (!isset($primary[$table_key['Key_name']])) {
					$primary[$table_key['Key_name']] = 0;
				}
				$primary[$table_key['Key_name']] += 1;

			}

		}
		//echo '<pre>'.print_r($unique,true).'</pre>';
		foreach ($table_keys as $key => $table_key) {
			$count++;
			$sub_part = ($table_key['Sub_part']) ? '(' . $table_key['Sub_part'] . ')' : '';
			if ($table_key['Key_name'] == 'PRIMARY') {
				if ($table_key['Seq_in_index'] == 1) {
					$keys_output .= "\t" . 'ADD PRIMARY KEY (' . $table_key['Column_name'] . $sub_part;
				} else {
					$keys_output .= $table_key['Column_name'] . $sub_part;
				}
				if ($table_key['Seq_in_index'] == $primary[$table_key['Key_name']]) {
					if ($count < $keys_count) {
						$keys_output .= '),' . PHP_EOL;
					} else {
						$keys_output .= ');' . PHP_EOL;
					}
				} else {
					$keys_output .= ',';
				}
			}
			if ($table_key['Key_name'] == $table_key['Column_name']) {
				if ($table_key['Non_unique'] == 1) {
					$keys_output .= "\t" . 'ADD KEY ' . $table_key['Key_name'] . ' (' . $table_key['Column_name'] . $sub_part . ')';
				} else {
					$keys_output .= "\t" . 'ADD UNIQUE KEY ' . $table_key['Key_name'] . ' (' . $table_key['Column_name'] . $sub_part . ')';
				}
			}
			//check for combined unique 
			if ($table_key['Key_name'] != $table_key['Column_name'] && $table_key['Key_name'] != 'PRIMARY') {
				if ($table_key['Seq_in_index'] == 1) {
					if ($table_key['Non_unique'] == 1) {
						$keys_output .= "\t" . 'ADD KEY ' . $table_key['Key_name'] . ' (' . $table_key['Column_name'] . $sub_part . '';
						if ($table_key['Seq_in_index'] != $unique[$table_key['Key_name']]) {
							$keys_output .= ',';
						}
						if ($table_key['Seq_in_index'] == $unique[$table_key['Key_name']]) {
							if ($count < $keys_count) {
								$keys_output .= '),' . PHP_EOL;
							} else {
								$keys_output .= ');' . PHP_EOL;
							}
						}
					} else {
						$keys_output .= "\t" . 'ADD UNIQUE KEY ' . $table_key['Key_name'] . ' (' . $table_key['Column_name'] . $sub_part . ',';
					}
				} else {
					if ($table_key['Seq_in_index'] == $unique[$table_key['Key_name']]) {
						if ($count < $keys_count) {
							$keys_output .= $table_key['Column_name'] . $sub_part . '),' . PHP_EOL;
						} else {
							$keys_output .= $table_key['Column_name'] . $sub_part . ');' . PHP_EOL;
						}
					} else {
						$keys_output .= $table_key['Column_name'] . $sub_part . ',';
					}
				}

			}
			if (!isset($unique[$table_key['Key_name']]) && $table_key['Key_name'] != 'PRIMARY') {
				if ($count < $keys_count) {
					$keys_output .= ',';
				} else {
					$keys_output .= ';';
				}

				$keys_output .= PHP_EOL;
			}

		}

		$output .= $keys_output;
		if (isset($primary_field) && strstr($primary_field['Extra'], 'auto_increment')) {
			$autoincrement_output = '--' . PHP_EOL . '-- AUTO_INCREMENT for table ' . $table . PHP_EOL . '--' . PHP_EOL;
			$autoincrement_output .= 'ALTER TABLE ' . $table . PHP_EOL;
			$autoincrement_output .= "\t" . ' MODIFY ' . $primary_field['Field'] . ' ' . $primary_field['Type'] . ' NOT NULL AUTO_INCREMENT;' . PHP_EOL;
			$output .= $autoincrement_output;
			unset($primary_field);
		}

		return $output;
	}

	function mysqldump()
	{
	}

	/**
	 * Filters table list, removes tables that are not in $allowed_tables list 
	 * 
	 * @param array $tables all tables from current active database 
	 * @param array $allowed_tables tables to be kept while filtering 
	 * 
	 * @return array filtered table names list 
	 */
	function db_allowed_tables_filter($tables = '', $allowed_tables = '')
	{
		if (empty($allowed_tables)) {
			return $tables;
		} else {
			$new_tables = array();

			foreach ($tables as $table) {
				if (in_array($table, $allowed_tables)) {
					$new_tables[] = $table;
				}
			}
			return $new_tables;
		}
	}

	/**
	 * Searches through database for given $term string 
	 * 
	 * @param string $term term to be found in database
	 * @param array|mixed $args various arguments to specify more precise search in db 
	 * 
	 * @return array results from db query 
	 */
	function db_search($term = '', $args = array())
	{
		set_time_limit(0);
		if (empty($term))
			return;
		$tables = $this->show_tables();
		if (isset($args['criteria']['tables']) && !empty($args['criteria']['tables']) && $args['criteria']['db'] == 'partial') {
			$tables = $this->db_allowed_tables_filter($tables, $args['criteria']['tables']);
		}

		$results = array();

		foreach ($tables as $table) {
			$this->condition = '';
			$results[$table] = array();
			$results[$table]['table_name'] = $table;

			$table_columns = $this->db_show_columns($table);

			$results[$table]['table_columns'] = array();
			foreach ($table_columns as $column) {
				$results[$table]['table_columns'][] = $column['Field'];
				$options = array('condition' => 'OR', 'operator' => $this->get_operator($column['Type']), 'exact' => false);
				$this->add_condition($column['Field'], $term, $options);
			}
			$query = 'SELECT DISTINCT * FROM ' . $table . ' ' . $this->condition;


			if (!empty($this->condition)) {

				$q = $this->prepare($query);
				$q->execute();

				$results[$table]['table_results'] = '';
				while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
					foreach ($row as &$item) {
						if (stristr($item, $term)) {
							$item = htmlentities($item);
							$item = str_ireplace($term, '<span class="highlight">' . $term . '</span>', $item);
						}
						//	$item = htmlentities($item);
					}
					$results[$table]['table_results'][] = $row;
				}
			}
			if (empty($results[$table]['table_results']) || !isset($results[$table]['table_results'])) {
				unset($results[$table]); //flush if no results for table
			}



		}
		return $results;

	}

	/**
	 * Retrieves list of menu items with data 'slug','link' , 'name' and 'icon'
	 * 
	 * @return array list of menu items 
	 */
	function get_main_menu_items()
	{
		$items = array();


		//Quick Actions 
		$items[] = array(
			'link' => '?view=quick_actions',
			'slug' => 'quick_actions',
			'name' => 'Quick Actions',
			'icon' => 'icon_flowchart',
		);
		//wpconfig link
		$items[] = array(
			'link' => '?view=wpconfig_advanced',
			'slug' => 'wpconfig_advanced',
			'name' => 'WP Configuration File',
			'icon' => 'icon_documents',
		);
		//basic info
		$items[] = array(
			'link' => '?view=info',
			'slug' => 'info',
			'name' => 'Basic Information',
			'icon' => 'icon_info_alt',
		);
		//themes link
		$items[] = array(
			'link' => '?view=themes',
			'slug' => 'themes',
			'name' => 'Themes',
			'icon' => 'icon_pencil_alt',
		);
		//plugins link 
		$items[] = array(
			'link' => '?view=plugins',
			// 	'callback'=> array('DashboardController','view_plugins'),
			'slug' => 'plugins',
			'name' => 'Plugins',
			'icon' => 'icon_tools',
		);
		//backup files link 
		$items[] = array(
			'link' => '?view=backup_files',
			'slug' => 'backup_files',
			'name' => 'Backup Files',
			'icon' => 'icon_refresh',
		);
		//backup database 
		$items[] = array(
			'link' => '?view=backup_database',
			'slug' => 'backup_database',
			'name' => 'Backup Database',
			'icon' => 'icon_drive',
		);
		//htaccess settings
		$items[] = array(
			'link' => '?view=htaccess',
			'slug' => 'htaccess',
			'name' => '.Htaccess Settings',
			'icon' => 'icon_shield',
		);
		//robots.txt
		$items[] = array(
			'link' => '?view=robots',
			'slug' => 'robos',
			'name' => 'Robots.txt',
			'icon' => 'icon_compass',
		);
		//error log 
		$items[] = array(
			'link' => '?view=error_log',
			'slug' => 'error_log',
			'name' => 'Error Log',
			'icon' => 'icon_error-oct',
		);
		//autobackup settings for cron 
		$items[] = array(
			//'disabled'=> true,
			'link' => '?view=search_replace',
			'slug' => 'search_replace',
			'name' => 'Search and Replace',
			'icon' => 'icon_search-2',
		);
		$items[] = array(
			//'disabled'=> true,
			'link' => '?view=autobackup',
			'slug' => 'autobackup',
			'name' => 'Autobackup',
			'icon' => 'icon_floppy_alt',
		);

		$items[] = array(
			//'disabled'=> true,
			'link' => '?view=global_settings',
			'slug' => 'global_settings',
			'name' => 'Global Settings',
			'icon' => 'icon_floppy_alt',
		);

		//AI Assistant
		$items[] = array(
			'link' => '?view=ai-assistant',
			'slug' => 'ai-assistant',
			'name' => 'AI Assistant',
			'icon' => 'icon_robot',
		);

		//website migration 
		$items[] = array(
			'disabled' => true,
			'link' => '?view=migration',
			'slug' => 'migration',
			'name' => 'Website Migration',
			'icon' => 'icon_flowchart',
		);

		// System Health
		$items[] = array(
			'link' => '?view=system-health',
			'slug' => 'system-health',
			'name' => 'System Health',
			'icon' => 'icon_pulse',
		);

		// File Manager
		$items[] = array(
			'link' => '?view=file-manager',
			'slug' => 'file-manager',
			'name' => 'File Manager',
			'icon' => 'icon_folder',
		);

		// User Management
		$items[] = array(
			'link' => '?view=users',
			'slug' => 'users',
			'name' => 'User Management',
			'icon' => 'icon_users',
		);

		// Cron Manager
		$items[] = array(
			'link' => '?view=cron',
			'slug' => 'cron',
			'name' => 'Cron Jobs',
			'icon' => 'icon_clock',
		);

		// Database Query
		$items[] = array(
			'link' => '?view=database-query',
			'slug' => 'database-query',
			'name' => 'Database Query',
			'icon' => 'icon_database',
		);

		// Activity Log
		$items[] = array(
			'link' => '?view=activity-log',
			'slug' => 'activity-log',
			'name' => 'Activity Log',
			'icon' => 'icon_list',
		);

		// Email Testing
		$items[] = array(
			'link' => '?view=email-test',
			'slug' => 'email-test',
			'name' => 'Email Testing',
			'icon' => 'icon_mail',
		);

		// Security Scanner
		$items[] = array(
			'link' => '?view=security-scanner',
			'slug' => 'security-scanner',
			'name' => 'Security Scanner',
			'icon' => 'icon_shield',
		);

		// Performance Profiler
		$items[] = array(
			'link' => '?view=performance',
			'slug' => 'performance',
			'name' => 'Performance Profiler',
			'icon' => 'icon_speedometer',
		);

		// Media Library
		$items[] = array(
			'link' => '?view=media',
			'slug' => 'media',
			'name' => 'Media Library',
			'icon' => 'icon_image',
		);

		// Database Optimizer
		$items[] = array(
			'link' => '?view=database-optimizer',
			'slug' => 'database-optimizer',
			'name' => 'Database Optimizer',
			'icon' => 'icon_database_alt',
		);


		return $items;

	}

	/**
	 * Retrieves information from version.php file stored in wp-includes directory of WordPress site 
	 * 
	 * @return array|boolean list of items from version.php in array format or false if file doesn\'t exist
	 */
	function get_core_info()
	{
		$versions_file = $this->wp_dir . 'wp-includes/version.php';

		if (file_exists($versions_file)) {
			include_once $versions_file;
			$versions = array();
			$versions['wp_version'] = array(
				'name' => 'WordPress Core version',
				'description' => 'The WordPress version of your site',
				'variable_name' => '$wp_version',
				'version' => $wp_version,
			);
			$versions['wp_db_version'] = array(
				'name' => 'WordPress DB revision version',
				'description' => 'Holds the WordPress DB revision, increments when changes are made to the WordPress DB schema.',
				'variable_name' => '$wp_db_version',
				'version' => $wp_db_version,
			);

			$versions['tinymce_version'] = array(
				'name' => 'TinyMCE version',
				'description' => '',
				'variable_name' => '$tinymce_version',
				'version' => $tinymce_version,
			);
			$versions['required_php_version'] = array(
				'name' => 'Required PHP version',
				'description' => 'Minimum version of PHP so WordPress can function properly',
				'variable_name' => '$required_php_version',
				'version' => $required_php_version,
			);
			$versions['required_mysql_version'] = array(
				'name' => 'Required MySQL version',
				'description' => 'Minimum version of MySQL so WordPress can function properly',
				'variable_name' => '$required_mysql_version',
				'version' => $required_mysql_version,
			);
			return $versions;
		}

		return false;

	}
	/**
	 * Returns plugins info, wrapper for scan_plugins_directory
	 * 
	 * @return array list of plugins with their information 
	 */
	function get_plugins_info()
	{
		return $this->scan_plugins_directory($this->wp_dir); //add some vars to skip too much memory buffer 
	}
	function get_themes_info()
	{

	}


	/**
	 * 
	 * @param mixed $url array, list of urls to be changed with proposed change
	 * 
	 * @return void 
	 */
	function change_site_urls($url = '')
	{
		if (empty($url)) {
			return;
		}
	}
	/**
	 * 
	 * @param array $newuser mixed data about user and data to be changed 
	 * 
	 * @return void 
	 */
	function change_admin_default($newuser = '')
	{
		if (empty($newuser)) {
			return;

		}
		//UPDATE wp_users SET user_login = 'newuser' WHERE user_login = 'admin';
	}

	/**
	 * Renames database tables with new prefix 
	 *  
	 * @param string $newprefix new prefix for database tables 
	 * @param string $oldprefix old prefix for database tables 
	 * 
	 * @return void
	 */
	function change_table_prefix($newprefix = '', $oldprefix = '')
	{
	}


	/**
	 * Updates home and siteurl records in options table 
	 * 
	 * @param string $home value for home option
	 * @param string $siteurl value for siteurl option 
	 * 
	 * @return void
	 */
	function update_site_url($home = '', $siteurl = '')
	{

		if (empty($home) || empty($siteurl)) {
			return;
		}

		$this->update_option_data('home', $home);
		$this->update_option_data('siteurl', $siteurl);

	}
	/**
	 * Returns value for home record from options table 
	 * 
	 * @return array values  from options table for given option
	 */
	function get_home_url()
	{
		return $this->get_option_data('home');
	}

	/**
	 * Returns value for siteurl record from options table 
	 * 
	 * @return array from options table for given option
	 */
	function get_site_url()
	{
		return $this->get_option_data('siteurl');
	}

	/**
	 * Returns associative array of whole record values for given option name from options table  
	 * 
	 * @param string $option_name name of option for data to be retrieven from options table 
	 * 
	 * @return array an associative array that holds data for record from options table 
	 */
	/**
	 * Get option data with caching
	 * 
	 * @param string $option_name Option name
	 * @return array|null
	 */
	function get_option_data(string $option_name = ''): ?array
	{
		if (empty($option_name)) {
			return null;
		}

		// Cache frequently accessed options
		$cacheable_options = ['home', 'siteurl', 'active_plugins', 'template', 'stylesheet'];
		$cache = Cache::getInstance();
		$cacheKey = 'option_' . $option_name;

		if (in_array($option_name, $cacheable_options)) {
			return $cache->remember($cacheKey, function () use ($option_name) {
				return $this->get_option_data_uncached($option_name);
			}, 600); // Cache for 10 minutes
		}

		return $this->get_option_data_uncached($option_name);
	}

	/**
	 * Get option data (uncached)
	 * 
	 * @param string $option_name Option name
	 * @return array|null
	 */
	private function get_option_data_uncached(string $option_name): ?array
	{
		// SECURITY FIX: Use parameter binding to prevent SQL injection
		$q = $this->prepare("SELECT * FROM  " . $this->db_prefix . "options WHERE option_name = :option_name;");
		$q->bindParam(':option_name', $option_name, PDO::PARAM_STR);
		$q->execute();
		$result = $q->fetch(PDO::FETCH_ASSOC);
		return $result ?: null;
	}

	/**
	 * 
	 * updates value in wp options table by given name of option 
	 * 
	 * @param string $option_name
	 * @param string $option_value
	 * @param boolean $to_json
	 * 
	 * @return void
	 */
	/**
	 * Update option data and clear cache
	 * 
	 * @param string $option_name Option name
	 * @param mixed $option_value Option value
	 * @param bool $to_json Whether to JSON encode
	 * @return bool
	 */
	function update_option_data(string $option_name, $option_value = '', bool $to_json = false): bool
	{

		if (is_array($option_value)) {
			if ($to_json == false) {
				$option_value = serialize($option_value);
			} else {
				$option_value = json_encode($option_value);
			}
		}

		try {

			$q = $this->prepare("UPDATE  " . $this->db_prefix . "options SET option_value = :option_value WHERE option_name = :option_name;");
			$q->bindParam(':option_value', $option_value);
			$q->bindParam(':option_name', $option_name);
			$q->execute();

			// Clear cache for this option
			$cache = Cache::getInstance();
			$cache->delete('option_' . $option_name);

			return true;
		} catch (PDOException $ex) {
			Logger::error('Database error updating option', ['option' => $option_name, 'error' => $ex->getMessage()]);
			// Don't output in API context
			if (!defined('WPSM_API')) {
				echo '<p style="color:red">Error: </p>' . $ex->getMessage();
			}
			return false;
		}
	}

	/**
	 * 
	 * Removes all post revisions from posts table. Optimizes table this way 
	 * 
	 * @return void
	 */
	function delete_revisions()
	{
		$q = $this->prepare("DELETE FROM " . $this->db_prefix . "posts WHERE post_type = 'revision'");
		$q->execute();
	}

	/**
	 * Deletes all unapproved comments from comments table 
	 * 
	 * @return void 
	 */
	function delete_unapproved_comments()
	{
		$this->delete_comments();
	}

	/**
	 * Deletes all comments marked as spam from comments table 
	 * 
	 * @return void 
	 */
	function delete_spam_comments()
	{
		$this->delete_comments('spam');
	}

	/**
	 * Deletes all comments with given condition 
	 * 
	 * @param string $comment_approved refers to comment_approved column value 
	 * 
	 * @return void 
	 */
	function delete_comments($comment_approved = '0')
	{
		$q = $this->prepare("DELETE FROM " . $this->db_prefix . "comments WHERE comment_approved = :comment_approved");
		$q->bindParam(':comment_approved', $comment_approved);
		$q->execute();
	}

	function get_robots()
	{
		$robots_file = $this->robots_path;
		if (file_exists($robots_file)) {
			$content = file_get_contents($robots_file);
			return $content;
		} else {
			$form = "<form action='' method='POST'>
					<input type='submit' class='btn btn-blue' name='create_robots_file' id='create_robots_file' value='Create robots.txt'> 
				</form>";
			return "File robots.txt does't exists on your site<br/><br/>" . $form;
		}
	}

	function get_robots_options()
	{
		$robots_options = array();
		$robots_options['cgi_bin'] = array(
			'name' => 'cgi_bin',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow cgi-bin',
			'input_key' => 'cgi_bin',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['wp_admin'] = array(
			'name' => 'wp_admin',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow wp-admin',
			'input_key' => 'wp_admin',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['archives'] = array(
			'name' => 'archives',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow archives',
			'input_key' => 'archives',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['replytocom'] = array(
			'name' => 'replytocom',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow replytocom',
			'input_key' => 'replytocom',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['wp_includes'] = array(
			'name' => 'wp_includes',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow wp-includes',
			'input_key' => 'wp_includes',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['wp_content_plugins'] = array(
			'name' => 'wp_content_plugins',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow /wp-content/plugins',
			'input_key' => 'wp_content_plugins',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['wp_content_cache'] = array(
			'name' => 'wp_content_cache',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow /wp-content/cache',
			'input_key' => 'wp_content_cache',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['wp_content_themes'] = array(
			'name' => 'wp_content_themes',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Disallow /wp-content/themes',
			'input_key' => 'wp_content_themes',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['mediapartners_google'] = array(
			'name' => 'mediapartners_google',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Allow Mediapartners-Google User Agent',
			'input_key' => 'mediapartners_google',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['googlebot_image'] = array(
			'name' => 'googlebot_image',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Allow Googlebot-Image User Agent',
			'input_key' => 'googlebot_image',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['adsbot_google'] = array(
			'name' => 'adsbot_google',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Allow Adsbot-Google User Agent',
			'input_key' => 'adsbot_google',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['googlebot_mobile'] = array(
			'name' => 'googlebot_mobile',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Allow Googlebot-Mobile User Agent',
			'input_key' => 'googlebot_mobile',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['sitemap'] = array(
			'name' => 'sitemap',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'constant',
			'input_label' => 'Your sitemaps',
			'input_key' => 'sitemap',
			'default_value' => '0',
			'input_type' => 'checkbox',
		);
		$robots_options['sitemap_urls'] = array(
			'name' => 'sitemap_urls',
			'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
			'type' => 'variable',
			'input_label' => 'Add sitemap URL',
			'input_key' => 'sitemap_urls',
			'default_value' => '0',
			'input_type' => 'text',
			'default_value' => '',
			'value_type' => 'string',
			'condition' => 'sitemap',
		);

		return $robots_options;

	}

	function get_login()
	{
		$login_file = $this->settings['sfstore'] . 'wpsm_login.json';
		$login_data = DashboardHelpers::get_data($login_file, true);
		return $login_data;
	}

	function set_global_settings($global_settings_item)
	{
		$global_settings_file = $this->settings['sfstore'] . 'global_settings.json';
		if (empty($global_settings_item)) {
			return;
		}
		DashboardHelpers::put_data($global_settings_file, $global_settings_item, true, true);
		return;
	}

	function get_global_settings()
	{
		$file = $this->settings['sfstore'] . 'global_settings.json';
		$data = DashboardHelpers::get_data($file, true);
		return $data;
	}


	function set_login($login = '')
	{
		$login_file = $this->settings['sfstore'] . 'wpsm_login.json';
		if (empty($login)) {
			return;
		}
		DashboardHelpers::put_data($login_file, $login, true, true);
		return;
	}
	//END CLASS 
}

