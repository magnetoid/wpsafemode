<?php



class HelpModel {
	
	function __construct(){
		
	}
	
	public static function get_page_help( $page = '' ){
		
		$page = filter_input(INPUT_GET,'view');
		
		$page_help = array(
			'wpconfig_advanced' => 'This section helps you to setup your wp-config.php without editing it directly. Every constant and variable in wp-config.php is shown as checkbox or as text input which you can edit and set according to needs for current WordPress site. Be careful what you change, since it is going to be written in wp-config.php and your sight might become inaccessible due wrong setup.',
			'info' => 'Basic Information section shows information about your WordPress core, php.ini setup, server variables and themes and plugins list. It is useful to have all this data on one place, that can help you to determin possible cause of problem. ',
			'themes' => 'Themes section can help you to switch from theme to theme or to download safe theme from wordpress.org and activate it. This is useful in case you cannot access your site due to bad update of active theme, or if you have bad code in theme that causes errors which make your site inaccessible',
			'plugins' => 'Plugins section can help you to activate/deactivate plugins that are present in your WordPress site. It is useful to do this action outsite your WordPress site in case you cannot access it. Disabling plugins can help you to determine if some plugin causes site malfunction.',
			'backup_files' => 'Backup all files from active WordPress site\'s directory. Please, don\'t leave this page if you started file backup, since backup file might be unusable',
			'backup_database' => 'Backup your WordPress database fully, partially and choose between two formats .sql or .csv. Optionally you can choose to archive backup in .zip archive',
			'htaccess' => '.htaccess section helps you to setup and add directives to your WordPress  site\'s main .htaccess file without having to edit it directly. It keeps generic directives added from your WordPress site, and adds new ones. Keep in mind that it will overwrite all manually entered directives. Section will remember your last settings, so you can continue where you finished after you save settings. ',
			'error_log' => 'Error log section displays overview of php error_log file. You can search through your error_log file or to download it. List is split into pages for better view.',
			'search_replace' => 'Search and replace through your WordPress database. Be careful with replace, since it cannot be undone!',
			'website_migration' => 'Description for Website Migration options!',
			'autobackup' => 'Setup your autobackup so you can have automatic backup on cron. You can set to backup database, all files, .htaccess or wp-config.php. Setup autobackup interval according to your needs. You can enable/disable autobackup on this section as well.',
			'robots' => 'Edit your robots.txt file using WP Safe Mode UI. ',
		);
		foreach( $page_help as $key => $value ){
			if($page == $key){
				return $page_help[$page]; 
			}
		}
		
	}
	
	
	
}

?>