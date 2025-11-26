<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WP Safe Mode - Premium Dashboard</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">

    <!-- Premium Safe Mode CSS -->
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/components.css">

    <link rel="icon" type="image/ico" href="favicon.ico">
</head>

<body>
    <div class="app-layout">

        <!-- Sidebar Navigation -->
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <span class="material-symbols-outlined"
                    style="font-size: 2rem; color: var(--color-primary);">shield</span>
                <span>WP Safe Mode</span>
            </div>

            <nav>
                <ul class="nav-list">
                    <?php if (isset($data['menu_items'])): ?>
                        <?php foreach ($data['menu_items'] as $menu_item): ?>
                            <?php
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
                            $icon = isset($iconMap[$slug]) ? $iconMap[$slug] : 'circle';
                            $isActive = (isset($data['current_page']) && $data['current_page'] == $slug);
                            $isDisabled = isset($menu_item['disabled']) && $menu_item['disabled'] == true;
                            ?>

                            <?php if ($isDisabled): ?>
                                <li class="nav-item" style="opacity: 0.5; cursor: not-allowed;">
                                    <span class="material-symbols-outlined nav-icon"><?php echo $icon; ?></span>
                                    <span><?php echo htmlspecialchars($menu_item['name']); ?></span>
                                    <span class="badge badge-warning" style="margin-left: auto;">Soon</span>
                                </li>
                            <?php else: ?>
                                <a href="<?php echo isset($menu_item['link']) ? $menu_item['link'] : '#'; ?>"
                                    class="nav-item <?php echo $isActive ? 'active' : ''; ?>">
                                    <span class="material-symbols-outlined nav-icon"><?php echo $icon; ?></span>
                                    <span><?php echo htmlspecialchars($menu_item['name']); ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="app-main">

            <!-- Page Header -->
            <header class="page-header">
                <h1 class="page-title" id="page-title">
                    <?php echo isset($data['current_page']) ? ucfirst(str_replace('_', ' ', $data['current_page'])) : 'Dashboard'; ?>
                </h1>

                <div style="display: flex; gap: var(--space-md); align-items: center;">
                    <?php if (isset($data['login']) && $data['login'] == true): ?>
                        <a href="<?php echo DashboardHelpers::build_url('', array('view' => 'info', 'action' => 'logout')); ?>"
                            class="btn btn-outline">
                            <span class="material-symbols-outlined">logout</span>
                            Logout
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Messages -->
            <?php if (isset($data['message'])): ?>
                <div class="card"
                    style="background-color: var(--color-primary-dim); border-color: var(--color-primary); margin-bottom: var(--space-lg);">
                    <div style="display: flex; align-items: center; gap: var(--space-md);">
                        <span class="material-symbols-outlined" style="color: var(--color-primary);">info</span>
                        <span style="flex: 1;"><?php echo $data['message']; ?></span>
                        <button onclick="this.parentElement.parentElement.remove()"
                            style="background: none; border: none; color: var(--color-text-muted); cursor: pointer;">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Content starts here -->