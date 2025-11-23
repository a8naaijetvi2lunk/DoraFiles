<?php
/**
 * Installation Wizard - Dora Files
 * System inspired by WordPress installation
 */

// Check if already installed
if (file_exists(__DIR__ . '/../.env') && filesize(__DIR__ . '/../.env') > 0) {
    // Check if .env has real configuration
    $envContent = file_get_contents(__DIR__ . '/../.env');
    if (strpos($envContent, 'DB_DATABASE=') !== false && strpos($envContent, 'CHANGE_THIS') === false) {
        header('Location: ../index.php');
        exit;
    }
}

// Enable error reporting for setup
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Read step from POST (form submission) or GET (navigation), default to 1
$step = isset($_POST['step']) ? (int)$_POST['step'] : (isset($_GET['step']) ? (int)$_GET['step'] : 1);
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Requirements check passed, go to step 2
        $step = 2;
    } elseif ($step === 2) {
        // Database configuration
        $_SESSION['setup_data'] = [
            'db_host' => trim($_POST['db_host'] ?? ''),
            'db_port' => trim($_POST['db_port'] ?? '3306'),
            'db_name' => trim($_POST['db_name'] ?? ''),
            'db_user' => trim($_POST['db_user'] ?? ''),
            'db_pass' => trim($_POST['db_pass'] ?? ''),
            'app_url' => trim($_POST['app_url'] ?? ''),
        ];

        // Test database connection
        try {
            $dsn = "mysql:host={$_SESSION['setup_data']['db_host']};port={$_SESSION['setup_data']['db_port']};dbname={$_SESSION['setup_data']['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $_SESSION['setup_data']['db_user'], $_SESSION['setup_data']['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $step = 3;
        } catch (PDOException $e) {
            $errors[] = "Database connection failed: " . $e->getMessage();
        }
    } elseif ($step === 3) {
        // Admin account creation
        $_SESSION['setup_data']['admin_email'] = trim($_POST['admin_email'] ?? '');
        $_SESSION['setup_data']['admin_password'] = $_POST['admin_password'] ?? '';
        $_SESSION['setup_data']['ftp_host'] = trim($_POST['ftp_host'] ?? '');
        $_SESSION['setup_data']['ftp_port'] = trim($_POST['ftp_port'] ?? '21');
        $_SESSION['setup_data']['ftp_user'] = trim($_POST['ftp_user'] ?? '');
        $_SESSION['setup_data']['ftp_pass'] = $_POST['ftp_pass'] ?? '';
        $_SESSION['setup_data']['ftp_path'] = trim($_POST['ftp_path'] ?? '/');

        // Validation
        if (!filter_var($_SESSION['setup_data']['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address";
        }
        if (strlen($_SESSION['setup_data']['admin_password']) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }

        // Test FTP connection if provided
        if (!empty($_SESSION['setup_data']['ftp_host'])) {
            $ftp = @ftp_connect($_SESSION['setup_data']['ftp_host'], $_SESSION['setup_data']['ftp_port'], 5);
            if ($ftp && @ftp_login($ftp, $_SESSION['setup_data']['ftp_user'], $_SESSION['setup_data']['ftp_pass'])) {
                @ftp_close($ftp);
            } else {
                $errors[] = "FTP connection failed. Please check your credentials.";
            }
        }

        if (empty($errors)) {
            $step = 4;
        }
    } elseif ($step === 4) {
        // Final installation
        try {
            $data = $_SESSION['setup_data'];

            // Generate encryption key
            $encryptionKey = 'base64:' . base64_encode(random_bytes(32));

            // Create .env file
            $envContent = "# Application Configuration
APP_NAME=\"Dora Files\"
APP_ENV=production
APP_URL={$data['app_url']}
APP_ENCRYPTION_KEY=$encryptionKey

# Database Configuration
DB_CONNECTION=mysql
DB_HOST={$data['db_host']}
DB_PORT={$data['db_port']}
DB_DATABASE={$data['db_name']}
DB_USERNAME={$data['db_user']}
DB_PASSWORD={$data['db_pass']}

# FTP Configuration
FTP_TIMEOUT=10

# File Upload Configuration
MAX_FILE_SIZE=2147483648
ALLOWED_FILE_TYPES=image/,video/,audio/,application/pdf,application/zip,application/msword,application/vnd.openxmlformats-officedocument,text/

# Rate Limiting Configuration
DOWNLOAD_RATE_LIMIT=10

# Session Configuration
SESSION_LIFETIME=120
SESSION_COOKIE=dorafiles_session

# Security Configuration
TRUSTED_PROXIES=

# Activity Log Retention
ACTIVITY_LOG_RETENTION_DAYS=90
";

            file_put_contents(__DIR__ . '/../.env', $envContent);

            // Run database migrations
            require_once __DIR__ . '/../vendor/autoload.php';

            // Load environment
            $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }

            // Connect to database
            $dsn = "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $data['db_user'], $data['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Run migrations
            include __DIR__ . '/../database/migrations/001_initial_schema.php';
            include __DIR__ . '/../database/migrations/002_profile_feature.php';
            include __DIR__ . '/../database/migrations/003_two_factor_auth.php';

            // Create admin user
            $passwordHash = password_hash($data['admin_password'], PASSWORD_DEFAULT);

            // Encryption helper
            $key = base64_decode(substr($encryptionKey, 7));
            $encrypt = function($data) use ($key) {
                $iv = random_bytes(16);
                $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
                return base64_encode($iv . $encrypted);
            };

            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $data['admin_email'],
                $passwordHash,
                $encrypt($data['ftp_host']),
                $encrypt($data['ftp_port']),
                $encrypt($data['ftp_user']),
                $encrypt($data['ftp_pass']),
                $encrypt($data['ftp_path'])
            ]);

            $userId = $pdo->lastInsertId();

            // Create FTP connection
            $stmt = $pdo->prepare("
                INSERT INTO ftp_connections (user_id, connection_name, ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path, is_default, created_at)
                VALUES (?, 'Default Connection', ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                $userId,
                $encrypt($data['ftp_host']),
                $encrypt($data['ftp_port']),
                $encrypt($data['ftp_user']),
                $encrypt($data['ftp_pass']),
                $encrypt($data['ftp_path'])
            ]);

            $connId = $pdo->lastInsertId();

            // Set active connection
            $pdo->prepare("UPDATE users SET active_ftp_connection_id = ? WHERE id = ?")
                ->execute([$connId, $userId]);

            $step = 5;
            $_SESSION['installation_complete'] = true;
        } catch (Exception $e) {
            $errors[] = "Installation failed: " . $e->getMessage();
        }
    }
}

