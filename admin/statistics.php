<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_view_statistics');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/activity_log.php';

ensureActivityLogSchema($pdo);
ensureUserProfileColumns($pdo);

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function fetchCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function percentage(int $value, int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return (int)round(($value / $total) * 100);
}

$userCounts = [
    'total'    => 0,
    'admin'    => 0,
    'employee' => 0,
    'partner'  => 0,
];

$stmtUsers = $pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
foreach ($stmtUsers as $row) {
    $userCounts['total'] += (int)$row['c'];
    if (isset($userCounts[$row['role']])) {
        $userCounts[$row['role']] = (int)$row['c'];
    }
}

$activeUsersCount = fetchCount($pdo, "SELECT COUNT(*) FROM users WHERE last_activity_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$recentLogins = $pdo->query("SELECT username, last_login_at, last_logout_at, last_activity_at, last_activity_path FROM users ORDER BY COALESCE(last_login_at, created_at) DESC LIMIT 10")->fetchAll();

$loginHistory = [];
$recentPageViews = [];
$pageViewSummary = [];

if (tableExists($pdo, 'activity_logs')) {
    $loginHistoryStmt = $pdo->query("SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE al.action IN ('login','logout') ORDER BY al.created_at DESC LIMIT 12");
    $loginHistory = $loginHistoryStmt->fetchAll();

    $recentPageViewsStmt = $pdo->query("SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE al.action = 'page_view' ORDER BY al.created_at DESC LIMIT 12");
    $recentPageViews = $recentPageViewsStmt->fetchAll();

    $pageViewSummaryStmt = $pdo->query("SELECT context AS path, COUNT(*) AS hits, MAX(created_at) AS last_seen FROM activity_logs WHERE action = 'page_view' GROUP BY context ORDER BY hits DESC LIMIT 8");
    $pageViewSummary = $pageViewSummaryStmt->fetchAll();
}

$ticketsByStatus = [
    'open'        => 0,
    'in_progress' => 0,
    'waiting'     => 0,
    'closed'      => 0,
];

if (tableExists($pdo, 'tickets')) {
    $ticketStatusStmt = $pdo->query("SELECT status, COUNT(*) AS c FROM tickets GROUP BY status");
    foreach ($ticketStatusStmt as $row) {
        $ticketsByStatus[$row['status']] = (int)$row['c'];
    }
}

$ticketTotal = array_sum($ticketsByStatus);
$messageCount = tableExists($pdo, 'messages') ? fetchCount($pdo, "SELECT COUNT(*) FROM messages WHERE deleted_at IS NULL") : 0;
$newsPostsCount = tableExists($pdo, 'news_posts') ? fetchCount($pdo, "SELECT COUNT(*) FROM news_posts") : 0;
$newsCommentCount = tableExists($pdo, 'news_comments') ? fetchCount($pdo, "SELECT COUNT(*) FROM news_comments") : 0;
$newsReactionCount = tableExists($pdo, 'news_reactions') ? fetchCount($pdo, "SELECT COUNT(*) FROM news_reactions") : 0;

$warehouseCount = tableExists($pdo, 'warehouses') ? fetchCount($pdo, "SELECT COUNT(*) FROM warehouses") : 0;
$warehouseLogCount = tableExists($pdo, 'warehouse_logs') ? fetchCount($pdo, "SELECT COUNT(*) FROM warehouse_logs") : 0;
$warehouseHistory = [];
if (tableExists($pdo, 'warehouse_logs') && tableExists($pdo, 'warehouses') && tableExists($pdo, 'items')) {
    $historyStmt = $pdo->query("SELECT wl.*, w.name AS warehouse_name, i.name AS item_name, u.username FROM warehouse_logs wl LEFT JOIN warehouses w ON wl.warehouse_id = w.id LEFT JOIN items i ON wl.item_id = i.id LEFT JOIN users u ON wl.user_id = u.id ORDER BY wl.created_at DESC LIMIT 8");
    $warehouseHistory = $historyStmt->fetchAll();
}

$partnerLogCount = tableExists($pdo, 'partner_service_logs') ? fetchCount($pdo, "SELECT COUNT(*) FROM partner_service_logs") : 0;

renderHeader('Systemstatistiken', 'admin');
?>
<style>
    .stats-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        flex-wrap: wrap;
    }

    .hero-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
    }

    .badge-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: linear-gradient(120deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
        color: var(--text-main);
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .badge-chip small {
        color: var(--text-muted);
        font-weight: 500;
    }

    .badge-chip .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--accent-cyan);
        box-shadow: 0 0 10px var(--accent-cyan);
    }

    .badge-chip.secondary {
        border-color: rgba(255,255,255,0.08);
        color: var(--text-muted);
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }

    .kpi-card {
        position: relative;
        padding: 16px;
        border-radius: 18px;
        background: linear-gradient(160deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 18px 38px rgba(0,0,0,0.35);
        overflow: hidden;
    }

    .kpi-card::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 20%, rgba(0,247,255,0.08), transparent 45%);
        pointer-events: none;
    }

    .kpi-label {
        display: flex;
        align-items: center;
        gap: 8px;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.08em;
        color: var(--text-muted);
        margin-bottom: 8px;
    }

    .kpi-icon {
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: rgba(0,0,0,0.28);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);
    }

    .kpi-value {
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #e0f2fe;
    }

    .kpi-sub {
        margin-top: 6px;
        color: var(--text-muted);
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        align-items: center;
    }

    .pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255,255,255,0.07);
        border: 1px solid rgba(255,255,255,0.08);
        color: var(--text-main);
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .pill.pill-soft {
        background: rgba(0,247,255,0.08);
        border-color: rgba(0,247,255,0.25);
    }

    .pill.pill-magenta {
        background: rgba(255,0,255,0.08);
        border-color: rgba(255,0,255,0.2);
    }

    .pill.pill-gold {
        background: rgba(250,204,21,0.08);
        border-color: rgba(250,204,21,0.25);
        color: #facc15;
    }

    .section-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 12px;
        margin-top: 12px;
    }

    .card h3 {
        margin-bottom: 4px;
    }

    .table-compact table {
        width: 100%;
        border-collapse: collapse;
    }

    .table-compact th,
    .table-compact td {
        padding: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }

    .table-compact th {
        text-align: left;
        font-size: 12px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .table-compact tbody tr:hover {
        background: rgba(255,255,255,0.02);
    }

    .timeline-modern {
        list-style: none;
        padding-left: 0;
        display: grid;
        gap: 10px;
    }

    .timeline-modern li {
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.08);
        background: linear-gradient(150deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
        position: relative;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.03);
    }

    .timeline-modern li::before {
        content: "";
        position: absolute;
        left: -6px;
        top: 16px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: radial-gradient(circle at 30% 30%, #fff, var(--accent-cyan));
        box-shadow: 0 0 12px rgba(0,247,255,0.7);
    }

    .list-split {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 10px 18px;
        padding-left: 0;
        list-style: none;
    }

    .list-split li {
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.08);
        background: rgba(255,255,255,0.03);
    }

    .progress-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .progress-bar {
        flex: 1;
        height: 8px;
        background: rgba(255,255,255,0.06);
        border-radius: 999px;
        overflow: hidden;
    }

    .progress-bar span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, rgba(0,247,255,0.8), rgba(255,0,255,0.55));
    }

    .stat-footer-note {
        color: var(--text-muted);
        font-size: 13px;
        margin-top: 6px;
    }

    .meta-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--text-muted);
        font-weight: 600;
        letter-spacing: 0.02em;
        width: 100%;
    }

    .meta-label svg {
        width: 16px;
        height: 16px;
        opacity: 0.8;
    }
