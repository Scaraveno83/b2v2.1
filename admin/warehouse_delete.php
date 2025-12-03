<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

ensureWarehouseSchema($pdo);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT id FROM warehouses WHERE id = ?');
$stmt->execute([$id]);
$exists = $stmt->fetch();

if ($exists) {
    $pdo->prepare('DELETE FROM warehouses WHERE id = ?')->execute([$id]);
}

header('Location: /admin/warehouses.php');
exit;