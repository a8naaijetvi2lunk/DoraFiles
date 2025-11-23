<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();
session_start();

// Check authentication
if (!isAuthenticated()) {
    redirect('/login.php');
}

$user = auth();
$linkService = new \App\Services\LinkService();

// Get all links
$links = $linkService->getUserLinks($user['id']);

// Check for success message
$success = null;
if (isset($_SESSION['link_created'])) {
    $success = 'Lien créé avec succès: ' . $_SESSION['link_created'];
    unset($_SESSION['link_created']);
}

require __DIR__ . '/views/dashboard/links.php';
