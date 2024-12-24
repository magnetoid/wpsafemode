<?php


?>
<div class="row" data-equalizer>    
	<div class="large-4 columns text-left widget" data-equalizer-watch>
       	<div class="widget-title dashboard-panel ">
       		<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Autobackup</h6>
			<form action="" method="post">
				<ul>
		
					<li><input type="checkbox" name="enable_autobackup" id="enable_autobackup"  value="1" <?php echo $data['default_settings']['enable_autobackup']; ?>/> Enable Autobackup</li>
					<li><input type="checkbox" name="full_backup" id="full_backup" value="1" <?php echo $data['default_settings']['full_backup']; ?>/> Full Database Backup</li>
		

					<li><input type="checkbox" name="files_backup" id="files_backup"  value="1" <?php echo $data['default_settings']['files_backup']; ?>/> Files backup</li>
					<li><input type="checkbox" name="htaccess_backup" id="htaccess_backup"  value="1" <?php echo $data['default_settings']['htaccess_backup']; ?>/> Htaccess backup</li>
					<li><input type="checkbox" name="wp_config_backup" id="wp_config_backup"  value="1" <?php echo $data['default_settings']['wp_config_backup']; ?>/> wp_config backup</li>
					<li><label>Backup prefix</label><input type="text" name="prefix" id="prefix" placeholder="Set backup prefix" value="<?php echo $data['default_settings']['prefix']; ?>"/></li>
					<li><label>Set autobackup interval</label><input type="text" name="interval" id="interval" placeholder="Min interval 1h" value="<?php echo $data['default_settings']['interval']; ?>"></li>
					<li><input type="checkbox" name="reset_interval" id="reset_interval"  value="1"/> Reset Interval</li>
				</ul>
				<input type='submit' class='btn btn-blue' name="submit_autobackup" value="Save Autobackup"> 
			</form>		
		</div>
	</div>
</div>