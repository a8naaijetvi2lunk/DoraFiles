<?php $title = 'Inscription - Dora Files'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="auth-container auth-container-wide">
    <div class="auth-header">
        <img src="/public/images/logo.png" alt="Dora Files" class="auth-logo">
        <p class="auth-subtitle">Partagez vos fichiers FTP simplement</p>
    </div>

    <div class="card">
        <h2 class="auth-title">Créer un compte</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/register.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label>Adresse e-mail</label>
                <input type="email" name="email" placeholder="nom@exemple.com" required autofocus>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" placeholder="••••••••" required minlength="8">
                <p class="helper-text">Minimum 8 caractères</p>
            </div>

            <hr class="auth-separator">

            <h3 class="auth-section-title">Configuration FTP</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Hôte FTP</label>
                    <input type="text" name="ftp_host" required placeholder="ftp.example.com">
                </div>

                <div class="form-group form-group-small">
                    <label>Port</label>
                    <input type="number" name="ftp_port" required value="21">
                </div>
            </div>

            <div class="form-group">
                <label>Nom d'utilisateur FTP</label>
                <input type="text" name="ftp_username" required placeholder="utilisateur">
            </div>

            <div class="form-group">
                <label>Mot de passe FTP</label>
                <input type="password" name="ftp_password" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label>Chemin de base (optionnel)</label>
                <input type="text" name="ftp_base_path" placeholder="/" value="/">
                <p class="helper-text">Chemin racine sur le serveur FTP</p>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Créer le compte</button>
        </form>

        <div class="auth-footer">
            <p>
                Déjà un compte ? <a href="/login.php">Se connecter</a>
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
