<?php
	class ErrorLogController extends DashboardController {

		function __construct(){    	
			parent::__construct();         
		}


		/**
		* Renders php error_log section 
		* 
		* @return void 
		*/
		function view_error_log(){

			$page = filter_input(INPUT_GET , 'page');
			$lines = filter_input(INPUT_GET , 'lines');
			$search = filter_input(INPUT_GET , 'search'); //sanitize

			if(empty($lines)){
				$lines = 20;
			}
			if(empty($page)){
				$page = 1;
			}
			$this->data['results'] = $this->dashboard_model->get_error_log( $page , $lines , $search);

			if(!is_array($this->data['results'])){    
				$this->set_message($this->data['results']);
			}
			$this->data['results']['page'] = $page;
			$this->data['results']['lines'] = $lines; 
			$this->data['results']['search'] = $search; 

			$this->render( $this->view_url .'error_log', $this->data );
		}


	}
?>