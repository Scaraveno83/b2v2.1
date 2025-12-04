<?php
require_once __DIR__ . '/../config/db.php';

$needed = [
    'can_view_tickets',
    'can_create_tickets',
    'can_edit_tickets',
    'can_delete_tickets',
    'can_upload_files',
    'can_delete_files',
    'can_manage_ticket_categories',
    'can_manage_users',
    'can_manage_partners',
    'can_change_settings',
    'can_access_admin',
    'can_view_dashboard',
    'can_send_messages',
    'can_broadcast_messages',
    'can_moderate_messages',
    'can_view_forum',
    'can_create_threads',
    'can_reply_threads',
    'can_moderate_forum',
    'can_assign_ranks',
    'can_manage_warehouses',
    'can_use_warehouses'
];

// existierende Spalten ermitteln
$cols = [];
$stmt = $pdo->query("SHOW COLUMNS FROM ranks");
foreach ($stmt as $row) {
    $cols[] = $row['Field'];
}

// fehlende Spalten hinzufügen
foreach ($needed as $column) {
    if (!in_array($column, $cols)) {
        echo "➕ Hinzufügen: $column<br>";
        $pdo->exec("ALTER TABLE ranks ADD COLUMN $column TINYINT(1) NOT NULL DEFAULT 0");
    } else {
        echo "✔ Vorhanden: $column<br>";
    }
}

// Administrator vollständig freischalten
$pdo->exec("
UPDATE ranks SET
    can_view_tickets = 1,
    can_create_tickets = 1,
    can_edit_tickets = 1,
    can_delete_tickets = 1,
    can_upload_files = 1,
    can_delete_files = 1,
    can_manage_ticket_categories = 1,
    can_manage_users = 1,
    can_manage_partners = 1,
    can_change_settings = 1,
    can_access_admin = 1,
    can_view_dashboard = 1,
    can_send_messages = 1,
    can_broadcast_messages = 1,
    can_moderate_messages = 1,
    can_view_forum = 1,
    can_create_threads = 1,
    can_reply_threads = 1,
    can_moderate_forum = 1,
    can_assign_ranks = 1,
    can_manage_warehouses = 1,
    can_use_warehouses = 1
WHERE name = 'Administrator'
");

echo "<hr><b>FERTIG!</b> Rang 'Administrator' hat jetzt alle Rechte.";
