<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
if(function_exists('session_status') && session_status() == PHP_SESSION_NONE){
	session_start();
}else{
	if(session_id() == '') {
    session_start();
    }
}
if(!file_exists('settings.php')){
	if(!file_exists('settings.sample.php')){
		die('settings.sample.php missing. Please download new WP Safe Mode');
		return;
	}
	$file_contents = file_get_contents('settings.sample.php');
	file_put_contents('settings.php',$file_contents);
	
}

//TODO wrap up this in DashboardHelpers class 
if (!defined('T_ML_COMMENT')) {
   define('T_ML_COMMENT', T_COMMENT);
} else {
   define('T_DOC_COMMENT', T_ML_COMMENT);
}

define("HTPASSWDFILE", ".htpasswd");


// Load core classes first
include_once('core/Config.php');
include_once('core/Database.php');
include_once('core/Response.php');
include_once('core/InputValidator.php');
include_once('core/Logger.php');
include_once('core/Cache.php');

// Load services
include_once('services/SystemHealthService.php');
include_once('services/FileManagerService.php');
include_once('services/UserManagementService.php');
include_once('services/CronService.php');
include_once('services/ActivityLogService.php');
include_once('services/EmailService.php');
include_once('services/SecurityScannerService.php');
include_once('services/PerformanceProfilerService.php');
include_once('services/MediaLibraryService.php');
include_once('services/DatabaseOptimizerService.php');

// Load settings (for backward compatibility)
include_once('settings.php');
include_once 'helpers/helpers.php';

// Security classes - load before models and controllers
include_once('security/SecurityFixes.php');
include_once('security/CSRFProtection.php');
include_once('security/RateLimiter.php');

// Configure secure session settings
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.use_strict_mode', 1);

include_once ('model/db.model.php');
include_once ('model/dashboard.model.php');
include_once ('model/help.model.php');
include_once ('model/basicinfo.model.php');
include_once 'controller/main.controller.php';
include_once 'controller/dashboard.controller.php';

