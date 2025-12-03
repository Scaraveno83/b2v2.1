<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/news_service.php';

header('Content-Type: application/json');

try {
    $options = fetchMentionOptions($pdo);
    echo json_encode([
        'ok' => true,
        'ranks' => $options['ranks'],
        'partners' => $options['partners'],
        'employees' => $options['employees'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}