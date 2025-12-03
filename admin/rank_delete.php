<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_assign_ranks');
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // Administrator-Rang nicht löschbar
    $stmtName = $pdo->prepare("SELECT name FROM ranks WHERE id = ?");
    $stmtName->execute([$id]);
    $name = $stmtName->fetchColumn();
    if ($name === 'Administrator') {
        header("Location: /admin/ranks.php");
        exit;
    }

    // Prüfen, ob Rang von Benutzern verwendet wird
    $stmtUse = $pdo->prepare("SELECT COUNT(*) FROM users WHERE rank_id = ?");
    $stmtUse->execute([$id]);
    if ($stmtUse->fetchColumn() == 0) {
        $stmt = $pdo->prepare("DELETE FROM ranks WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header("Location: /admin/ranks.php");
exit;
