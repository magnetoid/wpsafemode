<?php
/**
 * Main Controller
 * Base controller for all application controllers
 */
class MainController {

    protected $config;
    public $settings;
    public $message;
    protected $wp_dir;
    protected $wp_config_path;
    protected $wp_config_backup_path;
    protected $htaccess_path;
    protected $htaccess_backup_path;
    protected $view_url;
    protected $current_page;
    protected $action;
    protected $data = array();
    protected $base_path;
    protected $last_missing_template;
    private static $template_cache = array(); // Cache for template file existence
    
    /**
     * Constructor - initialize main controller
     * 
     * @param Config|null $config Configuration instance
     */
    function __construct(?Config $config = null) {
        $this->config = $config ?? Config::getInstance();
        $this->settings = $this->config->all();
        $this->base_path = rtrim($this->settings['safemode_dir'] ?? dirname(__DIR__), '/\\') . '/';
        $this->view_url = rtrim($this->settings['view_url'] ?? 'view/', '/\\') . '/';
        $this->wp_dir = $this->settings['wp_dir'];
        $this->wp_config_path =  $this->wp_dir ."wp-config.php";
        $this->wp_config_backup_path = $this->settings['sfstore'] . 'wp-config-safemode-backup.php';
        $this->htaccess_path = $this->wp_dir . '.htaccess';
        $this->htaccess_backup_path = $this->settings['sfstore'] . '.htaccess.safemode.backup';
        $this->set_current_page();      
        $this->action = filter_input(INPUT_GET , 'action');
        $this->setup_dirs();
        
        // Don't clear redirect flag here - let action_login() handle it
        // Clearing it too early causes redirect loops
    }
    
    /**
	* Handles storage directores. Calls method to get directory list and check_directory to create directories that are missing
	* 
	* @return void
	*/
    function setup_dirs(){
	 $this->dirs = $this->get_storage_dirs();      
     DashBoardHelpers::check_directory($this->dirs); 	
	}
	/**
	* Returns list of local storage directories 
	* 
	* @return array list of directories 
	*/
    function get_storage_dirs(){
	return	array(
                'main_storage'=>$this->settings['sfstore'],
                'temp'=>$this->settings['sfstore'].'/temp',
                'db_main'=>$this->settings['sfstore'].'/db_backup',
                'db_csv'=>$this->settings['sfstore'].'/db_backup/csv',
                'db_full'=>$this->settings['sfstore'].'/db_backup/database',
                'db_tables'=>$this->settings['sfstore'].'/db_backup/tables',
                'htaccess'=>$this->settings['sfstore'].'/htaccess_backup',
                'wpconfig'=>$this->settings['sfstore'].'/wp_config_backup',
                'files_main'=>$this->settings['sfstore'].'/file_backup',
                'files_full'=>$this->settings['sfstore'].'/file_backup/full',
                'files_partial'=>$this->settings['sfstore'].'/file_backup/partial',       
            );  
	}
	
	/**
	 * Set current page from GET parameter
	 * 
	 * @return void
	 */
    function set_current_page(): void {
		$this->current_page = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if (empty($this->current_page)) {
			$this->current_page = 'info';
		}
	}
    
    /**
	* Renders section or partial template from view. If template doesn't exist, method will call show_404()
	* 
	* @param string $template name of template file. Do not include .php 
	* @param mixed $data data being passed in template to be rendered 
	* @param array $includes additional files to be included 
	* 
	* @return void 
	*/
    function render($template = '', $data = '' , $includes = ''){
        if(is_array($includes)){
            foreach($includes as $include){
                include_once $include;
            }
        }
        if(empty($template)){
            $template = $this->view_url . $this->current_page;
        }
        
        $template_path = $this->resolveTemplatePath($template);

        // Cache file existence checks (files rarely change during request)
        $cache_key = $template_path;
        if (!isset(self::$template_cache[$cache_key])) {
            self::$template_cache[$cache_key] = $template_path && file_exists($template_path);
        }
        
        if(self::$template_cache[$cache_key]){
             include $template_path;
        }else{
            $this->last_missing_template = $template_path ?: $template;
            // Only log in debug mode
            if ($this->settings['debug'] ?? false) {
                error_log('Template not found: ' . $this->last_missing_template);
            }
            $this->show_404();
        }
       
    }

	/**
	 * Resolve template path from template name
	 * 
	 * @param string $template Template name
	 * @return string Full path to template file
	 */
    protected function resolveTemplatePath(string $template): string {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        if (!str_ends_with($template, '.php')) {
            $template .= '.php';
        }

        if ($this->isAbsolutePath($template)) {
            return $template;
        }

        return $this->base_path . ltrim($template, '/\\');
    }

