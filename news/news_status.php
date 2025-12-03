<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/news_service.php';

header('Content-Type: application/json');

$scope = $_GET['scope'] ?? 'auto';
$user = $_SESSION['user'] ?? null;
ensureNewsSchema($pdo);

$ticker = fetchNewsTicker($pdo, $user, $scope, 1);
$latest = $ticker[0] ?? null;

if (!$latest) {
    echo json_encode(['latest_news_id' => null]);
    exit;
}

echo json_encode([
    'latest_news_id' => (int)$latest['id'],
    'latest_title' => $latest['title'],
    'latest_visibility' => $latest['visibility'],
    'latest_created_at' => $latest['created_at'],
]);