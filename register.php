<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security headers
\App\Security\SecurityMiddleware::applySecurityHeaders();

// Configure secure session
\App\Security\SecurityMiddleware::configureSecureSession();

// Redirect if already authenticated
if (isAuthenticated()) {
    redirect('/dashboard.php');
}

$authService = new \App\Services\AuthService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting
    if (!\App\Security\SecurityMiddleware::checkRateLimit('register', 3, 3600)) {
        \App\Security\SecurityMiddleware::logSecurityEvent('REGISTER_RATE_LIMIT_EXCEEDED');
        http_response_code(429);
        $error = 'Trop de tentatives. Veuillez réessayer dans 1 heure.';
        require __DIR__ . '/views/auth/register.php';
        exit;
    }

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        \App\Security\SecurityMiddleware::logSecurityEvent('INVALID_CSRF_TOKEN', ['action' => 'register']);
        http_response_code(403);
        die('Invalid CSRF token');
    }

    // Sanitize and validate inputs
    $email = \App\Security\SecurityMiddleware::sanitizeString($_POST['email'] ?? '', 255);
    $password = $_POST['password'] ?? '';
    $ftpHost = \App\Security\SecurityMiddleware::sanitizeString($_POST['ftp_host'] ?? '', 255);
    $ftpPort = \App\Security\SecurityMiddleware::sanitizeString($_POST['ftp_port'] ?? '21', 10);
    $ftpUsername = \App\Security\SecurityMiddleware::sanitizeString($_POST['ftp_username'] ?? '', 255);
    $ftpPassword = $_POST['ftp_password'] ?? '';
    $ftpBasePath = \App\Security\SecurityMiddleware::validatePath($_POST['ftp_base_path'] ?? '/');

    // Validate email
    $validatedEmail = \App\Security\SecurityMiddleware::validateEmail($email);
    if (!$validatedEmail) {
        $error = 'Adresse email invalide';
        require __DIR__ . '/views/auth/register.php';
        exit;
    }
    $email = $validatedEmail;

    // Validate password
    if (!\App\Security\SecurityMiddleware::validatePassword($password)) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères';
        require __DIR__ . '/views/auth/register.php';
        exit;
    }

    // Validate FTP host
    if (!\App\Security\SecurityMiddleware::validateFTPHost($ftpHost)) {
        $error = 'Hôte FTP invalide';
        require __DIR__ . '/views/auth/register.php';
        exit;
    }

    // Validate FTP port
    if (!\App\Security\SecurityMiddleware::validateFTPPort($ftpPort)) {
        $error = 'Port FTP invalide (1-65535)';
        require __DIR__ . '/views/auth/register.php';
        exit;
    }

    // Validate FTP username
    if (empty($ftpUsername) || strlen($ftpUsername) > 255) {
        $error = 'Nom d\'utilisateur FTP invalide';
        require __DIR__ . '/views/auth/register.php';
        exit;
    }

    // Validate FTP password
    if (empty($ftpPassword) || strlen($ftpPassword) > 255) {
        $error = 'Mot de passe FTP invalide';
        require __DIR__ . '/views/auth/register.php';
        exit;
    }

    if ($authService->register($email, $password, $ftpHost, $ftpPort, $ftpUsername, $ftpPassword, $ftpBasePath)) {
        \App\Security\SecurityMiddleware::logSecurityEvent('USER_REGISTERED', ['email' => $email]);
        // Auto-login after registration
        $authService->login($email, $password);
        redirect('/dashboard.php');
    } else {
        \App\Security\SecurityMiddleware::logSecurityEvent('REGISTER_FAILED', ['email' => $email]);
        $error = 'Erreur lors de la création du compte (email déjà utilisé?)';
    }
}

require __DIR__ . '/views/auth/register.php';
