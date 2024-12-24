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


include_once('settings.php');
include_once 'helpers/helpers.php';
include_once ('model/db.model.php');
include_once ('model/dashboard.model.php');
include_once ('model/help.model.php');
include_once 'controller/main.controller.php';
include_once 'controller/dashboard.controller.php';

