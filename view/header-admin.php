<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WP Safe Mode - Admin Dashboard</title>
    
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AdminLTE 3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin-custom.css">
    
    <link rel="icon" type="image/ico" href="favicon.ico">
    
    <style>
        /* Loading Screen - Fixed for Mobile */
        .app-loader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            max-width: 100%;
            max-height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(2px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            color: white;
            margin: 0;
            padding: 0;
            overflow: hidden;
            -webkit-overflow-scrolling: touch;
        }
        
        .app-loader p {
            margin-top: 1rem;
            font-size: 1rem;
            font-weight: 500;
            color: white;
        }
        
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            flex-shrink: 0;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Content Transitions */
        #main-content {
            transition: opacity 0.3s ease-in-out;
            min-height: 200px;
        }
        
        /* Content Wrapper Fixes */
        .content-wrapper {
            min-height: calc(100vh - 57px);
            position: relative;
        }
        
        /* Mobile Responsive Fixes */
        @media (max-width: 768px) {
            .app-loader {
                z-index: 999999;
            }
            
            .spinner {
                width: 40px;
                height: 40px;
                border-width: 3px;
            }
            
            .app-loader p {
                font-size: 0.9rem;
            }
            
            .content-wrapper {
                min-height: calc(100vh - 50px);
            }
            
            /* Prevent horizontal scroll on mobile */
            body {
                overflow-x: hidden;
            }
            
            .wrapper {
                overflow-x: hidden;
            }
        }
        
        /* Fix for iOS Safari viewport issues */
        @supports (-webkit-touch-callout: none) {
            .app-loader {
                position: -webkit-sticky;
                position: sticky;
            }
        }
        
        /* Prevent body scroll when loader is active */
        body.loading-active {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed <?php echo (isset($data['current_page']) && $data['current_page'] == 'login') ? 'login-page' : ''; ?>">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="?view=info" data-view="info" class="nav-link">Dashboard</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link">
                    <small class="text-muted">v0.6 beta</small>
                </span>
            </li>
            <li class="nav-item">
                <a href="http://wpsafemode.com/bug-report/" target="_blank" class="nav-link">
                    <i class="fas fa-bug"></i> Bug Report
                </a>
            </li>
            <li class="nav-item">
                <a href="http://wpsafemode.com/contact-us/" target="_blank" class="nav-link">
                    <i class="fas fa-envelope"></i> Contact
                </a>
            </li>
            <?php if(isset($data['login']) && $data['login'] == true): ?>
            <li class="nav-item">
                <a href="<?php echo DashboardHelpers::build_url('',array('view'=>'info' , 'action' => 'logout')); ?>" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="?view=info" data-view="info" class="brand-link">
            <img src="assets/img/safemode-logo.png" alt="WP Safe Mode" class="brand-image img-circle elevation-3" style="opacity: .8" onerror="this.style.display='none'">
            <span class="brand-text font-weight-light">WP Safe Mode</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <?php if(isset($data['menu_items'])): ?>
                    <?php foreach($data['menu_items'] as $menu_item): ?>
                        <?php if(isset($menu_item['disabled']) && $menu_item['disabled'] == true): ?>
                        <li class="nav-item">
                            <a href="#" class="nav-link disabled">
                                <i class="nav-icon <?php 
                                    $icon = isset($menu_item['icon']) ? $menu_item['icon'] : 'fas fa-circle';
                                    // Convert various icon formats to Font Awesome
                                    $icon = str_replace('icon ', 'fas fa-', $icon);
                                    $icon = str_replace('fi-', 'fas fa-', $icon);
                                    // Map common icon names
                                    $iconMap = array(
                                        'info' => 'fas fa-info-circle',
                                        'plugins' => 'fas fa-plug',
                                        'themes' => 'fas fa-paint-brush',
                                        'wpconfig' => 'fas fa-cog',
                                        'backup' => 'fas fa-database',
                                        'htaccess' => 'fas fa-file-code',
                                        'robots' => 'fas fa-robot',
                                        'error_log' => 'fas fa-exclamation-triangle',
                                        'autobackup' => 'fas fa-clock',
                                        'quick_actions' => 'fas fa-bolt',
                                        'global_settings' => 'fas fa-sliders-h',
                                        'ai-assistant' => 'fas fa-robot'
                                    );
                                    $slug = isset($menu_item['slug']) ? $menu_item['slug'] : '';
                                    if (isset($iconMap[$slug])) {
                                        $icon = $iconMap[$slug];
                                    }
                                    echo htmlspecialchars($icon);
                                ?>"></i>
                                <p><?php echo htmlspecialchars($menu_item['name']); ?> <small class="badge badge-warning">Soon</small></p>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="nav-item">
                            <a href="<?php echo isset($menu_item['link'])?$menu_item['link']:'#'; ?>" 
                               data-view="<?php echo $menu_item['slug']; ?>"
                               class="nav-link <?php echo (isset($data['current_page']) && $data['current_page'] == $menu_item['slug'])?'active':''; ?>">
                                <i class="nav-icon <?php 
                                    $icon = isset($menu_item['icon']) ? $menu_item['icon'] : 'fas fa-circle';
                                    // Convert various icon formats to Font Awesome
                                    $icon = str_replace('icon ', 'fas fa-', $icon);
                                    $icon = str_replace('fi-', 'fas fa-', $icon);
                                    // Map common icon names
                                    $iconMap = array(
                                        'info' => 'fas fa-info-circle',
                                        'plugins' => 'fas fa-plug',
                                        'themes' => 'fas fa-paint-brush',
                                        'wpconfig' => 'fas fa-cog',
                                        'backup' => 'fas fa-database',
                                        'htaccess' => 'fas fa-file-code',
                                        'robots' => 'fas fa-robot',
                                        'error_log' => 'fas fa-exclamation-triangle',
                                        'autobackup' => 'fas fa-clock',
                                        'quick_actions' => 'fas fa-bolt',
                                        'global_settings' => 'fas fa-sliders-h',
                                        'ai-assistant' => 'fas fa-robot'
                                    );
                                    $slug = isset($menu_item['slug']) ? $menu_item['slug'] : '';
                                    if (isset($iconMap[$slug])) {
                                        $icon = $iconMap[$slug];
                                    }
                                    echo htmlspecialchars($icon);
                                ?>"></i>
                                <p><?php echo htmlspecialchars($menu_item['name']); ?></p>
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0" id="page-title">Dashboard</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="?view=info" data-view="info">Home</a></li>
                            <li class="breadcrumb-item active" id="breadcrumb-current">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid" id="main-content">
                
                <!-- Messages -->
                <?php if(isset($data['message'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo $data['message']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>


