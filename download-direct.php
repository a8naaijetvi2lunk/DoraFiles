<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Increase execution time for large ZIP files
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '1G');

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized');
}

// Verify CSRF
if (!isset($_GET['csrf']) || !csrf_verify($_GET['csrf'])) {
    http_response_code(403);
    die('Invalid CSRF token');
}

// Get parameters
$path = $_GET['path'] ?? '';
$name = $_GET['name'] ?? 'download';
$type = $_GET['type'] ?? 'file';

if (empty($path)) {
    http_response_code(400);
    die('Missing path parameter');
}

try {
    $ftpUser = getFTPUserData();
    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    if ($type === 'dir') {
        // Show loading page, then prepare and download
        if (!isset($_GET['action']) || $_GET['action'] !== 'download') {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Préparation du téléchargement...</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        background: #0a0a0a;
                    }
                    .loader-container {
                        background: #111111;
                        border: 1px solid #1f1f1f;
                        padding: 40px;
                        border-radius: 12px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                        text-align: center;
                        max-width: 480px;
                        width: 90%;
                    }
                    h2 {
                        color: #ffffff;
                        margin-bottom: 30px;
                        font-size: 24px;
                        font-weight: 600;
                    }
                    .spinner {
                        border: 4px solid #1a1a1a;
                        border-top: 4px solid #ffffff;
                        border-radius: 50%;
                        width: 40px;
                        height: 40px;
                        animation: spin 1s linear infinite;
                        margin: 20px auto;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    #status {
                        color: #a1a1a1;
                        margin: 15px 0;
                        font-size: 15px;
                    }
                </style>
            </head>
            <body>
                <div class="loader-container">
                    <h2>Préparation du ZIP</h2>
                    <div class="spinner"></div>
                    <p id="status">Connexion au serveur FTP et création de l'archive...</p>
                    <p style="color: #666; font-size: 14px; margin-top: 16px;">
                        Dossier: <strong style="color: #a1a1a1;"><?= htmlspecialchars($name) ?></strong>
                    </p>
                    <p style="color: #666; font-size: 13px; margin-top: 24px;">
                        Le téléchargement démarrera automatiquement une fois l'archive créée.
                    </p>
                    <p style="color: #888; font-size: 11px; margin-top: 20px; line-height: 1.4;">
                        ⚠️ Les fichiers de plus de 1 Go peuvent prendre entre 5 et 20 minutes à préparer.
                    </p>
                    <a href="/browse.php" style="display: inline-block; margin-top: 24px; padding: 10px 20px; background: #1a1a1a; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; border: 1px solid #2a2a2a; transition: background 0.2s;">
                        ← Retour à l'accueil
                    </a>
                </div>

                <script>
                // Trigger the actual download immediately (it will take time to prepare)
                window.location.href = '<?= $_SERVER['REQUEST_URI'] ?>&action=download';
                </script>
            </body>
            </html>
            <?php
            exit;
        }

        // Actual ZIP creation and download
        $zipData = $ftpService->downloadFolderAsZip($path, $name);
        $zipFile = $zipData['zipFile'];
        $tempFiles = $zipData['tempFiles'];

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        readfile($zipFile);

        // Clean up ZIP and all temp files AFTER sending to user
        @unlink($zipFile);
        foreach ($tempFiles as $tempFile) {
            @unlink($tempFile);
        }

    } else {
        // Single file - direct download
        $fileSize = $ftpService->getFileSize($path);

        if ($fileSize === -1) {
            throw new \Exception("File not found");
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . $fileSize);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        if (ob_get_level()) {
            ob_end_clean();
        }

        $ftpService->streamFile($path);
    }

    $ftpService->close();

} catch (Exception $e) {
    http_response_code(500);
    die('Download error: ' . $e->getMessage());
}
