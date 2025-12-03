<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/news_service.php';

ensureNewsSchema($pdo);

$newsId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = $_SESSION['user'] ?? null;
$news = $newsId ? fetchNewsById($pdo, $newsId, $user) : null;

if (!$news) {
    http_response_code(404);
    renderHeader('News nicht gefunden', 'news');
    echo '<div class="card"><p class="muted">Diese News ist nicht sichtbar oder existiert nicht mehr.</p></div>';
    renderFooter();
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_comment';

    if ($action === 'add_comment' && isset($_POST['comment_body'])) {
        if (!$user) {
            $errors[] = 'Bitte melde dich an, um zu kommentieren.';
        } elseif (!hasPermission('can_comment_news')) {
            $errors[] = 'Keine Berechtigung zum Kommentieren.';
        } elseif (!$news['allow_comments']) {
            $errors[] = 'Kommentare wurden für diese News deaktiviert.';
        } else {
            $body = trim($_POST['comment_body']);
            if (mb_strlen($body) < 3) {
                $errors[] = 'Kommentar ist zu kurz.';
            } else {
                addNewsComment($pdo, $newsId, (int)$user['id'], $body);
                header('Location: /news/show.php?id=' . $newsId . '#comments');
                exit;
            }
        }
    } elseif (in_array($action, ['edit_comment', 'delete_comment'], true) && isset($_POST['comment_id'])) {
        $commentId = (int)$_POST['comment_id'];
        $existing = fetchNewsCommentById($pdo, $commentId);

        if (!$existing || (int)$existing['news_id'] !== $newsId) {
            $errors[] = 'Kommentar konnte nicht gefunden werden.';
        } elseif (!$user) {
            $errors[] = 'Bitte melde dich an.';
        } else {
            $isOwner = (int)$existing['author_id'] === (int)$user['id'];
            $canModerate = hasPermission('can_moderate_news');

            if (!$isOwner && !$canModerate) {
                $errors[] = 'Keine Berechtigung für diesen Kommentar.';
            } elseif ($action === 'edit_comment') {
                $body = trim($_POST['comment_body'] ?? '');
                if (mb_strlen($body) < 3) {
                    $errors[] = 'Kommentar ist zu kurz.';
                } else {
                    updateNewsComment($pdo, $commentId, $body);
                    header('Location: /news/show.php?id=' . $newsId . '#comments');
                    exit;
                }
            } elseif ($action === 'delete_comment') {
                deleteNewsComment($pdo, $commentId);
                header('Location: /news/show.php?id=' . $newsId . '#comments');
                exit;
            }
        }
    }
}

$comments = fetchNewsComments($pdo, $newsId);
$reactions = buildNewsReactionPayload($pdo, $newsId, $user['id'] ?? null);
$canReact = $user && hasPermission('can_react_news');
$canComment = $user && hasPermission('can_comment_news') && $news['allow_comments'];
$canModerateComments = $user && hasPermission('can_moderate_news');

renderHeader($news['title'], 'news');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">News &amp; Ankündigungen</p>
        <h1><?= htmlspecialchars($news['title']) ?></h1>
        <p class="muted">
            <?= htmlspecialchars($news['author_name'] ?? 'System') ?> ·
            <?= htmlspecialchars(date('d.m.Y H:i', strtotime($news['created_at']))) ?> ·
            <?= htmlspecialchars(getNewsAudienceLabel($news['visibility'])) ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/news/index.php">Zurück</a>
    </div>
</div>

<div class="card news-article" data-news-id="<?= (int)$news['id'] ?>">
    <div class="news-body"><?= formatNewsBody($pdo, $news['content']) ?></div>

    <div class="news-reactions" data-news-reaction-root>
        <div class="eyebrow">Reaktionen</div>
        <div class="reaction-row">
            <?php foreach (NEWS_REACTION_SET as $emoji): ?>
                <?php $count = $reactions['counts'][$emoji] ?? 0; ?>
                <button
                    class="reaction-button<?= in_array($emoji, $reactions['user'], true) ? ' is-active' : '' ?>"
                    type="button"
                    data-emoji="<?= htmlspecialchars($emoji) ?>"
                    data-news-reaction
                    <?= $canReact ? '' : 'disabled' ?>
                >
                    <span class="reaction-emoji"><?= htmlspecialchars($emoji) ?></span>
                    <span class="reaction-count" aria-live="polite"><?= (int)$count ?></span>
                </button>
            <?php endforeach; ?>
            <?php if (!$canReact): ?>
                <span class="muted">Reaktionen nur für berechtigte Benutzer.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card card--allow-overflow" id="comments">
    <div class="card-header">Kommentare</div>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$comments): ?>
        <p class="muted">Noch keine Kommentare.</p>
    <?php else: ?>
        <div class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <?php
                    $isAuthor = $user && ((int)$comment['author_id'] === (int)$user['id']);
                    $canManageComment = $isAuthor || $canModerateComments;
                ?>
                <div class="comment-item">
                    <div class="comment-meta">
                        <strong><?= htmlspecialchars($comment['author_name'] ?? 'User') ?></strong>
                        <span class="muted">· <?= htmlspecialchars(date('d.m.Y H:i', strtotime($comment['created_at']))) ?></span>
                    </div>
                    <div class="comment-body"><?= formatNewsBody($pdo, $comment['body']) ?></div>
                    <?php if ($canManageComment): ?>
                        <div class="comment-actions">
                            <details>
                                <summary>Bearbeiten</summary>
                                <form class="form" method="post">
                                    <input type="hidden" name="action" value="edit_comment">
                                    <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                                    <label for="comment_edit_<?= (int)$comment['id'] ?>" class="sr-only">Kommentar bearbeiten</label>
                                    <textarea
                                        id="comment_edit_<?= (int)$comment['id'] ?>"
                                        name="comment_body"
                                        rows="3"
                                        required
                                        data-mentionable="true"
                                        data-emoji-picker="true"
                                    ><?= htmlspecialchars($comment['body']) ?></textarea>
                                    <div class="form-actions">
                                        <button class="btn btn-primary" type="submit">Speichern</button>
                                    </div>
                                </form>
                            </details>
                            <form class="inline-form" method="post" onsubmit="return confirm('Kommentar wirklich löschen?');">
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                                <button class="btn btn-link btn-danger" type="submit">Löschen</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($canComment): ?>
        <form class="form" method="post">
            <input type="hidden" name="action" value="add_comment">
            <label for="comment_body">Antwort verfassen</label>
            <div class="input-with-suggestions">
                <textarea
                    id="comment_body"
                    name="comment_body"
                    rows="4"
                    required
                    data-mentionable="true"
                    data-emoji-picker="true"
                    placeholder="@Rang, @Partner oder @Mitarbeiter erwähnen und Feedback teilen..."
                ></textarea>
                <div class="mention-suggestions" data-mention-list hidden></div>
                <div class="emoji-toolbar" data-emoji-toolbar></div>
            </div>
            <div class="form-help">Mentions unterstützen Ränge, Partnernamen und Mitarbeiter.</div>
            <button class="btn btn-primary" type="submit">Kommentar posten</button>
        </form>
    <?php else: ?>
        <p class="muted">Kommentare sind für diese News deaktiviert oder du hast keine Berechtigung.</p>
    <?php endif; ?>
</div>
<?php
renderFooter();