<?php $title = 'Mon Profil - Dora Files'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h2 class="page-title">Mon Profil</h2>
    <p class="page-subtitle">Gérez votre compte et vos connexions FTP</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        ⚠️ <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        ✓ <?= e($success) ?>
    </div>
<?php endif; ?>

<div class="nav-tabs">
    <a href="/profile.php?tab=overview" class="nav-tab <?= $tab === 'overview' ? 'active' : '' ?>">
        Vue d'ensemble
    </a>
    <a href="/profile.php?tab=ftp-connections" class="nav-tab <?= $tab === 'ftp-connections' ? 'active' : '' ?>">
        Connexions FTP
    </a>
    <a href="/profile.php?tab=activity" class="nav-tab <?= $tab === 'activity' ? 'active' : '' ?>">
        Historique
    </a>
    <a href="/profile.php?tab=settings" class="nav-tab <?= $tab === 'settings' ? 'active' : '' ?>">
        Paramètres
    </a>
</div>

<div class="profile-content-wrapper">
    <?php
    switch ($tab) {
        case 'overview':
            require __DIR__ . '/overview.php';
            break;
        case 'ftp-connections':
            require __DIR__ . '/ftp-connections.php';
            break;
        case 'activity':
            require __DIR__ . '/activity.php';
            break;
        case 'settings':
            require __DIR__ . '/settings.php';
            break;
    }
    ?>
</div>



<?php require __DIR__ . '/../partials/footer.php'; ?>
