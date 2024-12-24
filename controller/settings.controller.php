<?php


class SettingsController{
	
	public $settings;
	
	
	function __construct(){
	include_once('settings.php');	
	
	}
	
	
	
}

global $settings;

$settings = new SettingsController;