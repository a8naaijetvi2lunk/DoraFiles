<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/links.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

$user = auth();
$linkId = (int) ($_POST['link_id'] ?? 0);

$linkService = new \App\Services\LinkService();
$linkService->deleteLink($linkId, $user['id']);

redirect('/links.php');
