<?php $title = 'Parcourir les fichiers - Dora Files'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <div>
        <h2 class="page-title">Explorateur de fichiers</h2>
        <p class="page-subtitle">Parcourez et partagez vos fichiers FTP</p>
    </div>

    <?php if (count($allConnections) > 1): ?>
    <div class="ftp-connection-selector">
        <label class="ftp-selector-label">🔌 Connexion FTP</label>
        <div class="ftp-selector-dropdown">
            <button onclick="toggleFTPDropdown()" class="ftp-selector-button">
                <span class="ftp-selector-current">
                    <span class="ftp-selector-name"><?= e($currentConnection['connection_name']) ?></span>
                    <code class="code-badge"><?= e($currentConnection['ftp_host_decrypted']) ?></code>
                </span>
                <span class="ftp-selector-icon">▼</span>
            </button>

            <div id="ftpDropdownMenu" class="ftp-dropdown-menu">
                <div class="ftp-dropdown-header">
                    <span class="ftp-dropdown-title">Connexions disponibles</span>
                    <span class="ftp-dropdown-count"><?= count($allConnections) ?></span>
                </div>
                <div class="ftp-dropdown-divider"></div>
                <?php foreach ($allConnections as $index => $conn): ?>
                    <a href="/browse.php?switch_ftp=<?= $conn['id'] ?>&path=<?= urlencode($path) ?>"
                       class="ftp-dropdown-item <?= $conn['id'] == $currentConnection['id'] ? 'active' : '' ?>">
                        <div class="ftp-dropdown-item-icon">
                            <?php if ($conn['id'] == $currentConnection['id']): ?>
                                <span class="ftp-icon-active">●</span>
                            <?php else: ?>
                                <span class="ftp-icon-inactive">○</span>
                            <?php endif; ?>
                        </div>
                        <div class="ftp-dropdown-item-content">
                            <div class="ftp-dropdown-item-name">
                                <?= e($conn['connection_name']) ?>
                                <?php if ($conn['id'] == $currentConnection['id']): ?>
                                    <span class="ftp-active-badge">Actif</span>
                                <?php endif; ?>
                            </div>
                            <div class="ftp-dropdown-item-details">
                                <span class="ftp-host-icon">🌐</span>
                                <code class="code-badge"><?= e($conn['ftp_host_decrypted']) ?></code>
                                <?php if ($conn['last_used_at']): ?>
                                    <span class="ftp-last-used">• Utilisé récemment</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($conn['id'] == $currentConnection['id']): ?>
                            <span class="ftp-check-icon">✓</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php elseif (count($allConnections) == 1): ?>
    <div class="ftp-connection-info">
        <span class="ftp-info-label">🔌 Connexion</span>
        <span class="ftp-info-name"><?= e($currentConnection['connection_name']) ?></span>
        <code class="code-badge"><?= e($currentConnection['ftp_host_decrypted']) ?></code>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="breadcrumb">
            <a href="/browse.php">🏠 Racine</a>
            <?php if (!empty($path) && $path !== '/'): ?>
                <?php
                $parts = explode('/', trim($path, '/'));
                $currentPath = '';
                foreach ($parts as $part):
                    $currentPath .= '/' . $part;
                ?>
                    <span>→</span>
                    <a href="/browse.php?path=<?= urlencode($currentPath) ?>"><?= e($part) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="flex gap-2">
            <button onclick="openModal('createFolderModal')" class="btn btn-secondary btn-sm">
                📁 Nouveau dossier
            </button>
            <button onclick="openModal('uploadModal')" class="btn btn-primary btn-sm">
                📤 Uploader un fichier
            </button>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="file-browser">
        <?php if (empty($files)): ?>
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <p class="empty-text">
                    Ce dossier est vide
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($files as $file): ?>
                <div class="file-item"
                     data-path="<?= e($file['path']) ?>"
                     data-name="<?= e($file['name']) ?>"
                     data-type="<?= $file['is_dir'] ? 'dir' : 'file' ?>"
                     data-size="<?= $file['size'] ?? 0 ?>"
                     <?php if ($file['is_dir']): ?>
                     ondragover="handleDragOver(event)"
                     ondragleave="handleDragLeave(event)"
                     ondrop="handleDrop(event)"
                     <?php endif; ?>>
                    <div class="drag-handle"
                         draggable="true"
                         ondragstart="handleDragStart(event)"
                         ondragend="handleDragEnd(event)"
                         title="Glisser pour déplacer">
                        ⋮⋮
                    </div>
                    <div class="file-icon">
                        <?php if ($file['is_dir']): ?>
                            📁
                        <?php else: ?>
                            📄
                        <?php endif; ?>
                    </div>
                    <div class="file-info">
                        <?php if ($file['is_dir']): ?>
                            <a href="/browse.php?path=<?= urlencode($file['path']) ?>" class="file-name" style="text-decoration: none; color: var(--text-primary); pointer-events: auto;" onclick="event.stopPropagation();">
                                <?= e($file['name']) ?>
                            </a>
                        <?php else: ?>
                            <div class="file-name"><?= e($file['name']) ?></div>
                        <?php endif; ?>
                        <div class="file-meta">
                            <?php if (!$file['is_dir']): ?>
                                <?= formatBytes($file['size']) ?> •
                            <?php endif; ?>
                            <?= e($file['date']) ?>
                        </div>
                    </div>
                    <div class="file-actions">
                        <?php if ($file['is_dir']): ?>
                            <a href="/download-direct.php?path=<?= urlencode($file['path']) ?>&name=<?= urlencode($file['name']) ?>&type=dir&csrf=<?= csrf_token() ?>"
                               class="btn btn-sm btn-secondary"
                               title="Télécharger le dossier (ZIP)">
                                ⬇ ZIP
                            </a>
                            <button onclick="openRenameModal('<?= e($file['name']) ?>', '<?= e($file['path']) ?>')"
                                    class="btn btn-sm btn-secondary"
                                    title="Renommer le dossier">
                                ✏️
                            </button>
                            <button onclick="confirmDelete('<?= e($file['name']) ?>', '<?= e($file['path']) ?>', 'dir')"
                                    class="btn btn-sm"
                                    class="btn btn-sm btn-danger-outline"
                                    title="Supprimer le dossier">
                                🗑️
                            </button>
                        <?php else: ?>
                            <a href="/download-direct.php?path=<?= urlencode($file['path']) ?>&name=<?= urlencode($file['name']) ?>&type=file&csrf=<?= csrf_token() ?>"
                               class="btn btn-sm btn-secondary"
                               title="Télécharger le fichier">
                                ⬇
                            </a>
                            <button onclick="openShareModal('<?= e($file['name']) ?>', '<?= e($file['path']) ?>', <?= $file['size'] ?>)"
                                    class="btn btn-sm btn-primary">
                                Partager
                            </button>
                            <button onclick="openRenameModal('<?= e($file['name']) ?>', '<?= e($file['path']) ?>')"
                                    class="btn btn-sm btn-secondary"
                                    title="Renommer le fichier">
                                ✏️
                            </button>
                            <button onclick="confirmDelete('<?= e($file['name']) ?>', '<?= e($file['path']) ?>', 'file')"
                                    class="btn btn-sm"
                                    class="btn btn-sm btn-danger-outline"
                                    title="Supprimer le fichier">
                                🗑️
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal with Multiple Files Support -->
<div id="uploadModal" class="modal">
    <div class="modal-content modal-content-large">
        <div class="modal-header">
            <h2>Uploader des fichiers</h2>
        </div>

        <div class="py-6">
            <div class="form-group">
                <label>Destination</label>
                <input type="text" value="<?= e($path ?: '/') ?>" readonly class="input-readonly">
            </div>

            <div class="form-group">
                <label>Sélectionner des fichiers</label>
                <input type="file" id="fileInput" multiple class="file-input-wrapper">
                <p class="helper-text">
                    Vous pouvez sélectionner plusieurs fichiers. Chaque fichier sera uploadé en parallèle.
                </p>
            </div>

            <!-- Upload Queue -->
            <div id="uploadQueue" class="upload-queue">
                <h3 class="upload-queue-title">
                    Files d'upload (<span id="queueCount">0</span>)
                </h3>
                <div id="uploadItems" class="upload-items-container"></div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="closeUploadModal()" class="btn btn-secondary">
                Fermer
            </button>
            <button type="button" onclick="startAllUploads()" class="btn btn-primary" id="startUploadButton" style="display: none;">
                Démarrer les uploads
            </button>
        </div>
    </div>
