<?php
/**
 * Database Connection Diagnostic
 * Tests if WordPress database connection is working
 */
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">database</span>
            Database Connection Diagnostic
        </h3>
    </div>

    <div style="padding: var(--space-lg);">
        <?php
        // Test 1: Check if wp-config constants are defined
        $constants_ok = defined('DB_NAME') && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWORD');
        ?>

        <div style="margin-bottom: var(--space-lg);">
            <h4 style="margin-bottom: var(--space-md);">1. WordPress Configuration Constants</h4>
            <table class="table">
                <tr>
                    <td><strong>DB_NAME</strong></td>
                    <td><?php echo defined('DB_NAME') ? '<span class="badge badge-success">✓ Defined</span>' : '<span class="badge" style="background-color: var(--color-danger);">✗ Not Defined</span>'; ?>
                    </td>
                    <td><?php echo defined('DB_NAME') ? htmlspecialchars(DB_NAME) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong>DB_HOST</strong></td>
                    <td><?php echo defined('DB_HOST') ? '<span class="badge badge-success">✓ Defined</span>' : '<span class="badge" style="background-color: var(--color-danger);">✗ Not Defined</span>'; ?>
                    </td>
                    <td><?php echo defined('DB_HOST') ? htmlspecialchars(DB_HOST) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong>DB_USER</strong></td>
                    <td><?php echo defined('DB_USER') ? '<span class="badge badge-success">✓ Defined</span>' : '<span class="badge" style="background-color: var(--color-danger);">✗ Not Defined</span>'; ?>
                    </td>
                    <td><?php echo defined('DB_USER') ? htmlspecialchars(DB_USER) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong>DB_PASSWORD</strong></td>
                    <td><?php echo defined('DB_PASSWORD') ? '<span class="badge badge-success">✓ Defined</span>' : '<span class="badge" style="background-color: var(--color-danger);">✗ Not Defined</span>'; ?>
                    </td>
                    <td><?php echo defined('DB_PASSWORD') ? '••••••••' : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong>Table Prefix</strong></td>
                    <td><?php echo isset($table_prefix) ? '<span class="badge badge-success">✓ Defined</span>' : '<span class="badge" style="background-color: var(--color-danger);">✗ Not Defined</span>'; ?>
                    </td>
                    <td><?php echo isset($table_prefix) ? htmlspecialchars($table_prefix) : 'N/A'; ?></td>
                </tr>
            </table>
        </div>

        <?php if ($constants_ok): ?>
            <div style="margin-bottom: var(--space-lg);">
                <h4 style="margin-bottom: var(--space-md);">2. Database Connection Test</h4>
                <?php
                try {
                    $test_db = new PDO(
                        'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST,
                        DB_USER,
                        DB_PASSWORD
                    );
                    $test_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    echo '<div class="card" style="background-color: rgba(0, 230, 118, 0.1); border-color: var(--color-success);">';
                    echo '<div style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-md);">';
                    echo '<span class="material-symbols-outlined" style="color: var(--color-success); font-size: 2rem;">check_circle</span>';
                    echo '<div>';
                    echo '<div style="font-weight: 600; color: var(--color-success);">Connection Successful!</div>';
                    echo '<div class="text-muted" style="font-size: var(--font-size-sm);">Successfully connected to database: ' . htmlspecialchars(DB_NAME) . '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';

                    // Test 3: Query WordPress tables
                    echo '<h4 style="margin: var(--space-lg) 0 var(--space-md) 0;">3. WordPress Tables</h4>';
                    $stmt = $test_db->query("SHOW TABLES LIKE '" . $table_prefix . "%'");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (count($tables) > 0) {
                        echo '<div class="text-muted" style="margin-bottom: var(--space-sm);">Found ' . count($tables) . ' WordPress tables:</div>';
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--space-sm);">';
                        foreach (array_slice($tables, 0, 12) as $table) {
                            echo '<div class="badge" style="background-color: var(--color-bg-surface-hover); padding: var(--space-sm);">' . htmlspecialchars($table) . '</div>';
                        }
                        if (count($tables) > 12) {
                            echo '<div class="text-muted">... and ' . (count($tables) - 12) . ' more</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="badge badge-warning">No WordPress tables found with prefix: ' . htmlspecialchars($table_prefix) . '</div>';
                    }

                } catch (PDOException $e) {
                    echo '<div class="card" style="background-color: rgba(255, 23, 68, 0.1); border-color: var(--color-danger);">';
                    echo '<div style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-md);">';
                    echo '<span class="material-symbols-outlined" style="color: var(--color-danger); font-size: 2rem;">error</span>';
                    echo '<div>';
                    echo '<div style="font-weight: 600; color: var(--color-danger);">Connection Failed</div>';
                    echo '<div class="text-muted" style="font-size: var(--font-size-sm);">' . htmlspecialchars($e->getMessage()) . '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        <?php else: ?>
            <div class="card" style="background-color: rgba(255, 23, 68, 0.1); border-color: var(--color-danger);">
                <div style="padding: var(--space-md);">
                    <div style="font-weight: 600; color: var(--color-danger);">Configuration Error</div>
                    <div class="text-muted" style="font-size: var(--font-size-sm);">Database constants are not properly
                        defined. Check your wp-config.php file.</div>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: var(--space-lg);">
            <h4 style="margin-bottom: var(--space-md);">4. File Paths</h4>
            <table class="table">
                <tr>
                    <td><strong>WordPress Directory</strong></td>
                    <td><?php echo isset($this->settings['wp_dir']) ? htmlspecialchars($this->settings['wp_dir']) : 'N/A'; ?>
                    </td>
                    <td><?php echo isset($this->settings['wp_dir']) && file_exists($this->settings['wp_dir']) ? '<span class="badge badge-success">✓ Exists</span>' : '<span class="badge" style="background-color: var(--color-danger);">✗ Not Found</span>'; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>wp-config.php</strong></td>
                    <td><?php echo isset($this->settings['wp_dir']) ? htmlspecialchars($this->settings['wp_dir'] . 'wp-config.php') : 'N/A'; ?>
                    </td>
                    <td><?php echo isset($this->settings['wp_dir']) && file_exists($this->settings['wp_dir'] . 'wp-config.php') ? '<span class="badge badge-success">✓ Exists</span>' : '<span class="badge" style="background-color: var(--color-danger);">✗ Not Found</span>'; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Safe Mode Directory</strong></td>
                    <td><?php echo isset($this->settings['safemode_dir']) ? htmlspecialchars($this->settings['safemode_dir']) : 'N/A'; ?>
                    </td>
                    <td><span class="badge badge-success">✓ Current</span></td>
                </tr>
            </table>
        </div>
    </div>
</div>