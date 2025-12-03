<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const NEWS_VISIBILITIES = ['public', 'employees', 'partners', 'internal'];
const NEWS_REACTION_SET = ['👍', '❤️', '🔥', '🎉', '⭐'];

function ensureNewsSchema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS news_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        visibility ENUM('public','employees','partners','internal') NOT NULL DEFAULT 'public',
        allow_comments TINYINT(1) NOT NULL DEFAULT 1,
        author_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_visibility_created (visibility, created_at),
        CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        news_id INT NOT NULL,
        author_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_news_comment (news_id, created_at),
        CONSTRAINT fk_news_comment_news FOREIGN KEY (news_id) REFERENCES news_posts(id) ON DELETE CASCADE,
        CONSTRAINT fk_news_comment_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        news_id INT NOT NULL,
        user_id INT NOT NULL,
        emoji VARCHAR(10) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reaction (news_id, user_id, emoji),
        INDEX idx_reaction_news (news_id),
        CONSTRAINT fk_news_reaction_news FOREIGN KEY (news_id) REFERENCES news_posts(id) ON DELETE CASCADE,
        CONSTRAINT fk_news_reaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function newsAudienceCondition(?array $user): array
{
    if (!$user) {
        return ['n.visibility = "public"', []];
    }

    $role = $user['role'] ?? 'guest';
    if ($role === 'admin') {
        return ['1=1', []];
    }

    $conditions = ['n.visibility = "public"'];
    $params = [];

    if (in_array($role, ['employee', 'partner'], true)) {
        $conditions[] = "n.visibility = 'internal'";
    }
    if ($role === 'employee') {
        $conditions[] = "n.visibility = 'employees'";
    }
    if ($role === 'partner') {
        $conditions[] = "n.visibility = 'partners'";
    }

    return [implode(' OR ', $conditions), $params];
}

function canSeeNewsVisibility(string $visibility, ?array $user): bool
{
    if ($visibility === 'public') {
        return true;
    }
    if (!$user) {
        return false;
    }
    $role = $user['role'] ?? 'guest';
    if ($role === 'admin') {
        return true;
    }

    if ($visibility === 'employees') {
        return $role === 'employee';
    }
    if ($visibility === 'partners') {
        return $role === 'partner';
    }
    if ($visibility === 'internal') {
        return in_array($role, ['employee', 'partner'], true);
    }

    return false;
}

function createNews(PDO $pdo, int $authorId, string $title, string $content, string $visibility, bool $allowComments): int
{
    ensureNewsSchema($pdo);

    if (!in_array($visibility, NEWS_VISIBILITIES, true)) {
        $visibility = 'public';
    }

    $stmt = $pdo->prepare("INSERT INTO news_posts (title, content, visibility, allow_comments, author_id) VALUES (?,?,?,?,?)");
    $stmt->execute([$title, $content, $visibility, $allowComments ? 1 : 0, $authorId]);

    return (int)$pdo->lastInsertId();
}

function updateNewsEntry(PDO $pdo, int $newsId, string $title, string $content, string $visibility, bool $allowComments): void
{
    ensureNewsSchema($pdo);

    if (!in_array($visibility, NEWS_VISIBILITIES, true)) {
        $visibility = 'public';
    }

    $stmt = $pdo->prepare("UPDATE news_posts SET title = ?, content = ?, visibility = ?, allow_comments = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$title, $content, $visibility, $allowComments ? 1 : 0, $newsId]);
}

function deleteNewsEntry(PDO $pdo, int $newsId): void
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("DELETE FROM news_posts WHERE id = ?");
    $stmt->execute([$newsId]);
}

function updateNewsCommentsFlag(PDO $pdo, int $newsId, bool $allow): void
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("UPDATE news_posts SET allow_comments = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$allow ? 1 : 0, $newsId]);
}

