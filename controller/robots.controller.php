<?php

	function RobotsController extends DashboardController{
		
		function __construct(){    	
	        parent::__construct();  
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
	   
	   	function create_robots_file(){
			$file = $this->dashboard_model->settings['wp_dir'].'robots.txt';
			$content =  "User-agent: *\nDisallow: /";
			file_put_contents($file, $content);
			$this->set_message("File is successfully created");
			$this->redirect(); 
			return;
		}
		
		
	}
?>