	/**
	 * Check if path is absolute
	 * 
	 * @param string $path Path to check
	 * @return bool True if absolute path
	 */
    protected function isAbsolutePath(string $path): bool {
        return (bool) preg_match('~^(?:[a-zA-Z]:[\\/]|\\\\|/)~', $path);
    }
    
    /**
	* Prints not found 
	* 
	* @return void 
	*/
    function show_404(){
        // Only show 404 once per request
        static $shown = false;
        if (!$shown) {
            $shown = true;
            if (!empty($this->settings['debug']) && !empty($this->last_missing_template)) {
                echo 'page not found: ' . htmlspecialchars($this->last_missing_template);
            } else {
                echo 'page not found';
            }
        }
    }
	
	/**
	* Handles redirection to specific section 
	* 
	* @param string $location slug of section. If empty it will redirect to default section 
	* 
	* @return void 
	*/
	/**
	 * Redirect to a new location with loop prevention
	 * 
	 * @param string $location URL or query string to redirect to
	 * @return void
	 */
    function redirect(string $location = ''): void {
    	if(empty($this->current_page)){
			$this->current_page = 'info';
		}
    	if(empty($location)){
			$location = '?view='.$this->current_page;
		}
		
		// Extract view parameter to check if we're already on that page
		$redirect_view = null;
		if (preg_match('/[?&]view=([^&]+)/', $location, $matches)) {
			$redirect_view = $matches[1];
		}
		
		// Prevent redirect loop: if we're already on the target page, don't redirect
		if ($redirect_view && $this->current_page === $redirect_view) {
			// Clear any redirect flags since we're already on the target
			if (isset($_SESSION['wpsm']['redirecting'])) {
				unset($_SESSION['wpsm']['redirecting']);
			}
			return;
		}
		
		// Prevent redirect loops by checking redirect count
		$redirect_count = isset($_SESSION['wpsm']['redirect_count']) ? intval($_SESSION['wpsm']['redirect_count']) : 0;
		if ($redirect_count > 3) {
			// Too many redirects - clear flag and stop
			unset($_SESSION['wpsm']['redirecting']);
			unset($_SESSION['wpsm']['redirect_count']);
			error_log('Redirect loop detected - stopping redirect to: ' . $location);
			return;
		}
		
		// Build absolute URL to prevent redirect loops
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		
		// If location is already absolute, use it as-is
		if (strpos($location, 'http://') === 0 || strpos($location, 'https://') === 0) {
			$redirect_url = $location;
		} else {
			// Get the current request URI path (without query string)
			$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
			$request_path = parse_url($request_uri, PHP_URL_PATH);
			
			// Get the directory of the current script
			$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
			if ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') {
				$script_dir = '';
			}
			
			// If location starts with ?, it's a query string - append to current path
			if (strpos($location, '?') === 0) {
				// Use the current path or script directory
				$base_path = $script_dir ?: $request_path;
				// Remove any existing query string from base path
				$base_path = preg_replace('/\?.*$/', '', $base_path);
				$redirect_url = $protocol . $host . rtrim($base_path, '/') . $location;
			} else {
				// Relative path - build from script directory
				$redirect_url = $protocol . $host . $script_dir . '/' . ltrim($location, '/');
			}
		}
		
		// Increment redirect count
		$_SESSION['wpsm']['redirect_count'] = $redirect_count + 1;
		$_SESSION['wpsm']['redirecting'] = true;
		