</div>

<!-- Create Folder Modal -->
<div id="createFolderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📁 Créer un nouveau dossier</h2>
        </div>

        <form id="createFolderForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="current_path" value="<?= e($path ?? '/') ?>">

            <div class="form-group">
                <label>Nom du dossier</label>
                <input type="text" name="folder_name" id="folderNameInput" placeholder="nouveau-dossier" required pattern="[a-zA-Z0-9_\-\.]+" title="Lettres, chiffres, tirets et underscores uniquement" autofocus>
                <p class="helper-text">
                    Utilisez uniquement des lettres, chiffres, tirets et underscores.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('createFolderModal')" class="btn btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary" id="createFolderButton">
                    Créer le dossier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div id="renameModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Renommer</h2>
        </div>

        <form id="renameForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="old_path" id="rename_old_path">
            <input type="hidden" name="current_dir" value="<?= e($path ?? '/') ?>">

            <div class="form-group">
                <label>Ancien nom</label>
                <input type="text" id="rename_old_name" readonly class="input-readonly">
            </div>

            <div class="form-group">
                <label>Nouveau nom</label>
                <input type="text" name="new_name" id="rename_new_name" placeholder="nouveau-nom" required pattern="[a-zA-Z0-9_\-\.]+" title="Lettres, chiffres, tirets et underscores uniquement" autofocus>
                <p class="helper-text">
                    Utilisez uniquement des lettres, chiffres, tirets et underscores.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('renameModal')" class="btn btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary" id="renameButton">
                    Renommer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="text-danger">⚠️ Confirmer la suppression</h2>
        </div>

        <div class="py-6">
            <p class="text-secondary mb-4">
                Êtes-vous sûr de vouloir supprimer cet élément ?
            </p>
            <div class="delete-confirmation-box">
                <div class="delete-item-name" id="delete_item_name"></div>
                <div class="delete-item-type" id="delete_item_type"></div>
            </div>
            <p class="text-danger text-sm mt-4">
                ⚠️ Cette action est irréversible
            </p>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="closeModal('deleteModal')" class="btn btn-secondary">
                Annuler
            </button>
            <button type="button" onclick="executeDelete()" class="btn btn-danger" id="deleteButton">
                Supprimer définitivement
            </button>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Créer un lien de partage</h2>
        </div>

        <form method="POST" action="/create-link.php" id="shareLinkForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="file_path" id="modal_file_path">
            <input type="hidden" name="file_name" id="modal_file_name">
            <input type="hidden" name="file_size" id="modal_file_size">
            <input type="hidden" name="current_path" value="<?= e($path ?? '/') ?>">

            <div class="form-group">
                <label>Fichier</label>
                <input type="text" id="modal_file_display" readonly class="input-readonly">
            </div>

            <p class="helper-text mb-4">
                Un lien de partage permanent sera créé pour ce fichier.
            </p>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('shareModal')" class="btn btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    Créer le lien
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Upload queue management
let uploadQueue = [];
let uploadedCount = 0;
let totalUploads = 0;

