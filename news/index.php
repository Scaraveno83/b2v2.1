<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/news_service.php';

ensureNewsSchema($pdo);

$user = $_SESSION['user'] ?? null;
$feed = fetchNewsFeed($pdo, $user, 20);
$canManage = $user && hasPermission('can_manage_news');

renderHeader('News & Ankündigungen', 'news');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">News &amp; Ankündigungen</p>
        <h1>Brandaktuelle Infos</h1>
        <p class="muted">Rollenbasierte News mit Mentions, Reaktionen und Kommentaren.</p>
    </div>
    <div class="page-actions">
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="/news/manage.php">News verfassen</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (!$feed): ?>
        <p class="muted">Noch keine News vorhanden.</p>
    <?php else: ?>
        <div class="news-list">
            <?php foreach ($feed as $item): ?>
                <article class="news-tile">
                    <div class="news-tile__meta">
                        <span class="badge badge-ghost"><?= htmlspecialchars(getNewsAudienceLabel($item['visibility'])) ?></span>
                        <span class="muted">
                            <?= htmlspecialchars($item['author_name'] ?? 'System') ?> ·
                            <?= htmlspecialchars(date('d.m.Y H:i', strtotime($item['created_at']))) ?>
                        </span>
                    </div>
                    <h3 class="news-tile__title"><?= htmlspecialchars($item['title']) ?></h3>
                    <p class="news-tile__excerpt">
                        <?= htmlspecialchars(mb_substr($item['content'], 0, 180)) ?>
                        <?php if (mb_strlen($item['content']) > 180): ?>…<?php endif; ?>
                    </p>
                    <div class="news-tile__actions">
                        <a class="btn btn-link" href="/news/show.php?id=<?= (int)$item['id'] ?>">Lesen &amp; reagieren</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
renderFooter();