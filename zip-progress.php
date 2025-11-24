<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    redirect('/login.php');
}

$user = auth();
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $_SESSION['error'] = 'Token manquant';
    redirect('/browse.php');
}

$title = 'Génération du ZIP - Dora Files';
?>
<?php require __DIR__ . '/views/partials/header.php'; ?>

<style>
    .zip-progress-container {
        max-width: 700px;
        margin: 50px auto;
    }

    .zip-progress-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 32px;
        box-shadow: var(--shadow-lg);
    }

    .zip-progress-header {
        text-align: center;
        margin-bottom: 32px;
    }

    .zip-progress-header h1 {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 12px 0;
    }

    .zip-status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }

    .zip-status-pending {
        background: #fbbf24;
        color: #000000;
    }

    .zip-status-processing {
        background: #3b82f6;
        color: #ffffff;
    }

    .zip-status-completed {
        background: var(--success);
        color: #ffffff;
    }

    .zip-status-failed {
        background: var(--danger);
        color: #ffffff;
    }

    .progress-wrapper {
        position: relative;
        margin: 24px 0;
    }

    .progress-bar-container {
        width: 100%;
        height: 36px;
        background: var(--bg-body);
        border-radius: var(--radius-lg);
        overflow: hidden;
        border: 2px solid var(--border-color);
        position: relative;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary) 0%, var(--primary-hover) 100%);
        transition: width 0.5s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 14px;
        min-width: 60px;
        box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
    }

    .progress-text-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-weight: 700;
        font-size: 14px;
        color: var(--text-primary);
        pointer-events: none;
        z-index: 10;
    }

    .progress-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin: 24px 0;
    }

    .info-card {
        padding: 16px;
        background: var(--bg-body);
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
    }

    .info-label {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
    }

    .info-value {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .zip-spinner {
        border: 3px solid var(--border-color);
        border-top: 3px solid var(--primary);
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

    .zip-actions {
        margin-top: 32px;
        text-align: center;
    }

    .back-link {
        display: inline-block;
        margin-top: 20px;
        color: var(--text-muted);
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: var(--text-primary);
    }
</style>

<div class="zip-progress-container">
    <div class="zip-progress-card">
        <div class="zip-progress-header">
            <h1>📦 Génération du ZIP</h1>
            <div id="status-container">
                <span id="status-badge" class="zip-status-badge zip-status-pending">En attente...</span>
            </div>
        </div>

        <div class="progress-wrapper">
            <div class="progress-bar-container">
                <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
                <span class="progress-text-overlay" id="progress-text">0%</span>
            </div>
        </div>

        <div class="progress-info">
            <div class="info-card">
                <div class="info-label">Fichiers traités</div>
                <div class="info-value" id="files-progress">0 / 0</div>
            </div>
            <div class="info-card">
                <div class="info-label">Taille traitée</div>
                <div class="info-value" id="size-progress">0 B / 0 B</div>
            </div>
            <div class="info-card">
                <div class="info-label">Temps restant</div>
                <div class="info-value" id="time-remaining">Calcul...</div>
            </div>
            <div class="info-card">
                <div class="info-label">Démarré à</div>
                <div class="info-value" id="started-at">-</div>
            </div>
        </div>

        <div id="error-container" style="display: none;">
            <div class="alert alert-error" id="error-message"></div>
        </div>

        <div class="zip-actions">
            <button class="btn btn-primary btn-lg" id="download-btn" disabled onclick="downloadZip()" style="width: 100%; max-width: 400px;">
                <span id="download-btn-text">⏳ ZIP en préparation...</span>
            </button>

            <a href="/browse.php" class="back-link">← Retour à la navigation</a>
        </div>
    </div>
</div>

<script>
    const token = '<?= e($token) ?>';
    let pollInterval = null;

    async function checkStatus() {
        try {
            const response = await fetch(`/api/zip-status.php?token=${token}`);
            const data = await response.json();

            if (!data.success) {
                showError(data.error || 'Erreur inconnue');
                stopPolling();
                return;
            }

            updateUI(data);

            // Stop polling if completed or failed
            if (data.status === 'completed' || data.status === 'failed') {
                stopPolling();
            }

        } catch (error) {
            console.error('Error checking status:', error);
            showError('Erreur de connexion au serveur');
        }
    }

    function updateUI(data) {
        // Update status badge
        const statusBadge = document.getElementById('status-badge');
        statusBadge.textContent = getStatusText(data.status);
        statusBadge.className = 'zip-status-badge zip-status-' + data.status;

        // Update progress bar
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const percent = parseInt(data.progress_percent) || 0;
        progressBar.style.width = percent + '%';
        progressText.textContent = percent + '%';

        // Change text color based on progress width (white when bar is wide enough)
        if (percent > 20) {
            progressText.style.color = 'white';
        } else {
            progressText.style.color = 'var(--text-primary)';
        }

        // Update info
        document.getElementById('files-progress').textContent =
            `${data.processed_files || 0} / ${data.total_files || 0}`;
        document.getElementById('size-progress').textContent =
            `${data.processed_size || '0 B'} / ${data.total_size || '0 B'}`;
        document.getElementById('time-remaining').textContent =
            data.time_remaining || 'Calcul...';

        if (data.started_at) {
            const startedDate = new Date(data.started_at.replace(' ', 'T'));
            document.getElementById('started-at').textContent =
                startedDate.toLocaleTimeString('fr-FR');
        }

        // Update download button
        const downloadBtn = document.getElementById('download-btn');
        const downloadBtnText = document.getElementById('download-btn-text');

        if (data.status === 'completed') {
            downloadBtn.disabled = false;
            downloadBtn.classList.remove('btn-secondary');
            downloadBtn.classList.add('btn-primary');
            downloadBtnText.textContent = '⬇️ Télécharger le ZIP';
        } else if (data.status === 'processing') {
            downloadBtnText.textContent = `⏳ Génération en cours... ${percent}%`;
        } else if (data.status === 'failed') {
            downloadBtnText.textContent = '❌ Échec de la génération';
            downloadBtn.classList.add('btn-secondary');
        }

        // Show error if failed
        if (data.status === 'failed' && data.error_message) {
            showError(data.error_message);
        }
    }

    function getStatusText(status) {
        const statusMap = {
            'pending': 'En attente',
            'processing': 'En cours',
            'completed': 'Terminé',
            'failed': 'Échec'
        };
        return statusMap[status] || status;
    }

    function showError(message) {
        document.getElementById('error-container').style.display = 'block';
        document.getElementById('error-message').textContent = message;
    }

    function downloadZip() {
        window.location.href = `/api/zip-download.php?token=${token}`;
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    // Start polling every 2 seconds
    checkStatus();
    pollInterval = setInterval(checkStatus, 2000);

    // Stop polling when user leaves page
    window.addEventListener('beforeunload', stopPolling);
</script>

<?php require __DIR__ . '/views/partials/footer.php'; ?>
