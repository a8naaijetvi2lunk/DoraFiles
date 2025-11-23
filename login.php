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
    // Rate limiting - 5 attempts per 15 minutes
    if (!\App\Security\SecurityMiddleware::checkRateLimit('login', 5, 900)) {
        \App\Security\SecurityMiddleware::logSecurityEvent('LOGIN_RATE_LIMIT_EXCEEDED');
        http_response_code(429);
        $error = 'Trop de tentatives. Veuillez réessayer dans 15 minutes.';
        require __DIR__ . '/views/auth/login.php';
        exit;
    }

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        \App\Security\SecurityMiddleware::logSecurityEvent('INVALID_CSRF_TOKEN', ['action' => 'login']);
        http_response_code(403);
        die('Invalid CSRF token');
    }

    // Sanitize and validate inputs
    $email = \App\Security\SecurityMiddleware::sanitizeString($_POST['email'] ?? '', 255);
    $password = $_POST['password'] ?? '';

    // Validate email format
    $validatedEmail = \App\Security\SecurityMiddleware::validateEmail($email);
    if (!$validatedEmail) {
        \App\Security\SecurityMiddleware::logSecurityEvent('LOGIN_INVALID_EMAIL', ['email' => $email]);
        $error = 'Email ou mot de passe incorrect';
        require __DIR__ . '/views/auth/login.php';
        exit;
    }
    $email = $validatedEmail;

    // Validate password is not empty
    if (empty($password) || strlen($password) > 72) {
        \App\Security\SecurityMiddleware::logSecurityEvent('LOGIN_INVALID_PASSWORD');
        $error = 'Email ou mot de passe incorrect';
        require __DIR__ . '/views/auth/login.php';
        exit;
    }

    $loginResult = $authService->login($email, $password);

    if ($loginResult === '2fa_required') {
        // Redirect to 2FA verification
        redirect('/verify-2fa.php');
    } elseif ($loginResult === true) {
        \App\Security\SecurityMiddleware::logSecurityEvent('LOGIN_SUCCESS', ['email' => $email]);
        redirect('/dashboard.php');
    } else {
        \App\Security\SecurityMiddleware::logSecurityEvent('LOGIN_FAILED', ['email' => $email]);
        $error = 'Email ou mot de passe incorrect';
    }
}

require __DIR__ . '/views/auth/login.php';
