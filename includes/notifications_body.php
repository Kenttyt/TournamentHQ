<?php
/** @var array $notifications */
/** @var int $notificationCount */
/** @var string $role */
$emptyHint = $role === 'organizer'
    ? 'When someone registers players for your tournaments, you will see requests here.'
    : 'New tournaments and registration updates will appear here.';
?>
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <div class="card-title">
                <i data-lucide="bell"></i>
                Notifications
            </div>
            <p class="text-muted text-xs" style="margin:4px 0 0">
                <?= $role === 'organizer'
                    ? 'Registration requests for your tournaments'
                    : 'New tournaments and registration updates' ?>
            </p>
        </div>
        <?php if ($notificationCount > 0): ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline btn-sm">Mark all as read</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body notifications-page-body">
        <?php if (empty($notifications)): ?>
        <div class="notification-empty">
            <i data-lucide="bell-off"></i>
            <p>No notifications yet</p>
            <span class="text-muted"><?= e($emptyHint) ?></span>
        </div>
        <?php else: ?>
        <div class="notifications-page-list">
            <?php foreach ($notifications as $n):
                $icon = notificationIcon($n['type']);
                $isUnread = empty($n['is_read']);
            ?>
            <article class="notification-card<?= $isUnread ? ' is-unread' : '' ?><?= !empty($n['live']) ? ' is-live' : '' ?>"
                     <?= !empty($n['id']) ? ' data-notification-id="' . (int) $n['id'] . '"' : '' ?>>
                <a href="<?= e($n['link'] ?? '#') ?>"
                   class="notification-card-link"
                   <?= !empty($n['id']) ? ' data-notification-id="' . (int) $n['id'] . '"' : '' ?>>
                    <div class="notification-card-icon">
                        <i data-lucide="<?= e($icon) ?>"></i>
                    </div>
                    <div class="notification-card-content">
                        <div class="notification-card-header">
                            <h3 class="notification-card-title"><?= e($n['title']) ?></h3>
                            <time class="notification-card-time"><?= e(notificationTimeAgo($n['created_at'] ?? '')) ?></time>
                        </div>
                        <p class="notification-card-message"><?= e($n['message']) ?></p>
                    </div>
                </a>
                <?php if (!empty($n['id'])): ?>
                <div class="notif-kebab-wrap">
                    <button type="button" class="notif-kebab-btn" aria-label="Notification options">
                        <i data-lucide="more-vertical"></i>
                    </button>
                    <div class="notif-kebab-menu" hidden>
                        <?php if ($isUnread): ?>
                        <button type="button" class="notif-kebab-item" data-notif-action="mark_read" data-notif-id="<?= (int) $n['id'] ?>">
                            <i data-lucide="check"></i> Mark as Read
                        </button>
                        <?php endif; ?>
                        <button type="button" class="notif-kebab-item notif-kebab-item--danger" data-notif-action="delete" data-notif-id="<?= (int) $n['id'] ?>">
                            <i data-lucide="trash-2"></i> Delete Notification
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
