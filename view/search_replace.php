<?php
if (isset($data['search_results'])) {
	//	echo '<pre>' . print_r( $data['search_results'] , true ) . '</pre>';
}
?>
<form action="" method="post">
	<div class="row" data-equalizer>
		<div class="large-6 columns text-left widget" data-equalizer-watch>
			<div class="widget-title dashboard-panel " data-equalizer-watch>
				<?php include('help_centar.php'); ?>
				<h6 class="heading bold">Search and Replace in your database</h6>
				<div>Due to different server time out limits, search will be limited up to 1000 records per search
				</div>
				<div class="row">
					<div class="columns large-12">
						<label for="term">Search Term</label>
						<input type="text" name="term" id="term" />
					</div>
				</div>
				<div class="row">
					<div class="columns large-12">
						<label for="replace_term">Replace Term</label>
						<input type="text" name="replace_term" id="replace_term" disabled="disabled"
							placeholder="Replacement is currently disabled" />
					</div>
				</div>
				<div class="row">
					<div class="columns large-12">
						<input type="submit" name="submit_search_replace" class="button switch small round"
							value="Search" />
						<?php echo CSRFProtection::get_token_field('search_replace'); ?>
					</div>
				</div>
				<div class="row">
					<div class="columns large-12">
						<a href="#" class="advanced-toggle" rel="advanced-search">Advanced</a>
						<div id="advanced-search" class="advanced-panel">
							<div class="row">
								<div class="columns large-12">
									<label for="search-criteria-term"><b>Find values: </b></label>
									<label for="search-criteria-term-1"><input type="radio" name="search_criteria_term"
											id="search-criteria-term-1" value="contains" checked />That contain
										term</label>
									<label for="search-criteria-term-2"><input type="radio" name="search_criteria_term"
											id="search-criteria-term-2" value="exact" />That have exact term </label>
									<label for="search-criteria-term-3"><input type="radio" name="search_criteria_term"
											id="search-criteria-term-3" value="any" />That have any of the terms</label>
								</div>
							</div>
							<div class="row">
								<div class="columns large-12">
									<label for="search-criteria-1"><b>Search through:</b></label>
									<label><input type='radio' name='search_criteria_db' class="search-criteria-db"
											value='full' checked> Full Database</label>
									<label><input type='radio' name='search_criteria_db' class="search-criteria-db"
											value='partial'> Only selected tables</label>
								</div>
							</div>
							<div class="row">
								<div class="columns large-11 large-offset-1">
									<div class="tables-list">
										<?php foreach ($data['tables'] as $table): ?>
											<label><input type='checkbox' name='search_tables_list[]'
													value='<?php echo $table; ?>' /> <?php echo $table; ?></label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>
						<div style="clear: both"></div>
					</div>
				</div>
			</div>
		</div>
		<?php if (isset($data['search_results'])): ?>
			<div class="row">
				<div class="large-12 columns text-left widget_error">
					<div class="widget-title dashboard-panel">
						<?php foreach ($data['search_results'] as $result): ?>
							<h6 class="heading bold"><?php echo $result['table_name'] ?></h6>
							<?php echo DashboardHelpers::html_table($result['table_results'], $result['table_columns']); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</form>