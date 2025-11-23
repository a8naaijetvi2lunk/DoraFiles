<?php $title = 'Mes liens - Dora Files'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h2 class="page-title">Mes liens de partage</h2>
    <p class="page-subtitle">Gérez tous vos liens de partage</p>
</div>

<div class="card">
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (empty($links)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔗</div>
            <p class="empty-text">
                Vous n'avez pas encore créé de lien
            </p>
            <a href="/browse.php" class="btn btn-primary">Parcourir mes fichiers</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th>Lien</th>
                    <th>Taille</th>
                    <th>Créé le</th>
                    <th>Téléchargements</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $link):
                    $isRevoked = $link['revoked_at'] !== null;
                ?>
                    <tr class="<?= $isRevoked ? 'opacity-40' : '' ?>">
                        <td>
                            <div class="font-medium"><?= e($link['file_name']) ?></div>
                            <div class="text-muted text-xs mt-2"><?= e($link['file_path']) ?></div>
                        </td>
                        <td>
                            <code class="code-badge">
                                <?= e(substr($link['token'], 0, 20)) ?>...
                            </code>
                        </td>
                        <td><?= formatBytes($link['file_size']) ?></td>
                        <td class="text-sm"><?= date('d/m/Y H:i', strtotime($link['created_at'])) ?></td>
                        <td>
                            <div class="font-medium"><?= $link['download_count'] ?></div>
                            <?php if ($link['last_downloaded_at']): ?>
                                <div class="text-muted text-xs mt-2">
                                    <?= date('d/m H:i', strtotime($link['last_downloaded_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex gap-2 items-center">
                                <?php if (!$isRevoked): ?>
                                    <button onclick="copyToClipboard('<?= env('APP_URL') ?>/dl/<?= e($link['token']) ?>')"
                                            class="btn btn-sm btn-secondary">
                                        Copier
                                    </button>
                                    <form method="POST" action="/delete-link.php" class="inline-block">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Voulez-vous vraiment supprimer ce lien?')">
                                            Supprimer
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-danger text-sm">Révoqué</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
