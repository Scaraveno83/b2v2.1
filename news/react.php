<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/news_service.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

if (!hasPermission('can_react_news')) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

ensureNewsSchema($pdo);

$newsId = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
$emoji = trim($_POST['emoji'] ?? '');

$news = $newsId ? fetchNewsById($pdo, $newsId, $_SESSION['user']) : null;
if (!$news) {
    http_response_code(404);
    echo json_encode(['error' => 'News nicht gefunden']);
    exit;
}

try {
    $payload = toggleNewsReaction($pdo, $newsId, (int)$_SESSION['user']['id'], $emoji);
    echo json_encode([
        'ok' => true,
        'counts' => $payload['counts'],
        'user' => $payload['user'],
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}