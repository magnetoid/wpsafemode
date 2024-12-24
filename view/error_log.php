<?php 
//TODO add download error log 
//TODO add error log path 
//TODO download results 
//TODO add date range 
//TODO add purge error log 
//TODO add Backup and archive error log 

 ?>
<div class="row" data-equalizer>    
   <div class="large-8 columns text-left widget_error" data-equalizer-watch>
       <div class="widget-title dashboard-panel ">
       		<?php include('help_centar.php'); ?>
	       <div class="top_error">
		       <h6 class="heading bold error_header">Error log</h6>
		       <div class="row">
				   	<div class="large-8 columns search_form">
				       	<form action="<?php echo DashboardHelpers::build_url('',array('view'=>'error_log')); ?>" method="get">
				           <input type="text" name="search" placeholder="Search log" value="<?php echo isset($data['results']['search'])?$data['results']['search']:''; ?>"/>
				           <input type="hidden" name="view" value="error_log"/>
				       	</form>
		       		</div>	
		       		<div class="large-4 columns">		       			
				   		<a href="<?php echo DashboardHelpers::build_url('',array('download'=>'error_log')); ?>" target="_blank" class="button link_download">Download Error Log</a>	
		       		</div>	       		
			   </div>
		       
	       </div>
          <?php if(is_array($data['results']) && isset($data['results']['headers'])): ?>
          <?php echo DashboardHelpers::html_table( $data['results']['rows'] , $data['results']['headers']); ?>          
          <?php endif; ?>
          <?php if(isset($data['results']['number_lines'])): ?>
          <?php echo DashboardHelpers::paginate($data['results']['number_lines']); ?>          
          <?php endif; ?>
       </div>
   </div>   
</div>