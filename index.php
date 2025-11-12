<?php
/**
* 
* WP Safe Mode v0.06 beta 
* @author CloudIndustry - http://cloud-industry.com 
* @author and contributors Nikola Kirincic, Marko Tiosavljevic, Daliborka Ciric, Luka Cvetinovic , Nikola Stojanovic
* @see For more information about installation, usage, licensing and other notes see README 
  @author
*/

define('WPSM',true);

include_once('autoload.php');

// Handle AI API requests
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/ai/') !== false) {
    include_once('controller/ai.controller.php');
    $ai = new AIController();
    $ai->handle();
    exit;
}

// Handle API requests first (before normal page rendering)
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    include_once('controller/api.controller.php');
    $api = new ApiController();
    $api->handle();
    exit;
}