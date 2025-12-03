<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Stellt sicher, dass die Tabellen für das Nachrichtensystem existieren.
 */
function ensureMessageTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NULL,
        target_type ENUM('all','role','rank','user') NOT NULL DEFAULT 'all',
        target_value VARCHAR(255) NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        moderated TINYINT(1) NOT NULL DEFAULT 0,
        deleted_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_target_type (target_type),
        INDEX idx_sender (sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_reads (
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(message_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_deletions (
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        scope ENUM('inbox','sent') NOT NULL,
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(message_id, user_id, scope)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

/**
 * Liefert alle Nachrichten, die für den eingeloggten Nutzer sichtbar sind.
 */
function fetchInboxMessages(PDO $pdo, array $user): array
{
    $rankId = !empty($user['rank_id']) ? (int)$user['rank_id'] : -1;

    $stmt = $pdo->prepare("SELECT
        m.*,
        sender.username AS sender_name,
        targetRank.name AS target_rank_name,
        targetUser.username AS target_user_name
    FROM messages m
    LEFT JOIN users sender ON sender.id = m.sender_id
    LEFT JOIN ranks targetRank ON m.target_type = 'rank' AND targetRank.id = m.target_value
    LEFT JOIN users targetUser ON m.target_type = 'user' AND targetUser.id = m.target_value
    WHERE m.deleted_at IS NULL AND (
        m.target_type = 'all'
        OR (m.target_type = 'role' AND m.target_value = :role)
        OR (m.target_type = 'rank' AND m.target_value = :rankId)
        OR (m.target_type = 'user' AND m.target_value = :userId)
    )
    AND NOT EXISTS (
        SELECT 1 FROM message_deletions md
        WHERE md.message_id = m.id AND md.user_id = :userId AND md.scope = 'inbox'
    )
    ORDER BY m.created_at DESC");

    $stmt->execute([
        ':role'   => $user['role'],
        ':rankId' => $rankId,
        ':userId' => $user['id'],
    ]);

    return $stmt->fetchAll();
}

function fetchInboxStatus(PDO $pdo, array $user): array
{
    $rankId = !empty($user['rank_id']) ? (int)$user['rank_id'] : -1;

    $visibilityCondition = "m.deleted_at IS NULL AND (\n        m.target_type = 'all'\n        OR (m.target_type = 'role' AND m.target_value = :role)\n        OR (m.target_type = 'rank' AND m.target_value = :rankId)\n        OR (m.target_type = 'user' AND m.target_value = :userId)\n    )\n    AND NOT EXISTS (\n        SELECT 1 FROM message_deletions md\n        WHERE md.message_id = m.id AND md.user_id = :userId AND md.scope = 'inbox'\n    )";

    $params = [
        ':role' => $user['role'],
        ':rankId' => $rankId,
        ':userId' => $user['id'],
    ];

    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages m WHERE {$visibilityCondition} AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = :userId)");
    $unreadStmt->execute($params);
    $unreadCount = (int)$unreadStmt->fetchColumn();

    $latestStmt = $pdo->prepare(
        "SELECT m.id, m.subject, m.created_at, sender.username AS sender_name\n         FROM messages m\n         LEFT JOIN users sender ON sender.id = m.sender_id\n         WHERE {$visibilityCondition}\n         ORDER BY m.created_at DESC\n         LIMIT 1"
    );
    $latestStmt->execute($params);
    $latest = $latestStmt->fetch();

    return [
        'unread_count' => $unreadCount,
        'latest_message_id' => $latest['id'] ?? null,
        'latest_subject' => $latest['subject'] ?? null,
        'latest_sender' => $latest['sender_name'] ?? null,
        'latest_created_at' => $latest['created_at'] ?? null,
    ];
}

/**
 * Liefert alle Nachrichten, die der Nutzer selbst versendet hat.
 */
function fetchSentMessages(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT
        m.*,
        sender.username AS sender_name,
        targetRank.name AS target_rank_name,
        targetUser.username AS target_user_name
    FROM messages m
    LEFT JOIN users sender ON sender.id = m.sender_id
    LEFT JOIN ranks targetRank ON m.target_type = 'rank' AND targetRank.id = m.target_value
    LEFT JOIN users targetUser ON m.target_type = 'user' AND targetUser.id = m.target_value
    WHERE m.deleted_at IS NULL AND m.sender_id = :senderId
    AND NOT EXISTS (
        SELECT 1 FROM message_deletions md
        WHERE md.message_id = m.id AND md.user_id = :senderId AND md.scope = 'sent'
    )
    ORDER BY m.created_at DESC");

    $stmt->execute([':senderId' => $userId]);

    return $stmt->fetchAll();
}

/**
 * Liefert eine Übersicht aller Nachrichten (Admin-Blick).
 */
function fetchAllMessages(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT
        m.*, 
        sender.username AS sender_name,
        targetRank.name AS target_rank_name,
        targetUser.username AS target_user_name
    FROM messages m
    LEFT JOIN users sender ON sender.id = m.sender_id
    LEFT JOIN ranks targetRank ON m.target_type = 'rank' AND targetRank.id = m.target_value
    LEFT JOIN users targetUser ON m.target_type = 'user' AND targetUser.id = m.target_value
    WHERE m.deleted_at IS NULL
    ORDER BY m.created_at DESC");

    return $stmt->fetchAll();
}

/**
 * Speichert eine neue Nachricht.
 */
function createMessage(PDO $pdo, int $senderId, string $targetType, ?string $targetValue, string $subject, string $body): void
{
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, target_type, target_value, subject, body) VALUES (?,?,?,?,?)");
    $stmt->execute([$senderId, $targetType, $targetValue, $subject, $body]);
}

/**
 * Holt eine Nachricht, sofern der Nutzer Leserechte hat oder Absender ist.
 */
function fetchMessageForUser(PDO $pdo, array $user, int $messageId): ?array
{
    $rankId = !empty($user['rank_id']) ? (int)$user['rank_id'] : -1;

    $stmt = $pdo->prepare("SELECT
        m.*,
        sender.username AS sender_name,
        targetRank.name AS target_rank_name,
        targetUser.username AS target_user_name
    FROM messages m
    LEFT JOIN users sender ON sender.id = m.sender_id
    LEFT JOIN ranks targetRank ON m.target_type = 'rank' AND targetRank.id = m.target_value
    LEFT JOIN users targetUser ON m.target_type = 'user' AND targetUser.id = m.target_value
    WHERE m.id = :messageId AND m.deleted_at IS NULL AND (
        m.sender_id = :userId
        OR m.target_type = 'all'
        OR (m.target_type = 'role' AND m.target_value = :role)
        OR (m.target_type = 'rank' AND m.target_value = :rankId)
        OR (m.target_type = 'user' AND m.target_value = :userId)
    )
    LIMIT 1");

    $stmt->execute([
        ':messageId' => $messageId,
        ':userId' => $user['id'],
        ':role' => $user['role'],
        ':rankId' => $rankId,
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $scope = ((int)$row['sender_id'] === (int)$user['id']) ? 'sent' : 'inbox';
    if (isMessageDeletedForUser($pdo, $messageId, (int)$user['id'], $scope)) {
        return null;
    }

    return $row;
}

/**
 * Markiert eine Nachricht als gelesen.
 */
function markMessageRead(PDO $pdo, int $messageId, int $userId): void
{
    $stmt = $pdo->prepare("INSERT INTO message_reads (message_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP");
    $stmt->execute([$messageId, $userId]);
}

function fetchReadMap(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT message_id FROM message_reads WHERE user_id = ?");
    $stmt->execute([$userId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $map = [];
    foreach ($ids as $mid) {
        $map[(int)$mid] = true;
    }
    return $map;
}

function deleteMessage(PDO $pdo, int $messageId): void
{
    $stmt = $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$messageId]);
}

function deleteMessageForUser(PDO $pdo, int $messageId, int $userId, string $scope): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO message_deletions (message_id, user_id, scope) VALUES (?, ?, ?)"
        . " ON DUPLICATE KEY UPDATE deleted_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$messageId, $userId, $scope]);
}

function isMessageDeletedForUser(PDO $pdo, int $messageId, int $userId, string $scope): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM message_deletions WHERE message_id = ? AND user_id = ? AND scope = ? LIMIT 1");
    $stmt->execute([$messageId, $userId, $scope]);
    return (bool)$stmt->fetchColumn();
}

function moderateMessage(PDO $pdo, int $messageId, bool $flag): void
{
    $stmt = $pdo->prepare("UPDATE messages SET moderated = ? WHERE id = ?");
    $stmt->execute([$flag ? 1 : 0, $messageId]);
}

function messageTargetLabel(array $row): string
{
    switch ($row['target_type']) {
        case 'all':
            return 'Alle Benutzer';
        case 'role':
            return 'Rolle: ' . ($row['target_value'] ?? '');
        case 'rank':
            return 'Rang: ' . ($row['target_rank_name'] ?? ('#' . $row['target_value']));
        case 'user':
            return 'Direkt: ' . ($row['target_user_name'] ?? ('User #' . $row['target_value']));
        default:
            return 'Unbekannt';
    }
}

function messageStats(PDO $pdo): array
{
    $total = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE deleted_at IS NULL")->fetchColumn();
    $lastMessage = $pdo->query("SELECT subject, created_at FROM messages WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 1")->fetch();

    return [
        'total' => $total,
        'last_subject' => $lastMessage['subject'] ?? null,
        'last_created_at' => $lastMessage['created_at'] ?? null,
    ];
}