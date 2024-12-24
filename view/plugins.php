<?php

$active_plugins = array();
if(isset($data['plugins']['active_plugins']) && is_array($data['plugins']['active_plugins'])){
    $active_plugins = $data['plugins']['active_plugins'];
}
//echo '<pre>'.print_r($data['plugins']['all_plugins'],true).'</pre>';
?>
<form method="post" action="">
<div class="row" data-equalizer>
	<div class="large-4 columns text-left widget" data-equalizer-watch>
		<div class="dashboard-panel widget-title">
			<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Active WP Plugins From Database</h6>
			<?php if(isset($data['plugins']['all_plugins'])) {
				$all_plugins = $data['plugins']['all_plugins'];
				foreach($all_plugins as $key => $value): 
					$checked = '';
					if(in_array($key,$active_plugins)){
						$checked = 'checked';
					} ?>
					<ul>
						<li>
							<input type="checkbox" class="plugins-checkbox" name="plugins[]" value="<?php echo $key; ?>" <?php echo $checked; ?>/> <span data-tooltip aria-haspopup="true" class="has-tip" data-options="disable_for_touch:true"  title="<?php echo $value['info']; ?>"> <?php echo $value['name']; ?> </span>
						</li>
					</ul>
				<?php endforeach; ?><br />
		</div>
	</div>
	<div class="large-4 columns text-left widget" style="float: left;" data-equalizer-watch>
		<div class="dashboard-panel ">
		
			<ul>
				<li><input class="submit-plugins-action" type="radio" name="submit_plugins_action" value="enable_selected" checked/> Enable Selected</li>
				<li><input class="submit-plugins-action"  type="radio" name="submit_plugins_action" value="enable_all" /> Enable All</li>
				<li><input class="submit-plugins-action"  type="radio" name="submit_plugins_action" value="disable_all" /> Disable All</li>
				<li><input  class="submit-plugins-action"  type="radio" name="submit_plugins_action" value="revert" /> Revert to initial state</li>
				<li>
				<input type="checkbox" name="rebuild_plugins_backup" value="rebuild" /> Rebuild Plugins Backup Data
				<span class="error">warning: rebuilding plugins backup data will remove initial state of active plugins</span>
				</li>
			</ul>
			<input type="submit" name="submit_plugins" value="Save Plugins Data"  class="btn btn-blue"/>
		</div>
	</div>                   
</div>
<?php } ?>
</form>

<?php /* ?>
<h3>WP Plugins From ScanDir</h3>
<form method="post" action="">
    <?php
    if(isset($data['result']['all_plugins_info'])) {
        $p =  $data['result']['all_plugins_info'];
        print_r($p);
        foreach($p as $key => $value): ?>
            <input type="checkbox" name="plugins[]" value="<?php echo $key; ?>" <?php echo (in_array($key , $p))?'checked':''; ?>/> <?php echo $value; ?><br />
        <?php endforeach; ?><br />

        <input id="exampleCheckboxSwitch4" type="submit" name="revert" value="revert"  class="button switch small round"/>
    <?php } else if(!isset($data['result']['all_plugins_info']))
    {
        print($msg);
    } ?>
</form>
<?php */ ?>

