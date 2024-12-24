<?php
?>
<div class="row" data-equalizer>
	<div class="columns text-left large-4 widget" data-equalizer-watch>
    <div class="dashboard-panel widget-title">
    	<?php include('help_centar.php'); ?>
      	<h6 class="heading bold">WP Config Advanced</h6>
		<form action="" method="post">
 			<ul>
		    <?php foreach($data['wpconfig_options'] as $wpconfig_option): ?>
		    	<li>
		   			<?php echo DashboardHelpers::form_item($wpconfig_option); ?>
		   		</li>
		    <?php endforeach; ?>
			</ul>
		    <!-- ---------------- SUBMIT SAVE CONFIG ------------------------------->
		    <input type=submit value="Save Config" name="saveconfig_advanced" class="btn btn-blue"/>
		    
		</form>
		</div>
	</div>
</div>