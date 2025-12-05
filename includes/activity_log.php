<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../auth/user_session.php';

function ensureActivityLogSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(50) NOT NULL,
        context TEXT NULL,
        ip_address VARCHAR(64) NULL,
        user_agent TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action_created (action, created_at),
        INDEX idx_user_created (user_id, created_at),
        CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function logActivity(PDO $pdo, string $action, ?string $context = null, ?int $userId = null): void
{
    ensureActivityLogSchema($pdo);

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, context, ip_address, user_agent) VALUES (?,?,?,?,?)");
    $stmt->execute([$userId, $action, $context, $ip, $agent]);
}

function touchUserActivity(PDO $pdo, int $userId, string $path): void
{
    ensureUserProfileColumns($pdo);

    $stmt = $pdo->prepare("UPDATE users SET last_activity_at = CURRENT_TIMESTAMP, last_activity_path = ? WHERE id = ?");
    $stmt->execute([mb_substr($path, 0, 255), $userId]);
}

function recordLoginEvent(PDO $pdo, int $userId, string $path): void
{
    ensureUserProfileColumns($pdo);

    $stmt = $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP, last_activity_at = CURRENT_TIMESTAMP, last_activity_path = ? WHERE id = ?");
    $stmt->execute([mb_substr($path, 0, 255), $userId]);

    logActivity($pdo, 'login', $path, $userId);
}

function recordLogoutEvent(PDO $pdo, int $userId, string $path): void
{
    ensureUserProfileColumns($pdo);

    $stmt = $pdo->prepare("UPDATE users SET last_logout_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$userId]);

    logActivity($pdo, 'logout', $path, $userId);
}

function recordPageActivity(PDO $pdo, int $userId, string $path): void
{
    touchUserActivity($pdo, $userId, $path);
    logActivity($pdo, 'page_view', $path, $userId);
}