function fetchNewsFeed(PDO $pdo, ?array $user, int $limit = 12, int $offset = 0): array
{
    ensureNewsSchema($pdo);
    [$where, $params] = newsAudienceCondition($user);
    $sql = "SELECT n.*, u.username AS author_name FROM news_posts n LEFT JOIN users u ON u.id = n.author_id WHERE {$where} ORDER BY n.created_at DESC, n.id DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function fetchNewsForManagement(PDO $pdo): array
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->query("SELECT n.*, u.username AS author_name FROM news_posts n LEFT JOIN users u ON u.id = n.author_id ORDER BY n.created_at DESC, n.id DESC");
    return $stmt->fetchAll();
}

function fetchNewsForManagementById(PDO $pdo, int $newsId): ?array
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("SELECT n.*, u.username AS author_name FROM news_posts n LEFT JOIN users u ON u.id = n.author_id WHERE n.id = :id LIMIT 1");
    $stmt->bindValue(':id', $newsId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetchNewsById(PDO $pdo, int $newsId, ?array $user = null): ?array
{
    ensureNewsSchema($pdo);
    [$where, $params] = newsAudienceCondition($user);
    $stmt = $pdo->prepare("SELECT n.*, u.username AS author_name FROM news_posts n LEFT JOIN users u ON u.id = n.author_id WHERE n.id = :id AND ({$where}) LIMIT 1");
    $stmt->bindValue(':id', $newsId, PDO::PARAM_INT);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val);
    }
    $stmt->execute();
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetchNewsComments(PDO $pdo, int $newsId): array
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("SELECT c.*, u.username AS author_name FROM news_comments c LEFT JOIN users u ON u.id = c.author_id WHERE c.news_id = ? ORDER BY c.created_at ASC");
    $stmt->execute([$newsId]);
    return $stmt->fetchAll();
}

function fetchNewsCommentById(PDO $pdo, int $commentId): ?array
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("SELECT c.*, u.username AS author_name FROM news_comments c LEFT JOIN users u ON u.id = c.author_id WHERE c.id = :id LIMIT 1");
    $stmt->bindValue(':id', $commentId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    return $row ?: null;
}

function addNewsComment(PDO $pdo, int $newsId, int $authorId, string $body): void
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("INSERT INTO news_comments (news_id, author_id, body) VALUES (?,?,?)");
    $stmt->execute([$newsId, $authorId, $body]);
}

function updateNewsComment(PDO $pdo, int $commentId, string $body): void
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("UPDATE news_comments SET body = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$body, $commentId]);
}

function deleteNewsComment(PDO $pdo, int $commentId): void
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("DELETE FROM news_comments WHERE id = ?");
    $stmt->execute([$commentId]);
}

