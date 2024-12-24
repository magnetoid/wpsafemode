<?php
	class ThemesController extends DashboardController{
		
		function __construct(){    	
	        parent::__construct();         
	     }
     
		/**
		* 
		* Renders themes section 
		* 
		* @return void 
		*/
	    function view_themes(){

	        $sfstore = $this->dashboard_model->settings['sfstore'];
	        $this->data['themes']['active_theme'] = $this->dashboard_model->get_active_themes();
	        $this->data['themes']['all_themes'] =  $this->dashboard_model->get_all_themes($this->wp_dir);

	        $this->render( $this->view_url.'themes', $this->data );
	    }

	    /**
		* Handles submission from themes section 
		* 
		* @return void 
		*/
	    function submit_themes(){
	       $set_active_theme = filter_input(INPUT_POST, 'active_theme');
	       $all_themes =  $this->dashboard_model->get_all_themes($this->wp_dir);
	       if(!empty($set_active_theme) && $set_active_theme == 'downloadsafe'){
	       	
	            $this->dashboard_model->safemode_download_theme();
	            
	            $theme = array(
	                'template'=> 'twentyfifteen',
	                'stylesheet'=> 'twentyfifteen',
	                'current_theme'=> 'twentyfifteen',
	            );
	            $this->dashboard_model->set_active_theme($theme);
	               $this->redirect('?view='.$this->current_page);
	            return;
	       }
	       foreach($all_themes as $key=>$value){
	           if($set_active_theme == $key){
	             
	               if(isset($value['theme_parent'])){
	                   $theme = array(
	                       'template'=> $value['theme_parent'],
	                       'stylesheet'=> $key,
	                       'current_theme'=> $value['theme_name'],
	                   );
	               }else{
	                   $theme = array(
	                    'template'=> $key,
	                    'stylesheet'=> $key,
	                    'current_theme'=> $value['theme_name'],
	                   );
	               }
	               $this->dashboard_model->set_active_theme($theme);
	               $this->redirect('?view='.$this->current_page);
	               return;
	           }
	       }
	    }
	    
	    /**
		* Calls method from model to get all themes data 
		* 
		* @return boolean|array false if method from model returns empty results, or list of themes info upon success
		*/	
		public function get_themes_info(){
			 
			$versions = $this->dashboard_model->get_all_themes($this->wp_dir);
			if($versions){
				return $versions;
			}
			return false;
		}	
		
		
	}
?>