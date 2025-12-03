<?php
require_once __DIR__ . '/../auth/check_role.php';
checkRole(['admin', 'employee', 'partner']);
requirePermission('can_view_forum');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/forum_service.php';
require_once __DIR__ . '/../includes/layout.php';
requireAbsenceAccess('forum');

ensureForumTables($pdo);

$categories = fetchForumCategories($pdo);

renderHeader('Forum', 'forum');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">Ultra Modern Board</p>
        <h1>Community Forum</h1>
        <p class="muted">Diskutiere mit anderen Mitgliedern, teile Wissen und entdecke die neuesten Insights.</p>
    </div>
</div>

<div class="grid-2">
    <?php foreach ($categories as $category): ?>
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="eyebrow">Kategorie</div>
                    <h3><?= htmlspecialchars($category['title']) ?></h3>
                </div>
                <div class="chip"><?= (int)$category['thread_count'] ?> Threads</div>
            </div>
            <p class="muted"><?= htmlspecialchars($category['description'] ?? '') ?></p>
            <?php if ($category['latest_post_at']): ?>
                <div class="muted" style="margin:10px 0;">
                    Letzter Beitrag: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($category['latest_post_at']))) ?>
                </div>
            <?php endif; ?>
            <a class="btn btn-primary" href="/forum/category.php?id=<?= (int)$category['id'] ?>">Öffnen</a>
        </div>
    <?php endforeach; ?>
</div>
<?php
renderFooter();