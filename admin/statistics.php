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
<div class="page-header">
    <div>
        <p class="eyebrow">Admin · Monitoring</p>
        <h1>Systemstatistiken</h1>
        <p class="muted">Wer ist aktiv? Was passiert wo? Übersichtliche Kennzahlen zu Logins, Seitenaufrufen, Tickets und Lagerbuchungen.</p>
    </div>
</div>

<div class="card">
    <h2>Überblick</h2>
    <div class="grid grid-3" style="margin-top:12px;">
        <div class="stat">
            <div class="stat-label">Benutzer gesamt</div>
            <div class="stat-value"><?= (int)$userCounts['total'] ?></div>
            <div class="stat-sub">Admins: <?= (int)$userCounts['admin'] ?> · Mitarbeiter: <?= (int)$userCounts['employee'] ?> · Partner: <?= (int)$userCounts['partner'] ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Aktiv (15 Min)</div>
            <div class="stat-value"><?= (int)$activeUsersCount ?></div>
            <div class="stat-sub">mit aktuellem Seitenaufruf</div>
        </div>
        <div class="stat">
            <div class="stat-label">Tickets offen</div>
            <div class="stat-value"><?= (int)$ticketsByStatus['open'] ?></div>
            <div class="stat-sub">Gesamt: <?= (int)$ticketTotal ?> · In Bearbeitung: <?= (int)$ticketsByStatus['in_progress'] ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Nachrichten</div>
            <div class="stat-value"><?= (int)$messageCount ?></div>
            <div class="stat-sub">News: <?= (int)$newsPostsCount ?> Beiträge / <?= (int)$newsCommentCount ?> Kommentare</div>
        </div>
        <div class="stat">
            <div class="stat-label">Lagerhistorie</div>
            <div class="stat-value"><?= (int)$warehouseLogCount ?></div>
            <div class="stat-sub">Lager: <?= (int)$warehouseCount ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Partner-Services</div>
            <div class="stat-value"><?= (int)$partnerLogCount ?></div>
            <div class="stat-sub">erfasste Leistungen / Buchungen</div>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:12px;">
    <div class="card">
        <h3>Letzte Logins & Aktivität</h3>
        <p class="muted">Zeitpunkte pro Benutzer, inklusive zuletzt besuchter Seite.</p>
        <div class="table-responsive">
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
                            <td><?= htmlspecialchars($login['username']) ?></td>
                            <td><?= $login['last_login_at'] ? date('d.m.Y H:i', strtotime($login['last_login_at'])) : '—' ?></td>
                            <td><?= $login['last_activity_at'] ? date('d.m.Y H:i', strtotime($login['last_activity_at'])) : '—' ?></td>
                            <td class="muted" style="max-width:220px;"><?= $login['last_activity_path'] ? htmlspecialchars($login['last_activity_path']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3>Anmelde-Historie</h3>
        <p class="muted">Login- und Logout-Ereignisse mit Zeitstempel und IP.</p>
        <ol class="timeline">
            <?php if (!$loginHistory): ?>
                <li class="muted">Noch keine An- oder Abmeldungen protokolliert.</li>
            <?php else: ?>
                <?php foreach ($loginHistory as $entry): ?>
                    <li>
                        <strong><?= htmlspecialchars($entry['action'] === 'login' ? 'Login' : 'Logout') ?></strong>
                        · <?= $entry['username'] ? htmlspecialchars($entry['username']) : 'Unbekannt' ?>
                        <span class="muted">(<?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?>)</span>
                        <?php if (!empty($entry['ip_address'])): ?>
                            <div class="muted">IP: <?= htmlspecialchars($entry['ip_address']) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ol>
    </div>
</div>

<div class="grid grid-2" style="margin-top:12px;">
    <div class="card">
        <h3>Seitenaufrufe & Wege</h3>
        <p class="muted">Welche Bereiche wurden zuletzt besucht? Was sind die Top-Seiten?</p>
        <div class="grid grid-2">
            <div>
                <h4>Letzte Aufrufe</h4>
                <ul class="list">
                    <?php if (!$recentPageViews): ?>
                        <li class="muted">Noch keine Seitenaufrufe gespeichert.</li>
                    <?php else: ?>
                        <?php foreach ($recentPageViews as $view): ?>
                            <li>
                                <div><strong><?= $view['username'] ? htmlspecialchars($view['username']) : 'Unbekannt' ?></strong> · <span class="muted"><?= date('d.m.Y H:i', strtotime($view['created_at'])) ?></span></div>
                                <div class="muted" style="word-break:break-all;"><?= htmlspecialchars($view['context'] ?? '—') ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h4>Meistbesuchte Pfade</h4>
                <ul class="list">
                    <?php if (!$pageViewSummary): ?>
                        <li class="muted">Noch keine Auswertung verfügbar.</li>
                    <?php else: ?>
                        <?php foreach ($pageViewSummary as $pathRow): ?>
                            <li>
                                <div><strong><?= htmlspecialchars($pathRow['path'] ?? '—') ?></strong></div>
                                <div class="muted">Aufrufe: <?= (int)$pathRow['hits'] ?> · Zuletzt: <?= $pathRow['last_seen'] ? date('d.m.Y H:i', strtotime($pathRow['last_seen'])) : '—' ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="card">
        <h3>Tickets & Kommunikation</h3>
        <p class="muted">Statusstände aus dem Ticketsystem sowie Nutzung von Nachrichten und News.</p>
        <div class="grid grid-2">
            <div>
                <h4>Ticketstatus</h4>
                <ul class="list">
                    <li>Offen: <?= (int)$ticketsByStatus['open'] ?></li>
                    <li>In Bearbeitung: <?= (int)$ticketsByStatus['in_progress'] ?></li>
                    <li>Wartend: <?= (int)$ticketsByStatus['waiting'] ?></li>
                    <li>Geschlossen: <?= (int)$ticketsByStatus['closed'] ?></li>
                </ul>
            </div>
            <div>
                <h4>News & Nachrichten</h4>
                <ul class="list">
                    <li>Nachrichten gesamt: <?= (int)$messageCount ?></li>
                    <li>News-Beiträge: <?= (int)$newsPostsCount ?></li>
                    <li>News-Kommentare: <?= (int)$newsCommentCount ?></li>
                    <li>News-Reaktionen: <?= (int)$newsReactionCount ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:12px;">
    <h3>Lager- & Partnerbuchungen</h3>
    <p class="muted">Aktivitäten in den Lagern und erfasste Partnerleistungen.</p>
    <div class="grid grid-2">
        <div>
            <h4>Letzte Lagerbuchungen</h4>
            <ul class="list">
                <?php if (!$warehouseHistory): ?>
                    <li class="muted">Keine Lagerbuchungen gefunden.</li>
                <?php else: ?>
                    <?php foreach ($warehouseHistory as $log): ?>
                        <li>
                            <div><strong><?= htmlspecialchars($log['warehouse_name'] ?? 'Lager') ?></strong> · <?= htmlspecialchars($log['item_name'] ?? 'Artikel') ?></div>
                            <div class="muted">Menge: <?= (int)$log['change_amount'] ?> (Bestand: <?= (int)$log['resulting_stock'] ?>) · <?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></div>
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