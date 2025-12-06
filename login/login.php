<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../auth/user_session.php';
require_once __DIR__ . '/../includes/warehouse_service.php';
require_once __DIR__ . '/../includes/activity_log.php';

$error = "";

// users-Tabelle anlegen, falls sie fehlt
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    role ENUM('admin','employee','partner') NOT NULL DEFAULT 'employee',
    rank_id INT NULL,
    avatar_path VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// rank_id-Spalte sicherstellen (falls alte Installationen)
try {
    $pdo->query("SELECT rank_id FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD rank_id INT NULL");
}

// avatar_path-Spalte sicherstellen
try {
    $pdo->query("SELECT avatar_path FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD avatar_path VARCHAR(255) NULL AFTER rank_id");
}

// Migration: alte JSON-basierte ranks-Tabelle erkennen und ggf. droppen
try {
    $pdo->query("SELECT permissions FROM ranks LIMIT 1");
    $pdo->exec("DROP TABLE ranks");
} catch (PDOException $e) {
    // ignorieren
}

// ranks-Tabelle (TinyInt-basierte Rechte) anlegen
$pdo->exec("CREATE TABLE IF NOT EXISTS ranks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    can_view_tickets TINYINT(1) NOT NULL DEFAULT 0,
    can_create_tickets TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_tickets TINYINT(1) NOT NULL DEFAULT 0,
    can_delete_tickets TINYINT(1) NOT NULL DEFAULT 0,
    can_upload_files TINYINT(1) NOT NULL DEFAULT 0,
    can_delete_files TINYINT(1) NOT NULL DEFAULT 0,
    can_manage_ticket_categories TINYINT(1) NOT NULL DEFAULT 0,
    can_manage_users TINYINT(1) NOT NULL DEFAULT 0,
    can_manage_partners TINYINT(1) NOT NULL DEFAULT 0,
    can_change_settings TINYINT(1) NOT NULL DEFAULT 0,
    can_access_admin TINYINT(1) NOT NULL DEFAULT 0,
    can_view_dashboard TINYINT(1) NOT NULL DEFAULT 0,
    can_send_messages TINYINT(1) NOT NULL DEFAULT 0,
    can_broadcast_messages TINYINT(1) NOT NULL DEFAULT 0,
    can_moderate_messages TINYINT(1) NOT NULL DEFAULT 0,
    can_view_forum TINYINT(1) NOT NULL DEFAULT 0,
    can_create_threads TINYINT(1) NOT NULL DEFAULT 0,
    can_reply_threads TINYINT(1) NOT NULL DEFAULT 0,
    can_moderate_forum TINYINT(1) NOT NULL DEFAULT 0,
    can_assign_ranks TINYINT(1) NOT NULL DEFAULT 0,
    can_manage_warehouses TINYINT(1) NOT NULL DEFAULT 0,
    can_use_warehouses TINYINT(1) NOT NULL DEFAULT 0,
    can_view_statistics TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// can_broadcast_messages-Spalte sicherstellen (Migration älterer Installationen)
try {
    $pdo->query("SELECT can_broadcast_messages FROM ranks LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE ranks ADD can_broadcast_messages TINYINT(1) NOT NULL DEFAULT 0");
}

// Forum-bezogene Spalten sicherstellen
foreach (['can_view_forum', 'can_create_threads', 'can_reply_threads', 'can_moderate_forum'] as $forumColumn) {
    try {
        $pdo->query("SELECT {$forumColumn} FROM ranks LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE ranks ADD {$forumColumn} TINYINT(1) NOT NULL DEFAULT 0");
    }
}

// Lager-Tabellen anlegen
ensureWarehouseSchema($pdo);
ensureRankPermissionColumns($pdo);

// Standard-Ränge anlegen, falls keine vorhanden
$countRanks = (int)$pdo->query("SELECT COUNT(*) FROM ranks")->fetchColumn();
if ($countRanks === 0) {
    // Administrator - alle Rechte
    $pdo->exec("INSERT INTO ranks
        (name, description,
         can_view_tickets,
         can_create_tickets,
         can_edit_tickets,
         can_delete_tickets,
         can_upload_files,
         can_delete_files,
         can_manage_ticket_categories,
         can_manage_users,
         can_manage_partners,
         can_change_settings,
         can_access_admin,
         can_view_dashboard,
         can_send_messages,
         can_broadcast_messages,
         can_moderate_messages,
         can_view_forum,
         can_create_threads,
         can_reply_threads,
         can_moderate_forum,
         can_assign_ranks,
         can_manage_warehouses,
         can_use_warehouses,
         can_view_statistics)
        VALUES (
         'Administrator',
         'Voller Zugriff auf alle Funktionen.',
         1,1,1,1,
         1,1,
         1,
         1,1,
         1,
         1,
         1,
         1,
         1,
         1,1,1,1,
         1,
         1,
         1,
         1,
         1
        )");

    // Mitarbeiter
    $pdo->exec("INSERT INTO ranks
        (name, description,
         can_view_tickets,
         can_create_tickets,
         can_edit_tickets,
         can_delete_tickets,
         can_upload_files,
         can_delete_files,
         can_manage_ticket_categories,
         can_manage_users,
         can_manage_partners,
         can_change_settings,
         can_access_admin,
         can_view_dashboard,
         can_send_messages,
         can_broadcast_messages,
         can_moderate_messages,
         can_view_forum,
         can_create_threads,
         can_reply_threads,
         can_moderate_forum,
         can_assign_ranks,
         can_manage_warehouses,
         can_use_warehouses,
         can_view_statistics)
        VALUES (
         'Mitarbeiter',
         'Standard-Mitarbeiter mit typischen Rechten.',
         0,1,0,0,
         1,0,
         0,
         0,0,
         0,
         0,
         1,
         1,
         0,
         0,
         1,1,1,0,
         0,
         0,
         1,
         0
        )");

    // Partner
    $pdo->exec("INSERT INTO ranks
        (name, description,
         can_view_tickets,
         can_create_tickets,
         can_edit_tickets,
         can_delete_tickets,
         can_upload_files,
         can_delete_files,
         can_manage_ticket_categories,
         can_manage_users,
         can_manage_partners,
         can_change_settings,
         can_access_admin,
         can_view_dashboard,
         can_send_messages,
         can_broadcast_messages,
         can_moderate_messages,
         can_view_forum,
         can_create_threads,
         can_reply_threads,
         can_moderate_forum,
         can_assign_ranks,
         can_manage_warehouses,
         can_use_warehouses,
         can_view_statistics)
        VALUES (
         'Partner',
         'Externer Partner mit eingeschränktem Zugriff.',
         0,1,0,0,
         0,0,
         0,
         0,0,
         0,
         0,
         1,
         1,
         0,
         0,
         1,1,1,0,
         0,
         0,
         0,
         0
        )");

    // Support
    $pdo->exec("INSERT INTO ranks
        (name, description,
         can_view_tickets,
         can_create_tickets,
         can_edit_tickets,
         can_delete_tickets,
         can_upload_files,
         can_delete_files,
         can_manage_ticket_categories,
         can_manage_users,
         can_manage_partners,
         can_change_settings,
         can_access_admin,
         can_view_dashboard,
         can_send_messages,
         can_broadcast_messages,
         can_moderate_messages,
         can_view_forum,
         can_create_threads,
         can_reply_threads,
         can_moderate_forum,
         can_assign_ranks,
         can_manage_warehouses,
         can_use_warehouses,
         can_view_statistics)
        VALUES (
         'Support',
         'Support-Rolle mit Fokus auf Tickets & Moderation.',
         1,1,1,0,
         1,0,
         1,
         0,0,
         0,
         1,
         1,
         1,
         1,
         0,
         1,1,1,1,
         0,
         0,
         1,
         0
        )");
}

// Standard-Admin-Benutzer scaraveno anlegen, falls nicht vorhanden
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute(['scaraveno']);
if (!$stmt->fetch()) {
    $adminHash = password_hash('15118329112006', PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,?)");
    $ins->execute(['scaraveno', 'admin@example.com', $adminHash, 'admin']);
}

// Administrator-Rang ID ermitteln
$stmtRank = $pdo->prepare("SELECT id FROM ranks WHERE name = ?");
$stmtRank->execute(['Administrator']);
$adminRankId = $stmtRank->fetchColumn();
if ($adminRankId) {
    $upd = $pdo->prepare("UPDATE users SET rank_id = ? WHERE username = ?");
    $upd->execute([$adminRankId, 'scaraveno']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

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
            r.can_use_warehouses,
            r.can_view_statistics
        FROM users u
        LEFT JOIN ranks r ON u.rank_id = r.id
        WHERE u.username = ?
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = buildSessionUserData($pdo, $user);

        recordLoginEvent($pdo, (int)$user['id'], $_SERVER['REQUEST_URI'] ?? '/login/login.php');

        header("Location: /index.php");
        exit;
    } else {
        $error = "Login fehlgeschlagen.";
    }
}

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Login', 'login');
?>
<div class="card">
    <h2>Login</h2>
    <p class="muted">Melde dich mit deinem Konto an, um das Panel zu nutzen.</p>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="username">Benutzername</label>
            <input id="username" name="username" required>
        </div>
        <div class="field-group">
            <label for="password">Passwort</label>
            <input id="password" type="password" name="password" required>
        </div>
        <button class="btn btn-primary" type="submit">Einloggen</button>
        <a class="btn btn-secondary" href="/index.php">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();
