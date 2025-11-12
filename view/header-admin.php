<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WP Safe Mode - Admin Dashboard</title>
    
    <!-- Material Design 3 - Roboto Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <!-- Material Design 3 CSS -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@material/web@1.0.0-pre.19/all.min.css">
    
    <!-- Material Components for Web -->
    <link rel="stylesheet" href="https://unpkg.com/material-components-web@latest/dist/material-components-web.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin-custom.css">
    
    <link rel="icon" type="image/ico" href="favicon.ico">
    
    <style>
        /* Material Design 3 Theme Variables */
        :root {
            --md-sys-color-primary: #6750A4;
            --md-sys-color-on-primary: #FFFFFF;
            --md-sys-color-primary-container: #EADDFF;
            --md-sys-color-on-primary-container: #21005D;
            --md-sys-color-secondary: #625B71;
            --md-sys-color-on-secondary: #FFFFFF;
            --md-sys-color-secondary-container: #E8DEF8;
            --md-sys-color-on-secondary-container: #1D192B;
            --md-sys-color-tertiary: #7D5260;
            --md-sys-color-on-tertiary: #FFFFFF;
            --md-sys-color-error: #BA1A1A;
            --md-sys-color-on-error: #FFFFFF;
            --md-sys-color-surface: #FFFBFE;
            --md-sys-color-on-surface: #1C1B1F;
            --md-sys-color-surface-variant: #E7E0EC;
            --md-sys-color-on-surface-variant: #49454F;
            --md-sys-color-outline: #79747E;
            --md-sys-color-shadow: #000000;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: var(--md-sys-color-surface);
            color: var(--md-sys-color-on-surface);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Loading Screen - Material Design 3 Style */
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
            backdrop-filter: blur(4px);
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
            margin-top: 1.5rem;
            font-size: 1rem;
            font-weight: 400;
            color: white;
            font-family: 'Roboto', sans-serif;
        }
        
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--md-sys-color-primary);
            border-radius: 50%;
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
        
        /* Prevent body scroll when loader is active */
        body.loading-active {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
        
        /* Material Design 3 Layout */
        .md3-layout {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .md3-top-app-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: var(--md-sys-color-surface);
            color: var(--md-sys-color-on-surface);
            box-shadow: 0px 2px 3px 0px rgba(0, 0, 0, 0.3), 0px 1px 1px 0px rgba(0, 0, 0, 0.2), 0px 1px 3px 0px rgba(0, 0, 0, 0.12);
        }
        
        .md3-navigation-drawer {
            position: fixed;
            top: 64px;
            left: 0;
            width: 256px;
            height: calc(100vh - 64px);
            background-color: var(--md-sys-color-surface);
            box-shadow: 2px 0px 3px 0px rgba(0, 0, 0, 0.3), 1px 0px 1px 0px rgba(0, 0, 0, 0.2), 1px 0px 3px 0px rgba(0, 0, 0, 0.12);
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
            z-index: 999;
        }
        
        .md3-navigation-drawer.closed {
            transform: translateX(-100%);
        }
        
        .md3-content {
            margin-left: 256px;
            margin-top: 64px;
            padding: 24px;
            min-height: calc(100vh - 64px);
            transition: margin-left 0.3s ease-in-out;
        }
        
        .md3-content.full-width {
            margin-left: 0;
        }
        
        /* Mobile Responsive */
        @media (max-width: 960px) {
            .md3-navigation-drawer {
                transform: translateX(-100%);
                box-shadow: 2px 0px 8px 0px rgba(0, 0, 0, 0.3);
            }
            
            .md3-navigation-drawer.open {
                transform: translateX(0);
            }
            
            .md3-content {
                margin-left: 0;
            }
            
            .md3-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 998;
                display: none;
            }
            
            .md3-overlay.show {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .md3-top-app-bar {
                height: 56px;
            }
            
            .md3-navigation-drawer {
                top: 56px;
                height: calc(100vh - 56px);
            }
            
            .md3-content {
                margin-top: 56px;
                padding: 16px;
            }
            
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
        }
    </style>
</head>
<body class="<?php echo (isset($data['current_page']) && $data['current_page'] == 'login') ? 'login-page' : ''; ?>">
<div class="md3-layout">

    <!-- Top App Bar -->
    <header class="md3-top-app-bar">
        <div style="display: flex; align-items: center; height: 64px; padding: 0 16px;">
            <button class="md3-icon-button" id="menu-toggle" style="margin-right: 16px; background: none; border: none; cursor: pointer; padding: 8px; color: var(--md-sys-color-on-surface);">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <h1 class="md3-title" style="flex: 1; margin: 0; font-size: 1.25rem; font-weight: 500; line-height: 2rem; letter-spacing: 0.0125em;" id="page-title">Dashboard</h1>
            <div style="display: flex; gap: 8px;">
                <?php if(isset($data['login']) && $data['login'] == true): ?>
                <a href="<?php echo DashboardHelpers::build_url('',array('view'=>'info' , 'action' => 'logout')); ?>" 
                   class="md3-icon-button" 
                   style="background: none; border: none; cursor: pointer; padding: 8px; color: var(--md-sys-color-on-surface); text-decoration: none; display: flex; align-items: center;">
                    <span class="material-symbols-outlined">logout</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Navigation Drawer -->
    <nav class="md3-navigation-drawer" id="navigation-drawer">
        <div style="padding: 16px;">
            <div style="display: flex; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--md-sys-color-outline); margin-bottom: 8px;">
                <img src="assets/img/safemode-logo.png" alt="WP Safe Mode" style="width: 40px; height: 40px; margin-right: 12px; border-radius: 50%;" onerror="this.style.display='none'">
                <span style="font-size: 1.25rem; font-weight: 500; color: var(--md-sys-color-on-surface);">WP Safe Mode</span>
            </div>
            
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php if(isset($data['menu_items'])): ?>
                <?php foreach($data['menu_items'] as $menu_item): ?>
                    <?php if(isset($menu_item['disabled']) && $menu_item['disabled'] == true): ?>
                    <li style="margin: 4px 0;">
                        <a href="#" class="md3-list-item disabled" style="display: flex; align-items: center; padding: 12px 16px; text-decoration: none; color: var(--md-sys-color-on-surface-variant); border-radius: 28px; cursor: not-allowed;">
                            <span class="material-symbols-outlined" style="margin-right: 12px; font-size: 24px;"><?php 
                                $slug = isset($menu_item['slug']) ? $menu_item['slug'] : '';
                                $iconMap = array(
                                    'info' => 'info',
                                    'plugins' => 'extension',
                                    'themes' => 'palette',
                                    'wpconfig' => 'settings',
                                    'backup' => 'database',
                                    'htaccess' => 'code',
                                    'robots' => 'smart_toy',
                                    'error_log' => 'error',
                                    'autobackup' => 'schedule',
                                    'quick_actions' => 'bolt',
                                    'global_settings' => 'tune',
                                    'ai-assistant' => 'psychology',
                                    'system-health' => 'monitor_heart',
                                    'file-manager' => 'folder',
                                    'users' => 'people',
                                    'cron' => 'schedule',
                                    'database-query' => 'data_object'
                                );
                                echo isset($iconMap[$slug]) ? $iconMap[$slug] : 'circle';
                            ?></span>
                            <span style="flex: 1; font-size: 0.875rem; font-weight: 500;"><?php echo htmlspecialchars($menu_item['name']); ?></span>
                            <span style="font-size: 0.75rem; padding: 2px 8px; background: var(--md-sys-color-secondary-container); color: var(--md-sys-color-on-secondary-container); border-radius: 12px;">Soon</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li style="margin: 4px 0;">
                        <a href="<?php echo isset($menu_item['link'])?$menu_item['link']:'#'; ?>" 
                           data-view="<?php echo $menu_item['slug']; ?>"
                           class="md3-list-item <?php echo (isset($data['current_page']) && $data['current_page'] == $menu_item['slug'])?'active':''; ?>" 
                           style="display: flex; align-items: center; padding: 12px 16px; text-decoration: none; color: var(--md-sys-color-on-surface); border-radius: 28px; transition: background-color 0.2s;">
                            <span class="material-symbols-outlined" style="margin-right: 12px; font-size: 24px;"><?php 
                                $slug = isset($menu_item['slug']) ? $menu_item['slug'] : '';
                                $iconMap = array(
                                    'info' => 'info',
                                    'plugins' => 'extension',
                                    'themes' => 'palette',
                                    'wpconfig' => 'settings',
                                    'backup' => 'database',
                                    'htaccess' => 'code',
                                    'robots' => 'smart_toy',
                                    'error_log' => 'error',
                                    'autobackup' => 'schedule',
                                    'quick_actions' => 'bolt',
                                    'global_settings' => 'tune',
                                    'ai-assistant' => 'psychology',
                                    'system-health' => 'monitor_heart',
                                    'file-manager' => 'folder',
                                    'users' => 'people',
                                    'cron' => 'schedule',
                                    'database-query' => 'data_object'
                                );
                                echo isset($iconMap[$slug]) ? $iconMap[$slug] : 'circle';
                            ?></span>
                            <span style="flex: 1; font-size: 0.875rem; font-weight: 500;"><?php echo htmlspecialchars($menu_item['name']); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Overlay for mobile -->
    <div class="md3-overlay" id="drawer-overlay"></div>

    <!-- Main Content -->
    <main class="md3-content" id="main-content">
        
        <!-- Breadcrumb -->
        <nav style="margin-bottom: 24px;">
            <ol style="display: flex; list-style: none; padding: 0; margin: 0; gap: 8px; font-size: 0.875rem;">
                <li><a href="?view=info" data-view="info" style="color: var(--md-sys-color-primary); text-decoration: none;">Home</a></li>
                <li style="color: var(--md-sys-color-on-surface-variant);">/</li>
                <li id="breadcrumb-current" style="color: var(--md-sys-color-on-surface);">Dashboard</li>
            </ol>
        </nav>

        <!-- Messages -->
        <?php if(isset($data['message'])): ?>
        <div class="md3-snackbar" style="display: flex; align-items: center; padding: 16px; background: var(--md-sys-color-inverse-surface); color: var(--md-sys-color-inverse-on-surface); border-radius: 4px; margin-bottom: 16px; box-shadow: 0px 3px 5px -1px rgba(0, 0, 0, 0.2), 0px 6px 10px 0px rgba(0, 0, 0, 0.14), 0px 1px 18px 0px rgba(0, 0, 0, 0.12);">
            <span class="material-symbols-outlined" style="margin-right: 12px;">info</span>
            <span style="flex: 1;"><?php echo $data['message']; ?></span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; padding: 4px; margin-left: 8px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
            </button>
        </div>
        <?php endif; ?>

