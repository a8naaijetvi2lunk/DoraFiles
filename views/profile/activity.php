<?php
use App\Services\ActivityLogService;
?>

<div class="card">
    <h3 class="card-title">Historique d'activité</h3>

    <?php if (empty($activityData['logs'])): ?>
        <div class="empty-state">
            <div class="empty-icon">📊</div>
            <p class="empty-text">Aucune activité enregistrée</p>
        </div>
    <?php else: ?>
        <div class="activity-list">
            <?php foreach ($activityData['logs'] as $log): ?>
                <?php
                // Determine category for styling
                $actionCategory = 'default';
                if (str_contains($log['action'], 'login') || str_contains($log['action'], 'logout') || str_contains($log['action'], 'register')) {
                    $actionCategory = 'auth';
                } elseif (str_contains($log['action'], 'ftp_connection')) {
                    $actionCategory = 'ftp';
                } elseif (str_contains($log['action'], 'file') || str_contains($log['action'], 'folder')) {
                    $actionCategory = 'file';
                } elseif (str_contains($log['action'], 'link')) {
                    $actionCategory = 'link';
                } elseif (str_contains($log['action'], 'email') || str_contains($log['action'], 'password') || str_contains($log['action'], 'account')) {
                    $actionCategory = 'user';
                }
                ?>
                <div class="activity-item">
                    <div class="activity-icon-wrapper activity-icon-<?= $actionCategory ?>">
                        <span class="activity-icon-text"><?= ActivityLogService::getActionIcon($log['action']) ?></span>
                    </div>

                    <div class="activity-content">
                        <div class="activity-main">
                            <strong class="activity-action"><?= ActivityLogService::getActionDescription($log['action']) ?></strong>

                            <?php if ($log['entity_name']): ?>
                                <span class="activity-entity"><?= e($log['entity_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($log['details']): ?>
                            <div class="activity-details-text"><?= e($log['details']) ?></div>
                        <?php endif; ?>

                        <div class="activity-meta">
                            <span class="activity-time"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></span>

                            <?php if ($log['ip_address']): ?>
                                <span class="activity-separator">•</span>
                                <code class="code-badge"><?= e($log['ip_address']) ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($activityData['pages'] > 1): ?>
            <div class="activity-pagination">
                <?php if ($activityData['current_page'] > 1): ?>
                    <a href="/profile.php?tab=activity&page=<?= $activityData['current_page'] - 1 ?>" class="btn btn-secondary">
                        ← Précédent
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>

                <span class="activity-pagination-info">
                    Page <?= $activityData['current_page'] ?> / <?= $activityData['pages'] ?>
                    <span style="color: var(--text-muted);">(<?= $activityData['total'] ?> activités)</span>
                </span>

                <?php if ($activityData['current_page'] < $activityData['pages']): ?>
                    <a href="/profile.php?tab=activity&page=<?= $activityData['current_page'] + 1 ?>" class="btn btn-secondary">
                        Suivant →
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 24px;
}

.activity-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: var(--bg-body);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}

.activity-item:hover {
    border-color: var(--border-hover);
    background: var(--bg-card-hover);
}

.activity-icon-wrapper {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    background: var(--bg-card);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--border-color);
    transition: all 0.2s;
}

.activity-item:hover .activity-icon-wrapper {
    transform: scale(1.05);
    border-color: var(--primary);
}

.activity-icon-text {
    font-size: 20px;
    line-height: 1;
}

/* Category-specific icon colors */
.activity-icon-auth {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}

.activity-icon-ftp {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.activity-icon-file {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
}

.activity-icon-link {
    background: rgba(139, 92, 246, 0.1);
    border-color: rgba(139, 92, 246, 0.3);
}

.activity-icon-user {
    background: rgba(236, 72, 153, 0.1);
    border-color: rgba(236, 72, 153, 0.3);
}

.activity-item:hover .activity-icon-auth {
    border-color: rgba(99, 102, 241, 0.6);
    background: rgba(99, 102, 241, 0.15);
}

.activity-item:hover .activity-icon-ftp {
    border-color: rgba(16, 185, 129, 0.6);
    background: rgba(16, 185, 129, 0.15);
}

.activity-item:hover .activity-icon-file {
    border-color: rgba(245, 158, 11, 0.6);
    background: rgba(245, 158, 11, 0.15);
}

.activity-item:hover .activity-icon-link {
    border-color: rgba(139, 92, 246, 0.6);
    background: rgba(139, 92, 246, 0.15);
}

.activity-item:hover .activity-icon-user {
    border-color: rgba(236, 72, 153, 0.6);
    background: rgba(236, 72, 153, 0.15);
}

.activity-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.activity-main {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.activity-action {
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 500;
}

.activity-entity {
    color: var(--primary);
    font-size: 13px;
    font-weight: 500;
    background: rgba(99, 102, 241, 0.1);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
}

.activity-details-text {
    color: var(--text-muted);
    font-size: 13px;
}

.activity-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--text-muted);
}

.activity-time {
    font-weight: 500;
}

.activity-separator {
    color: var(--text-muted);
}

.activity-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.activity-pagination-info {
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
}
</style>
