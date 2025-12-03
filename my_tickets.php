<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/auth/check_role.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

checkRole(['admin','employee','partner']);
requireAbsenceAccess('tickets');

// Tabellen sicherstellen (falls Adminbereich noch nicht aufgerufen wurde)
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open','in_progress','waiting','closed') NOT NULL DEFAULT 'open',
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    category_id INT NULL,
    created_by INT NOT NULL,
    assigned_to INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$userId = (int)($_SESSION['user']['id'] ?? 0);

$sql = "SELECT
            t.*,
            c.name AS category_name,
            a.username AS assignee_name,
            (SELECT tc.message FROM ticket_comments tc WHERE tc.ticket_id = t.id ORDER BY tc.created_at DESC LIMIT 1) AS last_comment,
            (SELECT tc.created_at FROM ticket_comments tc WHERE tc.ticket_id = t.id ORDER BY tc.created_at DESC LIMIT 1) AS last_comment_at,
            (SELECT u.username FROM ticket_comments tc LEFT JOIN users u ON tc.user_id = u.id WHERE tc.ticket_id = t.id ORDER BY tc.created_at DESC LIMIT 1) AS last_comment_user
        FROM tickets t
        LEFT JOIN ticket_categories c ON t.category_id = c.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE t.created_by = ?
        ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll();

renderHeader('Meine Tickets', 'my_tickets');
?>
<div class="card">
    <h2>Meine Support-Tickets</h2>
    <p class="muted">Hier findest du alle Tickets, die du erstellt hast, inklusive Antworten des Teams.</p>

    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <a class="btn btn-primary" href="/ticket_create.php">Neues Ticket erstellen</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Status</th>
                <th>Priorität</th>
                <th>Kategorie</th>
                <th>Letzte Antwort</th>
                <th>Erstellt</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>#<?= (int)$t['id'] ?></td>
                    <td>
                        <a class="btn-link" href="/ticket_view.php?id=<?= (int)$t['id'] ?>">
                            <?= htmlspecialchars($t['title']) ?>
                        </a>
                    </td>
                    <td class="ticket-status-<?= htmlspecialchars($t['status']) ?>">
                        <?= htmlspecialchars($t['status']) ?>
                    </td>
                    <td class="ticket-priority-<?= htmlspecialchars($t['priority']) ?>">
                        <?= htmlspecialchars($t['priority']) ?>
                    </td>
                    <td><?= htmlspecialchars($t['category_name'] ?? '–') ?></td>
                    <td>
                        <?php if ($t['last_comment']): ?>
                            <div class="muted" style="font-size:0.85rem;">
                                von <?= htmlspecialchars($t['last_comment_user'] ?? '–') ?> am <?= htmlspecialchars($t['last_comment_at']) ?>
                            </div>
                            <div style="max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($t['last_comment']) ?>
                            </div>
                        <?php else: ?>
                            <span class="muted">Noch keine Antworten</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <tr>
                    <td colspan="7" class="muted">Du hast noch keine Tickets erstellt.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();