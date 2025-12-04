<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

checkRole(['employee', 'admin', 'partner']);
requirePermission('can_use_warehouses');
if (in_array($_SESSION['user']['role'], ['employee', 'admin'], true)) {
    requireAbsenceAccess('staff');
}
requireAbsenceAccess('warehouses');
ensureWarehouseSchema($pdo);

$user = $_SESSION['user'];
$warehouses = getAccessibleWarehouses($pdo, $user);

$currentWarehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;
if ($currentWarehouseId === null && $warehouses) {
    $currentWarehouseId = (int)$warehouses[0]['id'];
}

$warehouseIds = array_column($warehouses, 'id');
$hasManageOverride = !empty($user['permissions']['can_manage_warehouses']);
if ($warehouses && !$hasManageOverride && !in_array($currentWarehouseId, $warehouseIds, true)) {
    $currentWarehouseId = (int)$warehouses[0]['id'];
}
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentWarehouseId = (int)($_POST['warehouse_id'] ?? 0);

    if (!in_array($currentWarehouseId, $warehouseIds, true) && !$hasManageOverride) {
        http_response_code(403);
        echo 'Kein Zugriff auf dieses Lager.';
        exit;
    }

    if (in_array($action, ['add_stock', 'remove_stock'], true)) {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $note = trim($_POST['note'] ?? '');
        if ($quantity > 0) {
            $delta = $action === 'add_stock' ? $quantity : -$quantity;
            if (adjustWarehouseStock($pdo, $currentWarehouseId, $itemId, $delta, $action, $user['id'] ?? null, $note)) {
                $message = 'Bestand aktualisiert.';
            } else {
                $message = 'Artikel konnte nicht geändert werden.';
            }
        }
    }
}

$items = [];
$currentWarehouse = null;
$searchQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($currentWarehouseId) {
    foreach ($warehouses as $wh) {
        if ((int)$wh['id'] === $currentWarehouseId) {
            $currentWarehouse = $wh;
            break;
        }
    }
    if ($currentWarehouse) {
        $items = getWarehouseItems($pdo, $currentWarehouseId);
    }
}

