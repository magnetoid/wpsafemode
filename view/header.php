<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>WP Safe Mode</title>
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,300,300italic,400italic,600,600italic,700,700italic,800,800italic&subset=latin,latin-ext' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="assets/css/foundation.css" />

	<link href="http://cdnjs.cloudflare.com/ajax/libs/foundicons/3.0.0/foundation-icons.css" rel="stylesheet">
	<link rel="stylesheet" href="assets/css/style.css" />
	<link rel="stylesheet" href="assets/css/normalize.css" />
	<link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="assets/css/et-icons.css" />
	<link rel="stylesheet" href="assets/css/wpsafemode.css" />
	<script src="assets/js/vendor/modernizr.js"></script>
	<link rel="icon" type="image/ico" href="favicon.ico" />
	<style>
		.app-loader {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0,0,0,0.7);
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
			z-index: 9999;
			color: white;
		}
		.spinner {
			border: 4px solid #f3f3f3;
			border-top: 4px solid #3498db;
			border-radius: 50%;
			width: 40px;
			height: 40px;
			animation: spin 1s linear infinite;
		}
		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		#main-content {
			transition: opacity 0.2s ease-in-out;
		}
	</style>
</head>
<body>

	<?php include('menu.php'); ?>

	<section class="main">
  	<nav>
	    <div class="row row-fluid text-center">
	        <div class="large-12 columns">	        
	            <label class="version">Version 0.6 beta</label>
	            <a href="http://wpsafemode.com/" target="_blank"><img src="assets/img/logo-nav.png" width="150" alt="" class="logo"></a> 
	            <a href="http://wpsafemode.com/bug-report/" target="_blank" class="btn btn-blue pull-right">Bug Report</a>
	            <a href="http://wpsafemode.com/contact-us/" target="_blank" class="btn btn-blue pull-right">Contact us</a>	
	            <?php if(isset($data['login']) && $data['login'] == true): ?>
	            <a href="<?php  echo DashboardHelpers::build_url('',array('view'=>'info' , 'action' => 'logout'));  ?>" class="btn btn-blue pull-right">Logout</a>	
	            <?php endif; ?>
	        </div>
	    </div>
	</nav>
  
<div class="row content" id="main-content">	
  	
  	<?php if(isset($data['message'])): ?>
  	 <div class="row">
  	   <div class="columns large-12">
  	     <div class="alert-box [radius round]" data-alert>
  		<?php echo $data['message'] ?><a href="#" class="close">&times;</a>
     	</div>
  	   </div>
  	 </div>
  	<?php endif; ?>
