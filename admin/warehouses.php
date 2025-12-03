<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

ensureWarehouseSchema($pdo);

// Warehouses mit Item-Anzahl laden
$stmt = $pdo->query("SELECT w.*, COUNT(wi.id) AS item_count
    FROM warehouses w
    LEFT JOIN warehouse_items wi ON wi.warehouse_id = w.id
    GROUP BY w.id
    ORDER BY w.name ASC");
$warehouses = $stmt->fetchAll();

$ranks = getAllRanks($pdo);
$rankLookup = [];
foreach ($ranks as $rank) {
    $rankLookup[$rank['id']] = $rank['name'];
}

renderHeader('Lagerverwaltung', 'admin');
?>
<div class="card">
    <h2>Lagerverwaltung</h2>
    <p class="muted">Lege neue Lager an, verwalte deren Ränge und überprüfe Bestände.</p>
    <div class="toolbar">
        <a class="btn btn-primary" href="/admin/warehouse_add.php">Neues Lager anlegen</a>
        <a class="btn" href="/admin/warehouse_logs.php">Lagerübersicht / Historie</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Beschreibung</th>
                <th>Artikel</th>
                <th>Freigegebene Ränge</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$warehouses): ?>
                <tr><td colspan="5" class="muted">Noch keine Lager angelegt.</td></tr>
            <?php else: ?>
                <?php foreach ($warehouses as $warehouse): ?>
                    <?php $rankIds = getWarehouseRankIds($pdo, (int)$warehouse['id']); ?>
                    <tr>
                        <td><?= htmlspecialchars($warehouse['name']) ?></td>
                        <td><?= htmlspecialchars($warehouse['description'] ?? '') ?></td>
                        <td><?= (int)$warehouse['item_count'] ?></td>
                        <td>
                            <?php if (empty($rankIds)): ?>
                                <span class="muted">Kein Rang zugewiesen</span>
                            <?php else: ?>
                                <?php foreach ($rankIds as $rid): ?>
                                    <span class="badge"><?= htmlspecialchars($rankLookup[$rid] ?? ('Rang #' . $rid)) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn-link" href="/admin/warehouse_items.php?warehouse_id=<?= (int)$warehouse['id'] ?>">Artikel</a> |
                            <a class="btn-link" href="/admin/warehouse_edit.php?id=<?= (int)$warehouse['id'] ?>">Bearbeiten</a> |
                            <a class="btn-link" href="/admin/warehouse_logs.php?warehouse_id=<?= (int)$warehouse['id'] ?>">Historie</a> |
                            <a class="btn-link" href="/admin/warehouse_delete.php?id=<?= (int)$warehouse['id'] ?>" onclick="return confirm('Lager wirklich löschen? Alle Artikel und Logs werden entfernt.');">Löschen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();