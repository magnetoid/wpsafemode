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
                <span class="material-symbols-outlined"
                    style="margin-right: 8px; vertical-align: middle;">refresh</span>
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
            <li><a href="#" onclick="navigateToPath(''); return false;"
                    style="color: var(--md-sys-color-primary); text-decoration: none;">Root</a></li>
            <?php if (!empty($data['current_path'])): ?>
                <li style="color: var(--md-sys-color-on-surface-variant);">/</li>
                <li style="color: var(--md-sys-color-on-surface);"><?php echo htmlspecialchars($data['current_path']); ?>
                </li>
            <?php endif; ?>
        </ol>
    </nav>

    <?php if (isset($data['error'])): ?>
        <div class="md3-snackbar error">
            <span class="material-symbols-outlined" style="margin-right: 12px;">error</span>
            <span><?php echo htmlspecialchars($data['error']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($data['files']) && !empty($data['files'])): ?>
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
                    <?php foreach ($data['files'] as $file): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="material-symbols-outlined" style="font-size: 20px;">
                                        <?php echo $file['type'] === 'directory' ? 'folder' : 'description'; ?>
                                    </span>
                                    <?php if ($file['type'] === 'directory'): ?>
                                        <a href="#"
                                            onclick="navigateToPath('<?php echo htmlspecialchars($file['path']); ?>'); return false;"
                                            style="text-decoration: none; color: var(--md-sys-color-primary);">
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="#"
                                            onclick="viewFile('<?php echo htmlspecialchars($file['path']); ?>'); return false;"
                                            style="text-decoration: none; color: var(--md-sys-color-on-surface);">
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
                                    <?php if ($file['type'] === 'file'): ?>
                                        <button class="md3-icon-button"
                                            onclick="downloadFile('<?php echo htmlspecialchars($file['path']); ?>')"
                                            title="Download">
                                            <span class="material-symbols-outlined">download</span>
                                        </button>
                                        <button class="md3-icon-button"
                                            onclick="editFile('<?php echo htmlspecialchars($file['path']); ?>')" title="Edit">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                    <?php endif; ?>
                                    <button class="md3-icon-button"
                                        onclick="deleteItem('<?php echo htmlspecialchars($file['path']); ?>', '<?php echo $file['type']; ?>')"
                                        title="Delete" style="color: var(--md-sys-color-error);">
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
        WPSafeMode.Router.navigate('file-manager', { path: path });
    }

    function showModal(title, content, actions = []) {
        // Remove existing modal if any
        const existingModal = document.getElementById('app-modal');
        if (existingModal) existingModal.remove();

        const modal = document.createElement('div');
        modal.id = 'app-modal';
        modal.className = 'md3-modal-overlay';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;';

        let actionsHtml = '';
        actions.forEach(action => {
            actionsHtml += `<button class="md3-button ${action.class || ''}" onclick="${action.onclick}">${action.label}</button>`;
        });

        modal.innerHTML = `
        <div class="md3-card" style="width: 80%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; padding: 0;">
            <div style="padding: 24px 24px 16px; border-bottom: 1px solid var(--md-sys-color-outline-variant);">
                <h3 class="md3-card-title" style="margin: 0;">${title}</h3>
            </div>
            <div style="padding: 16px 24px; overflow-y: auto; flex: 1;">
                ${content}
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid var(--md-sys-color-outline-variant); display: flex; justify-content: flex-end; gap: 8px;">
                <button class="md3-button" onclick="document.getElementById('app-modal').remove()">Close</button>
                ${actionsHtml}
            </div>
        </div>
    `;

        document.body.appendChild(modal);
    }

    function viewFile(path) {
        WPSafeMode.Utils.showLoading(true);
        WPSafeMode.API.get('/api/file-manager', { action: 'read', path: path })
            .then(response => {
                if (response.success) {
                    const content = `<pre style="background: #f5f5f5; padding: 16px; border-radius: 4px; overflow: auto; max-height: 60vh;">${WPSafeMode.Utils.escapeHtml(response.data.content)}</pre>`;
                    showModal('View File: ' + path.split('/').pop(), content);
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to read file', 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }

    function downloadFile(path) {
        window.location.href = '?action=download&file=' + encodeURIComponent(path);
    }

    function editFile(path) {
        WPSafeMode.Utils.showLoading(true);
        WPSafeMode.API.get('/api/file-manager', { action: 'read', path: path })
            .then(response => {
                if (response.success) {
                    const content = `<textarea id="file-editor-content" style="width: 100%; height: 60vh; font-family: monospace; padding: 8px; border: 1px solid var(--md-sys-color-outline); border-radius: 4px;">${WPSafeMode.Utils.escapeHtml(response.data.content)}</textarea>`;
                    showModal('Edit File: ' + path.split('/').pop(), content, [
                        {
                            label: 'Save',
                            class: 'md3-button-filled',
                            onclick: `saveFile('${path.replace(/'/g, "\\'")}')`
                        }
                    ]);
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to read file', 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }

    function saveFile(path) {
        const content = document.getElementById('file-editor-content').value;
        WPSafeMode.Utils.showLoading(true);

        const formData = new FormData();
        formData.append('path', path);
        formData.append('content', content);

        WPSafeMode.API.post('/api/file-manager?action=write', formData)
            .then(response => {
                if (response.success) {
                    WPSafeMode.Utils.showMessage('File saved successfully', 'success');
                    document.getElementById('app-modal').remove();
                    refreshFiles();
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to save file', 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }

    function deleteItem(path, type) {
        if (!confirm('Are you sure you want to delete this ' + type + '?')) {
            return;
        }

        WPSafeMode.Utils.showLoading(true);
        const formData = new FormData();
        formData.append('path', path);

        WPSafeMode.API.post('/api/file-manager?action=delete', formData)
            .then(response => {
                if (response.success) {
                    WPSafeMode.Utils.showMessage(type + ' deleted successfully', 'success');
                    refreshFiles();
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to delete ' + type, 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }

    function refreshFiles() {
        const currentPath = '<?php echo htmlspecialchars($data['current_path'] ?? ''); ?>';
        navigateToPath(currentPath);
    }

    function showUploadDialog() {
        const content = `
        <div style="padding: 16px; text-align: center; border: 2px dashed var(--md-sys-color-outline); border-radius: 8px; cursor: pointer;" onclick="document.getElementById('file-upload-input').click()">
            <span class="material-symbols-outlined" style="font-size: 48px; color: var(--md-sys-color-primary);">cloud_upload</span>
            <p>Click to select a file to upload</p>
            <input type="file" id="file-upload-input" style="display: none;" onchange="handleFileUpload(this)">
        </div>
        <div id="upload-status" style="margin-top: 16px;"></div>
    `;
        showModal('Upload File', content);
    }

    function handleFileUpload(input) {
        if (input.files.length === 0) return;

        const file = input.files[0];
        const currentPath = '<?php echo htmlspecialchars($data['current_path'] ?? ''); ?>';

        const reader = new FileReader();
        reader.onload = function (e) {
            const content = e.target.result;
            const path = currentPath ? currentPath + '/' + file.name : file.name;

            WPSafeMode.Utils.showLoading(true);
            const formData = new FormData();
            formData.append('path', path);
            formData.append('content', content);

            WPSafeMode.API.post('/api/file-manager?action=write', formData)
                .then(response => {
                    if (response.success) {
                        WPSafeMode.Utils.showMessage('File uploaded successfully', 'success');
                        document.getElementById('app-modal').remove();
                        refreshFiles();
                    } else {
                        WPSafeMode.Utils.showMessage(response.message || 'Failed to upload file', 'error');
                    }
                })
                .catch(error => {
                    WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
                })
                .finally(() => {
                    WPSafeMode.Utils.showLoading(false);
                });
        };
        reader.readAsText(file);
    }
</script>