renderHeader('Lager', 'warehouses');
?>
<div class="card">
    <h2>Lagersystem</h2>
    <p class="muted">Entnehme oder füge Artikel hinzu – basierend auf deiner Rangfreigabe.</p>

    <?php if (!$warehouses): ?>
        <div class="error">Dir ist aktuell kein Lager zugewiesen. Bitte kontaktiere einen Admin.</div>
    <?php else: ?>
        <form method="get" class="grid grid-2" style="gap:12px; align-items:end;">
            <div class="field-group" style="min-width:220px;">
                <label for="warehouse_id">Lager auswählen</label>
                <select id="warehouse_id" name="warehouse_id" onchange="this.form.submit()">
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= (int)$wh['id'] ?>" <?= $currentWarehouseId === (int)$wh['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wh['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group" style="min-width:220px;">
                <label for="q">Artikel suchen</label>
                <input id="q" name="q" placeholder="Name oder Beschreibung" value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            <div>
                <button class="btn" type="submit">Ansicht aktualisieren</button>
            </div>
        </form>

        <?php if ($message): ?>
            <div class="notice" style="margin-bottom:12px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($currentWarehouse): ?>
            <h3><?= htmlspecialchars($currentWarehouse['name']) ?></h3>
            <p class="muted"><?= htmlspecialchars($currentWarehouse['description'] ?? '') ?></p>
            <p class="muted">Mindest- und Höchstbestände gelten global über alle Lager; die Warnhinweise beziehen den Gesamtbestand ein.</p>
            <div class="toolbar" style="margin:8px 0;">
                <a class="btn" href="/staff/farming.php">Farming-Aufgaben anzeigen</a>
            </div>

            <?php if ($items): ?>
                <div class="action-panel">
                    <h4>Bestand anpassen</h4>
                    <form method="post" class="grid grid-4" style="gap:10px; align-items:end;">
                        <input type="hidden" name="warehouse_id" value="<?= (int)$currentWarehouseId ?>">
                        <div class="field-group">
                            <label for="item_id">Artikel auswählen</label>
                            <select id="item_id" name="item_id" required>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>">
                                        <?= htmlspecialchars($item['name']) ?> (<?= (int)$item['current_stock'] ?> im Lager, <?= (int)$item['total_stock'] ?> gesamt)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="quantity">Menge</label>
                            <input id="quantity" name="quantity" type="number" min="1" value="1" required>
                        </div>
                        <div class="field-group">
                            <label for="action">Aktion</label>
                            <select id="action" name="action" required>
                                <option value="add_stock">Hinzufügen</option>
                                <option value="remove_stock">Entnehmen</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="note">Notiz</label>
                            <input id="note" name="note" type="text" placeholder="Optional">
                        </div>
                        <div class="field-group" style="align-self:end;">
                            <button class="btn btn-primary" type="submit">Buchung ausführen</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <style>
                .item-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; }
                .item-card { border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 12px; background: rgba(255,255,255,0.02); }
                .item-card header { display: flex; justify-content: space-between; gap: 12px; align-items: baseline; margin-bottom: 6px; }
                .item-card .stock { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
                .item-card .stock .badge { background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 6px; font-size: 12px; }
                .stock-highlight { display: flex; gap: 12px; align-items: center; padding: 8px; background: rgba(0,150,136,0.12); border-radius: 8px; }
                .stock-highlight .stock-number { font-size: 26px; font-weight: 700; }
                .action-panel { background: rgba(255,255,255,0.03); padding: 10px; border-radius: 10px; margin-bottom: 14px; border: 1px solid rgba(255,255,255,0.05); }
                .action-panel h4 { margin: 0 0 8px 0; }
                .muted { color: #a0a8b3; }
            </style>

            <?php
            if ($searchQuery !== '') {
                $items = array_filter($items, static function ($item) use ($searchQuery) {
                    $haystack = strtolower(($item['name'] ?? '') . ' ' . ($item['description'] ?? ''));
                    return strpos($haystack, strtolower($searchQuery)) !== false;
                });
            }
            ?>

            <?php if (!$items): ?>
                <div class="muted" style="margin-top:8px;">Keine Artikel hinterlegt oder keine Treffer für die Suche.</div>
            <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="item-card">
                            <header>
                                <div>
                                    <div><strong><?= htmlspecialchars($item['name']) ?></strong></div>
                                    <div class="muted" style="margin-top:2px;">
                                        <?= $item['description'] ? htmlspecialchars($item['description']) : 'Keine Beschreibung' ?>
                                    </div>
                                </div>
                            </header>

                            <div class="stock">
                                <div class="stock-highlight">
                                    <div class="stock-number"><?= (int)$item['current_stock'] ?></div>
                                    <div>
                                        <div>Stück aktuell verfügbar</div>
                                        <div class="muted">Bestand in diesem Lager</div>
                                    </div>
                                </div>
                                <div class="badge">Gesamt: <?= (int)$item['total_stock'] ?></div>
                                <div class="badge">Min <?= (int)$item['min_stock'] ?></div>
                                <div class="badge">Max <?= (int)$item['max_stock'] ?></div>
                                <?php if (!empty($item['farmable'])): ?>
                                    <div class="badge" style="background:rgba(76,175,80,0.15); color:#b2ffb2;">Farmbar</div>
                                <?php endif; ?>
                                <?php if ($item['total_stock'] < $item['min_stock']): ?>
                                    <div class="error" style="margin-top:6px;">Unter Mindestbestand (gesamt)!</div>
                                <?php elseif ($item['max_stock'] > 0 && $item['total_stock'] > $item['max_stock']): ?>
                                    <div class="notice" style="margin-top:6px;">Über Höchstbestand (gesamt).</div>
                                <?php endif; ?>
                            </div>

                            <div class="muted">Buchungen bitte über das Dropdown oben vornehmen.</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
renderFooter();
?>