</style>
<div class="page-header">
    <div class="stats-hero">
        <div>
            <p class="eyebrow">Admin · Monitoring</p>
            <h1>Systemstatistiken</h1>
            <p class="muted">Wer ist aktiv? Was passiert wo? Übersichtliche Kennzahlen zu Logins, Seitenaufrufen, Tickets und Lagerbuchungen.</p>
            <div class="hero-badges">
                <span class="badge-chip"><span class="dot"></span> Live-Stand</span>
                <span class="badge-chip secondary">Aktualisiert · <?= date('d.m.Y H:i') ?></span>
            </div>
        </div>
        <div class="kpi-card" style="min-width:220px;">
            <div class="kpi-label">
                <span class="kpi-icon">📈</span>
                Aktivität jetzt
            </div>
            <div class="kpi-value"><?= (int)$activeUsersCount ?></div>
            <div class="kpi-sub">aktive Nutzer in den letzten 15 Minuten</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header-row" style="align-items:center;">
        <div>
            <h2>Überblick</h2>
            <p class="muted">Schnelle Kennzahlen als Taktgeber für System- und Supportaktivitäten.</p>
        </div>
        <div class="pill pill-soft">Gesamtstatus · <?= (int)$ticketTotal ?> Tickets</div>
    </div>
    <div class="kpi-grid" style="margin-top:12px;">
        <div class="kpi-card">
            <div class="kpi-label"><span class="kpi-icon">👥</span>Benutzer gesamt</div>
            <div class="kpi-value"><?= (int)$userCounts['total'] ?></div>
            <div class="kpi-sub">
                <span class="pill pill-magenta">Admins <?= (int)$userCounts['admin'] ?></span>
                <span class="pill">Mitarbeiter <?= (int)$userCounts['employee'] ?></span>
                <span class="pill">Partner <?= (int)$userCounts['partner'] ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><span class="kpi-icon">🎫</span>Tickets offen</div>
            <div class="kpi-value"><?= (int)$ticketsByStatus['open'] ?></div>
            <div class="kpi-sub">In Bearbeitung: <?= (int)$ticketsByStatus['in_progress'] ?> · Gesamt: <?= (int)$ticketTotal ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><span class="kpi-icon">💬</span>Nachrichten & News</div>
            <div class="kpi-value"><?= (int)$messageCount ?></div>
            <div class="kpi-sub">Beiträge: <?= (int)$newsPostsCount ?> · Kommentare: <?= (int)$newsCommentCount ?> · Reaktionen: <?= (int)$newsReactionCount ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><span class="kpi-icon">📦</span>Lageraktivität</div>
            <div class="kpi-value"><?= (int)$warehouseLogCount ?></div>
            <div class="kpi-sub">Lagerstandorte: <?= (int)$warehouseCount ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><span class="kpi-icon">🤝</span>Partner-Services</div>
            <div class="kpi-value"><?= (int)$partnerLogCount ?></div>
            <div class="kpi-sub">erfasste Leistungen / Buchungen</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><span class="kpi-icon">🕑</span>Aktive Nutzer (15 Min)</div>
            <div class="kpi-value"><?= (int)$activeUsersCount ?></div>
            <div class="kpi-sub">Letzter Seitenaufruf</div>
        </div>
    </div>