// File input change handler - Add files to queue
document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);

    if (files.length === 0) return;

    // Clear previous queue
    uploadQueue = [];
    uploadedCount = 0;

    // Add files to queue
    files.forEach(file => {
        const uploadId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        uploadQueue.push({
            id: uploadId,
            file: file,
            status: 'pending', // pending, uploading, completed, error
            progress: 0,
            error: null
        });
    });

    // Show queue and update UI
    totalUploads = uploadQueue.length;
    document.getElementById('queueCount').textContent = totalUploads;
    document.getElementById('uploadQueue').style.display = 'block';
    document.getElementById('startUploadButton').style.display = 'inline-block';

    // Render upload items
    renderUploadQueue();
});

function renderUploadQueue() {
    const container = document.getElementById('uploadItems');
    container.innerHTML = '';

    uploadQueue.forEach(item => {
        const fileSize = item.file.size > 1024*1024
            ? (item.file.size / (1024*1024)).toFixed(2) + ' MB'
            : (item.file.size / 1024).toFixed(2) + ' KB';

        const statusColor = {
            'pending': '#666',
            'uploading': '#ffffff',
            'completed': '#10b981',
            'error': '#ef4444'
        }[item.status];

        const statusIcon = {
            'pending': '⏳',
            'uploading': '⬆️',
            'completed': '✅',
            'error': '❌'
        }[item.status];

        const statusText = {
            'pending': 'En attente',
            'uploading': `Upload en cours... ${Math.round(item.progress)}%`,
            'completed': 'Terminé',
            'error': item.error || 'Erreur'
        }[item.status];

        const card = document.createElement('div');
        card.id = `upload-${item.id}`;
        card.className = 'upload-card';
        card.innerHTML = `
            <div class="upload-card-header">
                <div style="flex: 1;">
                    <div class="upload-filename">${item.file.name}</div>
                    <div class="upload-filesize">${fileSize}</div>
                </div>
                <div style="text-align: right;">
                    <span class="upload-status-icon">${statusIcon}</span>
                </div>
            </div>
            <div style="margin-bottom: 8px;">
                <div class="upload-status-text" style="color: ${statusColor};">
                    ${statusText}
                </div>
                <div class="upload-progress-container">
                    <div class="upload-progress-bar" style="width: ${item.progress}%;"></div>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

function startAllUploads() {
    // Hide start button
    document.getElementById('startUploadButton').style.display = 'none';

    // Start all uploads in parallel
    uploadQueue.forEach(item => {
        if (item.status === 'pending') {
            uploadFile(item);
        }
    });
}

function uploadFile(uploadItem) {
    uploadItem.status = 'uploading';
    renderUploadQueue();

    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    formData.append('target_path', '<?= e($path ?? '/') ?>');
    formData.append('file', uploadItem.file);

    const xhr = new XMLHttpRequest();

    // Progress handler
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            uploadItem.progress = (e.loaded / e.total) * 100;
            renderUploadQueue();
        }
    });

    // Completion handler
    xhr.addEventListener('load', function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    uploadItem.status = 'completed';
                    uploadItem.progress = 100;
                    uploadedCount++;

                    // Check if all uploads are done
                    if (uploadedCount === totalUploads) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    uploadItem.status = 'error';
                    uploadItem.error = response.error || 'Erreur inconnue';
                }
            } catch (e) {
                uploadItem.status = 'error';
                uploadItem.error = 'Erreur de parsing';
            }
        } else {
            uploadItem.status = 'error';
            uploadItem.error = 'Erreur serveur';
        }
        renderUploadQueue();
    });

    // Error handler
    xhr.addEventListener('error', function() {
        uploadItem.status = 'error';
        uploadItem.error = 'Erreur de connexion';
        renderUploadQueue();
    });

    // Send request
    xhr.open('POST', '/upload.php', true);
    xhr.send(formData);
}

function closeUploadModal() {
    // Only close if all uploads are done or none started
    const hasActiveUploads = uploadQueue.some(item => item.status === 'uploading');

    if (hasActiveUploads) {
        if (!confirm('Des uploads sont en cours. Voulez-vous vraiment fermer ?')) {
            return;
        }
    }

    closeModal('uploadModal');

    // Reset queue
    uploadQueue = [];
    uploadedCount = 0;
    totalUploads = 0;
    document.getElementById('uploadQueue').style.display = 'none';
    document.getElementById('fileInput').value = '';
}

function openShareModal(fileName, filePath, fileSize) {
    document.getElementById('modal_file_display').value = fileName;
    document.getElementById('modal_file_path').value = filePath;
    document.getElementById('modal_file_name').value = fileName;
    document.getElementById('modal_file_size').value = fileSize;
    openModal('shareModal');
}

// Delete functionality
let deleteItemData = null;

function confirmDelete(name, path, type) {
    deleteItemData = { name, path, type };
    document.getElementById('delete_item_name').textContent = name;
    document.getElementById('delete_item_type').textContent = type === 'dir' ? 'Dossier' : 'Fichier';
    openModal('deleteModal');
}

async function executeDelete() {
    if (!deleteItemData) return;

    const deleteButton = document.getElementById('deleteButton');
    deleteButton.disabled = true;
    deleteButton.textContent = 'Suppression en cours...';

    try {
        const formData = new FormData();
        formData.append('csrf_token', '<?= csrf_token() ?>');
        formData.append('path', deleteItemData.path);
        formData.append('type', deleteItemData.type);
        formData.append('name', deleteItemData.name);

        const response = await fetch('/delete.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            closeModal('deleteModal');
            // Show success message and reload
            window.location.reload();
        } else {
            alert('Erreur: ' + (result.error || 'Suppression échouée'));
            deleteButton.disabled = false;
            deleteButton.textContent = 'Supprimer définitivement';
        }
    } catch (error) {
        alert('Erreur: ' + error.message);
        deleteButton.disabled = false;
        deleteButton.textContent = 'Supprimer définitivement';
    }
}

// Rename functionality
function openRenameModal(oldName, oldPath) {
    document.getElementById('rename_old_name').value = oldName;
    document.getElementById('rename_old_path').value = oldPath;
    document.getElementById('rename_new_name').value = oldName;
    openModal('renameModal');

    // Select just the filename without extension
    setTimeout(() => {
        const input = document.getElementById('rename_new_name');
        const lastDot = oldName.lastIndexOf('.');
        if (lastDot > 0) {
            input.setSelectionRange(0, lastDot);
        } else {
            input.select();
        }
        input.focus();
    }, 100);
}

document.getElementById('renameForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const renameButton = document.getElementById('renameButton');

    renameButton.disabled = true;
    renameButton.textContent = 'Renommage en cours...';

    try {
        const response = await fetch('/rename.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            closeModal('renameModal');
            window.location.reload();
        } else {
            alert('Erreur: ' + (result.error || 'Renommage échoué'));
            renameButton.disabled = false;
            renameButton.textContent = 'Renommer';
        }
    } catch (error) {
        alert('Erreur: ' + error.message);
        renameButton.disabled = false;
        renameButton.textContent = 'Renommer';
    }
});

// Create folder functionality
document.getElementById('createFolderForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const createButton = document.getElementById('createFolderButton');

    createButton.disabled = true;
    createButton.textContent = 'Création en cours...';

    try {
        const response = await fetch('/create-folder.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            closeModal('createFolderModal');
            window.location.reload();
        } else {
            alert('Erreur: ' + (result.error || 'Création échouée'));
            createButton.disabled = false;
            createButton.textContent = 'Créer le dossier';
        }
    } catch (error) {
        alert('Erreur: ' + error.message);
        createButton.disabled = false;
        createButton.textContent = 'Créer le dossier';
    }
});

// Drag and drop functionality
let draggedItem = null;

function handleDragStart(e) {
    // Get data from parent file-item
    const fileItem = e.currentTarget.closest('.file-item');
    draggedItem = {
        path: fileItem.dataset.path,
        name: fileItem.dataset.name,
        type: fileItem.dataset.type,
        element: fileItem
    };

    // Change cursor to grabbing
    e.currentTarget.style.cursor = 'grabbing';
    fileItem.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd(e) {
    e.currentTarget.style.cursor = 'grab';
    if (draggedItem && draggedItem.element) {
        draggedItem.element.style.opacity = '1';
    }
    // Remove all drag-over styles
    document.querySelectorAll('.file-item').forEach(item => {
        item.style.background = '';
        item.style.borderColor = '';
    });
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }

    const targetType = e.currentTarget.dataset.type;
    if (targetType === 'dir' && draggedItem) {
        e.dataTransfer.dropEffect = 'move';
        e.currentTarget.style.background = '#1a1a1a';
        e.currentTarget.style.borderColor = '#ffffff';
        return false;
    }
}

function handleDragLeave(e) {
    e.currentTarget.style.background = '';
    e.currentTarget.style.borderColor = '';
}

async function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    e.preventDefault();

    e.currentTarget.style.background = '';
    e.currentTarget.style.borderColor = '';

    const targetPath = e.currentTarget.dataset.path;
    const targetName = e.currentTarget.dataset.name;

    // Don't move item into itself
    if (draggedItem && draggedItem.path !== targetPath) {
        if (confirm(`Déplacer "${draggedItem.name}" dans le dossier "${targetName}" ?`)) {
            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= csrf_token() ?>');
                formData.append('source_path', draggedItem.path);
                formData.append('destination_dir', targetPath);
                formData.append('item_name', draggedItem.name);

                const response = await fetch('/move.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Déplacement échoué'));
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
        }
    }

    draggedItem = null;
    return false;
}

// FTP Connection Dropdown
function toggleFTPDropdown() {
    const dropdown = document.getElementById('ftpDropdownMenu');
    const icon = document.querySelector('.ftp-selector-icon');
    const isActive = dropdown.classList.toggle('active');

    // Rotate icon
    if (icon) {
        icon.style.transform = isActive ? 'rotate(180deg)' : 'rotate(0deg)';
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('ftpDropdownMenu');
    const button = document.querySelector('.ftp-selector-button');
    const icon = document.querySelector('.ftp-selector-icon');

    if (dropdown && !dropdown.contains(e.target) && !button.contains(e.target)) {
        dropdown.classList.remove('active');
        if (icon) {
            icon.style.transform = 'rotate(0deg)';
        }
    }
});
</script>



<?php require __DIR__ . '/../partials/footer.php'; ?>
