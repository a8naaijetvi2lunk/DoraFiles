<div class="card">
    <div class="card-header">
        <h3 class="card-title">Authentification à deux facteurs (2FA)</h3>
    </div>

    <?php if (!$twoFactorEnabled): ?>
        <!-- 2FA Not Enabled -->
        <?php if (!$twoFactorSecret): ?>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">
                Ajoutez une couche de sécurité supplémentaire à votre compte en activant l'authentification à deux facteurs.
                Vous aurez besoin d'une application d'authentification comme Google Authenticator, Authy, ou 1Password.
            </p>

            <a href="/profile.php?tab=settings&setup_2fa=1" class="btn btn-primary">
                Activer le 2FA
            </a>
        <?php else: ?>
            <!-- Setup in progress -->
            <div style="max-width: 600px;">
                <h4 style="color: var(--text-primary); margin-bottom: 16px;">Étape 1 : Scannez le QR Code</h4>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    Ouvrez votre application d'authentification et scannez ce QR code :
                </p>

                <div style="background: white; padding: 20px; border-radius: var(--radius-md); display: inline-block; margin-bottom: 20px;">
                    <img src="<?= $twoFactorQRCode ?>"
                         alt="QR Code"
                         style="display: block;">
                </div>

                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 24px;">
                    Impossible de scanner ? Code manuel : <code class="code-badge"><?= e($twoFactorSecret) ?></code>
                </p>

                <h4 style="color: var(--text-primary); margin-bottom: 16px;">Étape 2 : Entrez le code de vérification</h4>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    Entrez le code à 6 chiffres affiché dans votre application :
                </p>

                <form method="POST" action="/profile.php?tab=settings">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="enable_2fa">

                    <div class="form-group">
                        <input type="text"
                               name="code"
                               placeholder="000000"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               required
                               autocomplete="off"
                               style="max-width: 200px; text-align: center; font-size: 24px; letter-spacing: 8px; font-family: monospace;">
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary">
                            Vérifier et activer
                        </button>
                        <a href="/profile.php?tab=settings" class="btn btn-secondary">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- 2FA Enabled -->
        <div class="alert alert-success" style="margin-bottom: 24px;">
            ✓ L'authentification à deux facteurs est activée sur votre compte
        </div>

        <?php if ($backupCodes): ?>
            <!-- Show backup codes once -->
            <div class="card" style="background: var(--bg-body); border: 2px solid var(--primary); margin-bottom: 24px;">
                <div class="card-header">
                    <h4 style="color: var(--primary); margin: 0;">⚠️ Codes de secours - Sauvegardez-les maintenant !</h4>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 16px;">
                    Ces codes ne seront affichés qu'une seule fois. Conservez-les dans un endroit sûr.
                    Vous pouvez les utiliser pour vous connecter si vous perdez l'accès à votre application d'authentification.
                </p>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
                    <?php foreach ($backupCodes as $code): ?>
                        <code class="code-badge" style="padding: 12px; font-size: 16px; text-align: center;">
                            <?= e($code) ?>
                        </code>
                    <?php endforeach; ?>
                </div>

                <button onclick="downloadBackupCodes()" class="btn btn-secondary" style="margin-right: 12px;">
                    📥 Télécharger les codes
                </button>
                <button onclick="copyBackupCodes()" class="btn btn-secondary">
                    📋 Copier les codes
                </button>

                <script>
                function downloadBackupCodes() {
                    const codes = <?= json_encode($backupCodes) ?>;
                    const text = 'Codes de secours Dora Files\n\n' + codes.join('\n') + '\n\nConservez ces codes dans un endroit sûr.';
                    const blob = new Blob([text], { type: 'text/plain' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'dora-files-backup-codes.txt';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }

                function copyBackupCodes() {
                    const codes = <?= json_encode($backupCodes) ?>;
                    const text = codes.join('\n');
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Codes copiés dans le presse-papiers !');
                    });
                }
                </script>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 24px;">
            <h4 style="color: var(--text-primary); margin-bottom: 12px;">Codes de secours</h4>
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                Codes de secours restants : <strong style="color: var(--text-primary);"><?= $remainingBackupCodes ?></strong> / 8
            </p>

            <?php if ($remainingBackupCodes < 3): ?>
                <div class="alert alert-error" style="margin-bottom: 16px;">
                    ⚠️ Il vous reste peu de codes de secours. Pensez à en régénérer.
                </div>
            <?php endif; ?>

            <button onclick="showRegenerateModal()" class="btn btn-secondary">
                Régénérer les codes de secours
            </button>
        </div>

        <div style="padding-top: 24px; border-top: 1px solid var(--border-color);">
            <h4 style="color: var(--text-primary); margin-bottom: 12px;">Désactiver le 2FA</h4>
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                Si vous souhaitez désactiver l'authentification à deux facteurs, vous devrez confirmer avec votre mot de passe.
            </p>

            <button onclick="showDisableModal()" class="btn btn-danger">
                Désactiver le 2FA
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Disable 2FA Modal -->
<div id="disableModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Désactiver le 2FA</h2>
        </div>

        <p style="color: var(--text-secondary); margin-bottom: 24px;">
            Êtes-vous sûr de vouloir désactiver l'authentification à deux facteurs ?
            Votre compte sera moins sécurisé.
        </p>

        <form method="POST" action="/profile.php?tab=settings">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="disable_2fa">

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required placeholder="Votre mot de passe actuel">
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('disableModal')" class="btn btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn btn-danger">
                    Désactiver le 2FA
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Regenerate Backup Codes Modal -->
<div id="regenerateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Régénérer les codes de secours</h2>
        </div>

        <p style="color: var(--text-secondary); margin-bottom: 24px;">
            ⚠️ Attention : Les anciens codes de secours seront invalidés et remplacés par de nouveaux codes.
        </p>

        <form method="POST" action="/profile.php?tab=settings">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="regenerate_backup_codes">

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required placeholder="Votre mot de passe actuel">
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('regenerateModal')" class="btn btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    Régénérer les codes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showDisableModal() {
    document.getElementById('disableModal').classList.add('active');
}

function showRegenerateModal() {
    document.getElementById('regenerateModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>
