<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification 2FA - Dora Files</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1 class="auth-title">Vérification 2FA</h1>
            <p class="auth-subtitle">Entrez votre code d'authentification</p>
        </div>

        <div class="card">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ⚠️ <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/verify-2fa.php">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form-group">
                    <label for="code">Code d'authentification</label>
                    <input type="text"
                           id="code"
                           name="code"
                           placeholder="000000"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           required
                           autocomplete="off"
                           autofocus
                           style="text-align: center; font-size: 24px; letter-spacing: 8px; font-family: monospace;">
                    <div class="helper-text">Entrez le code à 6 chiffres de votre application d'authentification</div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Vérifier
                </button>
            </form>

            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color); text-align: center;">
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 12px;">
                    Vous n'avez pas accès à votre application d'authentification ?
                </p>
                <button onclick="toggleBackupCodeForm()" class="btn btn-secondary" id="showBackupBtn">
                    Utiliser un code de secours
                </button>
            </div>

            <div id="backupCodeForm" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                <form method="POST" action="/verify-2fa.php">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="use_backup_code" value="1">

                    <div class="form-group">
                        <label for="backup_code">Code de secours</label>
                        <input type="text"
                               id="backup_code"
                               name="code"
                               placeholder="XXXXXXXX"
                               maxlength="8"
                               required
                               autocomplete="off"
                               style="text-align: center; font-size: 18px; letter-spacing: 4px; font-family: monospace; text-transform: uppercase;">
                        <div class="helper-text">Entrez l'un de vos codes de secours à 8 caractères</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        Vérifier avec code de secours
                    </button>
                </form>
            </div>

            <div class="card-footer">
                <a href="/logout.php" style="color: var(--text-secondary); text-decoration: none;">
                    ← Retour à la connexion
                </a>
            </div>
        </div>
    </div>

    <script>
    function toggleBackupCodeForm() {
        const form = document.getElementById('backupCodeForm');
        const btn = document.getElementById('showBackupBtn');

        if (form.style.display === 'none') {
            form.style.display = 'block';
            btn.textContent = 'Utiliser le code d\'authentification';
        } else {
            form.style.display = 'none';
            btn.textContent = 'Utiliser un code de secours';
        }
    }

    // Auto-submit when 6 digits entered
    document.getElementById('code').addEventListener('input', function(e) {
        if (this.value.length === 6 && /^[0-9]{6}$/.test(this.value)) {
            this.form.submit();
        }
    });

    // Convert backup code to uppercase
    const backupCodeInput = document.getElementById('backup_code');
    if (backupCodeInput) {
        backupCodeInput.addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    }
    </script>
</body>
</html>
