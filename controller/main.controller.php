<?php



class MainController {

    public $settings;
    public $message;
    function __construct() {
        global $settings;
        $this->settings = $settings;
        $this->wp_dir = $this->settings['wp_dir'];
        $this->wp_config_path =  $this->wp_dir ."wp-config.php";
        $this->wp_config_backup_path = $this->settings['sfstore'] . 'wp-config-safemode-backup.php';
        $this->htaccess_path = $this->wp_dir . '.htaccess';
        $this->htaccess_backup_path = $this->settings['sfstore'] . '.htaccess.safemode.backup';
        $this->set_current_page();      
        $this->action = filter_input(INPUT_GET , 'action');
        $this->setup_dirs();
        
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
	* Sets current page according to view from query string 
	* 
	* @return void 
	*/
    function set_current_page(){
		  $this->current_page = filter_input(INPUT_GET,'view');
		  if(empty($this->current_page)){
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
		if(file_exists($template.'.php')){
			 include_once  $template.'.php';
		}else{
			$this->show_404();
		}
       
    }
    
    /**
	* Prints not found 
	* 
	* @return void 
	*/
    function show_404(){
		echo 'page not found'; 
	}
	
	/**
	* Handles redirection to specific section 
	* 
	* @param string $location slug of section. If empty it will redirect to default section 
	* 
	* @return void 
	*/
    function redirect( $location = ''){
    	if(empty($this->current_page)){
			$this->current_page = 'info';
		}
    	if(empty($location)){
			$location = '?view='.$this->current_page;
		}
	 header("location: " . $location);
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
	* Renders main header template
	* 
	* @return void 
	*/
    function header(){
		 $this->render($this->view_url . 'header' , $this->data);
	}
	
	/**
	* Renders main footer template 
	* 
	* @return void 
	*/
	function footer(){
		  $this->render($this->view_url .'footer' , $this->data);
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
	
	
	function get_session_var( $var = ''){
		if(!isset($_SESSION['wpsm'])){
			$_SESSION['wpsm'] = array();
			return;
		}
		if(empty($var)){
		return  $_SESSION['wpsm'];	
		}
		if(!isset($_SESSION['wpsm'][$var])){
		return;	
		}
		if(DashboardHelpers::is_json($_SESSION['wpsm'][$var])){
		 return json_decode($_SESSION['wpsm'][$var]);
		}
		return $_SESSION['wpsm'][$var];
		
	}

	function set_session_var( $var = '' , $val = ''){
		if(empty($var)){
			return;
		}
		if(is_array($val)){
		  $val = json_encode($val);	
		}
		$_SESSION['wpsm'][$var] = $val;
	}
	
	function remove_session_var( $var = '' ){
		if(empty($var) || !isset($_SESSION['wpsm'][$var])){
		return;	
		}
		
		unset($_SESSION['wpsm']);
		return;
	}
	
	function check_login(){
		
	}
	function action_logout(){
		$this->remove_session_var( 'login' );
		$this->set_message('You have been successfully logged out.');
		$this->redirect();
	}
	function action_login(){
		$login = $this->dashboard_model->get_login();
		if(empty($login)){
			$this->set_message('Login is not set. Please Set your login');
			if($this->current_page=='login'){
				$this->redirect('?view=info');	
			}
			return;
		}
		$check_login = $this->get_session_var('login');
		if(empty($check_login) || $check_login!=true){
			if($this->current_page!='login'){
			
			$this->set_message('please login');
			$this->redirect('?view=login');	
			}
			
			return;
		}else{
			if($this->current_page=='login'){
				$this->redirect('?view=info');	
			}
			$this->data['login'] = true;
		}
	}
	
	function view_global_settings(){
		$user_data_default = array(
			'username' => '',
			'email' => '',		
		);
		$api_key_data = array(
			'api_key' => '',	
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
		$user_data = array(
		'username' =>'',
	//	'email' => '',		
		'password' => '',					
		);
		foreach($user_data as $key=>$user_item){
		  	$user_data[$key] = filter_input(INPUT_POST, $key);
		}
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
				 	$this->set_session_var('login' , true);
				 	$this->set_message('You have been successfully logged in.'); 
				 	$this->redirect('?view=info');	
				 }
				
				}
			}
		}
	}
}