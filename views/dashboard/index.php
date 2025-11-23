<?php $title = 'Dashboard - Dora Files'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h2 class="page-title">Tableau de bord</h2>
    <p class="page-subtitle">Vue d'ensemble de vos partages</p>
</div>

<div class="stats">
    <div class="stat-card">
        <div class="stat-value"><?= $totalLinks ?></div>
        <div class="stat-label">Liens actifs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalDownloads ?></div>
        <div class="stat-label">Téléchargements totaux</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $linksExpiringSoon ?></div>
        <div class="stat-label">Liens expirant bientôt</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liens récents</h3>
        <a href="/browse.php" class="btn btn-sm btn-primary">Nouveau lien</a>
    </div>

    <?php if (empty($recentLinks)): ?>
        <div class="empty-state">
            <div class="empty-icon">📎</div>
            <p class="empty-text">
                Aucun lien créé pour le moment
            </p>
            <a href="/browse.php" class="btn btn-primary">Parcourir mes fichiers</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th>Lien</th>
                    <th>Créé le</th>
                    <th>Expire le</th>
                    <th>Téléchargements</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLinks as $link): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 500;"><?= e($link['file_name']) ?></div>
                        </td>
                        <td>
                            <code class="code-badge">
                                <?= e(substr($link['token'], 0, 16)) ?>...
                            </code>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($link['created_at'])) ?></td>
                        <td>
                            <?php if ($link['expires_at']): ?>
                                <?= date('d/m/Y H:i', strtotime($link['expires_at'])) ?>
                            <?php else: ?>
                                <span style="color: #666;">Jamais</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight: 500;"><?= $link['download_count'] ?></span>
                        </td>
                        <td>
                            <button onclick="copyToClipboard('<?= env('APP_URL') ?>/dl/<?= e($link['token']) ?>')" class="btn btn-sm btn-secondary">
                                Copier
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="card-footer">
            <a href="/links.php" class="btn btn-secondary">Voir tous mes liens →</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
