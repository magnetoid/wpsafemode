<?php
	
	class RobotsModel extends DashboardModel {
		
		function __construct(){
			parent::__construct();
		}
		
		
		/**
		* Returns content from site's main robots.txt file or if file not exixsts create one
		* 
		* @param boolean $clean_comments if set to true, strips out comments from file
		* 
		* @return string raw content from main site's .htaccess file
		*/
		function get_robots($clean_comments = false){
			$robots_file = $this->robots_path;
			if(file_exists($robots_file)){
				$content = file_get_contents($robots_file);	
				if($clean_comments == true){
					return DashboardHelpers::strip_comments($content);
				}		
			} else{
				$form = "<form action='' method='POST'>
							<input type='submit' class='btn btn-blue' name='create_robots_file' id='create_robots_file' value='Create robots.txt'> 
						</form>";
				return "File robots.txt does't exists on your site<br/><br/>" . $form;
			}			
			return;
		}
		/**
		* Returns robots options for robots.txt section in array format. Each item holds several keys so we can use to build a form.  
		* 
		* @return array $robots_options that holds array of robots items available to setup in robots.txt section
		* 
		*/
		function get_robots_options(){
			$robots_options = array();
			$robots_options['cgi_bin'] = array(
				'name' => 'cgi_bin',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow cgi-bin',
				'input_key'=> 'cgi_bin', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['wp_admin'] = array(
				'name' => 'wp_admin',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow wp-admin',
				'input_key'=> 'wp_admin', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['archives'] = array(
				'name' => 'archives',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow archives',
				'input_key'=> 'archives', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['replytocom'] = array(
				'name' => 'replytocom',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow replytocom',
				'input_key'=> 'replytocom', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['wp_includes'] = array(
				'name' => 'wp_includes',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow wp-includes',
				'input_key'=> 'wp_includes', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['wp_content_plugins'] = array(
				'name' => 'wp_content_plugins',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow /wp-content/plugins',
				'input_key'=> 'wp_content_plugins', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['wp_content_cache'] = array(
				'name' => 'wp_content_cache',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow /wp-content/cache',
				'input_key'=> 'wp_content_cache', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['wp_content_themes'] = array(
				'name' => 'wp_content_themes',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Disallow /wp-content/themes',
				'input_key'=> 'wp_content_themes', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['mediapartners_google'] = array(
				'name' => 'mediapartners_google',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Allow Mediapartners-Google User Agent',
				'input_key'=> 'mediapartners_google', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['googlebot_image'] = array(
				'name' => 'googlebot_image',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Allow Googlebot-Image User Agent',
				'input_key'=> 'googlebot_image', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['adsbot_google'] = array(
				'name' => 'adsbot_google',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Allow Adsbot-Google User Agent',
				'input_key'=> 'adsbot_google', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['googlebot_mobile'] = array(
				'name' => 'googlebot_mobile',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Allow Googlebot-Mobile User Agent',
				'input_key'=> 'googlebot_mobile', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['sitemap'] = array(
				'name' => 'sitemap',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'constant',
				'input_label'=> 'Your sitemaps',
				'input_key'=> 'sitemap', 		
				'default_value'=> '0',
				'input_type'=> 'checkbox',
			);
			$robots_options['sitemap_urls'] = array(
				'name' => 'sitemap_urls',
				'description' => 'It\'s ability to deny multiple IP addresses from accessing your site',
				'type' => 'variable',
				'input_label'=> 'Add sitemap URL',
				'input_key'=> 'sitemap_urls', 		
				'default_value'=> '0',
				'input_type'=> 'text',
				'default_value' => '',		
				'value_type' => 'string', 
				'condition' => 'sitemap',
			);
			
			return $robots_options;
			
		}
		
		/**
		* Saves new robots.txt file in main WordPress directory 
		* 
		* @param string $data regenerated robots directives submitted from robots section 
		* 
		* @return void
		*/
		public function save_robots_file($data = ''){
			if(empty($data))
			return;
			DashboardHelpers::put_data($this->robots_path , $data );
		}
		
		
	}
?>