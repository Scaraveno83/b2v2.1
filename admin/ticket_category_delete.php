<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_ticket_categories');
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // Optional: Kategorie in Tickets auf NULL setzen
    $pdo->prepare("UPDATE tickets SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM ticket_categories WHERE id = ?")->execute([$id]);
}
header("Location: /admin/ticket_categories.php");
exit;