</div>

<div class="section-grid" style="margin-top:12px;">
    <div class="card">
        <h3>Letzte Logins & Aktivität</h3>
        <p class="muted">Zeitpunkte pro Benutzer, inklusive zuletzt besuchter Seite.</p>
        <div class="table-responsive table-compact">
            <table>
                <thead>
                    <tr>
                        <th>Benutzer</th>
                        <th>Letzter Login</th>
                        <th>Letzte Aktivität</th>
                        <th>Pfad</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recentLogins): ?>
                    <tr><td colspan="4" class="muted">Noch keine Logins erfasst.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentLogins as $login): ?>
                        <tr>
                            <td><span class="pill pill-soft"><?= htmlspecialchars($login['username']) ?></span></td>
                            <td><?= $login['last_login_at'] ? date('d.m.Y H:i', strtotime($login['last_login_at'])) : '—' ?></td>
                            <td><?= $login['last_activity_at'] ? date('d.m.Y H:i', strtotime($login['last_activity_at'])) : '—' ?></td>
                            <td class="muted" style="max-width:220px; word-break:break-all;"><?= $login['last_activity_path'] ? htmlspecialchars($login['last_activity_path']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header-row" style="align-items:center;">
            <div>
                <h3>Anmelde-Historie</h3>
                <p class="muted">Login- und Logout-Ereignisse mit Zeitstempel und IP.</p>
            </div>
            <span class="pill pill-gold">Letzte <?= count($loginHistory) ?> Einträge</span>
        </div>
        <ol class="timeline-modern">
            <?php if (!$loginHistory): ?>
                <li class="muted">Noch keine An- oder Abmeldungen protokolliert.</li>
            <?php else: ?>
                <?php foreach ($loginHistory as $entry): ?>
                    <li>
                        <div class="meta-label">
                            <?= htmlspecialchars($entry['action'] === 'login' ? 'Login' : 'Logout') ?>
                            · <?= $entry['username'] ? htmlspecialchars($entry['username']) : 'Unbekannt' ?>
                        </div>
                        <div class="muted"><?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?><?php if (!empty($entry['ip_address'])): ?> · IP: <?= htmlspecialchars($entry['ip_address']) ?><?php endif; ?></div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ol>
    </div>
</div>

<div class="section-grid" style="margin-top:12px;">
    <div class="card">
        <div class="card-header-row" style="align-items:center;">
            <div>
                <h3>Seitenaufrufe & Wege</h3>
                <p class="muted">Welche Bereiche wurden zuletzt besucht? Was sind die Top-Seiten?</p>
            </div>
            <span class="pill">Nutzungspfad</span>
        </div>
        <div class="grid grid-2">
            <div>
                <h4>Letzte Aufrufe</h4>
                <ul class="list-split">
                    <?php if (!$recentPageViews): ?>
                        <li class="muted">Noch keine Seitenaufrufe gespeichert.</li>
                    <?php else: ?>
                        <?php foreach ($recentPageViews as $view): ?>
                            <li>
                                <div class="meta-label">
                                    <?= $view['username'] ? htmlspecialchars($view['username']) : 'Unbekannt' ?>
                                    <span class="pill pill-soft" style="margin-left:auto;"><?= date('d.m. H:i', strtotime($view['created_at'])) ?></span>
                                </div>
                                <div class="muted" style="word-break:break-all;"><?= htmlspecialchars($view['context'] ?? '—') ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h4>Meistbesuchte Pfade</h4>
                <ul class="list-split">
                    <?php if (!$pageViewSummary): ?>
                        <li class="muted">Noch keine Auswertung verfügbar.</li>
                    <?php else: ?>
                        <?php foreach ($pageViewSummary as $pathRow): ?>
                            <li>
                                <div class="meta-label">
                                    <?= htmlspecialchars($pathRow['path'] ?? '—') ?>
                                    <span class="pill pill-magenta" style="margin-left:auto;"><?= (int)$pathRow['hits'] ?> Hits</span>
                                </div>
                                <div class="muted">Zuletzt: <?= $pathRow['last_seen'] ? date('d.m.Y H:i', strtotime($pathRow['last_seen'])) : '—' ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header-row" style="align-items:center;">
            <div>
                <h3>Tickets & Kommunikation</h3>
                <p class="muted">Statusstände aus dem Ticketsystem sowie Nutzung von Nachrichten und News.</p>
            </div>
            <span class="pill">Kommunikationslage</span>
        </div>
        <div class="grid grid-2">
            <div>
                <h4>Ticketstatus</h4>
                <ul class="list" style="list-style:none; padding-left:0;">
                    <li class="progress-row">
                        <span class="pill pill-soft">Offen <?= (int)$ticketsByStatus['open'] ?></span>
                        <div class="progress-bar"><span style="width: <?= percentage((int)$ticketsByStatus['open'], max($ticketTotal, 1)) ?>%;"></span></div>
                    </li>
                    <li class="progress-row">
                        <span class="pill">In Bearbeitung <?= (int)$ticketsByStatus['in_progress'] ?></span>
                        <div class="progress-bar"><span style="width: <?= percentage((int)$ticketsByStatus['in_progress'], max($ticketTotal, 1)) ?>%;"></span></div>
                    </li>
                    <li class="progress-row">
                        <span class="pill">Wartend <?= (int)$ticketsByStatus['waiting'] ?></span>
                        <div class="progress-bar"><span style="width: <?= percentage((int)$ticketsByStatus['waiting'], max($ticketTotal, 1)) ?>%;"></span></div>
                    </li>
                    <li class="progress-row">
                        <span class="pill pill-gold">Geschlossen <?= (int)$ticketsByStatus['closed'] ?></span>
                        <div class="progress-bar"><span style="width: <?= percentage((int)$ticketsByStatus['closed'], max($ticketTotal, 1)) ?>%;"></span></div>
                    </li>
                </ul>
                <div class="stat-footer-note">Gesamt: <?= (int)$ticketTotal ?> Tickets</div>
            </div>
            <div>
                <h4>News & Nachrichten</h4>
                <ul class="list-split">
                    <li class="meta-label">Nachrichten gesamt <span class="pill pill-soft" style="margin-left:auto;"><?= (int)$messageCount ?></span></li>
                    <li class="meta-label">News-Beiträge <span class="pill" style="margin-left:auto;"><?= (int)$newsPostsCount ?></span></li>
                    <li class="meta-label">News-Kommentare <span class="pill" style="margin-left:auto;"><?= (int)$newsCommentCount ?></span></li>
                    <li class="meta-label">News-Reaktionen <span class="pill pill-magenta" style="margin-left:auto;"><?= (int)$newsReactionCount ?></span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:12px;">
    <div class="card-header-row" style="align-items:center;">
        <div>
            <h3>Lager- & Partnerbuchungen</h3>
            <p class="muted">Aktivitäten in den Lagern und erfasste Partnerleistungen.</p>
        </div>
        <span class="pill pill-soft">Letzte Bewegungen</span>
    </div>
    <div class="grid grid-2">
        <div>
            <h4>Letzte Lagerbuchungen</h4>
            <ul class="list-split">
                <?php if (!$warehouseHistory): ?>
                    <li class="muted">Keine Lagerbuchungen gefunden.</li>
                <?php else: ?>
                    <?php foreach ($warehouseHistory as $log): ?>
                        <li>
                            <div class="meta-label">
                                <?= htmlspecialchars($log['warehouse_name'] ?? 'Lager') ?> · <?= htmlspecialchars($log['item_name'] ?? 'Artikel') ?>
                                <span class="pill pill-soft" style="margin-left:auto;"><?= date('d.m. H:i', strtotime($log['created_at'])) ?></span>
                            </div>
                            <div class="muted">Menge: <?= (int)$log['change_amount'] ?> (Bestand: <?= (int)$log['resulting_stock'] ?>)</div>
                            <div class="muted">Aktion: <?= htmlspecialchars($log['action']) ?> · Nutzer: <?= $log['username'] ? htmlspecialchars($log['username']) : 'System' ?></div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div>
            <h4>Partner-Service Übersicht</h4>
            <p class="muted">Erfasste Leistungen gesamt: <strong><?= (int)$partnerLogCount ?></strong>.</p>
            <p class="muted">Nutze die Partner-Verwaltung, um Details pro Partner einzusehen.</p>
        </div>
    </div>
</div>
<?php
renderFooter();