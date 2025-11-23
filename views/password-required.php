<?php $title = 'Mot de passe requis - Dora Files'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<div class="auth-container">
    <div class="auth-header">
        <img src="/public/images/logo.png" alt="Dora Files" class="auth-logo">
        <p class="auth-subtitle">Partage de fichiers sécurisé</p>
    </div>

    <div class="card text-center">
        <div style="font-size: 64px; margin-bottom: 24px;">🔒</div>
        <h2 style="margin-bottom: 16px; color: var(--text-primary); font-size: 24px; font-weight: 600;">Contenu protégé</h2>
        <p style="color: var(--text-secondary); margin-bottom: 32px; font-size: 15px;">
            Ce fichier est protégé par un mot de passe
        </p>

        <?php if (isset($error)): ?>
            <div class="alert alert-error" style="text-align: left;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/download.php">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-group" style="text-align: left;">
                <label>Mot de passe</label>
                <input type="password" name="password" placeholder="Entrez le mot de passe" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                Déverrouiller
            </button>
        </form>

        <div class="card-footer">
            <p style="color: var(--text-muted); font-size: 13px;">
                Fichier: <strong style="color: var(--text-secondary);"><?= e($fileName) ?></strong>
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
