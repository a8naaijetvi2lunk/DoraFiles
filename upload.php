<?php

require_once __DIR__ . '/vendor/autoload.php';

// Increase limits for large file uploads (up to 10GB)
set_time_limit(7200); // 2 hours
ini_set('memory_limit', '2G');
ini_set('max_execution_time', '7200');
ini_set('max_input_time', '7200');

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$user = auth();
$targetPath = $_POST['target_path'] ?? '/';

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No file uploaded';
    if (isset($_FILES['file']['error'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $maxUpload = ini_get('upload_max_filesize');
                $maxPost = ini_get('post_max_size');
                $errorMsg = "Le fichier dépasse la limite d'upload du serveur (upload_max_filesize: {$maxUpload}, post_max_size: {$maxPost}). Veuillez contacter l'administrateur pour augmenter ces limites.";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'Le fichier dépasse la limite MAX_FILE_SIZE spécifiée dans le formulaire HTML';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'Le fichier n\'a été que partiellement uploadé. Vérifiez votre connexion internet.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'Aucun fichier n\'a été uploadé';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMsg = 'Erreur serveur : dossier temporaire manquant';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMsg = 'Erreur serveur : impossible d\'écrire le fichier sur le disque';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMsg = 'Une extension PHP a bloqué l\'upload du fichier';
                break;
            default:
                $errorMsg = "Erreur d'upload inconnue (code: {$_FILES['file']['error']})";
        }
    }
    http_response_code(400);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

$uploadedFile = $_FILES['file'];
$fileName = basename($uploadedFile['name']);
$tmpPath = $uploadedFile['tmp_name'];

// SECURITY FIX: Check extension FIRST to prevent double extension bypass (e.g., file.php.png)
$dangerousExtensions = ['.php', '.phtml', '.php3', '.php4', '.php5', '.phar', '.exe', '.bat', '.cmd', '.sh', '.js', '.jar', '.py', '.pl', '.cgi', '.htaccess'];
$fileExtension = strtolower(substr($fileName, strrpos($fileName, '.')));

// Additional check: block multiple extensions (file.php.txt)
$allExtensions = [];
$parts = explode('.', $fileName);
if (count($parts) > 2) {
    // Check all extensions in multi-dot filenames
    for ($i = 1; $i < count($parts); $i++) {
        $allExtensions[] = '.' . strtolower($parts[$i]);
    }
} else {
    $allExtensions[] = $fileExtension;
}

foreach ($allExtensions as $ext) {
    if (in_array($ext, $dangerousExtensions)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Extension de fichier interdite pour des raisons de sécurité: ' . $ext
        ]);
        exit;
    }
}

// Validate file type to prevent malicious uploads
$allowedMimeTypes = explode(',', env('ALLOWED_FILE_TYPES', 'image/,video/,audio/,application/pdf,application/zip,text/'));
$detectedMimeType = mime_content_type($tmpPath);

$isAllowed = false;
foreach ($allowedMimeTypes as $allowedType) {
    $allowedType = trim($allowedType);
    // Support both exact match and prefix match (e.g., "image/" matches "image/png")
    if ($detectedMimeType === $allowedType || str_starts_with($detectedMimeType, rtrim($allowedType, '/'))) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Type de fichier non autorisé: ' . $detectedMimeType,
        'allowed_types' => $allowedMimeTypes
    ]);
    exit;
}

try {
    $ftpUser = getFTPUserData();
    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    // Upload the file
    $ftpService->uploadFile($tmpPath, $targetPath, $fileName);
    $ftpService->close();

    echo json_encode([
        'success' => true,
        'message' => 'Fichier uploadé avec succès',
        'file_name' => $fileName
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'upload: ' . $e->getMessage()]);
}
