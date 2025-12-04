<?php
require_once __DIR__ . '/permissions.php';

/**
 * Stellt sicher, dass alle Berechtigungs-Spalten in der ranks-Tabelle existieren.
 */
function ensureRankPermissionColumns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    foreach (array_keys(getAllPermissions()) as $permColumn) {
        try {
            $pdo->query("SELECT {$permColumn} FROM ranks LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE ranks ADD {$permColumn} TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
}

/**
 * Erzeuge die Session-Nutzerdaten inkl. dynamischer Berechtigungen.
 */
function ensureUserProfileColumns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo->query("SELECT avatar_path FROM users LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE users ADD avatar_path VARCHAR(255) NULL AFTER rank_id");
    }
}

function buildSessionUserData(PDO $pdo, array $user): array
{
    // Rang-Fallback basierend auf Rolle, falls kein Rang gesetzt ist
    if (empty($user['rank_id'])) {
        $fallbackRankName = null;
        if ($user['role'] === 'admin') {
            $fallbackRankName = 'Administrator';
        } elseif ($user['role'] === 'employee') {
            $fallbackRankName = 'Mitarbeiter';
        } elseif ($user['role'] === 'partner') {
            $fallbackRankName = 'Partner';
        }

        if ($fallbackRankName) {
            $stmtR = $pdo->prepare("SELECT * FROM ranks WHERE name = ? LIMIT 1");
            $stmtR->execute([$fallbackRankName]);
            $rankRow = $stmtR->fetch();
            if ($rankRow) {
                $user['rank_id'] = $rankRow['id'];
                $user['rank_name'] = $rankRow['name'];
                $upd = $pdo->prepare("UPDATE users SET rank_id = ? WHERE id = ?");
                $upd->execute([$rankRow['id'], $user['id']]);
                foreach (array_keys(getAllPermissions()) as $permKey) {
                    if (isset($rankRow[$permKey])) {
                        $user[$permKey] = $rankRow[$permKey];
                    }
                }
            }
        }
    }

    // Berechtigungen zusammenstellen
    $allPermKeys = array_keys(getAllPermissions());
    $permissions = [];
    foreach ($allPermKeys as $key) {
        $permissions[$key] = !empty($user[$key]);
    }

    // Admin-Auto-Fix: alle Rechte aktiv und Rang sicherstellen
    if ($user['role'] === 'admin') {
        $permissions = array_fill_keys($allPermKeys, true);

        $stmtAdm = $pdo->prepare("SELECT * FROM ranks WHERE name = ? LIMIT 1");
        $stmtAdm->execute(['Administrator']);
        $admRank = $stmtAdm->fetch();

        if (!$admRank) {
            $permColumns = implode(',', $allPermKeys);
            $permValues = implode(',', array_fill(0, count($allPermKeys), '1'));
            $pdo->exec(
                "INSERT INTO ranks (name, description, {$permColumns}) VALUES " .
                "('Administrator', 'Automatisch erzeugter Vollzugriffs-Rang (Admin-Auto-Fix).', {$permValues})"
            );
            $admRank = $pdo->query("SELECT * FROM ranks WHERE name = 'Administrator' LIMIT 1")->fetch();
        } else {
            $setParts = [];
            foreach ($allPermKeys as $pk) {
                $setParts[] = $pk . ' = 1';
            }
            $sqlUpdateAdm = "UPDATE ranks SET " . implode(',', $setParts) . " WHERE id = ?";
            $stmtUpAdm = $pdo->prepare($sqlUpdateAdm);
            $stmtUpAdm->execute([$admRank['id']]);
        }

        $user['rank_id'] = $admRank['id'];
        $user['rank_name'] = $admRank['name'];
        $updAdmUser = $pdo->prepare("UPDATE users SET rank_id = ? WHERE id = ?");
        $updAdmUser->execute([$admRank['id'], $user['id']]);
    }

    return [
        'id'          => $user['id'],
        'username'    => $user['username'],
        'avatar_path' => $user['avatar_path'] ?? null,
        'role'        => $user['role'],
        'rank_id'     => $user['rank_id'] ?? null,
        'rank_name'   => $user['rank_name'] ?? null,
        'permissions' => $permissions,
    ];
}

/**
 * Lädt den aktuellen Benutzer frisch aus der Datenbank und aktualisiert die Session.
 */
function refreshSessionUserFromDb(PDO $pdo): void
{
    if (!isset($_SESSION['user']['id'])) {
        return;
    }

    ensureUserProfileColumns($pdo);
    ensureRankPermissionColumns($pdo);

    static $refreshed = false;
    if ($refreshed) {
        return;
    }
    $refreshed = true;

    $stmt = $pdo->prepare("
        SELECT
            u.*,
            r.name AS rank_name,
            r.can_view_tickets,
            r.can_create_tickets,
            r.can_edit_tickets,
            r.can_delete_tickets,
            r.can_upload_files,
            r.can_delete_files,
            r.can_manage_ticket_categories,
            r.can_manage_users,
            r.can_manage_partners,
            r.can_change_settings,
            r.can_manage_calendar,
            r.can_access_admin,
            r.can_view_dashboard,
            r.can_send_messages,
            r.can_broadcast_messages,
            r.can_moderate_messages,
            r.can_manage_news,
            r.can_comment_news,
            r.can_react_news,
            r.can_moderate_news,
            r.can_view_forum,
            r.can_create_threads,
            r.can_reply_threads,
            r.can_moderate_forum,
            r.can_assign_ranks,
            r.can_manage_partner_services,
            r.can_log_partner_services,
            r.can_generate_partner_invoices,
            r.can_manage_warehouses,
            r.can_use_warehouses
        FROM users u
        LEFT JOIN ranks r ON u.rank_id = r.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        return;
    }

    $_SESSION['user'] = buildSessionUserData($pdo, $user);
}