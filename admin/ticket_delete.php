<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_delete_tickets');
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // zugehörige Einträge löschen
    $pdo->prepare("DELETE FROM ticket_comments WHERE ticket_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM ticket_logs WHERE ticket_id = ?")->execute([$id]);
    $attStmt = $pdo->prepare("SELECT stored_name FROM ticket_attachments WHERE ticket_id = ?");
    $attStmt->execute([$id]);
    $allA = $attStmt->fetchAll();
    $uploadDir = __DIR__ . '/../uploads/tickets';
    foreach ($allA as $a) {
        $file = $uploadDir . '/' . $a['stored_name'];
        if (is_file($file)) {
            @unlink($file);
        }
    }
    $pdo->prepare("DELETE FROM ticket_attachments WHERE ticket_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM tickets WHERE id = ?")->execute([$id]);
}
header("Location: /admin/tickets.php");
exit;
