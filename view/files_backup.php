<?php
?>
<div class="row">
	<div class="large-4 columns  text-left widget">
		<div class="widget-title dashboard-panel ">
			<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Backup Site Directory</h6>
			<form method='post' id='' action=''>
				<input type='submit' class='btn btn-blue' name="submit_backup_files" value="Backup Files">
				<?php echo CSRFProtection::get_token_field('backup_files'); ?>
			</form>
		</div>
	</div>
	<div class="large-4 columns text-left widget">
		<div class="widget-title dashboard-panel ">
			<h6 class="heading bold">Existing Files backup</h6>
			<?php if (isset($data['backups']) && isset($data['backups']['full']) && count($data['backups']['full']) > 0): ?>
				<div class="row">
					<div class="columns text-left large-12">
						<h5>Full Site Backups</h5>
						<?php foreach ($data['backups']['full'] as $file_backup): ?>
							<a href="<?php echo $data['script_url']; ?>&download=sitefiles&filename=<?php echo basename($file_backup); ?>"
								target="_blank"><?php echo basename($file_backup); ?></a><br /><br />
						<?php endforeach; ?>
					</div>
				</div>
			<?php else: ?>
				<div class="columns text-left large-12">
					<h5>You don't have any backup yet</h5>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>