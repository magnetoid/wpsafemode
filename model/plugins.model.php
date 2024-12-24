<?php


/**
* 
*/
class PluginsModel extends DashboardModel {
	
	
	function __construct(){
		parent::__construct();
		
	}
	
	/**
    * 
	* Creates backup of an initial list of active plugins in serialized format 
	* 
	* @return boolean|void
	*/
    public function backup_active_plugins_list(){
    	 $sfstore = $this->settings['sfstore'];
    	 $file_backup = $sfstore.'active_plugins.txt';
	     $active_plugins = $this->get_active_plugins();
		 if($active_plugins){
	     return DashboardHelpers::put_data($file_backup, $active_plugins['option_value'] , false, true);
	
		 }
	}
	/**
	* Does the cleanup of plugin information comment block being hold in index.php file of each plugin 
	* 
	* @param string $info comment that holds plugin information
	* 
	* @return string cleaned plugin information 
	*/
    function plugin_info_cleanup( $info = ''){
		$info = strip_tags($info);
		$info = str_replace(array('\/*','*\/','"',"'"),'',$info);
		$info =  $info;
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
    function scan_plugins_directory($wordpress_dir = ''){
    	
    	//todo checkout _transient_plugin_slugs in wp_options, maybe it is faster , maybe first try to read that option, and then do the scan if empty
        $count = 0;
        $all_plugins_arr = array();
        foreach(glob($wordpress_dir .'wp-content/plugins/*') as $dir) {
            if(is_file($dir) && strstr($dir,'hello')){
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
            }
            elseif(is_dir($dir)){
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
    public function save_plugins($option_value = '' , $serialize = false){
        
        $q = $this->prepare("UPDATE ".$this->db_prefix."options SET option_value = '" . $option_value . "' WHERE option_name LIKE 'active_plugins';");
        $q->execute();
       
    }

    /**
    * 
	* Empties option_value for active_plugins option in options table. This disables all active plugins at once 
	* 
	* @return void
	*/
    public function disable_all_plugins(){        
        $q = $this->prepare("UPDATE ".$this->db_prefix."options SET option_value = '' WHERE option_name = 'active_plugins';");
        $q->execute();
    }
    
     /**
   * Returns plugins info, wrapper for scan_plugins_directory
   * 
   * @return array list of plugins with their information 
   */
   function get_plugins_info(){
   	 return $this->scan_plugins_directory(); //add some vars to skip too much memory buffer 
   }
	
	 /**
	* 
	* Returns active plugins data in serialized format
	* 
	* @return string value from option_value for active_plugins option
	*/
    public function get_active_plugins(){
       
        $q = $this->prepare("SELECT * FROM " . $this->db_prefix . "options WHERE option_name LIKE 'active_plugins';");
        $q->execute();

        return $q->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
    * 
	* Reverts active plugins using active_plugins.txt if exist 
	* 
	* @return boolean
	*/
    public function save_revert_plugins(){
    	$plugins_file = $this->settings['sfstore'] . 'active_plugins.txt';
    	
    	
        $file_contents = file_get_contents($plugins_file);
        $file_contents = DashboardHelpers::get_data($plugins_file);
        if($file_contents && !empty( $file_contents )){
        $this->save_plugins($file_contents);	
        return true;
		}
		return false;
			

    }
	
	
}