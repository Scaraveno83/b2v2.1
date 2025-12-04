<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Stellt Tabelle und Indizes für Live-Co-Browsing-Anfragen bereit.
 */
function ensureLiveSupportTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_support_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        requested_by INT NOT NULL,
        assigned_to INT NULL,
        status ENUM('pending','accepted','scheduled','declined','completed') NOT NULL DEFAULT 'pending',
        scheduled_for DATETIME NULL,
        note TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ticket (ticket_id),
        INDEX idx_status (status),
        INDEX idx_assigned (assigned_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

/**
 * Holt die jüngste Live-Support-Anfrage für ein Ticket inklusive Benutzer-Namen.
 */
function latestLiveSupportRequest(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare("SELECT lsr.*, req.username AS requester_name, asg.username AS assignee_name
        FROM live_support_requests lsr
        LEFT JOIN users req ON lsr.requested_by = req.id
        LEFT JOIN users asg ON lsr.assigned_to = asg.id
        WHERE lsr.ticket_id = ?
        ORDER BY lsr.id DESC
        LIMIT 1");
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Listet vergangene Live-Support-Anfragen eines Tickets (neueste zuerst).
 */
function liveSupportHistory(PDO $pdo, int $ticketId, int $limit = 10): array
{
    $stmt = $pdo->prepare("SELECT lsr.*, req.username AS requester_name, asg.username AS assignee_name
        FROM live_support_requests lsr
        LEFT JOIN users req ON lsr.requested_by = req.id
        LEFT JOIN users asg ON lsr.assigned_to = asg.id
        WHERE lsr.ticket_id = ?
        ORDER BY lsr.id DESC
        LIMIT ?");
    $stmt->bindValue(1, $ticketId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Neuen Logeintrag für Live-Support-Aktivitäten erzeugen.
 */
function logLiveSupportAction(PDO $pdo, int $ticketId, ?int $userId, string $details): void
{
    $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
    $log->execute([$ticketId, $userId, 'live_support', $details]);
}

/**
 * Prüft, ob eine Anfrage noch aktiv ist (also nicht abgelehnt oder abgeschlossen).
 */
function isLiveSupportActive(?array $request): bool
{
    if (!$request) {
        return false;
    }
    return in_array($request['status'], ['pending', 'accepted', 'scheduled'], true);
}