<?php
/**
 * File Manager View - Material Design 3
 * Browse and manage WordPress files
 */
?>

<!-- CodeMirror -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>

<style>
    .CodeMirror {
        height: 60vh;
        border: 1px solid var(--md-sys-color-outline);
        border-radius: 4px;
        font-family: 'Roboto Mono', monospace;
    }
</style>

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
            <button class="md3-button md3-button-outlined" onclick="fixPermissions()" style="margin-left: 8px;">
                <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">build</span>
                Fix Permissions
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
                                        <?php if (substr($file['name'], -4) === '.zip'): ?>
                                            <button class="md3-icon-button"
                                                onclick="unzipFile('<?php echo htmlspecialchars($file['path']); ?>')" title="Unzip">
                                                <span class="material-symbols-outlined">folder_zip</span>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Directory Actions -->
                                        <button class="md3-icon-button"
                                            onclick="zipDirectory('<?php echo htmlspecialchars($file['path']); ?>')" title="Zip">
                                            <span class="material-symbols-outlined">inventory_2</span>
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
    let editor = null;

    function navigateToPath(path) {
        WPSafeMode.Router.navigate('file-manager', { path: path });
    }

    function showModal(title, content, actions = [], onOpen = null) {
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
        <div class="md3-card" style="width: 80%; max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; padding: 0;">
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
        if (onOpen) onOpen();
    }

    function viewFile(path) {
        WPSafeMode.Utils.showLoading(true);
        WPSafeMode.API.get('/api/file-manager', { action: 'read', path: path })
            .then(response => {
                if (response.success) {
                    let content = '';
                    if (response.data.is_binary) {
                        content = `
                            <div style="text-align: center; padding: 32px;">
                                <span class="material-symbols-outlined" style="font-size: 48px; color: var(--md-sys-color-outline);">description</span>
                                <p>This is a binary file and cannot be viewed as text.</p>
                                <button class="md3-button md3-button-filled" onclick="downloadFile('${path}')">Download File</button>
                            </div>`;
                    } else {
                        content = `<pre style="background: #f5f5f5; padding: 16px; border-radius: 4px; overflow: auto; max-height: 60vh;">${WPSafeMode.Utils.escapeHtml(response.data.content)}</pre>`;
                    }
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
                    if (response.data.is_binary) {
                        WPSafeMode.Utils.showMessage('Cannot edit binary file', 'error');
                        return;
                    }

                    const content = `<textarea id="file-editor-content" style="width: 100%; height: 60vh;">${WPSafeMode.Utils.escapeHtml(response.data.content)}</textarea>`;

                    // Determine mode
                    let mode = 'xml'; // default
                    if (path.endsWith('.php')) mode = 'application/x-httpd-php';
                    else if (path.endsWith('.js')) mode = 'javascript';
                    else if (path.endsWith('.css')) mode = 'css';
                    else if (path.endsWith('.html')) mode = 'htmlmixed';

                    showModal('Edit File: ' + path.split('/').pop(), content, [
                        {
                            label: 'Save',
                            class: 'md3-button-filled',
                            onclick: `saveFile('${path.replace(/'/g, "\\'")}')`
                        }
                    ], () => {
                        // Init CodeMirror
                        const textarea = document.getElementById('file-editor-content');
                        editor = CodeMirror.fromTextArea(textarea, {
                            lineNumbers: true,
                            mode: mode,
                            theme: 'monokai',
                            viewportMargin: Infinity
                        });
                    });
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
        let content = '';
        if (editor) {
            content = editor.getValue();
        } else {
            content = document.getElementById('file-editor-content').value;
        }

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

    function zipDirectory(path) {
        const name = prompt('Enter name for the zip file (without .zip):', path.split('/').pop());
        if (!name) return;

        const destination = path + '/../' + name + '.zip'; // Sibling

        WPSafeMode.Utils.showLoading(true);
        const formData = new FormData();
        formData.append('path', path);
        formData.append('destination', destination);

        WPSafeMode.API.post('/api/file-manager?action=zip', formData)
            .then(response => {
                if (response.success) {
                    WPSafeMode.Utils.showMessage('Zip created successfully', 'success');
                    refreshFiles();
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to create zip', 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }

    function unzipFile(path) {
        if (!confirm('Are you sure you want to unzip this file here?')) return;

        const destination = path.substring(0, path.lastIndexOf('/'));

        WPSafeMode.Utils.showLoading(true);
        const formData = new FormData();
        formData.append('path', path);
        formData.append('destination', destination);

        WPSafeMode.API.post('/api/file-manager?action=unzip', formData)
            .then(response => {
                if (response.success) {
                    WPSafeMode.Utils.showMessage('File unzipped successfully', 'success');
                    refreshFiles();
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to unzip', 'error');
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

        WPSafeMode.Utils.showLoading(true);
        const formData = new FormData();
        formData.append('file', file);
        formData.append('destination', currentPath);

        WPSafeMode.API.post('/api/file-manager?action=upload', formData)
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
    }

    function fixPermissions() {
        const currentPath = '<?php echo htmlspecialchars($data['current_path'] ?? ''); ?>';
        if (!confirm('Are you sure you want to fix permissions for this directory? This will set all directories to 0755 and files to 0644 recursively.')) {
            return;
        }

        WPSafeMode.Utils.showLoading(true);
        const formData = new FormData();
        formData.append('path', currentPath);

        WPSafeMode.API.post('/api/file-manager?action=fix_permissions', formData)
            .then(response => {
                if (response.success) {
                    WPSafeMode.Utils.showMessage('Permissions fixed: ' + response.data.dirs + ' dirs, ' + response.data.files + ' files', 'success');
                    refreshFiles();
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to fix permissions', 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }
</script>