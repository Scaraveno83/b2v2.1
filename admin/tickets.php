<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_view_tickets');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

// Tabellen für Ticketsystem anlegen (falls nicht vorhanden)

// Kategorien
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Tickets
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_category (category_id),
    INDEX idx_assigned_to (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Kommentare
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Anhänge
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Activity Log
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// einfache Standardkategorien (falls leer)
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM ticket_categories")->fetchColumn();
if ($catCount === 0) {
    $pdo->exec("INSERT INTO ticket_categories (name) VALUES
        ('Allgemein'),
        ('Technik'),
        ('Verkauf'),
        ('Abrechnung')");
}

// Filter
$statusFilter = $_GET['status'] ?? '';
$prioFilter   = $_GET['priority'] ?? '';
$catFilter    = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$assignedMe   = isset($_GET['assigned_me']) ? (int)$_GET['assigned_me'] : 0;

$sql = "SELECT t.*, c.name AS category_name, u.username AS creator_name, g.guest_name, g.guest_email, a.username AS assignee_name
        FROM tickets t
        LEFT JOIN ticket_categories c ON t.category_id = c.id
        LEFT JOIN users u ON t.created_by = u.id
        LEFT JOIN guest_tickets g ON t.id = g.ticket_id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE 1=1";
$params = [];

if ($statusFilter !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}
if ($prioFilter !== '') {
    $sql .= " AND t.priority = ?";
    $params[] = $prioFilter;
}
if ($catFilter > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $catFilter;
}
if ($assignedMe && isset($_SESSION['user']['id'])) {
    $sql .= " AND t.assigned_to = ?";
    $params[] = (int)$_SESSION['user']['id'];
}

// Sichtbarkeit: ohne Bearbeitungsrecht nur eigene Tickets
if (!hasPermission('can_edit_tickets') && isset($_SESSION['user']['id'])) {
    $sql .= " AND t.created_by = ?";
    $params[] = (int)$_SESSION['user']['id'];
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Kategorien für Filter
$catStmt = $pdo->query("SELECT id, name FROM ticket_categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

renderHeader('Ticketsystem', 'admin');
?>
<div class="card">
    <h2>Ticketsystem</h2>
    <p class="muted">Tickets mit Prioritäten, Kategorien, Zuweisung und Kommentaren.</p>

    <div class="filter-bar">
        <form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <select name="status">
                <option value="">Status: alle</option>
                <option value="open" <?= $statusFilter==='open'?'selected':'' ?>>Offen</option>
                <option value="in_progress" <?= $statusFilter==='in_progress'?'selected':'' ?>>In Bearbeitung</option>
                <option value="waiting" <?= $statusFilter==='waiting'?'selected':'' ?>>Warten auf Kunde</option>
                <option value="closed" <?= $statusFilter==='closed'?'selected':'' ?>>Erledigt</option>
            </select>
            <select name="priority">
                <option value="">Priorität: alle</option>
                <option value="low" <?= $prioFilter==='low'?'selected':'' ?>>Niedrig</option>
                <option value="medium" <?= $prioFilter==='medium'?'selected':'' ?>>Mittel</option>
                <option value="high" <?= $prioFilter==='high'?'selected':'' ?>>Hoch</option>
                <option value="urgent" <?= $prioFilter==='urgent'?'selected':'' ?>>Dringend</option>
            </select>
            <select name="category_id">
                <option value="0">Kategorie: alle</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($_SESSION['user'])): ?>
                <label style="font-size:0.8rem;">
                    <input type="checkbox" name="assigned_me" value="1" <?= $assignedMe ? 'checked' : '' ?>>
                    nur mir zugewiesene
                </label>
            <?php endif; ?>
            <button class="btn btn-secondary" type="submit">Filtern</button>
        </form>
        <?php if (hasPermission('can_create_tickets')): ?>
            <a class="btn btn-primary" href="/admin/ticket_add.php">Neues Ticket</a>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Status</th>
                <th>Priorität</th>
                <th>Kategorie</th>
                <th>Ersteller</th>
                <th>Zugewiesen an</th>
                <th>Erstellt</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><?= (int)$t['id'] ?></td>
                    <td>
                        <a class="btn-link" href="/admin/ticket_view.php?id=<?= (int)$t['id'] ?>">
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
                        <?php
                            $creator = $t['creator_name'] ?? '';
                            if ($creator === '' || $creator === null) {
                                $creator = $t['guest_name'] ? 'Gast: ' . $t['guest_name'] : 'Gast';
                            }
                        ?>
                        <?= htmlspecialchars($creator) ?>
                    </td>
                    <td><?= htmlspecialchars($t['assignee_name'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($t['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <tr><td colspan="8" class="muted">Keine Tickets gefunden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();
