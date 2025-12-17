<div class="row" data-equalizer>
	<div class="large-4 columns text-left widget" data-equalizer-watch>
		<div class="widget-title dashboard-panel ">
			<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Robots.txt</h6>

			<div class="row">
				<?php if (isset($data['robots'])): ?>
					<?php echo $data['robots']; ?>
				<?php else: ?>
					<form method='post' id='save_robots' action=''>
						<h3>Options:</h3>
						<?php
						if (isset($data['robots_items'])) {
							foreach ($data['robots_items'] as $key => $value) {
								$option_id = $value['input_key'];
								if ($value['type'] == 'constant') { ?>
									<label>
										<input type="<?php echo $value['input_type']; ?>" id="<?php echo $value['input_key']; ?>"
											name="<?php echo $value['name']; ?>" <?php echo $inputs[$option_id]['checked']; ?> value="1"
											<?php echo $data['default_settings'][$option_id]; ?> /><span aria-haspopup="true"
											title="<?php echo $value['description']; ?>"> <?php echo $value['input_label']; ?></span>
									</label>
								<?php
								}
								if ($value['condition']) { ?>
									<div>
										<input type="<?php echo $value['input_type']; ?>" id="<?php echo $value['input_key']; ?>"
											name="<?php echo $value['name']; ?>" placeholder="<?php echo $value['description']; ?>"
											value="<?php echo $data['default_settings'][$option_id]; ?>"
											data-condition="<?php echo (isset($inputs[$option_id]['condition'])) ? $inputs[$option_id]['condition'] : ''; ?>" />
									</div>
								<?php }
							}
						}
						?>
						<input type='submit' class='btn btn-blue' name="save_robots" id="save_robots"
							value="Save robots.txt">
						<?php echo CSRFProtection::get_token_field('robots'); ?>
					<?php endif; ?>
				</form>
			</div>

		</div>
	</div>
	<div class="large-4 columns text-left edit_htaccess_div widget" data-equalizer-watch>
		<div class="dashboard-panel">
			<?php if (!isset($data['robots'])): ?>
				<?php $robots_file = file_get_contents($data['robots_file'], "r+"); ?>
				<textarea placeholder=".htaccess content" rows="12" cols="5" name="htaccess_content" id="htaccess_content"
					disabled="disabled"><?php echo $robots_file; ?></textarea></br>
			<?php endif; ?>
		</div>
	</div>
</div>