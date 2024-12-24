<?php


$title = 'All Plugins Info';
include_once 'header.php';

$this->all_plugins_info = $this->dashboard_model->scan_wordpress_plugins('../benchmark-safemod/');


include_once 'footer.php';