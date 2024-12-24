<?php
	class SearchAndReplaceController extends DashboardModel {
		
		function __construct(){    	
			parent::__construct();         
		}
		
		/**
		* Renders search and replace section 
		* 
		* @return void 
		*/
	    function view_search_replace(){
	    	
	           $this->data['tables'] = $this->dashboard_model->show_tables();	
	           $this->render( $this->view_url .'search_replace', $this->data);
		}

	    /**
		* Handles submission from search and replace 
		* 
		* @return array|void|mixed  
		*/
	    function submit_search_replace(){
			 $allowed_criteria_term = array('contains','exact','any');
			 $allowed_criteria_db = array('full','partial');
			 
			 $search_term = filter_input(INPUT_POST,'term');
			 $search_criteria_term = filter_input(INPUT_POST,'search_criteria_term');		 
			 $search_criteria_db = filter_input(INPUT_POST,'search_criteria_db');
			 $search_criteria_tables = filter_input(INPUT_POST,'search_tables_list',FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
			 
			 $args = array();
			 
			 $args['criteria']['term'] = (!empty($search_criteria_term) && in_array($search_criteria_term, $allowed_criteria_term))? $search_criteria_term : 'contains';
			 $args['criteria']['db'] = (!empty($search_criteria_db) && in_array($search_criteria_db, $allowed_criteria_db))? $search_criteria_db : 'full';
			 if(!empty($search_criteria_tables) && $search_criteria_db == 'partial'){
			 	 $args['criteria']['tables'] = $search_criteria_tables;
			 }
			  
			 
			 if(!empty($search_term)){
			   $this->data['search_results'] = $this->dashboard_model->db_search($search_term, $args );	
			 }
			 
			 
		}
	}
?>