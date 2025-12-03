<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_ticket_categories');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

// Tabelle sicherstellen
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$stmt = $pdo->query("SELECT * FROM ticket_categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

renderHeader('Ticket-Kategorien', 'admin');
?>
<div class="card">
    <h2>Ticket-Kategorien</h2>
    <p class="muted">Verwalte Kategorien, um Tickets besser zu strukturieren.</p>
    <a class="btn btn-primary" href="/admin/ticket_category_add.php">Kategorie hinzufügen</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Erstellt am</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= (int)$cat['id'] ?></td>
                    <td><?= htmlspecialchars($cat['name']) ?></td>
                    <td><?= htmlspecialchars($cat['created_at']) ?></td>
                    <td>
                        <a class="btn-link" href="/admin/ticket_category_edit.php?id=<?= (int)$cat['id'] ?>">Bearbeiten</a> |
                        <a class="btn-link" href="/admin/ticket_category_delete.php?id=<?= (int)$cat['id'] ?>" onclick="return confirm('Kategorie wirklich löschen? Tickets behalten dann diese Kategorie-ID, ggf. auf "Keine" setzen.');">Löschen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?>
                <tr><td colspan="4" class="muted">Noch keine Kategorien.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();
