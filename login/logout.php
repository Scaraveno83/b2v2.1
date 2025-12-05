<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_log.php';

$userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

if ($userId) {
    recordLogoutEvent($pdo, $userId, $_SERVER['REQUEST_URI'] ?? '/login/logout.php');
}

session_unset();
session_destroy();
header("Location: /index.php");
exit;
