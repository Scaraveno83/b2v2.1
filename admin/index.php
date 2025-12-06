<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_view_dashboard');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

$counts = [
    'total'    => 0,
    'admin'    => 0,
    'employee' => 0,
    'partner'  => 0,
];

$stmt = $pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
foreach ($stmt as $row) {
    $counts['total'] += (int)$row['c'];
    if (isset($counts[$row['role']])) {
        $counts[$row['role']] = (int)$row['c'];
    }
}

// Ranganzahl
$rankCount = (int)$pdo->query("SELECT COUNT(*) FROM ranks")->fetchColumn();

renderHeader('Admin Dashboard', 'admin');
?>
<div class="card">
    <h2>Admin Dashboard</h2>
    <p class="muted">Überblick über Benutzerrollen, Ränge und Module.</p>
    <div class="grid grid-3">
        <div class="stat">
            <div class="stat-label">Gesamt-Benutzer</div>
            <div class="stat-value"><?= (int)$counts['total'] ?></div>
            <div class="stat-sub">Alle aktiven Accounts.</div>
        </div>
        <div class="stat">
            <div class="stat-label">Admins</div>
            <div class="stat-value"><?= (int)$counts['admin'] ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Mitarbeiter</div>
            <div class="stat-value"><?= (int)$counts['employee'] ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Partner</div>
            <div class="stat-value"><?= (int)$counts['partner'] ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Ränge</div>
            <div class="stat-value"><?= (int)$rankCount ?></div>
            <div class="stat-sub">Konfigurierbare Rechteprofile.</div>
        </div>
    </div>

    <h3 style="margin-top:18px;">Module</h3>
    <div class="grid grid-2">
        <?php if (hasPermission('can_manage_users')): ?>
            <a class="btn btn-secondary" href="/admin/users.php">Benutzerverwaltung</a>
        <?php endif; ?>
        <?php if (hasPermission('can_view_tickets')): ?>
            <a class="btn btn-secondary" href="/admin/tickets.php">Ticketsystem</a>
        <?php endif; ?>
        <?php if (hasPermission('can_manage_ticket_categories')): ?>
            <a class="btn btn-secondary" href="/admin/ticket_categories.php">Ticket-Kategorien</a>
        <?php endif; ?>
        <?php if (hasPermission('can_view_statistics')): ?>
            <a class="btn btn-secondary" href="/admin/statistics.php">Statistiken</a>
        <?php endif; ?>
        <?php if (hasPermission('can_upload_files')): ?>
            <a class="btn btn-secondary" href="/admin/files.php">Dateiverwaltung (Platzhalter)</a>
        <?php endif; ?>
        <?php if (hasPermission('can_send_messages')): ?>
            <a class="btn btn-secondary" href="/admin/messages.php">Nachrichten verwalten</a>
        <?php endif; ?>
        <?php if (hasPermission('can_manage_news')): ?>
            <a class="btn btn-secondary" href="/news/manage.php">News &amp; Ankündigungen</a>
        <?php endif; ?>
        <?php if (hasPermission('can_assign_ranks')): ?>
            <a class="btn btn-secondary" href="/admin/ranks.php">Ränge verwalten</a>
        <?php endif; ?>
        <?php if (hasPermission('can_manage_partners')): ?>
            <a class="btn btn-secondary" href="/admin/partners.php">Partner & Verträge</a>
        <?php endif; ?>
        <?php if (hasPermission('can_manage_partner_services')): ?>
            <a class="btn btn-secondary" href="/admin/partner_services.php">Services & Preise</a>
        <?php endif; ?>
        <?php if (hasPermission('can_generate_partner_invoices')): ?>
            <a class="btn btn-secondary" href="/admin/partner_billing.php">Partner-Abrechnung</a>
        <?php endif; ?>
        <?php if (hasPermission('can_manage_warehouses')): ?>
            <a class="btn btn-secondary" href="/admin/warehouses.php">Lagerverwaltung</a>
            <a class="btn btn-secondary" href="/admin/module_settings.php">Lager-Module steuern</a>
            <a class="btn btn-secondary" href="/admin/processing_recipes.php">Weiterverarbeitungsmodul</a>
        <?php endif; ?>
        <?php if (hasPermission('can_manage_calendar')): ?>
            <a class="btn btn-secondary" href="/admin/calendar.php">Kalender &amp; Abmeldungen</a>
        <?php endif; ?>
        <?php if (hasPermission('can_change_settings')): ?>
            <a class="btn btn-secondary" href="/admin/theme.php">Design & Branding</a>
        <?php endif; ?>
    </div>
</div>
<?php
renderFooter();
