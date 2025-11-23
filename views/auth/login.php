<?php $title = 'Connexion - Dora Files'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="auth-container">
    <div class="auth-header">
        <img src="/public/images/logo.png" alt="Dora Files" class="auth-logo">
        <p class="auth-subtitle">Partagez vos fichiers FTP simplement</p>
    </div>

    <div class="card">
        <h2 class="auth-title">Connexion</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label>Adresse e-mail</label>
                <input type="email" name="email" placeholder="nom@exemple.com" required autofocus>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Se connecter</button>
        </form>

        <div class="auth-footer">
            <p>
                Pas encore de compte? <a href="/register.php">S'inscrire</a>
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
