<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

ensureWarehouseSchema($pdo);

$selectedWarehouseId = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== ''
    ? (int)$_GET['warehouse_id']
    : null;

$warehouses = $pdo->query('SELECT id, name FROM warehouses ORDER BY name ASC')->fetchAll();
$logs = getWarehouseLogEntries($pdo, $selectedWarehouseId, 300);

renderHeader('Lagerhistorie', 'admin');
?>
<div class="card">
    <h2>Lagerübersicht &amp; Historie</h2>
    <p class="muted">Nachvollziehen, wer wann welche Bestände geändert hat.</p>

    <form method="get" class="field-group" style="max-width:320px;">
        <label for="warehouse_id">Lager filtern</label>
        <select id="warehouse_id" name="warehouse_id" onchange="this.form.submit()">
            <option value="">Alle Lager</option>
            <?php foreach ($warehouses as $wh): ?>
                <option value="<?= (int)$wh['id'] ?>" <?= $selectedWarehouseId === (int)$wh['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($wh['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>Zeitpunkt</th>
                <th>Lager</th>
                <th>Artikel</th>
                <th>Aktion</th>
                <th>Menge</th>
                <th>Neuer Bestand</th>
                <th>User</th>
                <th>Notiz</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="8" class="muted">Noch keine Bewegungen erfasst.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                        <td><?= htmlspecialchars($log['warehouse_name']) ?></td>
                        <td><?= htmlspecialchars($log['item_name']) ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td><?= (int)$log['change_amount'] ?></td>
                        <td><?= (int)$log['resulting_stock'] ?></td>
                        <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                        <td><?= htmlspecialchars($log['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="toolbar" style="margin-top:12px;">
        <a class="btn" href="/admin/warehouses.php">Zurück zur Lagerverwaltung</a>
    </div>
</div>
<?php
renderFooter();