<?php $title = 'Inscription - Dora Files'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="auth-container" style="max-width: 500px; padding-top: 50px;">
    <div class="card">
        <h2 class="auth-title text-center">Créer un compte</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/register.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autofocus>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required minlength="8">
            </div>

            <hr class="auth-separator">

            <h3 class="auth-section-title">Configuration FTP</h3>

            <div class="form-group">
                <label>Hôte FTP</label>
                <input type="text" name="ftp_host" required placeholder="ftp.example.com">
            </div>

            <div class="form-group">
                <label>Port FTP</label>
                <input type="number" name="ftp_port" required value="21">
            </div>

            <div class="form-group">
                <label>Nom d'utilisateur FTP</label>
                <input type="text" name="ftp_username" required>
            </div>

            <div class="form-group">
                <label>Mot de passe FTP</label>
                <input type="password" name="ftp_password" required>
            </div>

            <div class="form-group">
                <label>Chemin de base (optionnel)</label>
                <input type="text" name="ftp_base_path" placeholder="/" value="/">
            </div>

            <button type="submit" class="btn btn-primary btn-full">Créer le compte</button>
        </form>

        <div class="auth-footer">
            <p>
                Déjà un compte? <a href="/login.php">Se connecter</a>
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
