<?php
require_once __DIR__ . '/auth/check_role.php';
checkRole(['admin', 'employee', 'partner']);
require_once __DIR__ . '/includes/message_service.php';

header('Content-Type: application/json');

try {
    ensureMessageTables($pdo);
    $user = $_SESSION['user'];

    $status = fetchInboxStatus($pdo, $user);

    echo json_encode($status);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Status konnte nicht geladen werden.']);
}