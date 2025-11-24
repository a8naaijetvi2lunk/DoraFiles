<?php $title = 'Téléchargement - Dora Files'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<div class="auth-container auth-container-wide">
    <div class="auth-header">
        <img src="/public/images/logo.png" alt="Dora Files" class="auth-logo">
        <p class="auth-subtitle">Partage de fichiers sécurisé</p>
    </div>

    <div class="card text-center">
        <?php if (isset($error)): ?>
            <div class="download-icon opacity-50">❌</div>
            <h2 class="download-title text-danger">Erreur</h2>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php else: ?>
            <div class="download-icon">📥</div>
            <h2 class="download-title">Téléchargement disponible</h2>

            <div class="download-file-info">
                <div class="download-file-name"><?= e($fileName) ?></div>
                <div class="download-file-size">Taille: <?= formatBytes($fileSize) ?></div>
                <?php if ($expiresAt): ?>
                    <div class="download-file-expires">
                        Expire le: <?= date('d/m/Y à H:i', strtotime($expiresAt)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="/download.php">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-primary btn-lg">
                    Télécharger le fichier
                </button>
            </form>

            <div class="card-footer">
                <p class="text-muted text-sm">
                    <?= $downloadCount ?> téléchargement<?= $downloadCount > 1 ? 's' : '' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
