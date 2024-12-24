<?php

	class ThemesModel extends DashboardModel {
		
		function __construct(){    	
	        parent::__construct();         
	     }
	     
	    /**
		* Returns information about active theme 
		* 	* 
		* @return array information about current template theme, stylesheet and current_theme 
		*/
	    public function get_active_themes(){        
	  
	        $q = $this->prepare("SELECT * FROM  ".$this->db_prefix."options WHERE option_name IN ( 'template', 'stylesheet' , 'current_theme' );");
	       
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
	    function set_active_theme($theme=''){
	        if(!empty($theme)){
	            foreach($theme as $key=>$value){
	            	$this->update_option_data($key , $value );
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
	    function get_all_themes($wordpress_dir = ''){
	        $count = 0;
	        $themes_data = array();
	        foreach(glob($wordpress_dir .'wp-content/themes/*') as $dir){
	            if(is_dir($dir)){
	                $found_plugin_info = false;
	                $theme_slug = str_replace($wordpress_dir .'wp-content/themes/','',$dir);
	                $filename = $dir . '/style.css';
	                $filecontents = file_get_contents($filename);
	                $theme_data = array();
	                $theme_main_file =  str_replace($wordpress_dir .'wp-content/themes/','',$filename);
	                $count ++;
	                preg_match_all('/\/\*(.*)\*\//sU', $filecontents, $filecontents_arr);
	                //echo '<pre>'.print_r($filecontents_arr,true).'</pre>'."<br>";
	                foreach($filecontents_arr as $filecontent){
	                    foreach($filecontent as $filecontent_data){
	                        $filecontent_data = str_replace(array("\*\/","\/\*"),'',$filecontent_data);
	                        $filecontent_data = str_replace(array("\n",'#','*'),PHP_EOL,$filecontent_data);
	                        $theme_data_arr = explode(PHP_EOL,$filecontent_data);
	                        foreach($theme_data_arr as $theme_data_line){
	                            if(strstr($theme_data_line, 'Theme Name:')){
	                                $theme_name = str_replace('Theme Name: ','',$theme_data_line);
	                                $theme_data['theme_name'] = trim($theme_name);
	                            }
	                            if(strstr($theme_data_line, 'Version:')){
	                                $theme_version = str_replace('Version: ','',$theme_data_line);
	                                $theme_data['theme_version'] = trim($theme_version);
	                            }
	                            if(strstr($theme_data_line, 'Template:')){
	                                $template = str_replace('Template: ','',$theme_data_line);
	                                $template = trim($template);
	                                if($theme_slug!=$template){
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
	    public function safemode_download_theme( $theme = 'twentyfifteen' ){
	        $download_url = 'http://downloads.wordpress.org/theme/';
	        
	        $default_themes = $this->default_themes;
	        foreach($default_themes as $available_theme => $theme_name ){
	            if($available_theme == $theme){
	                
	                $url =  $download_url . $theme . '.zip';
	                $filename = $this->wp_dir .'wp-content/themes/' . $theme . '.zip';
	               DashboardHelpers::remote_download($url, $filename);
	               if(file_exists($filename)){
				   	DashboardHelpers::unzip_data( $filename,'', true);
				   }else{
				   	return false;
				   }             
	            
	            }
	        }
	    }
	    
	    
	}
?>