<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dora Files' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
    <?php if (isAuthenticated()): ?>
    <div class="header">
        <div class="header-brand">
            <img src="/public/images/logo.png" alt="Dora Files" class="header-logo">
        </div>
        <div class="header-nav">
            <a href="/dashboard.php">Dashboard</a>
            <a href="/browse.php">Fichiers</a>
            <a href="/links.php">Mes liens</a>
            <a href="/profile.php">Profil</a>
            <span><?= e(auth()['email']) ?></span>
            <form method="POST" action="/logout.php" class="logout-form">
                <button type="submit" class="btn btn-sm btn-secondary">Déconnexion</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
