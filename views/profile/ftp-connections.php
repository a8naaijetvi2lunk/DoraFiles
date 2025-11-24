<div class="card">
    <div class="card-header">
        <h3 class="card-title">Mes connexions FTP</h3>
        <button onclick="openAddConnectionModal()" class="btn btn-primary">
            + Ajouter une connexion
        </button>
    </div>

    <?php if (empty($ftpConnections)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔌</div>
            <p class="empty-text">Aucune connexion FTP configurée</p>
            <button onclick="openAddConnectionModal()" class="btn btn-primary">
                Ajouter une connexion
            </button>
        </div>
    <?php else: ?>
        <div class="ftp-connections-grid">
            <?php foreach ($ftpConnections as $conn): ?>
                <div class="ftp-connection-card">
                    <?php if ($conn['is_default']): ?>
                        <span class="ftp-badge ftp-badge-active">Connexion active</span>
                    <?php endif; ?>

                    <h4 class="ftp-connection-name"><?= e($conn['connection_name']) ?></h4>

                    <div class="ftp-connection-details">
                        <div class="ftp-detail-row">
                            <span class="ftp-detail-label">Hôte</span>
                            <code class="code-badge"><?= e($conn['ftp_host_decrypted']) ?>:<?= e($conn['ftp_port_decrypted']) ?></code>
                        </div>
                        <div class="ftp-detail-row">
                            <span class="ftp-detail-label">Utilisateur</span>
                            <span class="ftp-detail-value"><?= e($conn['ftp_username_decrypted']) ?></span>
                        </div>
                        <div class="ftp-detail-row">
                            <span class="ftp-detail-label">Chemin</span>
                            <code class="code-badge"><?= e($conn['ftp_base_path_decrypted']) ?></code>
                        </div>
                        <?php if ($conn['last_used_at']): ?>
                        <div class="ftp-detail-row">
                            <span class="ftp-detail-label">Dernier usage</span>
                            <span class="ftp-detail-value"><?= date('d/m/Y H:i', strtotime($conn['last_used_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="ftp-connection-actions">
                        <?php if (!$conn['is_default']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="switch_ftp_connection">
                                <input type="hidden" name="connection_id" value="<?= $conn['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Activer</button>
                            </form>
                        <?php endif; ?>

                        <button onclick='openEditConnectionModal(<?= htmlspecialchars(json_encode($conn), ENT_QUOTES, "UTF-8") ?>)' class="btn btn-sm btn-secondary">
                            Modifier
                        </button>

                        <?php if (count($ftpConnections) > 1): ?>
                            <button onclick="confirmDeleteConnection(<?= $conn['id'] ?>, '<?= addslashes(e($conn['connection_name'])) ?>')" class="btn btn-sm btn-danger">
                                Supprimer
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Connection Modal -->
<div id="addConnectionModal" class="modal">
    <div class="modal-content modal-content-large">
        <div class="modal-header">
            <h2>Ajouter une connexion FTP</h2>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="create_ftp_connection">

            <div class="form-group">
                <label for="connection_name">Nom de la connexion *</label>
                <input type="text" id="connection_name" name="connection_name" required maxlength="100" placeholder="Ex: Serveur Principal">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="ftp_host">Hôte FTP *</label>
                    <input type="text" id="ftp_host" name="ftp_host" required placeholder="ftp.example.com">
                </div>

                <div class="form-group form-group-small">
                    <label for="ftp_port">Port *</label>
                    <input type="number" id="ftp_port" name="ftp_port" value="21" required min="1" max="65535">
                </div>
            </div>

            <div class="form-group">
                <label for="ftp_username">Nom d'utilisateur *</label>
                <input type="text" id="ftp_username" name="ftp_username" required>
            </div>

            <div class="form-group">
                <label for="ftp_password">Mot de passe *</label>
                <input type="password" id="ftp_password" name="ftp_password" required>
            </div>

            <div class="form-group">
                <label for="ftp_base_path">Chemin de base</label>
                <input type="text" id="ftp_base_path" name="ftp_base_path" value="/" placeholder="/">
                <div class="helper-text">Chemin racine sur le serveur FTP</div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_default" class="checkbox-input">
                    <span>Définir comme connexion active</span>
                </label>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('addConnectionModal')" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Tester et créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Connection Modal -->
<div id="editConnectionModal" class="modal">
    <div class="modal-content modal-content-large">
        <div class="modal-header">
            <h2>Modifier la connexion FTP</h2>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_ftp_connection">
            <input type="hidden" id="edit_connection_id" name="connection_id">

            <div class="form-group">
                <label for="edit_connection_name">Nom de la connexion *</label>
                <input type="text" id="edit_connection_name" name="connection_name" required maxlength="100">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_ftp_host">Hôte FTP *</label>
                    <input type="text" id="edit_ftp_host" name="ftp_host" required>
                </div>

                <div class="form-group form-group-small">
                    <label for="edit_ftp_port">Port *</label>
                    <input type="number" id="edit_ftp_port" name="ftp_port" required min="1" max="65535">
                </div>
            </div>

            <div class="form-group">
                <label for="edit_ftp_username">Nom d'utilisateur *</label>
                <input type="text" id="edit_ftp_username" name="ftp_username" required>
            </div>

            <div class="form-group">
                <label for="edit_ftp_password">Nouveau mot de passe</label>
                <input type="password" id="edit_ftp_password" name="ftp_password">
                <div class="helper-text">Laisser vide pour conserver le mot de passe actuel</div>
            </div>

            <div class="form-group">
                <label for="edit_ftp_base_path">Chemin de base</label>
                <input type="text" id="edit_ftp_base_path" name="ftp_base_path">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="edit_is_default" name="is_default" class="checkbox-input">
                    <span>Définir comme connexion active</span>
                </label>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('editConnectionModal')" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form id="deleteConnectionForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete_ftp_connection">
    <input type="hidden" id="delete_connection_id" name="connection_id">
</form>

<script>
function openAddConnectionModal() {
    const modal = document.getElementById('addConnectionModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openEditConnectionModal(connection) {
    document.getElementById('edit_connection_id').value = connection.id;
    document.getElementById('edit_connection_name').value = connection.connection_name;
    document.getElementById('edit_ftp_host').value = connection.ftp_host_decrypted;
    document.getElementById('edit_ftp_port').value = connection.ftp_port_decrypted;
    document.getElementById('edit_ftp_username').value = connection.ftp_username_decrypted;
    document.getElementById('edit_ftp_base_path').value = connection.ftp_base_path_decrypted;
    document.getElementById('edit_is_default').checked = connection.is_default == 1;

    const modal = document.getElementById('editConnectionModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

function confirmDeleteConnection(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la connexion "${name}" ?\n\nCette action est irréversible.`)) {
        document.getElementById('delete_connection_id').value = id;
        document.getElementById('deleteConnectionForm').submit();
    }
}

// Close modal on click outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('addConnectionModal');
        closeModal('editConnectionModal');
    }
});
</script>
