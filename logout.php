<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

$authService = new \App\Services\AuthService();
$authService->logout();

redirect('/login.php');
