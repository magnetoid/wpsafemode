<?php
?>
<div class="row" data-equalizer>	
	<div class="large-4 columns text-left widget" data-equalizer-watch>
		<div class="widget-title dashboard-panel ">
			<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Existing .htaccess backup</h6>
			<?php if(isset($data['backups'])): ?>
		    <div class="row">
				<div class="columns text-left large-12">
					<a href="<?php echo $data['script_url']; ?>&download=htaccess&filename=<?php echo basename($data['backups']); ?>" class="button" target="_blank"><?php echo basename($data['backups']); ?></a><br/><br/>
				</div>
			</div>
			
			<form method='post' id='save_htaccess' action=''>
				<div class="options"> 
		            <h3>Options:</h3> 
					<?php 
					if(isset($data['htaccess_items'])){
						foreach($data['htaccess_items'] as $key => $value){
							$option_id = $value['input_key'];
							if($value['type'] == 'constant'){ ?>
							<label data-tooltip aria-haspopup="true" class="has-tip" data-options="disable_for_touch:true"  title="<?php echo $value['description'] ?>"> 
								<input type="<?php echo $value['input_type']; ?>" id="<?php echo $value['input_key']; ?>" name="<?php echo $value['name']; ?>" <?php echo $inputs[$option_id]['checked']; ?> value="1" <?php echo $data['default_settings'][$option_id]; ?>/><span aria-haspopup="true" title="<?php echo $value['description']; ?>"> <?php echo $value['input_label']; ?></span>
							</label> 
							<?php 
							}
							if($value['condition']){ ?>
								 <div>
				                  	<input type="<?php echo $value['input_type']; ?>" id="<?php echo $value['input_key']; ?>" name="<?php echo $value['name']; ?>" placeholder="<?php echo $value['description']; ?>"  value="<?php echo $data['default_settings'][$option_id]; ?>" data-condition="<?php echo (isset($inputs[$option_id]['condition']))?$inputs[$option_id]['condition']:''; ?>"/>
				                 </div>
							<?php }					
						}
					}
					?>
				</div>
				<input type='submit' class='btn btn-blue' name="save_htaccess" id="save_htaccess" value="Save .htaccess"> 
				<input type='submit' class='btn btn-blue' name="save_revert" id="save_revert" value="Revert .htaccess"> 
				<?php echo CSRFProtection::get_token_field('htaccess'); ?>
				<?php echo CSRFProtection::get_token_field('htaccess_revert'); ?>
			</form>
				<?php else: ?>
				<div class="columns text-left large-12">
					<h5>You don't have any backup yet</h5>
				</div>				
			<?php endif; ?>	
		</div>
	</div>
	<div class="large-4 columns text-left edit_htaccess_div widget" data-equalizer-watch>
		<div class="dashboard-panel">
			<?php $file_open = file_get_contents($data['htaccess'], "r+"); ?>
			<textarea placeholder=".htaccess content" rows="18" cols="5" name="htaccess_content" id="htaccess_content" disabled="disabled"><?php echo $file_open; ?></textarea></br>
			<form method="post" action="">
				<input type='submit' class='btn btn-blue' name="save_htaccess_backup" id="save_htaccess_backup" value="Save .htaccess backup"> 
				<?php echo CSRFProtection::get_token_field('backup_files'); ?>
			</form>
		
		</div>
	</div>	
</div>