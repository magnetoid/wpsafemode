<?php

global $settings;

$settings = array();
$settings['demo'] = true;
$settings['debug'] = false;  // Set to false in production
$settings['safemode_url'] = '';  //url of your safe mode script
$settings['sfstore'] = 'sfstore/';  //directory to store your backup files 
$settings['wp_dir'] = '../';  //directory path to your wordpress site, not url, add trailing slash, only change this if wpsafemode tool is not in root of your WordPress website
$settings['safemode_dir'] = str_replace('\\', '/', dirname(__FILE__)) . '/'; //don't touch this


$settings['view_url'] = 'view/'; //don't touch this

//include_once(  $settings['wp_dir'] . 'wp-config.php'); //don't touch this
//avoid loading wp-settings.php and only loading variables and constants from wp-config.php
if (file_exists('wp-config-temp.php')) {
    unlink('wp-config-temp.php');
}
if (!file_exists($settings['wp_dir'] . 'wp-config.php')) {
    die('wp-config.php not found. Please check your WordPress site\'s files.');
    //exit;
}
$wp_config_data = file_get_contents($settings['wp_dir'] . 'wp-config.php');
$wp_config_data = str_replace("require_once(ABSPATH . 'wp-settings.php');", "//require_once(ABSPATH . 'wp-settings.php');", $wp_config_data);
file_put_contents('wp-config-temp.php', $wp_config_data);
include_once('wp-config-temp.php');

$settings['wp_db_prefix'] = $table_prefix; //don't touch this
