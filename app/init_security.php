<?php

/**
 * Security initialization - Include this file at the top of every endpoint
 */

// Apply security headers
\App\Security\SecurityMiddleware::applySecurityHeaders();

// Configure secure session
\App\Security\SecurityMiddleware::configureSecureSession();
