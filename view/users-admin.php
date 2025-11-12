<?php
/**
 * User Management View - Material Design 3
 * Manage WordPress users
 */
?>

<div class="md3-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 class="md3-card-title">User Management</h2>
            <p class="md3-card-subtitle">Manage WordPress users and roles</p>
        </div>
        <button class="md3-button md3-button-filled" onclick="showCreateUserDialog()">
            <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">person_add</span>
            Add User
        </button>
    </div>
    
    <?php if(isset($data['users']) && !empty($data['users'])): ?>
    <div class="md3-table-container">
        <table class="md3-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Display Name</th>
                    <th>Roles</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data['users'] as $user): ?>
                <tr>
                    <td><?php echo $user['ID']; ?></td>
                    <td><strong><?php echo htmlspecialchars($user['user_login']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['user_email']); ?></td>
                    <td><?php echo htmlspecialchars($user['display_name'] ?? $user['user_login']); ?></td>
                    <td>
                        <?php if(isset($user['roles']) && is_array($user['roles'])): ?>
                        <?php foreach($user['roles'] as $role): ?>
                        <span class="md3-chip"><?php echo htmlspecialchars($role); ?></span>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <span class="md3-chip">subscriber</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($user['user_registered'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 4px;">
                            <button class="md3-icon-button" onclick="editUser(<?php echo $user['ID']; ?>)" title="Edit">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <button class="md3-icon-button" onclick="deleteUser(<?php echo $user['ID']; ?>)" title="Delete" style="color: var(--md-sys-color-error);">
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
        <span>No users found</span>
    </div>
    <?php endif; ?>
</div>

<script>
function showCreateUserDialog() {
    WPSafeMode.Utils.showMessage('User creation dialog coming soon', 'info');
}

function editUser(userId) {
    WPSafeMode.Utils.showMessage('User editor coming soon', 'info');
}

function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) {
        return;
    }
    WPSafeMode.Utils.showMessage('User deletion coming soon', 'info');
}
</script>


