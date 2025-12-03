<?php
require_once __DIR__ . '/../auth/check_role.php';
checkRole(['admin', 'employee', 'partner']);
requirePermission('can_view_forum');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/forum_service.php';
require_once __DIR__ . '/../includes/layout.php';
requireAbsenceAccess('forum');

ensureForumTables($pdo);

$threadId = (int)($_GET['id'] ?? 0);
$thread = fetchThreadWithCategory($pdo, $threadId);
if (!$thread) {
    http_response_code(404);
    echo 'Thread nicht gefunden';
    exit;
}

$user = $_SESSION['user'];
$canModerate = hasPermission('can_moderate_forum');
$canReply = hasPermission('can_reply_threads');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        if (!$canReply) {
            http_response_code(403);
            echo 'Keine Berechtigung zum Antworten.';
            exit;
        }
        if ((int)$thread['locked'] === 1 && !$canModerate) {
            $error = 'Dieser Thread ist gesperrt.';
        } else {
            $body = trim($_POST['body'] ?? '');
            if ($body === '') {
                $error = 'Antwort darf nicht leer sein.';
            } else {
                addPostToThread($pdo, $threadId, (int)$user['id'], $body);
                header('Location: /forum/thread.php?id=' . $threadId . '#last');
                exit;
            }
        }
    }

    if ($action === 'toggle_lock' && $canModerate) {
        $newState = $_POST['state'] === 'lock';
        toggleThreadLock($pdo, $threadId, $newState);
        header('Location: /forum/thread.php?id=' . $threadId);
        exit;
    }

    if ($action === 'toggle_pin' && $canModerate) {
        $newState = $_POST['state'] === 'pin';
        toggleThreadPin($pdo, $threadId, $newState);
        header('Location: /forum/thread.php?id=' . $threadId);
        exit;
    }
}

$thread = fetchThreadWithCategory($pdo, $threadId);
$posts = fetchPostsForThread($pdo, $threadId);

renderHeader('Forum · ' . $thread['title'], 'forum');
?>
<div class="page-header">
    <div>
        <p class="eyebrow"><a class="btn-link" href="/forum/index.php">Forum</a> · <a class="btn-link" href="/forum/category.php?id=<?= (int)$thread['category_id'] ?>"><?= htmlspecialchars($thread['category_title']) ?></a></p>
        <h1><?= $thread['pinned'] ? '📌 ' : '' ?><?= htmlspecialchars($thread['title']) ?></h1>
        <p class="muted">Gestartet von <?= htmlspecialchars($thread['author_name'] ?? 'Gelöscht') ?> · <?= date('d.m.Y H:i', strtotime($thread['created_at'])) ?></p>
    </div>
    <?php if ($canModerate): ?>
        <div class="btn-group">
            <form method="post" style="display:inline-block;">
                <input type="hidden" name="action" value="toggle_pin">
                <input type="hidden" name="state" value="<?= (int)$thread['pinned'] === 1 ? 'unpin' : 'pin' ?>">
                <button class="btn btn-secondary" type="submit">
                    <?= (int)$thread['pinned'] === 1 ? '📍 Unpinnen' : '📌 Anpinnen' ?>
                </button>
            </form>
            <form method="post" style="display:inline-block;">
                <input type="hidden" name="action" value="toggle_lock">
                <input type="hidden" name="state" value="<?= (int)$thread['locked'] === 1 ? 'unlock' : 'lock' ?>">
                <button class="btn <?= (int)$thread['locked'] === 1 ? 'btn-secondary' : 'btn-danger' ?>" type="submit">
                    <?= (int)$thread['locked'] === 1 ? '🔓 Entsperren' : '🔒 Sperren' ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <?php foreach ($posts as $index => $post): ?>
        <div class="forum-post" id="post-<?= (int)$post['id'] ?>">
            <?php if ($index === count($posts) - 1): ?>
                <span id="last"></span>
            <?php endif; ?>
            <div class="forum-post-head">
                <div class="avatar-circle small"><?= strtoupper(substr($post['author_name'] ?? '?', 0, 2)) ?></div>
                <div>
                    <div class="forum-post-author"><?= htmlspecialchars($post['author_name'] ?? 'Gelöscht') ?></div>
                    <div class="muted"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></div>
                </div>
            </div>
            <div class="forum-post-body">
                <?= nl2br(htmlspecialchars($post['body'])) ?>
            </div>
            <?php if ($index < count($posts) - 1): ?>
                <hr>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

    <div class="card" id="reply">
        <h2>Antworten</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!$canReply && !$canModerate): ?>
            <p class="muted">Du hast keine Berechtigung, zu antworten.</p>
        <?php elseif ((int)$thread['locked'] === 1 && !$canModerate): ?>
            <p class="muted">Dieser Thread ist aktuell gesperrt.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="reply">
                <div class="field-group">
                    <label for="body">Deine Antwort</label>
                    <textarea id="body" name="body" rows="5" required></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Antwort posten</button>
                <a class="btn btn-secondary" href="/forum/category.php?id=<?= (int)$thread['category_id'] ?>">Zurück</a>
            </form>
        <?php endif; ?>
    </div>
<?php
renderFooter();