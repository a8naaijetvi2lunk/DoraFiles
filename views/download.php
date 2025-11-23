<?php $title = 'Téléchargement - Dora Files'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<div class="auth-container" style="max-width: 560px;">
    <div class="auth-header">
        <img src="/public/images/logo.png" alt="Dora Files" class="auth-logo">
        <p class="auth-subtitle">Partage de fichiers sécurisé</p>
    </div>

    <div class="card text-center">
        <?php if (isset($error)): ?>
            <div style="font-size: 64px; margin-bottom: 24px; opacity: 0.5;">❌</div>
            <h2 class="text-danger" style="margin-bottom: 20px; font-size: 24px; font-weight: 600;">Erreur</h2>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php else: ?>
            <div style="font-size: 64px; margin-bottom: 24px;">📥</div>
            <h2 style="margin-bottom: 32px; color: var(--text-primary); font-size: 24px; font-weight: 600;">Téléchargement disponible</h2>

            <div style="background: var(--bg-body); padding: 24px; border-radius: var(--radius-lg); margin-bottom: 32px; border: 1px solid var(--border-color);">
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">
                    <?= e($fileName) ?>
                </div>
                <div style="color: var(--text-secondary); font-size: 14px;">
                    Taille: <?= formatBytes($fileSize) ?>
                </div>
                <?php if ($expiresAt): ?>
                    <div style="color: var(--text-muted); font-size: 13px; margin-top: 8px;">
                        Expire le: <?= date('d/m/Y à H:i', strtotime($expiresAt)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="/download.php">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 14px 36px; width: 100%; max-width: 300px;">
                    Télécharger le fichier
                </button>
            </form>

            <div class="card-footer">
                <p style="color: var(--text-muted); font-size: 13px;">
                    <?= $downloadCount ?> téléchargement<?= $downloadCount > 1 ? 's' : '' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
