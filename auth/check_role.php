<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/user_session.php';
require_once __DIR__ . '/../includes/calendar_service.php';

if (isset($_SESSION['user'])) {
    refreshSessionUserFromDb($pdo);
    applyAbsenceContext($pdo);
}

function checkRole($roles) {
    if (!isset($_SESSION['user'])) {
        header("Location: /login/login.php");
        exit;
    }
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        echo "Keine Berechtigung.";
        exit;
    }
}

function hasPermission($perm) {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['permissions']) || !is_array($_SESSION['user']['permissions'])) {
        return false;
    }
    return !empty($_SESSION['user']['permissions'][$perm]);
}

function requirePermission($perm) {
    if ($perm === 'can_access_admin' && isAbsentRestrictedArea('admin')) {
        http_response_code(423);
        echo "Der Adminbereich ist während deiner Abmeldung gesperrt.";
        exit;
    }

    if (!hasPermission($perm)) {
        http_response_code(403);
        echo "Zugriff verweigert (fehlende Berechtigung: " . htmlspecialchars($perm) . ").";
        exit;
    }
}

function applyAbsenceContext(PDO $pdo): void
{
    if (!isset($_SESSION['user'])) {
        return;
    }

    $_SESSION['user']['absence'] = currentAbsenceContext($pdo, $_SESSION['user']);
}

function isAbsentRestrictedArea(string $areaKey): bool
{
    if (empty($_SESSION['user']['absence']['active'])) {
        return false;
    }

    return in_array($areaKey, $_SESSION['user']['absence']['restricted_areas'] ?? [], true);
}

function requireAbsenceAccess(string $areaKey): void
{
    if (!isAbsentRestrictedArea($areaKey)) {
        return;
    }

    http_response_code(423);
    echo "Dieser Bereich ist während deiner Abmeldung nicht verfügbar.";
    exit;
}