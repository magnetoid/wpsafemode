<?php
	class QuickActionModel extends DashboardModel {
		
		//fali optimize_tables
		
		public function __construct(){
	        parent::__construct();       
    	}
    	
       /**
	   * 
	   * Removes all post revisions from posts table. Optimizes table this way 
	   * 
	   * @return void
	   */
	   function delete_revisions(){
	   	$q = $this->prepare("DELETE FROM " . $this->db_prefix . "posts WHERE post_type = 'revision'");
	   	$q->execute();
	   }
	   
	   /**
	   * Deletes all unapproved comments from comments table 
	   * 
	   * @return void 
	   */
	   function delete_unapproved_comments(){
	   	$this->delete_comments();
	   }
	   
	   /**
	   * Deletes all comments marked as spam from comments table 
	   * 
	   * @return void 
	   */
	   function delete_spam_comments(){
	   	$this->delete_comments('spam');
	   }
	   
	   /**
	   * Deletes all comments with given condition 
	   * 
	   * @param string $comment_approved refers to comment_approved column value 
	   * 
	   * @return void 
	   */
	   function delete_comments($comment_approved = '0'){
	   	$q = $this->prepare("DELETE FROM " . $this->db_prefix . "comments WHERE comment_approved = :comment_approved");
	   	$q->bindParam(':comment_approved', $comment_approved);
	   	$q->execute();
	   }
	}
?>