function toggleNewsReaction(PDO $pdo, int $newsId, int $userId, string $emoji): array
{
    ensureNewsSchema($pdo);
    if (!in_array($emoji, NEWS_REACTION_SET, true)) {
        throw new InvalidArgumentException('Ungültige Reaktion');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM news_reactions WHERE news_id = ? AND user_id = ? AND emoji = ? LIMIT 1");
    $stmt->execute([$newsId, $userId, $emoji]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $del = $pdo->prepare("DELETE FROM news_reactions WHERE id = ?");
        $del->execute([$existing]);
    } else {
        $ins = $pdo->prepare("INSERT IGNORE INTO news_reactions (news_id, user_id, emoji) VALUES (?,?,?)");
        $ins->execute([$newsId, $userId, $emoji]);
    }

    $pdo->commit();

    return buildNewsReactionPayload($pdo, $newsId, $userId);
}

function buildNewsReactionPayload(PDO $pdo, int $newsId, ?int $userId = null): array
{
    ensureNewsSchema($pdo);
    $stmt = $pdo->prepare("SELECT emoji, COUNT(*) AS c FROM news_reactions WHERE news_id = ? GROUP BY emoji");
    $stmt->execute([$newsId]);
    $counts = array_fill_keys(NEWS_REACTION_SET, 0);
    foreach ($stmt as $row) {
        $emoji = $row['emoji'];
        $counts[$emoji] = (int)$row['c'];
    }

    $userReactions = [];
    if ($userId !== null) {
        $stmtUser = $pdo->prepare("SELECT emoji FROM news_reactions WHERE news_id = ? AND user_id = ?");
        $stmtUser->execute([$newsId, $userId]);
        $userReactions = array_column($stmtUser->fetchAll(), 'emoji');
    }

    return [
        'counts' => $counts,
        'user' => $userReactions,
    ];
}

function latestVisibleNews(PDO $pdo, ?array $user): ?array
{
    ensureNewsSchema($pdo);
    [$where, $params] = newsAudienceCondition($user);
    $sql = "SELECT id, title, visibility, created_at FROM news_posts n WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val);
    }
    $stmt->execute();
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetchNewsTicker(PDO $pdo, ?array $user, string $scope = 'auto', int $limit = 6): array
{
    ensureNewsSchema($pdo);
    if ($scope === 'public') {
        $where = "n.visibility = 'public'";
        $params = [];
    } else {
        [$where, $params] = newsAudienceCondition($user);
    }

    $sql = "SELECT n.id, n.title, n.visibility, n.created_at FROM news_posts n WHERE {$where} ORDER BY n.created_at DESC, n.id DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function getNewsAudienceLabel(string $visibility): string
{
    switch ($visibility) {
        case 'employees':
            return 'Nur Mitarbeiter';
        case 'partners':
            return 'Nur Partner';
        case 'internal':
            return 'Mitarbeiter & Partner';
        default:
            return 'Öffentlich';
    }
}

function fetchMentionOptions(PDO $pdo): array
{
    ensureNewsSchema($pdo);
    $rankNames = $pdo->query("SELECT name FROM ranks ORDER BY name ASC")->fetchAll();
    $partnerNames = $pdo->query("SELECT username FROM users WHERE role = 'partner' ORDER BY username ASC")->fetchAll();
    $employeeNames = $pdo->query("SELECT username FROM users WHERE role = 'employee' ORDER BY username ASC")->fetchAll();

    return [
        'ranks' => array_map(fn($r) => $r['name'], $rankNames),
        'partners' => array_map(fn($p) => $p['username'], $partnerNames),
        'employees' => array_map(fn($e) => $e['username'], $employeeNames),
    ];
}

function formatNewsBody(PDO $pdo, string $body): string
{
    ensureNewsSchema($pdo);
    static $mentionCache = null;

    if ($mentionCache === null) {
        $options = fetchMentionOptions($pdo);
        $mentionCache = [
            'ranks' => array_map(fn($r) => mb_strtolower($r), $options['ranks']),
            'partners' => array_map(fn($p) => mb_strtolower($p), $options['partners']),
            'employees' => array_map(fn($e) => mb_strtolower($e), $options['employees']),
        ];
    }

    $safe = nl2br(htmlspecialchars($body));
    $pattern = '/@([\p{L}\d][\p{L}\d _\-]{1,48})/u';

    $mentionType = static function (string $value) use ($mentionCache): string {
        $normalized = mb_strtolower($value);
        if (in_array($normalized, $mentionCache['ranks'], true)) {
            return 'rank';
        }
        if (in_array($normalized, $mentionCache['partners'], true)) {
            return 'partner';
        }
        if (in_array($normalized, $mentionCache['employees'], true)) {
            return 'employee';
        }

        return 'generic';
    };

    return preg_replace_callback($pattern, function ($matches) use ($mentionType) {
        $label = $matches[1];
        $words = preg_split('/\s+/u', $label, -1, PREG_SPLIT_NO_EMPTY);
        $bestLabel = $words[0] ?? $label;
        $bestType = 'generic';
        $bestCount = 1;

        if ($words) {
            $wordCount = count($words);
            for ($i = 1; $i <= $wordCount; $i++) {
                $candidate = implode(' ', array_slice($words, 0, $i));
                $type = $mentionType($candidate);
                if ($type !== 'generic') {
                    $bestLabel = $candidate;
                    $bestType = $type;
                    $bestCount = $i;
                }
            }
        }

        $remaining = $words ? array_slice($words, $bestCount) : [];
        $suffix = $remaining ? ' ' . htmlspecialchars(implode(' ', $remaining)) : '';
        $class = 'mention mention-' . $bestType;

        return '<span class="' . $class . '" aria-label="Mention">@' . htmlspecialchars($bestLabel) . '</span>' . $suffix;
    }, $safe);
}