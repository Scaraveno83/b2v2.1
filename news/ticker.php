<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/news_service.php';

header('Content-Type: application/json');

$scope = $_GET['scope'] ?? 'auto';
$limit = isset($_GET['limit']) ? max(1, min(12, (int)$_GET['limit'])) : 6;
$user = $_SESSION['user'] ?? null;

ensureNewsSchema($pdo);
$items = fetchNewsTicker($pdo, $user, $scope, $limit);

$payload = array_map(function ($row) {
    return [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'visibility' => $row['visibility'],
        'audience_label' => getNewsAudienceLabel($row['visibility']),
        'created_at' => $row['created_at'],
    ];
}, $items);

echo json_encode(['items' => $payload]);