		// Send redirect header
		header("Location: " . $redirect_url, true, 302);
		exit;	  
	}
    
    /**
	* 
	* Returns basename of main wpsafemode directory. Useful if user renames this directory
	* 
	* @param boolean $absolute_path if set to true it will return an absolute path to this directory
	* 
	* @return string absolute or relative pathname to main wpsafemode directory
	*/
	function main_dir( $absolute_path = true){
		$main_dir = dirname(dirname(__FILE__));	
		if($absolute_path == true){
		return  $main_dir;
		}else{
		return basename($main_dir);
		}
		
		
	}
	/**
	* Stores messages in session to be rendered upon page refresh 
	* //TODO move it to helpers class 
	* 
	* @param string $message message to be stored 
	* 
	* @return void
	*/
	function set_message($message = ''){
		if(empty($message))
		return;
		
		$message.='<br/>';
		$this->message.= '';
		if(!isset($_SESSION['sfmessage'])){
			$_SESSION['sfmessage'] = '';
		}
		$_SESSION['sfmessage'].= $message;
		
	}
	
	/**
	* Retrieves messages stored in $_SESSION['sfmessage']) and sets it to $data['message']. Cleans up $_SESSION['sfmessage']) after. 
	* 
	* @return void 
	*/
	function get_message(){
		if(isset($_SESSION['sfmessage'])){
			$message = $_SESSION['sfmessage'];			
			$this->data['message'] = $message;
			unset($_SESSION['sfmessage']);
		}
	}
	
	/**
	* Returns REQUEST_URI from $_SERVER var 
	* 
	* @return $script_url The URI which was given in order to access this page 
	* @see http://php.net/manual/en/reserved.variables.server.php
	*/
	function get_script_url(){
		$script_url = $_SERVER['REQUEST_URI'];
		return $script_url;
	}
	
	/**
	 * Render header template (admin or regular)
	 * 
	 * @return void
	 */
    function header(): void {
        // Check for admin header first
        $admin_template = $this->view_url . 'header-admin';
        $admin_path = $this->resolveTemplatePath($admin_template);
        
        // Cache file existence
        if (!isset(self::$template_cache[$admin_path])) {
            self::$template_cache[$admin_path] = $admin_path && file_exists($admin_path);
        }
        
        if (self::$template_cache[$admin_path]) {
            include $admin_path;
        } else {
            // Fallback to regular header
            $regular_path = $this->resolveTemplatePath($this->view_url . 'header');
            if (!isset(self::$template_cache[$regular_path])) {
                self::$template_cache[$regular_path] = $regular_path && file_exists($regular_path);
            }
            if (self::$template_cache[$regular_path]) {
                include $regular_path;
            }
        }
    }
	
	/**
	* Renders main footer template 
	* 
	* @return void 
	*/
	/**
	 * Render footer template (admin or regular)
	 * 
	 * @return void
	 */
	function footer(): void {
        // Check for admin footer first
        $admin_template = $this->view_url . 'footer-admin';
        $admin_path = $this->resolveTemplatePath($admin_template);
        
        // Cache file existence
        if (!isset(self::$template_cache[$admin_path])) {
            self::$template_cache[$admin_path] = $admin_path && file_exists($admin_path);
        }
        
        if (self::$template_cache[$admin_path]) {
            include $admin_path;
        } else {
            // Fallback to regular footer
            $regular_path = $this->resolveTemplatePath($this->view_url . 'footer');
            if (!isset(self::$template_cache[$regular_path])) {
                self::$template_cache[$regular_path] = $regular_path && file_exists($regular_path);
            }
            if (self::$template_cache[$regular_path]) {
                include $regular_path;
            }
        }
    }	
	
	/**
	* Sets access permissions to file or directory
	* 
	* @return boolean 
	*/
	function set_permissions(){
		return;
	}
	
	/**
	* Gets access permissions  for file or directory
	* 
	* @return boolean 
	*/
	function get_permissions(){
		return;
	}
	
	
	/**
	 * Get session variable
	 * 
	 * @param string $var Variable name (empty for all)
	 * @return mixed Session value or null
	 */
	protected function get_session_var(string $var = ''): mixed {
		if (!isset($_SESSION['wpsm'])) {
			$_SESSION['wpsm'] = array();
			return $var === '' ? $_SESSION['wpsm'] : null;
		}
		
		if (empty($var)) {
			return $_SESSION['wpsm'];
		}
		
		if (!isset($_SESSION['wpsm'][$var])) {
			return null;
		}
		
		$value = $_SESSION['wpsm'][$var];
		if (DashboardHelpers::is_json($value)) {
			return json_decode($value, true);
		}
		
		return $value;
	}

	/**
	 * Set session variable
	 * 
	 * @param string $var Variable name
	 * @param mixed $val Value to set
	 * @return void
	 */
	protected function set_session_var(string $var, mixed $val): void {
		if (empty($var)) {
			return;
		}
		
		if (!isset($_SESSION['wpsm'])) {
			$_SESSION['wpsm'] = array();
		}
		
		if (is_array($val)) {
			$val = json_encode($val);
		}
		
		$_SESSION['wpsm'][$var] = $val;
	}
	
	/**
	 * Remove session variable
	 * 
	 * @param string $var Variable name
	 * @return void
	 */
	protected function remove_session_var(string $var): void {
		if (empty($var) || !isset($_SESSION['wpsm'][$var])) {
			return;
		}
		
		unset($_SESSION['wpsm'][$var]);
	}
	
	/**
	 * Check login status (placeholder for future implementation)
	 * 
	 * @return void
	 */
	protected function check_login(): void {
		// Placeholder for future login checking logic
	}
	
	/**
	 * Handle logout action
	 * 
	 * @return void
	 */
	function action_logout(): void {
		$this->remove_session_var('login');
		$this->set_message('You have been successfully logged out.');
		$this->redirect();
	}
	/**
	 * Handle login action - checks login status and redirects if needed
	 * 
	 * @return void
	 */
	function action_login(): void {
		$login = $this->dashboard_model->get_login();
		$check_login = $this->get_session_var('login');
		
		// If login credentials are not configured, allow access without login
		if (empty($login)) {
			// Only show message on login page, not on every page
			if ($this->current_page === 'login') {
				$this->set_message('Login is not set. Please Set your login in Global Settings');
				$this->clearRedirectFlags();
				return;
			}
			// If login not configured, allow access (no login required)
			$this->data['login'] = false;
			$this->clearRedirectFlags();
			return;
		}
		
		// Login credentials exist, check session
		if (empty($check_login) || $check_login !== true) {
			if ($this->current_page !== 'login') {
				$this->set_message('please login');
				$this->redirect('?view=login');
			}
			$this->clearRedirectFlags();
			return;
		}
		
		// User is logged in
		if ($this->current_page === 'login') {
			$this->clearRedirectFlags();
			$this->redirect('?view=info');
		}
		$this->clearRedirectFlags();
		$this->data['login'] = true;
	}
	
	function view_global_settings(){
		$user_data_default = array(
			'username' => '',
			'email' => '',		
		);
		$api_key_data = array(
			'api_key' => '',
			'openai_api_key' => '',	
		);
		$login = $this->dashboard_model->get_login();
		$global_settings = $this->dashboard_model->get_global_settings();
		if(!empty($global_settings) && is_array($global_settings)){
			foreach($global_settings as $key => $value){
				if(isset($api_key_data[$key])){
					$api_key_data[$key] = $value;
				}
			}
		}
		if(!empty($login) && is_array($login)){
			foreach($login as $key=>$value){
				if(isset($user_data_default[$key])){
					$user_data_default[$key] = $value;
				}
			}			
		}
		
		$this->data['global_settings']['api_key_value'] = $api_key_data;
		$this->data['global_settings']['login'] = $user_data_default;
		$this->render($this->view_url . 'global_settings' , $this->data);
	}
	function submit_global_settings(){
		$this->submit_login_settings();
		$this->redirect();
	}
	function submit_login_settings(){
		$user_data = array(
		'username' =>'',
		'email' => '',		
		'password' => '',		
		'repeat_password' => '',	
		);
		$global_settings_data = array(
			'api_key' => '',
			'openai_api_key' => '',
			'email' => '',
		);
		
		
		foreach($global_settings_data as $key => $global_item){
			$global_settings_data[$key] = filter_input(INPUT_POST, $key);
		}
		
		$global_settings_item = array();
		if(!empty($global_settings_data['api_key'])){
			$global_settings_item['api_key'] = $global_settings_data['api_key'];
			
			//create array of data to be posted
			$post_data['apikey'] = $global_settings_item['api_key'];
			$post_data['domain'] = $_SERVER['HTTP_HOST'];
			 
			//traverse array and prepare data for posting (key1=value1)
			/*foreach ( $post_data as $key => $value) {
			    $post_items[] = $key . '=' . $value;
			}
			 
			//create the final string to be posted using implode()
			$post_string = implode ('&', $post_items);*/
			
			$result = DashboardHelpers::remote_post_request('http://my.wpsafemode.com/api/register/' , $post_data );
			

/*
			
			//send request to remote server
			$post_data['wpsafemode_api'] = $global_settings_item['api_key'];			
		 
			//traverse array and prepare data for posting (key1=value1)
			foreach ( $post_data as $key => $value) {
			    $post_items[] = $key . '=' . $value;
			}
			 
			//create the final string to be posted using implode()
			$post_string = implode ('&', $post_items);
			 
			//we also need to add a question mark at the beginning of the string
			$post_string = '?' . $post_string;
			 
			//we are going to need the length of the data string
			$data_length = strlen($post_string);
			 
			//let's open the connection
			$connection = fsockopen('my.wpsafemode.com', 80); 
			 
			//sending the data
			fputs($connection, "POST  /index.php  HTTP/1.1\r\n"); 
			fputs($connection, "Host:  my.wpsafemode.com \r\n"); 
			fputs($connection, "Content-Type: application/x-www-form-urlencoded\r\n"); 
			fputs($connection, "Content-Length: $data_length\r\n"); 
			fputs($connection, "Connection: close\r\n\r\n");
			fputs($connection, $post_string); 
			 
			//closing the connection
			fclose($connection);
	*/		
		}
		if(!empty($global_settings_data['openai_api_key'])){
			$global_settings_item['openai_api_key'] = $global_settings_data['openai_api_key'];
		}
		
		if(!empty($global_settings_data['email'])){
			$global_settings_item['email'] = $global_settings_data['email'];
		}
		

		foreach($user_data as $key=>$user_item){
		  	$user_data[$key] = filter_input(INPUT_POST, $key);
		}
		$login = $this->dashboard_model->get_login();
		if(empty($login) || !is_array($login)){
		   $login = array();
		}
		if(!empty($user_data['username'])){
			$login['username'] = $user_data['username'];
		}
		if(!empty($user_data['email']) &&  !filter_var($user_data['email'], FILTER_VALIDATE_EMAIL) === false){
			$login['email'] = $user_data['email'];
		}
		if(!empty($user_data['password']) && !empty($user_data['repeat_password']) && $user_data['password'] == $user_data['repeat_password']){
			include_once('ext/PasswordHash.php');
			$t_hasher = new PasswordHash(8, FALSE);
			$hash = $t_hasher->HashPassword($user_data['password']);
			$login['password'] = $hash;
		}else{
			if((!empty($user_data['password']) || !empty($user_data['repeat_password'])) && $user_data['password'] != $user_data['repeat_password']){
				$this->set_message('passwords must match');
			}
		}
		if(!empty($global_settings_item['api_key']) && !empty($global_settings_item['email'])){
			$this->dashboard_model->set_global_settings($global_settings_item);
		}
		if(!empty($login['username'] ) && !empty($login['email']) && !empty($login['password'])){
			$this->dashboard_model->set_login($login);
			
			$this->set_message('User login data set');
			$this->set_session_var('login' , true);

		}
		
		
	}
	
	function view_login(){
	
		$this->render($this->view_url . 'login' , $this->data);
	}
	
	function submit_login(){
		// SECURITY FIX: Validate CSRF token first
		if (!CSRFProtection::validate_post_token('login')) {
			$this->set_message('Invalid security token. Please try again.');
			$this->redirect('?view=login');
			return;
		}
		
		// SECURITY FIX: Check rate limiting
		if (!RateLimiter::check_rate_limit('login', 5, 300)) {
			$remaining = RateLimiter::get_reset_time('login', 300);
			$this->set_message('Too many login attempts. Please try again in ' . $remaining . ' seconds.');
			$this->redirect('?view=login');
			return;
		}
		
		RateLimiter::record_attempt('login');
		
		// SECURITY FIX: Use InputValidator for sanitization
		$user_data = array(
		'username' => InputValidator::getInput('username', INPUT_POST, 'string'),
		'password' => InputValidator::getInput('password', INPUT_POST, 'string'),					
		);
		$login = $this->dashboard_model->get_login();
		if(!empty($login)){
			$error = false;
			if(empty($user_data['password'])){
				$error = true;
				$this->set_message('Password field cannot be empty.'); 				
			}
			if(empty($user_data['username'])){
				$error = true;
				$this->set_message('Username/Email field cannot be empty.'); 		
				//&& !filter_var($user_data['email'], FILTER_VALIDATE_EMAIL) === false
			}
			if($error == true ){
				$this->redirect();
			}else{
				$login = $this->dashboard_model->get_login();
				if(!empty($login) && is_array($login)){
				 if( !filter_var($user_data['username'], FILTER_VALIDATE_EMAIL) === false){
				 	if($login['email'] != $user_data['username']){
					
						$error = true;
					}
				 }elseif($login['username']!= $user_data['username']){
						
						$error = true;
					
				 }
				 include_once('ext/PasswordHash.php');
			     $t_hasher = new PasswordHash(8, FALSE);
			     $hash = $login['password'];
			     $login['password'] = $hash;
				 $check_hash = $user_data['password'];
				 $check = $t_hasher->CheckPassword($check_hash, $hash);
				 if(!$check){
				 
				 	$error = true;
				 }
				 
				 if($error == true){
				 		$this->set_message('Wrong email/username and/or password'); 
				 		$this->redirect();
				 }else{
				 	// SECURITY FIX: Reset rate limit on successful login
				 	RateLimiter::reset_rate_limit('login');
				 	$this->set_session_var('login' , true);
				 	$this->set_message('You have been successfully logged in.'); 
				 	$this->redirect('?view=info');	
				 }
				
				}
			}
		}
	}
}