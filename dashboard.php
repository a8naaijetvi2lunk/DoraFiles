<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    redirect('/login.php');
}

$linkService = new \App\Services\LinkService();
$user = auth();

// Get statistics
$allLinks = $linkService->getUserLinks($user['id']);

$totalLinks = 0;
$totalDownloads = 0;
$linksExpiringSoon = 0;

foreach ($allLinks as $link) {
    $isExpired = $link['expires_at'] && strtotime($link['expires_at']) < time();
    $isRevoked = $link['revoked_at'] !== null;

    if (!$isExpired && !$isRevoked) {
        $totalLinks++;
        $totalDownloads += $link['download_count'];

        // Check if expires in next 24h
        if ($link['expires_at'] && strtotime($link['expires_at']) < strtotime('+24 hours')) {
            $linksExpiringSoon++;
        }
    }
}

// Get recent links (last 5)
$recentLinks = array_slice($allLinks, 0, 5);

require __DIR__ . '/views/dashboard/index.php';
