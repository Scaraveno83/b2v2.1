<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_news');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/news_service.php';

ensureNewsSchema($pdo);

$errors = [];
$success = null;
$editing = null;
$formData = [
    'title' => '',
    'content' => '',
    'visibility' => 'public',
    'allow_comments' => true,
];

if (isset($_GET['edit'])) {
    $editing = fetchNewsForManagementById($pdo, (int)$_GET['edit']);
    if ($editing) {
        $formData = [
            'title' => $editing['title'],
            'content' => $editing['content'],
            'visibility' => $editing['visibility'],
            'allow_comments' => (bool)$editing['allow_comments'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'create';
    if ($mode === 'update' && isset($_POST['news_id'])) {
        $editing = fetchNewsForManagementById($pdo, (int)$_POST['news_id']);
    }

    if (in_array($mode, ['create', 'update'], true)) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $visibility = $_POST['visibility'] ?? 'public';
        $allowComments = isset($_POST['allow_comments']);

        $formData = [
            'title' => $title,
            'content' => $content,
            'visibility' => $visibility,
            'allow_comments' => $allowComments,
        ];

        if (mb_strlen($title) < 4) {
            $errors[] = 'Titel ist zu kurz.';
        }
        if (mb_strlen($content) < 10) {
            $errors[] = 'Text ist zu kurz.';
        }

        if (!$errors) {
            if ($mode === 'update' && isset($_POST['news_id'])) {
                $newsId = (int)$_POST['news_id'];
                updateNewsEntry($pdo, $newsId, $title, $content, $visibility, $allowComments);
                $editing = fetchNewsForManagementById($pdo, $newsId);
                $success = 'News wurde aktualisiert (#' . $newsId . ').';
            } else {
                $newsId = createNews($pdo, (int)$_SESSION['user']['id'], $title, $content, $visibility, $allowComments);
                $success = 'News wurde veröffentlicht (#' . $newsId . ').';
                $formData = [
                    'title' => '',
                    'content' => '',
                    'visibility' => 'public',
                    'allow_comments' => true,
                ];
            }
        }
    } elseif ($mode === 'toggle_comments' && isset($_POST['news_id'])) {
        $newsId = (int)$_POST['news_id'];
        $allow = isset($_POST['allow_comments']);
        updateNewsCommentsFlag($pdo, $newsId, $allow);
        $success = 'Kommentar-Einstellung aktualisiert.';
    } elseif ($mode === 'delete' && isset($_POST['news_id'])) {
        $newsId = (int)$_POST['news_id'];
        deleteNewsEntry($pdo, $newsId);
        $success = 'News wurde gelöscht (#' . $newsId . ').';
        if ($editing && (int)$editing['id'] === $newsId) {
            $editing = null;
            $formData = [
                'title' => '',
                'content' => '',
                'visibility' => 'public',
                'allow_comments' => true,
            ];
        }
    }
}

$allNews = fetchNewsForManagement($pdo);

renderHeader('News verwalten', 'admin');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>News &amp; Ankündigungen</h1>
        <p class="muted">Sichtbarkeiten (Öffentlich, Mitarbeiter, Partner, Intern) sowie Kommentarfreigaben steuern.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/news/index.php">Zur Übersicht</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="card card--allow-overflow">
        <div class="card-header"><?= $editing ? 'News bearbeiten' : 'Neue News verfassen' ?></div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form class="form" method="post">
            <input type="hidden" name="mode" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="news_id" value="<?= (int)$editing['id'] ?>">
            <?php endif; ?>
            <label for="title">Titel</label>
            <input
                id="title"
                name="title"
                type="text"
                required
                value="<?= htmlspecialchars($formData['title']) ?>"
                placeholder="Update, Announcement oder Release-Note">

            <label for="visibility">Sichtbarkeit</label>
            <select id="visibility" name="visibility">
                <option value="public" <?= $formData['visibility'] === 'public' ? 'selected' : '' ?>>Öffentlich</option>
                <option value="employees" <?= $formData['visibility'] === 'employees' ? 'selected' : '' ?>>Nur Mitarbeiter</option>
                <option value="partners" <?= $formData['visibility'] === 'partners' ? 'selected' : '' ?>>Nur Partner</option>
                <option value="internal" <?= $formData['visibility'] === 'internal' ? 'selected' : '' ?>>Mitarbeiter &amp; Partner</option>
            </select>

            <label for="content">Inhalt</label>
            <div class="input-with-suggestions">
                <textarea
                    id="content"
                    name="content"
                    rows="8"
                    required
                    data-mentionable="true"
                    data-emoji-picker="true"
                    placeholder="Mit @Rängen, @Partnern oder @Mitarbeitern erwähnen"
                ><?= htmlspecialchars($formData['content']) ?></textarea>
                <div class="mention-suggestions" data-mention-list hidden></div>
                <div class="emoji-toolbar" data-emoji-toolbar></div>
            </div>
            <div class="form-help">Mentions: @Rangname, @Partnername oder @Mitarbeiter werden automatisch hervorgehoben.</div>

            <label class="checkbox">
                <input type="checkbox" name="allow_comments" <?= $formData['allow_comments'] ? 'checked' : '' ?>>
                <span>Kommentare erlauben</span>
            </label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $editing ? 'News aktualisieren' : 'News veröffentlichen' ?></button>
                <?php if ($editing): ?>
                    <a class="btn btn-secondary" href="/news/manage.php">Abbrechen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">Bestehende News</div>
        <?php if (!$allNews): ?>
            <p class="muted">Noch keine Einträge.</p>
        <?php else: ?>
            <div class="news-list">
                <?php foreach ($allNews as $item): ?>
                    <div class="news-tile">
                        <div class="news-tile__meta">
                            <span class="badge badge-ghost"><?= htmlspecialchars(getNewsAudienceLabel($item['visibility'])) ?></span>
                            <span class="muted">#<?= (int)$item['id'] ?> · <?= htmlspecialchars(date('d.m.Y H:i', strtotime($item['created_at']))) ?></span>
                        </div>
                        <h3 class="news-tile__title"><?= htmlspecialchars($item['title']) ?></h3>
                        <p class="news-tile__excerpt">Autor: <?= htmlspecialchars($item['author_name'] ?? 'System') ?></p>
                        <form method="post" class="toggle-form">
                            <input type="hidden" name="mode" value="toggle_comments">
                            <input type="hidden" name="news_id" value="<?= (int)$item['id'] ?>">
                            <label class="checkbox">
                                <input type="checkbox" name="allow_comments" <?= $item['allow_comments'] ? 'checked' : '' ?>>
                                <span>Kommentare erlauben</span>
                            </label>
                            <div class="news-tile__actions">
                                <button class="btn btn-secondary" type="submit">Speichern</button>
                                <a class="btn btn-link" href="/news/show.php?id=<?= (int)$item['id'] ?>">Ansehen</a>
                                <a class="btn btn-link" href="/news/manage.php?edit=<?= (int)$item['id'] ?>">Bearbeiten</a>
                            </div>
                        </form>
                        <form method="post" class="inline-form" onsubmit="return confirm('News wirklich löschen?')">
                            <input type="hidden" name="mode" value="delete">
                            <input type="hidden" name="news_id" value="<?= (int)$item['id'] ?>">
                            <button class="btn btn-link btn-danger" type="submit">Löschen</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
renderFooter();