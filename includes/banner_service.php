<?php
require_once __DIR__ . '/../config/db.php';

function ensureBannerSchema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $dbNameStmt = $pdo->query('SELECT DATABASE()');
    $database = $dbNameStmt->fetchColumn();

    $tableExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $tableExists->execute([$database, 'homepage_banners']);

    if (!$tableExists->fetchColumn()) {
        $pdo->exec(
            "CREATE TABLE homepage_banners (" .
            "id INT AUTO_INCREMENT PRIMARY KEY," .
            "title VARCHAR(120) NULL," .
            "image_path VARCHAR(255) NOT NULL," .
            "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function fetchHomepageBanners(PDO $pdo, int $limit = 20): array
{
    ensureBannerSchema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM homepage_banners ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function createHomepageBanner(PDO $pdo, string $title, string $imagePath): void
{
    ensureBannerSchema($pdo);
    $stmt = $pdo->prepare('INSERT INTO homepage_banners (title, image_path) VALUES (?, ?)');
    $stmt->execute([$title, $imagePath]);
}

function deleteHomepageBanner(PDO $pdo, int $id): ?array
{
    ensureBannerSchema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM homepage_banners WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $banner = $stmt->fetch();

    if ($banner) {
        $deleteStmt = $pdo->prepare('DELETE FROM homepage_banners WHERE id = ? LIMIT 1');
        $deleteStmt->execute([$id]);
        return $banner;
    }

    return null;
}