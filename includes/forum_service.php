<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ensureForumTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        position INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_threads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author_id INT NOT NULL,
        pinned TINYINT(1) NOT NULL DEFAULT 0,
        locked TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category_id),
        INDEX idx_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        thread_id INT NOT NULL,
        author_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_thread (thread_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM forum_categories")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO forum_categories (title, description, position) VALUES (?,?,?)");
        $stmt->execute(['Ankündigungen', 'Offizielle News & Updates', 1]);
        $stmt->execute(['Allgemein', 'Diskussionen rund um das Panel', 2]);
        $stmt->execute(['Feedback', 'Ideen, Wünsche und Kritik', 3]);
    }
}

function fetchForumCategories(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT c.*, (
            SELECT COUNT(*) FROM forum_threads t WHERE t.category_id = c.id
        ) AS thread_count,
        (
            SELECT MAX(p.created_at)
            FROM forum_threads t
            JOIN forum_posts p ON p.thread_id = t.id
            WHERE t.category_id = c.id
        ) AS latest_post_at
        FROM forum_categories c
        ORDER BY c.position ASC, c.title ASC");

    return $stmt->fetchAll();
}

function fetchForumCategory(PDO $pdo, int $categoryId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM forum_categories WHERE id = ? LIMIT 1");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();

    return $category ?: null;
}

function fetchThreadsByCategory(PDO $pdo, int $categoryId): array
{
    $stmt = $pdo->prepare("SELECT
            t.*, u.username AS author_name,
            (SELECT COUNT(*) FROM forum_posts p WHERE p.thread_id = t.id) AS post_count,
            (SELECT MAX(p2.created_at) FROM forum_posts p2 WHERE p2.thread_id = t.id) AS last_post_at
        FROM forum_threads t
        LEFT JOIN users u ON u.id = t.author_id
        WHERE t.category_id = :category
        ORDER BY t.pinned DESC, t.updated_at DESC");
    $stmt->execute([':category' => $categoryId]);

    return $stmt->fetchAll();
}

function fetchThreadWithCategory(PDO $pdo, int $threadId): ?array
{
    $stmt = $pdo->prepare("SELECT t.*, c.title AS category_title, c.id AS category_id, u.username AS author_name
        FROM forum_threads t
        JOIN forum_categories c ON c.id = t.category_id
        LEFT JOIN users u ON u.id = t.author_id
        WHERE t.id = ? LIMIT 1");
    $stmt->execute([$threadId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetchPostsForThread(PDO $pdo, int $threadId): array
{
    $stmt = $pdo->prepare("SELECT p.*, u.username AS author_name
        FROM forum_posts p
        LEFT JOIN users u ON u.id = p.author_id
        WHERE p.thread_id = ?
        ORDER BY p.created_at ASC");
    $stmt->execute([$threadId]);

    return $stmt->fetchAll();
}

function createThreadWithPost(PDO $pdo, int $categoryId, int $authorId, string $title, string $body): int
{
    $pdo->beginTransaction();

    $stmtThread = $pdo->prepare("INSERT INTO forum_threads (category_id, title, author_id) VALUES (?,?,?)");
    $stmtThread->execute([$categoryId, $title, $authorId]);
    $threadId = (int)$pdo->lastInsertId();

    $stmtPost = $pdo->prepare("INSERT INTO forum_posts (thread_id, author_id, body) VALUES (?,?,?)");
    $stmtPost->execute([$threadId, $authorId, $body]);

    $pdo->commit();

    return $threadId;
}

function addPostToThread(PDO $pdo, int $threadId, int $authorId, string $body): void
{
    $stmt = $pdo->prepare("INSERT INTO forum_posts (thread_id, author_id, body) VALUES (?,?,?)");
    $stmt->execute([$threadId, $authorId, $body]);

    $pdo->prepare("UPDATE forum_threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$threadId]);
}

function toggleThreadLock(PDO $pdo, int $threadId, bool $lock): void
{
    $pdo->prepare("UPDATE forum_threads SET locked = ? WHERE id = ?")
        ->execute([$lock ? 1 : 0, $threadId]);
}

function toggleThreadPin(PDO $pdo, int $threadId, bool $pin): void
{
    $pdo->prepare("UPDATE forum_threads SET pinned = ? WHERE id = ?")
        ->execute([$pin ? 1 : 0, $threadId]);
}

function userCanModerateForum(array $user): bool
{
    return !empty($user['permissions']['can_moderate_forum']);
}