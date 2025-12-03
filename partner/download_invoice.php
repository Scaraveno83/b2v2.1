<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/partner_service.php';

if (!isset($_SESSION['user'])) {
    header('Location: /login/login.php');
    exit;
}
requireAbsenceAccess('partner');

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM partner_invoices WHERE id = ? LIMIT 1");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();
if (!$invoice) {
    http_response_code(404);
    exit('Rechnung nicht gefunden.');
}

$userId = (int)$_SESSION['user']['id'];
$isAdmin = hasPermission('can_access_admin');
if (!$isAdmin && $invoice['partner_id'] !== $userId) {
    http_response_code(403);
    exit('Keine Berechtigung.');
}

$filePath = $invoice['file_path'] ?? '';
if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
if ($extension === 'html' || $extension === 'htm') {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="invoice_' . $invoiceId . '.html"');
} else {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="invoice_' . $invoiceId . '.txt"');
}

readfile($filePath);
exit;