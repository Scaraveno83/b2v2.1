<?php
require_once __DIR__ . '/../auth/check_role.php';
checkRole(['admin', 'employee', 'partner']);
requirePermission('can_view_forum');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/forum_service.php';
require_once __DIR__ . '/../includes/layout.php';
requireAbsenceAccess('forum');

ensureForumTables($pdo);

$categoryId = (int)($_GET['id'] ?? 0);
$category = fetchForumCategory($pdo, $categoryId);
if (!$category) {
    http_response_code(404);
    echo 'Kategorie nicht gefunden';
    exit;
}

$user = $_SESSION['user'];
$error = '';
$canCreateThread = hasPermission('can_create_threads');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_thread') {
        if (!$canCreateThread) {
            http_response_code(403);
            echo 'Keine Berechtigung zum Erstellen von Threads.';
            exit;
        }
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($title === '' || $body === '') {
            $error = 'Titel und Inhalt dürfen nicht leer sein.';
        } else {
            $threadId = createThreadWithPost($pdo, $categoryId, (int)$user['id'], $title, $body);
            header('Location: /forum/thread.php?id=' . $threadId);
            exit;
        }
    }
}

$threads = fetchThreadsByCategory($pdo, $categoryId);

renderHeader('Forum · ' . $category['title'], 'forum');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">Forum</p>
        <h1><?= htmlspecialchars($category['title']) ?></h1>
        <p class="muted"><?= htmlspecialchars($category['description'] ?? '') ?></p>
    </div>
    <div>
        <?php if ($canCreateThread): ?>
            <button class="btn btn-primary" type="button" onclick="document.getElementById('thread-form').scrollIntoView({behavior: 'smooth'});">Neuen Thread starten</button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Threads</h2>
    <?php if (empty($threads)): ?>
        <p class="muted">Noch keine Diskussionen. Starte den ersten Thread!</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Beiträge</th>
                    <th>Letzte Aktivität</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($threads as $thread): ?>
                    <tr>
                        <td>
                            <a class="btn-link" href="/forum/thread.php?id=<?= (int)$thread['id'] ?>">
                                <?= $thread['pinned'] ? '📌 ' : '' ?><?= htmlspecialchars($thread['title']) ?>
                            </a>
                            <div class="muted">von <?= htmlspecialchars($thread['author_name'] ?? 'Gelöscht') ?> · <?= date('d.m.Y H:i', strtotime($thread['created_at'])) ?></div>
                        </td>
                        <td>
                            <?php if ((int)$thread['locked'] === 1): ?>
                                <span class="chip danger">Gesperrt</span>
                            <?php else: ?>
                                <span class="chip success">Offen</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$thread['post_count'] ?></td>
                        <td><?= $thread['last_post_at'] ? date('d.m.Y H:i', strtotime($thread['last_post_at'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($canCreateThread): ?>
    <div class="card" id="thread-form">
        <h2>Neuen Thread erstellen</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="create_thread">
            <div class="field-group">
                <label for="title">Titel</label>
                <input id="title" name="title" required maxlength="255">
            </div>
            <div class="field-group">
                <label for="body">Inhalt</label>
                <textarea id="body" name="body" rows="6" required></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Thread veröffentlichen</button>
            <a class="btn btn-secondary" href="/forum/index.php">Zur Übersicht</a>
        </form>
    </div>
<?php endif; ?>
<?php
renderFooter();