<?php
?>
<div class="row">
	<div class="large-4 columns text-left widget">
		<div class="widget-title dashboard-panel ">
			<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Backup Database</h6>
			<form method='post' id='' action=''>
				<ul>
					<li><input type='radio' name='backup_database_type' class="backup-type" value='full' checked> Backup
						Full Database</li>
					<li><input type='radio' name='backup_database_type' class="backup-type" value='partial'> Backup
						Partially</li>
				</ul>
				<div class="tables-list">
					<?php foreach ($data['tables'] as $table): ?>
						<li><input type='checkbox' name='backup_tables_list[]' value='<?php echo $table; ?>' />
							<?php echo $table; ?></li>
					<?php endforeach; ?>
					<li><input type='checkbox' name='backup_tables_type[]' value='sql' checked /> Export Tables in SQL
						format</li>
					<li><input type='checkbox' name='backup_tables_type[]' value='csv' /> Export Tables in CSV format
					</li>
				</div>
				<li><input type='checkbox' name='backup_archive' value='1' checked /> Archive Backup</li>
				</ul>
				<input type='submit' class='btn btn-blue' name="submit_backup_database" value="Backup Database Data">
				<?php echo CSRFProtection::get_token_field('backup_database'); ?>
			</form>
		</div>
	</div>
	<div class="large-4 columns text-left widget">
		<div class="widget-title dashboard-panel ">
			<h6 class="heading bold">Existing Database backup</h6>
			<?php if (isset($data['backups']) && isset($data['backups']['database']) && count($data['backups']['database']) > 0): ?>
				<div class="row">
					<div class="columns text-left large-12">
						<h5>Full Database Backups</h5>
						<?php foreach ($data['backups']['database'] as $file_backup): ?>
							<a href="<?php echo $data['script_url']; ?>&download=database&filename=<?php echo basename($file_backup); ?>"
								target="_blank"><?php echo basename($file_backup); ?></a><br /><br />
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if (isset($data['backups']) && isset($data['backups']['tables']) && count($data['backups']['tables']) > 0): ?>
				<div class="row">
					<div class="columns text-left large-12">
						<h5>Partial / Table Backups in sql format</h5>
						<?php foreach ($data['backups']['tables'] as $file_backup): ?>
							<a href="<?php echo $data['script_url']; ?>&download=database&filename=<?php echo basename($file_backup); ?>"
								target="_blank"><?php echo basename($file_backup); ?></a><br />
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if (isset($data['backups']) && isset($data['backups']['csv']) && count($data['backups']['csv']) > 0): ?>
				<div class="row">
					<div class="columns text-left large-12">
						<h5>Partial / Table Backups in csv format</h5>
						<?php foreach ($data['backups']['csv'] as $file_backup): ?>
							<a href="<?php echo $data['script_url']; ?>&download=database&filename=<?php echo basename($file_backup); ?>"
								target="_blank"><?php echo basename($file_backup); ?></a><br />
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>