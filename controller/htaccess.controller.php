<?php

class HtaccessController extends MainController{
	
	function __construct(){    	
        parent::__construct();         
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
	* Resets .htaccess to initial state 
	* 
	* @return void 
	*/
	function reset_htaccess(){
		return;
	}
//End class
}
?>