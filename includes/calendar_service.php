<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const CALENDAR_AREA_CATALOG = [
    'messages'         => 'Nachrichten & Postfach',
    'forum'            => 'Community Forum',
    'staff'            => 'Mitarbeiterbereich',
    'partner'          => 'Partnerbereich',
    'tickets'          => 'Tickets & Support',
    'services'         => 'Services & Preise',
    'warehouses'       => 'Lagerverwaltung',
    'partner_services' => 'Partner-Services',
    'admin'            => 'Adminbereich',
];

function ensureCalendarSchema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_absences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        reason VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_absence_user (user_id, start_at, end_at),
        CONSTRAINT fk_absence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_restrictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('employee','partner') NOT NULL,
        area_key VARCHAR(100) NOT NULL,
        area_label VARCHAR(255) NOT NULL,
        UNIQUE KEY uniq_role_area (role, area_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_release_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        absence_id INT NOT NULL,
        user_id INT NOT NULL,
        message VARCHAR(500) NULL,
        status ENUM('pending','approved','declined') DEFAULT 'pending',
        decided_at DATETIME NULL,
        decided_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_release_absence (absence_id, status),
        CONSTRAINT fk_release_absence FOREIGN KEY (absence_id) REFERENCES calendar_absences(id) ON DELETE CASCADE,
        CONSTRAINT fk_release_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_release_decider FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function calendarAreaCatalog(): array
{
    return CALENDAR_AREA_CATALOG;
}

function normalizeAreaKeys(array $areas): array
{
    $catalog = calendarAreaCatalog();
    return array_values(array_intersect(array_keys($catalog), $areas));
}

function saveRoleRestrictions(PDO $pdo, string $role, array $areas): void
{
    ensureCalendarSchema($pdo);
    if (!in_array($role, ['employee', 'partner'], true)) {
        return;
    }

    $areas = normalizeAreaKeys($areas);

    $pdo->prepare("DELETE FROM calendar_restrictions WHERE role = ?")->execute([$role]);

    if (!$areas) {
        return;
    }

    $catalog = calendarAreaCatalog();
    $stmt = $pdo->prepare("INSERT INTO calendar_restrictions (role, area_key, area_label) VALUES (?,?,?)");
    foreach ($areas as $areaKey) {
        $stmt->execute([$role, $areaKey, $catalog[$areaKey] ?? $areaKey]);
    }
}

function getRoleRestrictions(PDO $pdo, string $role): array
{
    ensureCalendarSchema($pdo);
    $stmt = $pdo->prepare("SELECT area_key FROM calendar_restrictions WHERE role = ?");
    $stmt->execute([$role]);
    return array_column($stmt->fetchAll(), 'area_key');
}

function createAbsenceEntry(PDO $pdo, int $userId, DateTimeInterface $startAt, DateTimeInterface $endAt, string $reason = ''): int
{
    ensureCalendarSchema($pdo);

    $stmt = $pdo->prepare("INSERT INTO calendar_absences (user_id, start_at, end_at, reason) VALUES (?,?,?,?)");
    $stmt->execute([
        $userId,
        $startAt->format('Y-m-d H:i:s'),
        $endAt->format('Y-m-d H:i:s'),
        $reason !== '' ? mb_substr($reason, 0, 255) : null,
    ]);

    return (int)$pdo->lastInsertId();
}

function deleteAbsenceEntry(PDO $pdo, int $absenceId, int $userId): void
{
    ensureCalendarSchema($pdo);
    $stmt = $pdo->prepare("DELETE FROM calendar_absences WHERE id = ? AND user_id = ?");
    $stmt->execute([$absenceId, $userId]);
}

function findUserAbsence(PDO $pdo, int $absenceId, int $userId): ?array
{
    ensureCalendarSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM calendar_absences WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$absenceId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetchUserAbsences(PDO $pdo, int $userId): array
{
    ensureCalendarSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM calendar_absences WHERE user_id = ? ORDER BY start_at DESC, id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function fetchAbsenceOverview(PDO $pdo, int $limit = 25): array
{
    ensureCalendarSchema($pdo);
    $stmt = $pdo->prepare("SELECT a.*, u.username, u.role FROM calendar_absences a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.start_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getActiveAbsence(PDO $pdo, int $userId): ?array
{
    ensureCalendarSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM calendar_absences WHERE user_id = ? AND start_at <= NOW() AND end_at >= NOW() ORDER BY start_at ASC LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getPendingReleaseRequest(PDO $pdo, int $absenceId, int $userId): ?array
{
    ensureCalendarSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM calendar_release_requests WHERE absence_id = ? AND user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$absenceId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function createReleaseRequest(PDO $pdo, int $absenceId, int $userId, string $message = ''): int
{
    ensureCalendarSchema($pdo);

    $existing = getPendingReleaseRequest($pdo, $absenceId, $userId);
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $pdo->prepare("INSERT INTO calendar_release_requests (absence_id, user_id, message) VALUES (?,?,?)");
    $stmt->execute([
        $absenceId,
        $userId,
        $message !== '' ? mb_substr($message, 0, 500) : null,
    ]);

    return (int)$pdo->lastInsertId();
}

function fetchReleaseRequests(PDO $pdo, string $status = 'pending', int $limit = 50): array
{
    ensureCalendarSchema($pdo);
    $sql = "SELECT r.*, u.username, u.role, a.start_at, a.end_at, a.reason, d.username AS decided_by_username
            FROM calendar_release_requests r
            LEFT JOIN users u ON u.id = r.user_id
            LEFT JOIN calendar_absences a ON a.id = r.absence_id
            LEFT JOIN users d ON d.id = r.decided_by
            WHERE (:status IS NULL OR r.status = :status)
            ORDER BY r.created_at DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':status', $status === '' ? null : $status);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function approveReleaseRequest(PDO $pdo, int $requestId, int $deciderId): bool
{
    ensureCalendarSchema($pdo);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM calendar_release_requests WHERE id = ? FOR UPDATE");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request || $request['status'] !== 'pending') {
        $pdo->rollBack();
        return false;
    }

    $pdo->prepare("UPDATE calendar_release_requests SET status = 'approved', decided_at = NOW(), decided_by = ? WHERE id = ?")
        ->execute([$deciderId, $requestId]);

    $pdo->prepare("UPDATE calendar_absences SET end_at = NOW() WHERE id = ?")
        ->execute([(int)$request['absence_id']]);

    $pdo->commit();
    return true;
}

function declineReleaseRequest(PDO $pdo, int $requestId, int $deciderId): bool
{
    ensureCalendarSchema($pdo);

    $stmt = $pdo->prepare("UPDATE calendar_release_requests SET status = 'declined', decided_at = NOW(), decided_by = ? WHERE id = ? AND status = 'pending'");
    $stmt->execute([$deciderId, $requestId]);

    return $stmt->rowCount() > 0;
}

function currentAbsenceContext(PDO $pdo, array $user): array
{
    $role = $user['role'] ?? 'guest';
    if (!in_array($role, ['employee', 'partner'], true)) {
        return [
            'active' => false,
            'restricted_areas' => [],
            'entry' => null,
        ];
    }

    $active = getActiveAbsence($pdo, (int)$user['id']);
    return [
        'active' => (bool)$active,
        'restricted_areas' => $active ? getRoleRestrictions($pdo, $role) : [],
        'entry' => $active ? [
            'start_at' => $active['start_at'],
            'end_at' => $active['end_at'],
            'reason' => $active['reason'],
        ] : null,
    ];
}