// Check requirements
function checkRequirements() {
    $requirements = [
        'PHP Version >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'FTP Extension' => extension_loaded('ftp'),
        'Write permission (.env)' => is_writable(__DIR__ . '/..') || !file_exists(__DIR__ . '/../.env'),
        'Vendor directory exists' => is_dir(__DIR__ . '/../vendor'),
    ];

    return $requirements;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Dora Files</title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        .setup-container {
            max-width: 700px;
            margin: 60px auto;
            padding: 0 20px;
        }
        .setup-logo {
            text-align: center;
            margin-bottom: 40px;
        }
        .setup-logo img {
            height: 80px;
            margin-bottom: 16px;
        }
        .setup-logo h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .setup-logo p {
            color: var(--text-muted);
            font-size: 15px;
        }
        .setup-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .setup-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
            z-index: 0;
        }
        .setup-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .setup-step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.3s;
        }
        .setup-step.active .setup-step-circle {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
        }
        .setup-step.completed .setup-step-circle {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        .setup-step-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }
        .setup-step.active .setup-step-label {
            color: var(--primary);
        }
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            margin-bottom: 8px;
            background: var(--bg-body);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }
        .requirement-pass {
            color: var(--success);
            font-weight: 600;
        }
        .requirement-fail {
            color: var(--danger);
            font-weight: 600;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .success-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 24px;
            color: var(--success);
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 32px;
        }
        .feature-card {
            background: var(--bg-body);
            padding: 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .feature-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }
        .feature-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .feature-desc {
            font-size: 13px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-logo">
            <img src="../public/images/logo.png" alt="Dora Files">
            <h1>Installation de Dora Files</h1>
            <p>Système de gestion de fichiers FTP vers Web</p>
        </div>

        <!-- Progress Steps -->
        <div class="setup-steps">
            <div class="setup-step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="setup-step-circle"><?php echo $step > 1 ? '✓' : '1'; ?></div>
                <div class="setup-step-label">Prérequis</div>
            </div>
            <div class="setup-step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="setup-step-circle"><?php echo $step > 2 ? '✓' : '2'; ?></div>
                <div class="setup-step-label">Base de données</div>
            </div>
            <div class="setup-step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <div class="setup-step-circle"><?php echo $step > 3 ? '✓' : '3'; ?></div>
                <div class="setup-step-label">Compte admin</div>
            </div>
            <div class="setup-step <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
                <div class="setup-step-circle"><?php echo $step > 4 ? '✓' : '4'; ?></div>
                <div class="setup-step-label">Installation</div>
            </div>
            <div class="setup-step <?php echo $step >= 5 ? 'active completed' : ''; ?>">
                <div class="setup-step-circle"><?php echo $step >= 5 ? '✓' : '5'; ?></div>
                <div class="setup-step-label">Terminé</div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div>⚠ <?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Requirements -->
        <?php if ($step === 1): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Vérification des prérequis</h2>
                </div>

                <?php
                $requirements = checkRequirements();
                $allPassed = !in_array(false, $requirements);
                ?>

                <?php foreach ($requirements as $name => $passed): ?>
                    <div class="requirement-item">
                        <span><?php echo htmlspecialchars($name); ?></span>
                        <span class="<?php echo $passed ? 'requirement-pass' : 'requirement-fail'; ?>">
                            <?php echo $passed ? '✓ OK' : '✗ FAIL'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top: 24px;">
                    <?php if ($allPassed): ?>
                        <form method="POST">
                            <input type="hidden" name="step" value="1">
                            <button type="submit" class="btn btn-primary btn-full">
                                Continuer →
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error">
                            Certains prérequis ne sont pas satisfaits. Veuillez les corriger avant de continuer.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 2: Database Configuration -->
        <?php if ($step === 2): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Configuration de la base de données</h2>
                </div>

                <form method="POST">
                    <input type="hidden" name="step" value="2">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Hôte de la base de données</label>
                            <input type="text" name="db_host" value="<?php echo htmlspecialchars($_SESSION['setup_data']['db_host'] ?? '127.0.0.1'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Port</label>
                            <input type="number" name="db_port" value="<?php echo htmlspecialchars($_SESSION['setup_data']['db_port'] ?? '3306'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nom de la base de données</label>
                        <input type="text" name="db_name" value="<?php echo htmlspecialchars($_SESSION['setup_data']['db_name'] ?? ''); ?>" required>
                        <div class="helper-text">La base de données doit déjà exister</div>
                    </div>

                    <div class="form-group">
                        <label>Nom d'utilisateur</label>
                        <input type="text" name="db_user" value="<?php echo htmlspecialchars($_SESSION['setup_data']['db_user'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Mot de passe</label>
                        <input type="password" name="db_pass" value="<?php echo htmlspecialchars($_SESSION['setup_data']['db_pass'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>URL de l'application</label>
                        <input type="url" name="app_url" value="<?php echo htmlspecialchars($_SESSION['setup_data']['app_url'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')); ?>" required>
                        <div class="helper-text">URL complète (ex: https://files.example.com)</div>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            Tester et continuer →
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Step 3: Admin Account -->
        <?php if ($step === 3): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Compte administrateur</h2>
                </div>

                <form method="POST">
                    <input type="hidden" name="step" value="3">
                    <div class="form-group">
                        <label>Adresse email</label>
                        <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_SESSION['setup_data']['admin_email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Mot de passe</label>
                        <input type="password" name="admin_password" minlength="8" required>
                        <div class="helper-text">Minimum 8 caractères</div>
                    </div>

                    <hr class="auth-separator">
                    <h3 class="auth-section-title">Configuration FTP</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Hôte FTP</label>
                            <input type="text" name="ftp_host" value="<?php echo htmlspecialchars($_SESSION['setup_data']['ftp_host'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Port FTP</label>
                            <input type="number" name="ftp_port" value="<?php echo htmlspecialchars($_SESSION['setup_data']['ftp_port'] ?? '21'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nom d'utilisateur FTP</label>
                        <input type="text" name="ftp_user" value="<?php echo htmlspecialchars($_SESSION['setup_data']['ftp_user'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Mot de passe FTP</label>
                        <input type="password" name="ftp_pass" value="<?php echo htmlspecialchars($_SESSION['setup_data']['ftp_pass'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Chemin de base</label>
                        <input type="text" name="ftp_path" value="<?php echo htmlspecialchars($_SESSION['setup_data']['ftp_path'] ?? '/'); ?>" required>
                        <div class="helper-text">Répertoire racine sur le serveur FTP</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        Continuer →
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Step 4: Installation Progress -->
        <?php if ($step === 4): ?>
            <div class="card text-center">
                <div class="card-header">
                    <h2 class="card-title">Prêt à installer</h2>
                </div>

                <p style="color: var(--text-secondary); margin-bottom: 24px;">
                    Nous allons maintenant créer la configuration, installer la base de données et créer votre compte administrateur.
                </p>

                <form method="POST">
                    <input type="hidden" name="step" value="4">
                    <button type="submit" class="btn btn-primary btn-full">
                        🚀 Lancer l'installation
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Step 5: Success -->
        <?php if ($step === 5): ?>
            <div class="card text-center">
                <div class="success-icon">✓</div>
                <h2 style="color: var(--success); margin-bottom: 16px; font-size: 24px;">
                    Installation réussie !
                </h2>
                <p style="color: var(--text-secondary); margin-bottom: 32px;">
                    Dora Files a été installé avec succès. Vous pouvez maintenant vous connecter à votre compte.
                </p>

                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon">📁</div>
                        <div class="feature-title">Gestion FTP</div>
                        <div class="feature-desc">Accédez à vos fichiers</div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔗</div>
                        <div class="feature-title">Partage</div>
                        <div class="feature-desc">Liens de téléchargement</div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <div class="feature-title">Sécurité</div>
                        <div class="feature-desc">2FA disponible</div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <div class="feature-title">Statistiques</div>
                        <div class="feature-desc">Suivez vos activités</div>
                    </div>
                </div>

                <a href="../index.php" class="btn btn-primary btn-full" style="margin-top: 32px;">
                    Accéder à Dora Files →
                </a>
            </div>
        <?php endif; ?>

        <div class="footer">
            <div class="footer-content">
                Dora Files - Système de gestion de fichiers<br>
                <a href="https://github.com/yourusername/dora-files" style="color: var(--primary);">Documentation</a>
            </div>
        </div>
    </div>
</body>
</html>
