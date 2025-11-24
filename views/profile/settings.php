<div class="card">
    <h3 class="card-title">Modifier l'email</h3>

    <form method="POST" class="max-w-md">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_email">

        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" value="<?= e($profile['email']) ?>" required>
            <div class="helper-text">Utilisé pour vous connecter à votre compte</div>
        </div>

        <button type="submit" class="btn btn-primary">Mettre à jour l'email</button>
    </form>
</div>

<div class="card">
    <h3 class="card-title">Modifier le mot de passe</h3>

    <form method="POST" class="max-w-md">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_password">

        <div class="form-group">
            <label for="current_password">Mot de passe actuel *</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>

        <div class="form-group">
            <label for="new_password">Nouveau mot de passe *</label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
            <div class="helper-text">
                Minimum 8 caractères, avec au moins une majuscule, une minuscule et un chiffre
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirmer le nouveau mot de passe *</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
    </form>
</div>

<!-- Include 2FA configuration -->
<?php require __DIR__ . '/security.php'; ?>

<div class="card settings-danger-zone">
    <h3 class="card-title text-danger">Zone dangereuse</h3>

    <div class="danger-zone-content">
        <div>
            <h4 class="danger-zone-title">Supprimer mon compte</h4>
            <p class="danger-zone-text">
                Une fois supprimé, votre compte ne pourra pas être récupéré.
                Toutes vos données, connexions FTP et liens de partage seront définitivement supprimés.
            </p>
        </div>

        <button onclick="openDeleteAccountModal()" class="btn btn-danger">
            Supprimer mon compte
        </button>
    </div>
</div>

<!-- Delete Account Modal -->
<div id="deleteAccountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Supprimer mon compte</h2>
        </div>

        <div class="delete-warning-box">
            <div class="delete-warning-icon">⚠️</div>
            <div class="delete-warning-content">
                <h4>Attention : Cette action est irréversible !</h4>
                <p>En supprimant votre compte, vous perdrez :</p>
                <ul>
                    <li>Toutes vos connexions FTP configurées</li>
                    <li>Tous vos liens de partage actifs</li>
                    <li>Tout votre historique d'activité</li>
                    <li>L'accès à votre compte</li>
                </ul>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete_account">

            <div class="form-group">
                <label for="delete_password">Confirmez avec votre mot de passe *</label>
                <input type="password" id="delete_password" name="password" required autocomplete="current-password">
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous absolument sûr ? Cette action ne peut pas être annulée.')">
                    Oui, supprimer mon compte
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteAccountModal() {
    const modal = document.getElementById('deleteAccountModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteAccountModal');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    document.getElementById('delete_password').value = '';
}

// Close modal on click outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'deleteAccountModal' && e.target.classList.contains('modal')) {
        closeDeleteModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// Password confirmation validation
const confirmPasswordInput = document.getElementById('confirm_password');
if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;

        if (confirmPassword && newPassword !== confirmPassword) {
            this.setCustomValidity('Les mots de passe ne correspondent pas');
        } else {
            this.setCustomValidity('');
        }
    });
}
</script>
