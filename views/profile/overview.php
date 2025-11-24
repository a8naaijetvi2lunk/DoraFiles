<div class="card">
    <h3 class="card-title">Informations du compte</h3>

    <div class="profile-info-grid">
        <div class="profile-info-item">
            <div class="profile-info-label">Email</div>
            <div class="profile-info-value"><?= e($profile['email']) ?></div>
        </div>

        <div class="profile-info-item">
            <div class="profile-info-label">Membre depuis</div>
            <div class="profile-info-value"><?= date('d/m/Y', strtotime($profile['created_at'])) ?></div>
        </div>

        <?php if ($profile['last_login_at']): ?>
        <div class="profile-info-item">
            <div class="profile-info-label">Dernière connexion</div>
            <div class="profile-info-value"><?= date('d/m/Y H:i', strtotime($profile['last_login_at'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($profile['last_login_ip']): ?>
        <div class="profile-info-item">
            <div class="profile-info-label">IP dernière connexion</div>
            <div class="profile-info-value"><code class="code-badge"><?= e($profile['last_login_ip']) ?></code></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="stats">
    <div class="stat-card">
        <div class="stat-value"><?= $profile['stats']['total_links'] ?></div>
        <div class="stat-label">Liens actifs</div>
    </div>

    <div class="stat-card">
        <div class="stat-value"><?= $profile['stats']['total_downloads'] ?></div>
        <div class="stat-label">Téléchargements</div>
    </div>

    <div class="stat-card">
        <div class="stat-value"><?= $profile['stats']['total_connections'] ?></div>
        <div class="stat-label">Connexions FTP</div>
    </div>

    <div class="stat-card">
        <div class="stat-value"><?= $profile['stats']['recent_activities'] ?></div>
        <div class="stat-label">Activités (30j)</div>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Actions rapides</h3>

    <div class="quick-actions">
        <a href="/browse.php" class="quick-action-card">
            <div class="quick-action-icon">📁</div>
            <div class="quick-action-content">
                <div class="quick-action-title">Parcourir mes fichiers</div>
                <div class="quick-action-desc">Accéder à vos fichiers FTP</div>
            </div>
        </a>

        <a href="/profile.php?tab=ftp-connections" class="quick-action-card">
            <div class="quick-action-icon">🔌</div>
            <div class="quick-action-content">
                <div class="quick-action-title">Gérer mes connexions</div>
                <div class="quick-action-desc">Ajouter ou modifier des serveurs FTP</div>
            </div>
        </a>

        <a href="/links.php" class="quick-action-card">
            <div class="quick-action-icon">🔗</div>
            <div class="quick-action-content">
                <div class="quick-action-title">Mes liens de partage</div>
                <div class="quick-action-desc">Voir tous vos liens actifs</div>
            </div>
        </a>

        <a href="/profile.php?tab=settings" class="quick-action-card">
            <div class="quick-action-icon">⚙️</div>
            <div class="quick-action-content">
                <div class="quick-action-title">Paramètres</div>
                <div class="quick-action-desc">Modifier email et mot de passe</div>
            </div>
        </a>
    </div>
</div>
