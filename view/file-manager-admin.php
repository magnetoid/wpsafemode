<?php
/**
 * File Manager View - Material Design 3
 * Browse and manage WordPress files
 */
?>

<div class="md3-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 class="md3-card-title">File Manager</h2>
            <p class="md3-card-subtitle">Browse and manage your WordPress files</p>
        </div>
        <div>
            <button class="md3-button md3-button-filled" onclick="refreshFiles()">
                <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">refresh</span>
                Refresh
            </button>
            <button class="md3-button md3-button-outlined" onclick="showUploadDialog()" style="margin-left: 8px;">
                <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">upload</span>
                Upload
            </button>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <nav style="margin-bottom: 16px;">
        <ol style="display: flex; list-style: none; padding: 0; margin: 0; gap: 8px; font-size: 0.875rem;">
            <li><a href="#" onclick="navigateToPath(''); return false;" style="color: var(--md-sys-color-primary); text-decoration: none;">Root</a></li>
            <?php if(!empty($data['current_path'])): ?>
            <li style="color: var(--md-sys-color-on-surface-variant);">/</li>
            <li style="color: var(--md-sys-color-on-surface);"><?php echo htmlspecialchars($data['current_path']); ?></li>
            <?php endif; ?>
        </ol>
    </nav>
    
    <?php if(isset($data['error'])): ?>
    <div class="md3-snackbar error">
        <span class="material-symbols-outlined" style="margin-right: 12px;">error</span>
        <span><?php echo htmlspecialchars($data['error']); ?></span>
    </div>
    <?php endif; ?>
    
    <?php if(isset($data['files']) && !empty($data['files'])): ?>
    <div class="md3-table-container">
        <table class="md3-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th>Permissions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data['files'] as $file): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-outlined" style="font-size: 20px;">
                                <?php echo $file['type'] === 'directory' ? 'folder' : 'description'; ?>
                            </span>
                            <?php if($file['type'] === 'directory'): ?>
                            <a href="#" onclick="navigateToPath('<?php echo htmlspecialchars($file['path']); ?>'); return false;" style="text-decoration: none; color: var(--md-sys-color-primary);">
                                <?php echo htmlspecialchars($file['name']); ?>
                            </a>
                            <?php else: ?>
                            <a href="#" onclick="viewFile('<?php echo htmlspecialchars($file['path']); ?>'); return false;" style="text-decoration: none; color: var(--md-sys-color-on-surface);">
                                <?php echo htmlspecialchars($file['name']); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="md3-chip"><?php echo ucfirst($file['type']); ?></span>
                    </td>
                    <td><?php echo $file['size_formatted']; ?></td>
                    <td><?php echo $file['modified_formatted']; ?></td>
                    <td><?php echo $file['permissions']; ?></td>
                    <td>
                        <div style="display: flex; gap: 4px;">
                            <?php if($file['type'] === 'file'): ?>
                            <button class="md3-icon-button" onclick="downloadFile('<?php echo htmlspecialchars($file['path']); ?>')" title="Download">
                                <span class="material-symbols-outlined">download</span>
                            </button>
                            <button class="md3-icon-button" onclick="editFile('<?php echo htmlspecialchars($file['path']); ?>')" title="Edit">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <?php endif; ?>
                            <button class="md3-icon-button" onclick="deleteItem('<?php echo htmlspecialchars($file['path']); ?>', '<?php echo $file['type']; ?>')" title="Delete" style="color: var(--md-sys-color-error);">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="md3-snackbar info">
        <span class="material-symbols-outlined" style="margin-right: 12px;">info</span>
        <span>No files found in this directory</span>
    </div>
    <?php endif; ?>
</div>

<script>
function navigateToPath(path) {
    WPSafeMode.Router.navigate('file-manager', {path: path});
}

function viewFile(path) {
    // TODO: Implement file viewer
    WPSafeMode.Utils.showMessage('File viewer coming soon', 'info');
}

function downloadFile(path) {
    window.location.href = '?action=download&file=' + encodeURIComponent(path);
}

function editFile(path) {
    // TODO: Implement file editor
    WPSafeMode.Utils.showMessage('File editor coming soon', 'info');
}

function deleteItem(path, type) {
    if (!confirm('Are you sure you want to delete this ' + type + '?')) {
        return;
    }
    // TODO: Implement delete via API
    WPSafeMode.Utils.showMessage('Delete functionality coming soon', 'info');
}

function refreshFiles() {
    const currentPath = '<?php echo htmlspecialchars($data['current_path'] ?? ''); ?>';
    navigateToPath(currentPath);
}

function showUploadDialog() {
    // TODO: Implement upload dialog
    WPSafeMode.Utils.showMessage('File upload coming soon', 'info');
}
</script>


