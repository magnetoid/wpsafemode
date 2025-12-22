<?php



/**
 * 
 */
class DashboardController extends MainController
{

	protected $current_page;
	protected $dirs;
	public $dashboard_model;
	protected $plugin_service;

	protected $theme_service;
	protected $wp_config_service;
	protected $htaccess_service;
	protected $wpconfig_clean_comments_source;

	/**
	 * Get server info
	 * 
	 * @return array
	 */
	public function get_server_info()
	{
		return array(
			'php_version' => phpversion(),
			'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
			'mysql_version' => $this->dashboard_model->get_mysql_version() ?? 'Unknown'
		);
	}

	/**
	 * Get current page
	 * @return string|null
	 */
	public function get_current_page()
	{
		return $this->current_page;
	}

	/**
	 * Constructor - initialize dashboard controller
	 * 
	 * @return void
	 */
	function __construct()
	{
		parent::__construct();
		$this->dashboard_model = new DashboardModel($this->config);
		$this->plugin_service = new PluginService($this->dashboard_model);
		$this->theme_service = new ThemeService($this->dashboard_model);
		$this->wp_config_service = new WpConfigService($this->dashboard_model);
		$this->htaccess_service = new HtaccessService($this->dashboard_model, $this->settings);

		$this->current_page = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_SPECIAL_CHARS);
		$this->submit();
		$this->view();
	}
	/**
	 * Sets initial data in $data variable
	 * 
	 * @return void
	 */
	function init_data(): void
	{
		$this->data['result'] = array();
		$this->data['script_url'] = $this->get_script_url();
		$this->data['current_page'] = $this->current_page;
		$this->view_url = rtrim($this->view_url ?? ($this->settings['view_url'] ?? 'view/'), '/\\') . '/';
	}

	/**
	 * Triggers action methods if condition is fulfilled
	 * Action method can be triggered if action exists in query string, or if autoload is set to true
	 * 
	 * @return void
	 */
	function actions(): void
	{
		$actions = array(
			'login' => array(Constants::ACTION_TYPE_AUTOLOAD => true),
			'logout' => array(Constants::ACTION_TYPE_ACTION => 'logout'),
			'maintenance' => array(Constants::ACTION_TYPE_AUTOLOAD => true),
			'backup' => array(Constants::ACTION_TYPE_AUTOLOAD => true),
			'optimize_tables' => array(Constants::ACTION_TYPE_ACTION => 'optimize_tables'),
			'core_scan' => array(Constants::ACTION_TYPE_ACTION => 'core_scan'),
			'delete_revisions' => array(Constants::ACTION_TYPE_ACTION => 'delete_revisions'),
			'delete_spam_comments' => array(Constants::ACTION_TYPE_ACTION => 'delete_spam_comments'),
			'delete_unapproved_comments' => array(Constants::ACTION_TYPE_ACTION => 'delete_unapproved_comments'),
			'check_maintenance' => array(Constants::ACTION_TYPE_AUTOLOAD => true),
			'autobackup' => array(Constants::ACTION_TYPE_AUTOLOAD => true),
			'download' => array(Constants::ACTION_TYPE_AUTOLOAD => true),
			'message' => array(Constants::ACTION_TYPE_AUTOLOAD => true),
			'generate_magic_link' => array(Constants::ACTION_TYPE_ACTION => 'generate_magic_link'),
			'flush_object_cache' => array(Constants::ACTION_TYPE_ACTION => 'flush_object_cache'),
			'clean_transients' => array(Constants::ACTION_TYPE_ACTION => 'clean_transients'),
		);

		foreach ($actions as $key => $action) {
			$skip = false;
			$callback = array($this, Constants::ACTION_PREFIX . $key);

			if (isset($action[Constants::ACTION_TYPE_ACTION]) && (empty($this->action) || $this->action != $action[Constants::ACTION_TYPE_ACTION])) {
				$skip = true;
			}

			if ($skip == false && is_callable($callback)) {
				if ($key != 'message' && $key != 'logout' && $key != 'login' && isset($this->settings[Constants::DEMO_MODE_KEY]) && $this->settings[Constants::DEMO_MODE_KEY] == true) {
					if (!isset($action[Constants::ACTION_TYPE_AUTOLOAD]) && $key != 'maintenance') {
						$this->set_message(Constants::DEMO_MODE_MESSAGE);
					}
				} else {
					call_user_func($callback);
				}
			}
		}
	}


	/**
	 * Calls get_message() method
	 * 
	 * @return void
	 */
	function set_message($message): void
	{
		$this->get_message();
	}

	/**
	 * Handles the submit upon post requests
	 * 
	 * @return void
	 */
	function submit(): void
	{
		$submits = array(
			'submit_plugins' => array('callback' => array($this, 'submit_plugins')),
			'submit_themes' => array('callback' => array($this, 'submit_themes')),
			'submit_backup_database' => array('callback' => array($this, 'submit_backup_database')),
			'submit_backup_files' => array('callback' => array($this, 'submit_backup_files')),
			'submit_search_replace' => array('callback' => array($this, 'submit_search_replace')),
			'saveconfig' => array('callback' => array($this, 'submit_wpconfig')),
			'saveconfig_advanced' => array('callback' => array($this, 'submit_wpconfig_advanced')),
			'save_htaccess' => array('callback' => array($this, 'submit_htaccess')),
			'save_revert' => array('callback' => array($this, 'submit_htaccess_to_revert')),
			'submit_autobackup' => array('callback' => array($this, 'submit_autobackup')),
			'save_htaccess_backup' => array('callback' => array($this, 'save_htaccess_backup')),
			'submit_site_url' => array('callback' => array($this, 'submit_site_url')),
			'save_robots' => array('callback' => array($this, 'submit_robots')),
			'create_robots_file' => array('callback' => array($this, 'create_robots_file')),
			'submit_global_settings' => array('callback' => array($this, 'submit_global_settings')),
			'submit_login' => array('callback' => array($this, 'submit_login')),
		);

		foreach ($submits as $submit_key => $submit) {
			$submit_input = filter_input(INPUT_POST, $submit_key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
			if (!empty($submit_input)) {
				if (is_callable($submit['callback'])) {
					if ($submit_key != 'submit_login' && isset($this->settings['demo']) && $this->settings['demo'] == true) {
						$this->set_message('Saving settings and submission is disabled in demo mode');
					} else {
						call_user_func($submit['callback']);
					}

					return;
				}

			}
		}

		return;
	}

	/**
	 * renders main pages and triggers callbacks for main pages 
	 * 
	 * @return void 
	 */
	function view(): void
	{


		#--validate--#
		$pages = array();
		$pages['info'] = array(
			'slug' => 'info',
			'callback' => array($this, 'view_info'),
		);
		$pages['login'] = array(
			'slug' => 'login',
			'callback' => array($this, 'view_login'),
		);
		$pages['plugins'] = array(
			'slug' => 'plugins',
			'callback' => array($this, 'view_plugins'),
		);
		$pages['themes'] = array(
			'slug' => 'themes',
			'callback' => array($this, 'view_themes'),
		);
		$pages['users'] = array(
			'slug' => 'users',
			'callback' => array($this, 'view_users'),
		);
		$pages['file_manager'] = array(
			'slug' => 'file-manager',
			'callback' => array($this, 'view_file_manager'),
		);
		$pages['wpconfig'] = array(
			'slug' => 'wpconfig',
			'callback' => array($this, 'view_wpconfig'),
		);
		$pages['wpconfig_advanced'] = array(
			'slug' => 'wpconfig_advanced',
			'callback' => array($this, 'view_wpconfig_advanced'),
		);
		$pages['backup_database'] = array(
			'slug' => 'backup_database',
			'callback' => array($this, 'view_backup_database'),
		);
		$pages['database_optimizer'] = array(
			'slug' => 'database_optimizer',
			'callback' => array($this, 'view_database_optimizer'),
		);
		$pages['backup_files'] = array(
			'slug' => 'backup_files',
			'callback' => array($this, 'view_backup_files'),
		);
		$pages['htaccess'] = array(
			'slug' => 'htaccess',
			'callback' => array($this, 'view_htaccess'),
		);
		$pages['robots'] = array(
			'slug' => 'robots',
			'callback' => array($this, 'view_robots'),
		);
		$pages['error_log'] = array(
			'slug' => 'error_log',
			'callback' => array($this, 'view_error_log'),
		);

		$pages['core_scan'] = array(
			'slug' => 'core_scan',
			'callback' => array($this, 'action_core_scan'),
		);

		$pages['global_settings'] = array(
			'slug' => 'global_settings',
			'callback' => array($this, 'global_settings'),
		);



		$this->data['menu_items'] = $this->dashboard_model->get_main_menu_items();
		$this->data['htaccess_items'] = $this->dashboard_model->get_htaccess_options();
		$this->data['robots_items'] = $this->dashboard_model->get_robots_options();

		// Skip header/footer for login page (it's a standalone HTML page)
		$skip_layout = ($this->current_page === 'login');

		//call header template 
		if (!$skip_layout) {
			$this->header();
		}



		$page_found = false;
		foreach ($pages as $page_key => $page) {
			if ($this->current_page == $page['slug']) {
				if (is_callable($page['callback'])) {
					call_user_func($page['callback']);
					$page_found = true;
				}

			}
		}
		if ($page_found == false) {
			$wild_page = array($this, 'view_' . str_replace('-', '_', $this->current_page));
			if (is_callable($wild_page)) {
				call_user_func($wild_page);
				$page_found = true;
			} else {
				// Only redirect if not already on info page to prevent loops
				if ($this->current_page !== 'info') {
					$this->redirect('?view=info');
				} else {
					// If info page also fails, show error
					echo '<div class="alert alert-danger">Page not found: ' . htmlspecialchars($this->current_page) . '</div>';
				}
			}

		}


		//call footer template 
		if (!$skip_layout) {
			$this->footer();
		}
	}

	/**
	 * Renders quick actions section 
	 * 
	 * @return void 
	 */
	function view_quick_actions(): void
	{
		//turn on off maintenance 
		$check_maintenance = $this->action_check_maintenance(false);
		$quick_actions = array(
			'maintenance_enable' => array('action' => 'maintenance_enable', 'text' => 'Enable Maintenance Mode'),
			'maintenance_disable' => array('action' => 'maintenance_disable', 'text' => 'Disable Maintenance Mode'),
			'optimize_tables' => array('action' => 'optimize_tables', 'text' => 'Optimize Database Tables'),
			'reset_active_plugins' => array('action' => 'reset_active_plugins', 'text' => 'Reset Active Plugins'),
			'generate_magic_link' => array('action' => 'generate_magic_link', 'text' => 'Generate Magic Login Link'),
			'delete_spam_comments' => array('action' => 'delete_spam_comments', 'text' => 'Delete All Spam Comments'),
			'delete_unapproved_comments' => array('action' => 'delete_unapproved_comments', 'text' => 'Delete All Unapproved Comments'),
			'core_scan' => array('action' => 'core_scan', 'text' => 'Scan WordPress Core'),
		);

		foreach ($quick_actions as $key => $quick_action) {
			$skip = false;
			if ($key == 'maintenance_enable') {
				if ($check_maintenance) {
					$skip = true;
				}
			}
			if ($key == 'maintenance_disable') {
				if (!$check_maintenance) {
					$skip = true;
				}
			}
			if ($skip == false) {
				$this->data['quick_actions']['links'][$key] = array(
					'link' => DashboardHelpers::build_url('', array('view' => 'quick_actions', 'action' => $quick_action['action'])),
					'text' => $quick_action['text'],
				);
			}
		}

		$this->data['quick_actions']['data']['homeurl'] = $this->dashboard_model->get_home_url();
		$this->data['quick_actions']['data']['siteurl'] = $this->dashboard_model->get_site_url();

		$this->render('', $this->data);

	}

	/**
	 * Renders database optimizer section
	 */
	function view_database_optimizer(): void
	{
		$cache = new CacheService($this->dashboard_model);
		$this->data['cache_status'] = $cache->getCacheStatus();
		$this->data['transient_stats'] = $cache->getTransientStats();

		$this->render($this->view_url . 'database-optimizer', $this->data);
	}

	/**
	 * Flush Object Cache
	 */
	function action_flush_object_cache(): void
	{
		$cache = new CacheService($this->dashboard_model);
		if ($cache->flushCache()) {
			$this->set_message('Object Cache flushed successfully.');
		} else {
			$this->set_message('Failed to flush Object Cache. WordPress might not be loadable or no persistent cache plugin active.');
		}
		$this->redirect('?view=database_optimizer');
	}

	/**
	 * Clean Expired Transients
	 */
	function action_clean_transients(): void
	{
		$cache = new CacheService($this->dashboard_model);
		$count = $cache->cleanExpiredTransients();
		$this->set_message("Cleaned $count expired transients.");
		$this->redirect('?view=database_optimizer');
	}

	/**
	 * Retrieves info for wordpress core, plugins, themes, php, server and calls info template 
	 * 
	 * @return
	 */
	function view_info(): void
	{

		if (!isset($this->data['info'])) {
			$this->data['info'] = array();
		}
		$this->data['info']['core_info'] = $this->get_wordpress_core_info();
		$this->data['info']['plugins_info'] = $this->get_plugins_info();
		$this->data['info']['themes_info'] = $this->get_themes_info();
		$this->data['info']['php_info'] = $this->get_php_info();
		$this->data['info']['server'] = $this->get_server_info();
		//some php + mysql info 
		//count files info 
		$this->render($this->view_url . 'info', $this->data);

	}
	/**
	 * Renders autobackup settings section 
	 * 
	 * @return void 
	 */
	function view_autobackup(): void
	{
		$autobackup_settings_file = $this->settings['sfstore'] . 'autobackup_settings.json';
		$default_settings = array(
			'enable_autobackup' => '',
			'full_backup' => '',
			'files_backup' => '',
			'htaccess_backup' => '',
			'wp_config_backup' => '',
			'prefix' => '',
			'interval' => '5 hours',
		);
		if (file_exists($autobackup_settings_file)) {
			$autobackup_settings = DashboardHelpers::get_data($autobackup_settings_file, true);
			$this->data['autobackup_settings'] = $autobackup_settings;
			foreach ($autobackup_settings as $key => $value) {
				if ($key != 'interval' && $key != 'prefix') {
					if (isset($default_settings[$key])) {
						if ($value == 1) {
							$default_settings[$key] = 'checked="checked"';
						}
					}
				} else {
					if (isset($default_settings[$key])) {
						$default_settings[$key] = $value;
					}
				}
			}
		} else {
			foreach ($default_settings as $key => $value) {
				if ($key != 'interval' && $key != 'prefix') {
					if (isset($default_settings[$key])) {
						if ($value == 1) {
							$default_settings[$key] = 'checked="checked"';
						}
					}
				} else {
					if (isset($default_settings[$key])) {
						$default_settings[$key] = $value;
					}
				}
			}
		}


		$this->data['default_settings'] = $default_settings;
		$this->render($this->view_url . 'autobackup', $this->data);

	}


	/**
	 * Renders plugins section. 
	 * 
	 * @return void 
	 */
	function view_plugins(): void
	{

		$sfstore = $this->settings['sfstore'];
		$this->data['plugins']['active_plugins'] = $this->plugin_service->getActivePlugins();
		$this->data['plugins']['all_plugins'] = $this->dashboard_model->scan_plugins_directory($this->wp_dir);

		if (!file_exists($sfstore . 'active_plugins.txt')) {
			$this->plugin_service->backupActivePluginsList();
		}

		$this->data['plugins']['active_plugins'] = unserialize($this->data['plugins']['active_plugins']['option_value']);
		$this->render($this->view_url . 'plugins', $this->data);
	}

	/**
	 * Handles submission from plugins section. Filters submitted data and calls methods from controller and model 
	 * 
	 * @return void 
	 */
	function submit_plugins(): void
	{
		$rebuild_plugins_backup = filter_input(INPUT_POST, 'rebuild_plugins_backup');
		$submit_plugins_action = filter_input(INPUT_POST, 'submit_plugins_action');

		if (!empty($rebuild_plugins_backup) && $rebuild_plugins_backup == 'rebuild') {
			$this->plugin_service->backupActivePluginsList();
			$this->set_message('Plugins backup file has been rebuild');
		}
		if (!empty($submit_plugins_action) && $submit_plugins_action != 'revert') {

			$this->enable_selected_plugins();

		}


		if (!empty($submit_plugins_action) && $submit_plugins_action == 'revert') {
			$this->revert_plugins();
		}


	}

	/**
	 * To set all present plugins in WordPress plugins directory as active and redirects to current page 
	 * 
	 * @return void
	 */
	function enable_all_plugins(): void
	{
		$this->redirect('?view=' . $this->current_page);
	}

	/**
	 * Deactivates all active plugins in current WordPress instance  and redirects to current page 
	 * 
	 * @return void 
	 */
	function disable_all_plugins(): void
	{
		$this->plugin_service->disableAll();
		$this->redirect('?view=' . $this->current_page);
	}

	/**
	 * Activates all plugins selected from plugins section. It serializes array and calls method from model to save active plugins. 
	 * 
	 * @return void 
	 */
	function enable_selected_plugins(): void
	{
		$selected_plugins = filter_input(INPUT_POST, 'plugins', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$this->plugin_service->savePlugins($selected_plugins);
		$this->set_message('Selected plugins have been enabled');
		$this->redirect('?view=' . $this->current_page);

	}

	/**
	 * 
	 * Reverts active plugins to initial state. Calls method in model to save initial active plugins 
	 * 
	 * @return void 
	 */
	function revert_plugins(): void
	{



		$revert = $this->plugin_service->revertPlugins();

		if ($revert) {
			$this->set_message('Plugins reverted to initial state');
			$this->redirect('?view=' . $this->current_page);
		}


	}


	/**
	 * 
	 * Renders themes section 
	 * 
	 * @return void 
	 */
	function view_themes(): void
	{

		$sfstore = $this->config->get('sfstore');
		$this->data['themes']['active_theme'] = $this->theme_service->getActiveTheme();
		$this->data['themes']['all_themes'] = $this->theme_service->getAllThemes($this->wp_dir);

		$this->render($this->view_url . 'themes', $this->data);
	}

	/**
	 * Renders users section
	 * 
	 * @return void 
	 */
	function view_users(): void
	{
		$user_service = new UserManagementService($this->dashboard_model);
		$this->data['users'] = $user_service->getUsers();
		$this->render($this->view_url . 'users', $this->data);
	}

	/**
	 * Renders file manager section
	 * 
	 * @return void 
	 */
	function view_file_manager(): void
	{
		$file_manager = new FileManagerService();
		$path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

		try {
			$this->data['files'] = $file_manager->listDirectory($path);
			$this->data['current_path'] = $path;
		} catch (Exception $e) {
			$this->data['error'] = $e->getMessage();
			$this->data['files'] = [];
		}

		$this->render($this->view_url . 'file-manager', $this->data);
	}

	/**
	 * Handles submission from themes section 
	 * 
	 * @return void 
	 */
	function submit_themes(): void
	{
		$set_active_theme = filter_input(INPUT_POST, 'active_theme');
		$all_themes = $this->dashboard_model->get_all_themes($this->wp_dir);
		if (!empty($set_active_theme) && $set_active_theme == 'downloadsafe') {

			$this->theme_service->downloadSafeModeTheme();

			$theme = array(
				'template' => 'twentyfifteen',
				'stylesheet' => 'twentyfifteen',
				'current_theme' => 'twentyfifteen',
			);
			$this->theme_service->setActiveTheme($theme);
			$this->redirect('?view=' . $this->current_page);
			return;
		}
		foreach ($all_themes as $key => $value) {
			if ($set_active_theme == $key) {

				if (isset($value['theme_parent'])) {
					$theme = array(
						'template' => $value['theme_parent'],
						'stylesheet' => $key,
						'current_theme' => $value['theme_name'],
					);
				} else {
					$theme = array(
						'template' => $key,
						'stylesheet' => $key,
						'current_theme' => $value['theme_name'],
					);
				}
				$this->theme_service->setActiveTheme($theme);
				$this->redirect('?view=' . $this->current_page);
				return;
			}
		}
	}


	/**
	 * Renders basic wp configuration section 
	 * 
	 * @return void 
	 */
	function view_wpconfig(): void
	{
		$this->data['wpconfig']['config'] = $this->dashboard_model->get_wp_config();
		$this->data['wpconfig']['array'] = $this->dashboard_model->get_wp_config_array();
		$this->render($this->view_url . 'wpconfig', $this->data);
	}

	/**
	 * Renders php error_log section 
	 * 
	 * @return void 
	 */
	function view_error_log(): void
	{
		$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?: 1;
		$lines = filter_input(INPUT_GET, 'lines', FILTER_SANITIZE_NUMBER_INT) ?: 20;
		$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
		$date_from = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
		$date_to = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
		$severity = filter_input(INPUT_GET, 'severity', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
		$source = filter_input(INPUT_GET, 'source', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'php'; // Default to WordPress/PHP errors only

		$error_log_service = new ErrorLogService();
		$this->data['results'] = $error_log_service->getErrorLog($page, $lines, $search, $date_from, $date_to, $severity, $source);
		$this->data['stats'] = $error_log_service->getStats();

		if (isset($this->data['results']['error'])) {
			$this->set_message($this->data['results']['error']);
		}

		$this->data['results']['page'] = $page;
		$this->data['results']['lines'] = $lines;
		$this->data['results']['search'] = $search;
		$this->data['results']['date_from'] = $date_from;
		$this->data['results']['date_to'] = $date_to;
		$this->data['results']['severity'] = $severity;
		$this->data['results']['source'] = $source;

		// Check if AI is configured
		$ai_service = new AIService();
		$this->data['ai_configured'] = $ai_service->is_configured();

		// Check for AI analysis in session
		$show_ai = filter_input(INPUT_GET, 'show_ai', FILTER_SANITIZE_NUMBER_INT);
		if ($show_ai && isset($_SESSION['ai_error_analysis'])) {
			$this->data['ai_analysis'] = $_SESSION['ai_error_analysis'];
			$this->data['ai_analysis_timestamp'] = $_SESSION['ai_analysis_timestamp'];
		}

		$this->render($this->view_url . 'error_log', $this->data);
	}

	/**
	 * Download error log file
	 */
	function action_download_error_log(): void
	{
		$error_log_service = new ErrorLogService();
		$path = $error_log_service->getErrorLogPath();

		if ($path && file_exists($path)) {
			header('Content-Type: text/plain');
			header('Content-Disposition: attachment; filename="error_log.txt"');
			header('Content-Length: ' . filesize($path));
			readfile($path);
			exit;
		}

		$this->set_message('Error log file not found');
		$this->redirect('?view=error_log');
	}

	/**
	 * Clear error log file
	 */
	function action_clear_error_log(): void
	{
		// Add CSRF check here if not already handled globally

		$error_log_service = new ErrorLogService();
		if ($error_log_service->clearErrorLog()) {
			$this->set_message('Error log cleared successfully');
		} else {
			$this->set_message('Failed to clear error log');
		}
		$this->redirect('?view=error_log');
	}

	/**
	 * Archive error log file
	 */
	function action_archive_error_log(): void
	{
		$error_log_service = new ErrorLogService();
		$archive_path = $error_log_service->archiveErrorLog();

		if ($archive_path) {
			$this->set_message('Error log archived successfully: ' . $archive_path);
		} else {
			$this->set_message('Failed to archive error log');
		}
		$this->redirect('?view=error_log');
	}

	/**
	 * Enable error logging
	 */
	function action_enable_error_log(): void
	{
		$error_log_service = new ErrorLogService();
		if ($error_log_service->enable()) {
			$this->set_message('Error logging enabled successfully');
		} else {
			$this->set_message('Failed to enable error logging');
		}
		$this->redirect('?view=error_log');
	}

	/**
	 * Disable error logging
	 */
	function action_disable_error_log(): void
	{
		$error_log_service = new ErrorLogService();
		if ($error_log_service->disable()) {
			$this->set_message('Error logging disabled successfully');
		} else {
			$this->set_message('Failed to disable error logging');
		}
		$this->redirect('?view=error_log');
	}

	/**
	 * AI analyze error log
	 */
	function action_ai_analyze_error_log(): void
	{
		try {
			$ai_service = new AIService();

			if (!$ai_service->is_configured()) {
				$this->set_message('AI is not configured. Please set your OpenAI API key in Global Settings.');
				$this->redirect('?view=error_log');
				return;
			}

			$error_log_service = new ErrorLogService();

			// Get recent errors (last 100 lines)
			$results = $error_log_service->getErrorLog(1, 100, '', '', '', '', 'php');

			if (empty($results['rows'])) {
				$this->set_message('No errors found to analyze');
				$this->redirect('?view=error_log');
				return;
			}

			// Format errors for AI analysis
			$error_content = "Recent WordPress Errors:\n\n";
			foreach ($results['rows'] as $row) {
				$error_content .= sprintf(
					"[%s] %s - %s: %s\n",
					$row[0], // date
					$row[3], // severity
					$row[2], // type
					$row[4]  // message
				);
				if (!empty($row[5])) {
					$error_content .= "  File: {$row[5]}:{$row[6]}\n";
				}
				$error_content .= "\n";
			}

			// Get AI analysis
			$analysis = $ai_service->analyze_error_log($error_content);

			// Store analysis in session for display
			$_SESSION['ai_error_analysis'] = $analysis;
			$_SESSION['ai_analysis_timestamp'] = time();

			$this->set_message('AI analysis completed successfully');
			$this->redirect('?view=error_log&show_ai=1');

		} catch (Exception $e) {
			$this->set_message('AI analysis failed: ' . $e->getMessage());
			$this->redirect('?view=error_log');
		}
	}

	/**
	 * Renders advanced wp configuration section 
	 * 
	 * @return void 
	 */
	function view_wpconfig_advanced(): void
	{
		$this->data['wpconfig_options'] = $this->dashboard_model->get_wp_config_options();
		$this->render($this->view_url . 'wpconfig_advanced', $this->data);
	}



	/**
	 * Handles submission from wp config advanced section. Calls method from model to save wp-config.php with updated data 
	 * 
	 * @return void 
	 */
	function submit_wpconfig_advanced(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('wpconfig_advanced')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}

		$this->wp_config_service->handle_advanced_submission();
		$this->redirect();
	}

	/**
	 * Handles submission from basic wp configuration section 
	 * 
	 * @return void 
	 * 
	 */
	function submit_wpconfig(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('wpconfig')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}

		$this->wp_config_service->handle_basic_submission();
		$this->redirect('?view=' . $this->current_page);
	}

	/**
	 * Triggers download of files stored in WP Safe Mode storage 
	 * 
	 * @return void 
	 */
	function action_download(): void
	{
		// SECURITY FIX: Use InputValidator for sanitization
		$download = InputValidator::getInput('download', INPUT_GET, 'string');
		$filename = InputValidator::getInput('filename', INPUT_GET, 'string');

		// Only process if download parameter is set
		if (empty($download)) {
			return;
		}

		// SECURITY FIX: Validate filename format
		if (empty($filename) || !InputValidator::validate($filename, 'filename')) {
			$this->set_message('Invalid filename');
			$this->redirect();
			return;
		}

		if ($download == 'database') {
			$base_directory = $this->settings['sfstore'] . 'db_backup/';

			// SECURITY FIX: Validate file path
			$filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
			if ($filepath === false || !file_exists($filepath)) {
				$this->set_message('File not found');
				$this->redirect();
				return;
			}

			SecureFileOperations::secure_download_file($filename, $base_directory);
			exit;
		}

		if ($download == 'sitefiles') {
			$base_directory = $this->settings['sfstore'] . 'file_backup/';

			// SECURITY FIX: Validate file path
			$filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
			if ($filepath === false || !file_exists($filepath)) {
				$this->set_message('File not found');
				$this->redirect();
				return;
			}

			SecureFileOperations::secure_download_file($filename, $base_directory);
			exit;
		}

		//download htaccess backup file
		if ($download == 'htaccess') {
			$base_directory = $this->settings['sfstore'] . 'htaccess_backup/';

			// SECURITY FIX: Validate file path
			$filepath = SecureFileOperations::validate_file_path($filename, $base_directory);
			if ($filepath === false || !file_exists($filepath)) {
				$this->set_message('File not found');
				$this->redirect();
				return;
			}

			SecureFileOperations::secure_download_file($filename, $base_directory);
			exit;
		}

		if ($download == 'error_log') {
			$error_file = ini_get('error_log');
			if ($error_file && file_exists($error_file)) {
				// SECURITY FIX: Validate error log file path
				$filepath = realpath($error_file);
				if ($filepath && is_file($filepath)) {
					SecureFileOperations::secure_download_file(basename($error_file), dirname($filepath));
					exit;
				}
			}
			$this->set_message('Error log file not found');
			$this->redirect();
		}
	}

	/**
	 * Renders backup database section 
	 * 
	 * @return void 
	 */
	function view_backup_database(): void
	{

		$this->data['tables'] = $this->dashboard_model->show_tables();
		$this->data['backups'] = $this->dashboard_model->get_database_backups();
		$this->render($this->view_url . 'db_backup', $this->data);
	}

	/**
	 * Renders backup files section 
	 * 
	 * @return void 
	 */
	function view_backup_files(): void
	{
		$this->data['backups'] = $this->dashboard_model->get_file_backups();
		$this->render($this->view_url . 'files_backup', $this->data);
	}


	/**
	 * Renders robots.txt editing section 
	 * 
	 * @return void 
	 */
	function view_robots(): void
	{
		$robots_settings_file = $this->settings['sfstore'] . 'robots_revision_last.json';
		$robots_settings = DashboardHelpers::get_data($robots_settings_file, true);
		if (empty($robots_settings) || !is_array($robots_settings)) {
			$robots_settings = array();
		}

		$this->data['robots_settings'] = $robots_settings;
		$default_settings = array(
			'cgi_bin' => '',
			'wp_admin' => '',
			'archives' => '',
			'replytocom' => '',
			'wp_includes' => '',
			'wp_content_plugins' => '',
			'wp_content_cache' => '',
			'wp_content_themes' => '',
			'user_agent_allow' => '',
			'mediapartners_google' => '',
			'googlebot_image' => '',
			'adsbot_google' => '',
			'googlebot_mobile' => '',
			'sitemap' => '',
			'sitemap_urls' => '',
		);

		foreach ($robots_settings as $key => $value) {
			if ($key != 'sitemap_urls') {
				if (isset($default_settings[$key])) {
					if ($value == 1) {
						$default_settings[$key] = 'checked="checked"';
					}
				}
			} else {
				if (isset($default_settings[$key])) {

					$default_settings[$key] = $value;
				}
			}
		}

		$this->data['robots_file'] = $this->wp_dir . 'robots.txt';
		$this->data['robots'] = $this->dashboard_model->get_robots();
		$this->data['default_settings'] = $default_settings;
		$this->render($this->view_url . 'robots', $this->data);
	}

	/**
	 * Handles submission from robots.txt edit section 
	 * 
	 * @return void 
	 */
	function submit_robots(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('robots')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$robots_action = filter_input(INPUT_POST, 'save_robots');
		$file = $this->wp_dir . "robots.txt";
		$robots_settings_file = $this->settings['sfstore'] . 'robots_revision_last.json';
		$robots_settings = DashboardHelpers::get_data($robots_settings_file, true);
		$string = "User-agent: *\nAllow: /";

		$output = "";
		$robots_revision = array();
		$robots_revision['cgi_bin'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /cgi-bin/\n",
		);
		$robots_revision['wp_admin'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-admin/\n",
		);
		$robots_revision['archives'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /archives/\n",
		);
		$robots_revision['replytocom'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: *?replytocom\n",
		);
		$robots_revision['wp_includes'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-includes\n",
		);
		$robots_revision['wp_content_plugins'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-content/plugins\n",
		);
		$robots_revision['wp_content_cache'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-content/cache\n",
		);
		$robots_revision['wp_content_themes'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nDisallow: /wp-content/themes\n",
		);
		$robots_revision['user_agent_allow'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: *\nAllow: /\n",
		);
		$robots_revision['mediapartners_google'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Mediapartners-Google*\nAllow: /\n",
		);
		$robots_revision['googlebot_image'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Googlebot-Image\nAllow: /wp-content/uploads/\n",
		);
		$robots_revision['adsbot_google'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Adsbot-Google\nAllow: /\n",
		);
		$robots_revision['googlebot_mobile'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nUser-agent: Googlebot-Mobile\nAllow: /\n",
		);
		$robots_revision['sitemap'] = array(
			'type' => 'boolean',
			'unformatted_value' => "\nSitemap: %s\n",
		);
		$robots_revision['sitemap_urls'] = array(
			'type' => 'string',
		);

		foreach ($robots_revision as $revisio_key => $revision_value) {
			$input = filter_input(INPUT_POST, $revisio_key);

			if (!empty($input)) {
				if ($revision_value['type'] == 'boolean') {
					$robots_settings[$revisio_key] = 1;
				} else {
					$robots_settings[$revisio_key] = $input;
				}

				if ($robots_settings == false || empty($robots_settings)) {
					$robots_settings = array();
				} else {
					if ($revisio_key != 'sitemap') {
						$output .= $revision_value['unformatted_value'];
					} else {
						if ($revisio_key == 'sitemap') {
							$sitemaps = filter_input(INPUT_POST, 'sitemap_urls');
							$sitemaps_urls = explode(",", $sitemaps);
							foreach ($sitemaps_urls as $sitemaps_url) {
								$url .= "Sitemap: " . $sitemaps_url . "\n";
							}
							$output .= sprintf($revision_value['unformatted_value'], $url);
						}

					}
				}

			} else {
				if (isset($robots_settings[$revisio_key])) {
					unset($robots_settings[$revisio_key]);
				}
			}
		}

		//save revision by date
		$robots_revision_json = json_encode($robots_revision);
		$robots_revision_json_filename = $this->settings['sfstore'] . 'robots_revision_' . date('Y-m-d--H-i-s') . '.json';
		//save last revision
		$robots_revision_json_last_filename = $this->settings['sfstore'] . 'robots_revision_last.json';
		$robots_revision_json_last_content = file_get_contents($robots_revision_json_last_filename);
		file_put_contents($robots_revision_json_filename, $robots_revision_json_last_content);
		file_put_contents($robots_revision_json_last_filename, $robots_revision_json);

		$new_robots = $string . $output;
		$this->dashboard_model->save_robots_file($new_robots);

		DashboardHelpers::put_data($robots_settings_file, $robots_settings, true);
		$this->set_message('robots.txt has been saved');
		$this->redirect();
		return;
	}



	/**
	 * Renders .htaccess section 
	 * 
	 * @return void 
	 */
	function view_htaccess(): void
	{
		$htaccess_settings_file = $this->settings['sfstore'] . 'htaccess_revision_last.json';
		$htaccess_settings = DashboardHelpers::get_data($htaccess_settings_file, true);
		if (empty($htaccess_settings) || !is_array($htaccess_settings)) {
			$htaccess_settings = array();
		}

		$this->data['htaccess_settings'] = $htaccess_settings;
		$default_settings = array(
			'bad_ips' => '',
			'block_ips' => '',
			'block_bots' => '',
			'block_hidden' => '',
			'block_source' => '',
			'old_domain' => '',
			'new_domain' => '',
			'deny_referrer' => '',
			'referrer' => '',
			'media_download' => '',
			'redirect_www' => '',
			'canonical_url' => '',
			'trailing_slash' => '',
			'pass_single_file' => '',
			'single_file_name' => '',
			'pass_directory' => '',
			'directory_browsing' => '',
			'server_signature' => '',
			'disable_hotlinking' => '',
			'disable_trace' => '',
			'restrict_wpincludes' => '',
			'development_redirect' => '',
			'redirect_url' => '',
			'protected_config' => '',
			'protected_htaccess' => '',
			'protect_from_xmlrpc' => '',
			'caching' => '',
			'default_page' => '',
			'default_file_name' => '',
			'set_language' => '',
			'language_value' => '',
			'set_charset' => '',
			'charset_value' => '',
			'error_page' => '',
			'error_400' => '',
			'error_401' => '',
			'error_403' => '',
			'error_404' => '',
			'error_500' => '',
			'allow_wpadmin' => '',
			'allow_wpadmin_ip' => '',
		);
		foreach ($htaccess_settings as $key => $value) {
			if ($key != 'bad_ips' && $key != 'new_domain' && $key != 'referrer' && $key != 'single_file_name' && $key != 'redirect_url' && $key != 'allow_wpadmin_ip' && $key != 'default_file_name' && $key != 'charset_value' && $key != 'language_value' && $key != 'error_400' && $key != 'error_401' && $key != 'error_403' && $key != 'error_404' && $key != 'error_500') {
				if (isset($default_settings[$key])) {
					if ($value == 1) {
						$default_settings[$key] = 'checked="checked"';
					}
				}
			} else {
				if (isset($default_settings[$key])) {

					$default_settings[$key] = $value;
				}
			}
		}
		$file = $this->wp_dir . ".htaccess";
		//if in initial state doesn't exists htaccess file create one
		if (!file_exists($file)) {
			$fo = fopen($file, "w+") or die("Cannot create file");
			$content = "# BEGIN WordPress-SafeMode\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n\n</IfModule>\n# END WordPress-SafeMode";
			fwrite($fo, $content);
			fclose($fo);
			file_put_contents($this->wp_dir . 'htaccess.safemode.backup', $content);
		}

		$pass_file = $this->wp_dir . ".htpasswd";
		if (!file_exists($pass_file)) {
			$fopen = fopen($pass_file, "w+") or die("Cannot create file");
		}
		$this->data['backups'] = $this->dashboard_model->get_htaccess_backups();
		$this->data['htaccess'] = $this->wp_dir . '.htaccess';
		$this->data['default_settings'] = $default_settings;
		$this->render($this->view_url . 'htaccess', $this->data);
	}

	/**
	 * 
	 * Handles submission of htaccess settings from .htaccess section 
	 * 
	 * @return void
	 */
	function submit_htaccess(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('htaccess')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$this->htaccess_service->handle_submission();
		$this->set_message('robots.txt has been saved');
		$this->redirect();
		return;
	}

	/**
	 * 
	 * Revert htacces file from last backup
	 * 
	 * @return
	 */
	function submit_htaccess_to_revert(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('htaccess_revert')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$htaccess_revert = filter_input(INPUT_POST, 'save_revert');
		$dir = $this->settings['wp_dir'] . "htaccess.safemode.backup";
		$htaccess_revision_json_last_filename = $this->settings['sfstore'] . 'htaccess_revision_last.json';
		$json_backup = $this->settings['sfstore'] . 'htaccess_revision_backup.json';
		$json_backup_content = file_get_contents($json_backup);
		if (isset($htaccess_revert) && file_exists($dir)) {
			$htaccess_bacukup = file_get_contents($dir);
			file_put_contents($htaccess_revision_json_last_filename, $json_backup_content);
			$this->dashboard_model->save_htaccess_file($htaccess_bacukup);
		}
		$this->set_message('.htaccess revert');
		$this->redirect();
	}

	/**
	 * Saves backup file of htaccess 
	 * 
	 * @return void
	 */
	function save_htaccess_backup(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('backup_files')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$save_backup = filter_input(INPUT_POST, 'save_htaccess_backup');
		$file = $this->dashboard_model->htaccess_path;
		$file_backup = $this->dashboard_model->htaccess_backup_path;
		$this->dashboard_model->get_htaccess_revert();
		$this->set_message(".htaccess backup has been saved at " . realpath($file_backup));
		$this->redirect();
		return;
	}

	/**
	 * Handles submission from backup database section 
	 * 
	 * @return void
	 */
	function submit_backup_database(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('backup_database')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}

		set_time_limit(0);
		$backup_tables_list = filter_input(INPUT_POST, 'backup_tables_list', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$backup_tables_type = filter_input(INPUT_POST, 'backup_tables_type', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$backup_database_type = filter_input(INPUT_POST, 'backup_database_type');
		$backup_archive = filter_input(INPUT_POST, 'backup_archive');
		if (!empty($backup_archive) && $backup_archive == '1') {
			$archive = true;
		} else {
			$archive = false;
		}
		if (!empty($backup_database_type) && $backup_database_type == 'full') {

			$this->dashboard_model->backup_tables('', true, $archive);
		}
		if (!empty($backup_database_type) && $backup_database_type == 'partial' && !empty($backup_tables_type) && in_array('sql', $backup_tables_type)) {

			if ($tables_backup_result = $this->dashboard_model->backup_tables($backup_tables_list, false, $archive)) {
				$backup_tables_list_string = implode(', ', $backup_tables_list);
				if (is_array($tables_backup_result)) {
					$tables_backup_result = implode('<br/>', $tables_backup_result);

					$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following files: <br/>' . $tables_backup_result);
				} else {
					$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following file: <br/>' . $tables_backup_result);
				}

			}
		}
		if (!empty($backup_database_type) && $backup_database_type == 'partial' && !empty($backup_tables_type) && in_array('csv', $backup_tables_type)) {
			if ($csv_backup_result = $this->dashboard_model->backup_tables_csv($backup_tables_list, $archive)) {
				$backup_tables_list_string = implode(', ', $backup_tables_list);
				if (is_array($csv_backup_result)) {
					$csv_backup_result = implode('<br/>', $csv_backup_result);

					$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following files: <br/>' . $csv_backup_result);
				} else {
					$this->set_message('Selected tables: ' . $backup_tables_list_string . ' successfully exported in following file: <br/>' . $csv_backup_result);
				}
			}

		}
		$this->redirect('?view=' . $this->current_page);

	}

	/**
	 * Handles submission from backup files section 
	 * 
	 * @return void 
	 */
	function submit_backup_files(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('backup_files')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$this->backup_all_files();
	}


	/**
	 * Creates recursivelly  backup of all files stored in main WordPress directory
	 * 
	 * @return void 
	 */
	function backup_all_files()
	{
		set_time_limit(0);
		$view_url = $this->dashboard_model->settings['view_url'];

		$wp_base_name = basename($this->wp_dir);
		$sfstore = $this->settings['sfstore'];
		$date = date('d-m-Y--H-i-s');
		$file = $sfstore . 'file_backup/full/filesbackup_' . $date . '.zip';

		if (DashboardHelpers::zip_all_data($this->wp_dir, $file)) {
			$this->set_message('All site files successfully archived in ' . $file);
		}
		$this->redirect('?view=' . $this->current_page);

	}


	/**
	 * Triggers method in model to backup full WordPress database or partial only selected tables 
	 * 
	 * @return void 
	 */
	function backup_database()
	{

		$this->data['mysql']['tables'] = $this->dashboard_model->backup_tables();

		$this->render($this->view_url . 'mysqlbackup', $this->data);
	}


	/**
	 * Renders search and replace section 
	 * 
	 * @return void 
	 */
	function view_search_replace()
	{

		$this->data['tables'] = $this->dashboard_model->show_tables();
		$this->render($this->view_url . 'search_replace', $this->data);
	}

	/**
	 * Handles submission from search and replace 
	 * 
	 * @return array|void|mixed  
	 */
	function submit_search_replace(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('search_replace')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$allowed_criteria_term = array('contains', 'exact', 'any');
		$allowed_criteria_db = array('full', 'partial');

		$search_term = filter_input(INPUT_POST, 'term');
		$search_criteria_term = filter_input(INPUT_POST, 'search_criteria_term');
		$search_criteria_db = filter_input(INPUT_POST, 'search_criteria_db');
		$search_criteria_tables = filter_input(INPUT_POST, 'search_tables_list', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

		$args = array();

		$args['criteria']['term'] = (!empty($search_criteria_term) && in_array($search_criteria_term, $allowed_criteria_term)) ? $search_criteria_term : 'contains';
		$args['criteria']['db'] = (!empty($search_criteria_db) && in_array($search_criteria_db, $allowed_criteria_db)) ? $search_criteria_db : 'full';
		if (!empty($search_criteria_tables) && $search_criteria_db == 'partial') {
			$args['criteria']['tables'] = $search_criteria_tables;
		}


		if (!empty($search_term)) {
			$this->data['search_results'] = $this->dashboard_model->db_search($search_term, $args);
		}


	}


	/**
	 * Calls method from model to get WordPress core info 
	 * 
	 * @return boolean|array false if method from model returns empty results, or list of core info upon success 
	 */
	public function get_wordpress_core_info()
	{

		$versions = $this->dashboard_model->get_core_info();
		if ($versions) {

			return $versions;
		}
		return false;
	}

	/**
	 * Calls method from model to get all plugins data 
	 * 
	 * @return boolean|array false if method from model returns empty results, or list of plugins info upon success
	 */
	public function get_plugins_info()
	{

		$plugins = $this->dashboard_model->scan_plugins_directory($this->wp_dir);
		if ($plugins) {
			return $plugins;
		}
		return false;
	}

	/**
	 * Calls method from model to get all themes data 
	 * 
	 * @return boolean|array false if method from model returns empty results, or list of themes info upon success
	 */
	public function get_themes_info()
	{

		$versions = $this->dashboard_model->get_all_themes($this->wp_dir);
		if ($versions) {
			return $versions;
		}
		return false;
	}

	/**
	 * Calls method from model to get php vars information 
	 * 
	 * @return array php info data with description
	 */
	function get_php_info()
	{
		$description = $this->dashboard_model->get_php_ini_vars();
		return $description;
	}

	/**
	 * Calls method from model to get information from $_SERVER 
	 * 
	 * @return array set of data from $_SERVER along with description 
	 */
	function get_script_url(): string
	{
		$server_option = $this->dashboard_model->get_server_options();
		return $server_option;
	}

	/**
	 * Scans recursivelly through WordPress core files and compares with clean copy of WordPress files from wordpress.org. 
	 * 
	 * @return array 
	 */
	public function action_core_scan(): void
	{
		set_time_limit(0);
		$time_start = microtime(true);
		$versions = $this->dashboard_model->get_core_info();
		if ($versions && isset($versions['wp_version'])) {
			$wp_version = $versions['wp_version']['version'];

			// SECURITY FIX: Validate wp_version format to prevent command injection or path traversal
			if (!preg_match('/^[0-9]+\.[0-9]+(\.[0-9]+)?$/', $wp_version)) {
				$this->set_message('Invalid WordPress version format');
				$this->redirect();
				return;
			}

			$remote_file = 'https://wordpress.org/wordpress-' . $wp_version . '.zip';

			$local_file = $this->settings['sfstore'] . 'temp/wordpress-' . $wp_version . '.zip';
			$remote_file_download = true;
			if (file_exists($local_file)) {
				$md5_remote = 'https://wordpress.org/wordpress-' . $wp_version . '.zip.md5';
				$md5_remote = file_get_contents($md5_remote);
				$md5_local = md5_file($local_file);
				if ($md5_remote === $md5_local) {
					//	echo 'files identical';
					$remote_file_download = false;
				}
			}
			//https://wordpress.org/wordpress-4.3.1.zip.md5
			if ($remote_file_download == true) {
				DashboardHelpers::remote_download($remote_file, $local_file);
			}

			DashboardHelpers::unzip_data($local_file);
			$original_wp_dir = $this->settings['sfstore'] . 'temp/wordpress/';
			$original_wp_files = DashboardHelpers::scan_directory_recursive($original_wp_dir);
			$site_wp_files = DashboardHelpers::scan_directory_recursive($this->wp_dir);
			//echo '<pre>'.print_r($site_wp_files,true).'</pre>';
			$files_compared = 0;
			$files_missing_core = array();
			$files_different = array();
			foreach ($original_wp_files as $file_path => $file) {
				if (isset($site_wp_files[$file_path])) {
					$files_compared++;
					if ($site_wp_files[$file_path]['md5'] != $file['md5']) {
						if (!strstr($file_path, 'wp-content') && !strstr($file_path, 'wp-content/themes') && !strstr($file_path, 'wp-content/plugins')) {
							$this->set_message('files are different: ' . $file_path);
							$files_different[] = $file_path;
						}
					}
				} else {
					if (!strstr($file_path, 'wp-content') && !strstr($file_path, 'wp-content/themes') && !strstr($file_path, 'wp-content/plugins')) {
						$files_missing_core[] = $file_path;

					}

				}
			}

			$this->set_message('total files compared ' . $files_compared);
			$this->set_message('skipped folders and files from wp-content');
			if (count($files_missing_core) > 0) {
				$this->set_message('files missing: <pre>' . print_r($files_missing_core, true) . '</pre>');
			}
			//if(count($files_different) > 0){
			$this->set_message('total different files found: ' . count($files_different));
			//}


		}

		$time_end = microtime(true);
		//dividing with 60 will give the execution time in minutes other wise seconds
		$execution_time = ($time_end - $time_start);

		//execution time of the script
		$this->set_message('<b>Total Scan Time:</b> ' . $execution_time . ' Seconds');
		$this->redirect();

	}
	public function help_info_file($page = '')
	{
		$store = $this->dashboard_model->settings['sfstore'];
		$page = filter_input(INPUT_GET, 'view');
		$file_name = $store . $page . ".txt";
		if (file_exists($file_name)) {
			$text = file_get_contents($file_name);
			return $text;
		}

	}

	/**
	 * Triggers automatic backup of all site data, depending on settings in autobackup section 
	 * 
	 * @return void 
	 */
	function action_autobackup(): void
	{
		set_time_limit(0);
		$default_interval = '6 hours';


		$autobackup_settings_file = $this->settings['sfstore'] . 'autobackup_settings.json';
		if (file_exists($autobackup_settings_file)) {
			$autobackup_settings = DashboardHelpers::get_data($autobackup_settings_file, true);


			if ($autobackup_settings == false || !is_array($autobackup_settings)) {
				return;
			}
			if (!isset($autobackup_settings['last_autobackup']))
				return;

			if (!isset($autobackup_settings['enable_autobackup']) || $autobackup_settings['enable_autobackup'] != 1) {

				return;
			}



			if (!isset($autobackup_settings['interval'])) {
				$interval = $default_interval;
			} else {
				$interval = $autobackup_settings['interval'];
			}

			$current_time = strtotime('now - ' . $interval);
			$last_cron = $autobackup_settings['last_autobackup'];

			if ($current_time < $last_cron) {
				return;
			}


			//do the magic 

			if (isset($autobackup_settings['prefix'])) {
				$prefix = $autobackup_settings['prefix'];
			} else {
				$prefix = '';
			}
			$prefix .= 'autobackup_';
			$date = date('d-m-Y--H-i-s');
			if (isset($autobackup_settings['htaccess_backup']) && $autobackup_settings['htaccess_backup'] == 1) {
				$file_to_backup = $this->wp_dir . '.htaccess';
				$sourcedir = $this->settings['safemode_dir'] . $this->settings['sfstore'] . '/htaccess_backup/' . $prefix . $date . '_htaccess';
				$source = file_get_contents($file_to_backup);
				file_put_contents($sourcedir, $source);
				$this->set_message("Htaccess backup is successfully stored at " . $sourcedir);
			}
			//wp config file backup 
			if (isset($autobackup_settings['wp_config_backup']) && $autobackup_settings['wp_config_backup'] == 1) {
				$file_to_backup = $this->wp_dir . 'wp-config.php';
				$sourcedir = $this->settings['safemode_dir'] . $this->settings['sfstore'] . '/wp_config_backup/' . $prefix . $date . '_wp-config.php';
				$source = file_get_contents($file_to_backup);
				file_put_contents($sourcedir, $source);
				$this->set_message("WP Config file backup is successfully stored at " . $sourcedir);
			}
			//files backup 
			if (isset($autobackup_settings['files_backup']) && $autobackup_settings['files_backup'] == 1) {
				$this->wp_dir = $this->settings['wp_dir'];
				$file = $this->settings['safemode_dir'] . $this->settings['sfstore'] . '/file_backup/full/' . $prefix . $date . '.zip';

				if (DashboardHelpers::zip_all_data($this->wp_dir, $file)) {
					$this->set_message('All site files successfully archived in ' . $file);
				}
			}

			//database backup 
			if (isset($autobackup_settings['full_backup']) && $autobackup_settings['full_backup'] == 1) {

				$this->dashboard_model->backup_tables('', true, true, $prefix . $date);
				$this->set_message("Full database backup is successfully stored");
			}
			$autobackup_settings['last_autobackup'] = strtotime('now');
			DashboardHelpers::put_data($autobackup_settings_file, $autobackup_settings, true);
			$this->set_message('Autobackup done');
			$this->redirect();
		}

	}

	/**
	 * Calls method from model to optimize tables from active database 
	 * 
	 * @return void 
	 */
	function action_optimize_tables(): void
	{
		$this->dashboard_model->optimize_tables();
		$this->set_message('All database tables have been optimized');
		$this->redirect();
	}

	/**
	 * Calls method from model to delete all post revisions
	 * 
	 * @return void 
	 */
	function action_delete_revisions(): void
	{
		$this->dashboard_model->delete_revisions();
		$this->set_message('All post revisions have been removed');
		$this->redirect();
	}

	/**
	 * Calls method from model to delete all comments from db marked as spam 
	 * 
	 * @return void 
	 */
	function action_delete_spam_comments(): void
	{
		$this->dashboard_model->delete_spam_comments();
		$this->set_message('All spam comments have been removed');
		$this->redirect();
	}

	/**
	 * Calls method from model to delete all comments from db marked as unapproved 
	 * 
	 * @return
	 */
	function action_delete_unapproved_comments(): void
	{
		$this->dashboard_model->delete_unapproved_comments();
		$this->set_message('All unapproved comments have been removed');
		$this->redirect();
	}

	/**
	 * Creates quick backup of all files and calls method in model to create full active database backup 
	 * 
	 * @return void 
	 */
	function action_backup()
	{
		$manual_backup = filter_input(INPUT_GET, 'backup');
		if (!empty($manual_backup) && $manual_backup == 'quick') {
			$prefix .= 'quickbackup_';
			$date = date('d-m-Y--H-i-s');
			$this->wp_dir = $this->settings['wp_dir'];
			$file = $this->settings['safemode_dir'] . $this->settings['sfstore'] . '/file_backup/full/' . $prefix . $date . '.zip';

			if (DashboardHelpers::zip_all_data($this->wp_dir, $file)) {
				$this->set_message('Quick backup of all site files successfully archived in ' . $file);
			}

			$this->dashboard_model->backup_tables('', true, true, $prefix . $date);
			$this->set_message("Full database quick backup is successfully stored");

		}
	}
	/**
	 * Handless submission from autobackup settings section 
	 * 
	 * @return void 
	 */
	function submit_autobackup(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('autobackup')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$default_interval = '6 hours';
		$submit_autobackup = filter_input(INPUT_POST, 'submit_autobackup');

		//$autobackup_settings = 
		if (!isset($submit_autobackup) || empty($submit_autobackup)) {
			return;
		}
		$autobackup_settings_file = $this->settings['sfstore'] . 'autobackup_settings.json';

		$autobackup_settings = DashboardHelpers::get_data($autobackup_settings_file, true);
		if ($autobackup_settings == false) {
			$autobackup_settings = array();
		}


		$autobackup_inputs = array();
		$autobackup_inputs['enable_autobackup'] = 'enable_autobackup';
		$autobackup_inputs['full_backup'] = 'full_backup';
		$autobackup_inputs['files_backup'] = 'files_backup';
		$autobackup_inputs['htaccess_backup'] = 'htaccess_backup';
		$autobackup_inputs['wp_config_backup'] = 'wp_config_backup';
		$autobackup_inputs['prefix'] = 'prefix';
		$autobackup_inputs['interval'] = 'interval';
		$reset_interval = filter_input(INPUT_POST, 'reset_interval');

		// $autobackup_settings_new = array();
		foreach ($autobackup_inputs as $autobackup_key => $autobackup_input) {
			$input = filter_input(INPUT_POST, $autobackup_key);
			if (!empty($input)) {
				if ($autobackup_key != 'interval' && $autobackup_key != 'prefix') {
					//	$autobackup_settings_new = ''
					$autobackup_settings[$autobackup_key] = 1;
				} else {
					if ($autobackup_key == 'interval' || $autobackup_key == 'prefix') {
						$autobackup_settings[$autobackup_key] = $input;
					}

				}
			} else {
				if (isset($autobackup_settings[$autobackup_key])) {
					unset($autobackup_settings[$autobackup_key]);
				}
			}
		}
		if (!isset($autobackup_settings['interval'])) {
			$autobackup_settings['interval'] = $default_interval;
		}
		if (!isset($autobackup_settings['last_autobackup'])) {
			$autobackup_settings['last_autobackup'] = strtotime('now - ' . $autobackup_settings['interval']);
		}
		if (!empty($reset_interval)) {
			$autobackup_settings['last_autobackup'] = strtotime('now - ' . $autobackup_settings['interval']);
		}




		DashboardHelpers::put_data($autobackup_settings_file, $autobackup_settings, true);
		$this->set_message('Autobackup settings saved');
		$this->redirect();
		return;

	}


	/**
	 * Checks if maintenances for site is active or not 
	 * 
	 * @param boolean $set_message if set to false it will not leave a message on dashboard 
	 * 
	 * @return boolean depends on if maintenance is active or not 
	 */
	function action_check_maintenance($redirect = true): bool
	{


		$htaccess_content = DashboardHelpers::get_data($this->htaccess_path);

		if (!empty($htaccess_content) && $htaccess_content) {
			if (strstr($htaccess_content, 'WPSM-MAINTENANCE')) {
				if ($redirect == true) {
					$this->set_message('Wordpress site is in maintenance mode. To disable it, go to Quick Actions.');
				}


				return true;
			}
		} else {

		}

		return false;
	}

	/**
	 * Triggers methods to disable or enable maintenance mode 
	 * 
	 * @return void 
	 */
	function action_maintenance(): void
	{

		if (!empty($this->action)) {
			if ($this->action == 'maintenance_enable') {
				$this->htaccess_service->maintenance_mode_on();


			} elseif ($this->action == 'maintenance_disable') {
				$this->htaccess_service->maintenance_mode_off();
				$this->set_message('Maintenance mode disabled');
			}
		}
	}






	/**
	 * Resets .htaccess to initial state 
	 * 
	 * @return void 
	 */
	function reset_htaccess()
	{
		return;
	}

	/**
	 * Handles submission of new site url 
	 * 
	 * @return void 
	 */
	function submit_site_url(): void
	{
		// SECURITY FIX: Validate CSRF token
		if (!CSRFProtection::validate_post_token('site_url')) {
			$this->set_message('Invalid CSRF token');
			$this->redirect();
			return;
		}
		$siteurl = filter_input(INPUT_POST, 'site_url');
		$home = filter_input(INPUT_POST, 'home_url');

		if (empty($home)) {
			$this->set_message('Home URL cannot be empty');
			return;
		}
		if (empty($siteurl)) {
			$this->set_message('Site URL cannot be empty');
			return;
		}
		$this->dashboard_model->update_site_url($home, $siteurl);
		$this->set_message('Home and SiteUrl have been changed');
		$this->redirect();
	}


	function create_robots_file(): void
	{
		$file = $this->dashboard_model->settings['wp_dir'] . 'robots.txt';
		$content = "User-agent: *\nDisallow: /";
		file_put_contents($file, $content);
		$this->set_message("File is successfully created");
		$this->redirect();
		return;
	}



}