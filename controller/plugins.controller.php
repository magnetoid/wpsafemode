<?php


class PluginsController extends MainController{
	
	 function __construct(){    	
        parent::__construct();         
     }
    
    
	/**
	* Renders plugins section. 
	* 
	* @return void 
	*/
    function view_plugins(){
       
        $sfstore = $this->settings['sfstore'];
        $this->data['plugins']['active_plugins'] = $this->dashboard_model->get_active_plugins();
        $this->data['plugins']['all_plugins'] =    $this->dashboard_model->scan_plugins_directory($this->wp_dir);

        if (!file_exists($sfstore.'active_plugins.txt')) {
           $this->dashboard_model->backup_active_plugins_list();
        }

        $this->data['plugins']['active_plugins'] = unserialize($this->data['plugins']['active_plugins']['option_value']);
        $this->render( $this->view_url.'plugins', $this->data );
    }
    
    /**
	* Handles submission from plugins section. Filters submitted data and calls methods from controller and model 
	* 
	* @return void 
	*/
    function submit_plugins(){
        $rebuild_plugins_backup = filter_input(INPUT_POST,'rebuild_plugins_backup');
        $submit_plugins_action = filter_input(INPUT_POST,'submit_plugins_action');

        if(!empty($rebuild_plugins_backup) && $rebuild_plugins_backup == 'rebuild'){
			 $this->dashboard_model->backup_active_plugins_list();
			 $this->set_message('Plugins backup file has been rebuild');
		}
       if(!empty($submit_plugins_action) && $submit_plugins_action!= 'revert'){
       
            $this->enable_selected_plugins();

        }
       

        if(!empty($submit_plugins_action) &&  $submit_plugins_action == 'revert'){
            $this->revert_plugins();
        }
        
        
    }
    
    /**
	* To set all present plugins in WordPress plugins directory as active and redirects to current page 
	* 
	* @return void
	*/
    function enable_all_plugins(){
    $this->redirect('?view='.$this->current_page);
    }

    /**
	* Deactivates all active plugins in current WordPress instance  and redirects to current page 
	* 
	* @return void 
	*/
    function disable_all_plugins(){
        $this->dashboard_model->disable_all_plugins();
        $this->redirect('?view='.$this->current_page);
    }

    /**
	* Activates all plugins selected from plugins section. It serializes array and calls method from model to save active plugins. 
	* 
	* @return void 
	*/
    function enable_selected_plugins(){
        $selected_plugins = filter_input(INPUT_POST,'plugins',FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $selected_plugins = serialize($selected_plugins);
        $this->dashboard_model->save_plugins($selected_plugins);
        $this->set_message('Selected plugins have been enabled');
        $this->redirect('?view='.$this->current_page);
        
    }

    /**
	* 
	* Reverts active plugins to initial state. Calls method in model to save initial active plugins 
	* 
	* @return void 
	*/
    function revert_plugins(){
     
        
        $revert = $this->dashboard_model->save_revert_plugins();
       
        if($revert){
        	 $this->set_message('Plugins reverted to initial state');
			 $this->redirect('?view='.$this->current_page);
		}
  
      
     }
	
	
}