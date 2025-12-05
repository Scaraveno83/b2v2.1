<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

checkRole(['employee', 'admin', 'partner']);
requirePermission('can_use_warehouses');
requireAbsenceAccess('staff');
requireAbsenceAccess('warehouses');
ensureWarehouseSchema($pdo);

$user = $_SESSION['user'] ?? [];
$warehouses = getAccessibleWarehouses($pdo, $user);
$warehouseIds = array_map(static fn($wh) => (int)$wh['id'], $warehouses);
$selectedWarehouseId = (int)($_POST['warehouse_id'] ?? $_GET['warehouse_id'] ?? ($warehouseIds[0] ?? 0));
if ($warehouses && !in_array($selectedWarehouseId, $warehouseIds, true)) {
    $selectedWarehouseId = (int)$warehouseIds[0];
}

$processableItems = getProcessableItems($pdo);
$processableIds = array_map(static fn($row) => (int)$row['id'], $processableItems);
$currentItemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? ($processableIds[0] ?? 0));
$desiredAmount = max(1, (int)($_POST['desired_amount'] ?? 1));
$message = '';
$plan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book_ingredients') {
    $currentItemId = (int)($_POST['item_id'] ?? $currentItemId);
    $desiredAmount = max(1, (int)($_POST['desired_amount'] ?? $desiredAmount));
    $selectedWarehouseId = (int)($_POST['warehouse_id'] ?? $selectedWarehouseId);
    $note = trim($_POST['note'] ?? '');

    if (!in_array($currentItemId, $processableIds, true)) {
        $message = 'Ungültiger Artikel für die Weiterverarbeitung.';
    } elseif (!$warehouses || !in_array($selectedWarehouseId, $warehouseIds, true)) {
        $message = 'Kein gültiges Lager ausgewählt.';
    } else {
        $planForBooking = calculateProcessingNeeds($pdo, $currentItemId, $desiredAmount);
        if (!$planForBooking) {
            $message = 'Für diesen Artikel ist noch keine Rezeptur hinterlegt. Bitte kontaktiere einen Admin.';
        } else {
            $missing = [];
            foreach ($planForBooking['ingredients'] as $ingredient) {
                $warehouseStock = getWarehouseStock($pdo, $selectedWarehouseId, (int)$ingredient['item_id']);
                if ($warehouseStock < (int)$ingredient['total_needed']) {
                    $missing[] = sprintf('%s (benötigt %d, vorhanden %d)', $ingredient['name'], (int)$ingredient['total_needed'], $warehouseStock);
                }
            }

            if ($missing) {
                $message = 'Nicht genug Bestand im ausgewählten Lager für: ' . implode(', ', $missing) . '.';
            } else {
                $noteText = $note !== '' ? $note : sprintf('Verbrauch für %d× Weiterverarbeitung', $desiredAmount);
                foreach ($planForBooking['ingredients'] as $ingredient) {
                    adjustWarehouseStock(
                        $pdo,
                        $selectedWarehouseId,
                        (int)$ingredient['item_id'],
                        -(int)$ingredient['total_needed'],
                        'processing_use',
                        $user['id'] ?? null,
                        $noteText
                    );
                }
                $message = 'Zutaten wurden erfolgreich ausgebucht.';
            }
        }
    }
}

if ($currentItemId && in_array($currentItemId, $processableIds, true)) {
    $plan = calculateProcessingNeeds($pdo, $currentItemId, $desiredAmount);
    if (!$plan) {
        $message = 'Für diesen Artikel ist noch keine Rezeptur hinterlegt. Bitte kontaktiere einen Admin.';
    } elseif ($plan['ingredients'] && $selectedWarehouseId) {
        foreach ($plan['ingredients'] as &$ing) {
            $ing['warehouse_stock'] = getWarehouseStock($pdo, $selectedWarehouseId, (int)$ing['item_id']);
        }
        unset($ing);
    }
} elseif (!$processableItems) {
    $message = 'Es wurden noch keine Artikel für die Weiterverarbeitung freigegeben.';
}

function findItemById(array $items, int $id): ?array {
    foreach ($items as $item) {
        if ((int)$item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

$currentItem = $currentItemId ? findItemById($processableItems, $currentItemId) : null;

renderHeader('Weiterverarbeitung planen', 'warehouses');
?>
<div class="card">
    <h2>Weiterverarbeitung planen</h2>
    <p class="muted">Berechne den Materialbedarf für Mitarbeiteraufträge, z.B. 50× Repair Kits.</p>

    <?php if ($message): ?>
        <div class="notice"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($processableItems): ?>
        <form method="post" class="grid grid-3" style="gap:10px; align-items:end;">
            <div class="field-group">
                <label for="item_id">Zielartikel</label>
                <select id="item_id" name="item_id">
                    <?php foreach ($processableItems as $item): ?>
                        <option value="<?= (int)$item['id'] ?>" <?= $currentItemId === (int)$item['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group">
                <label for="desired_amount">Gewünschte Menge</label>
                <input id="desired_amount" name="desired_amount" type="number" min="1" value="<?= (int)$desiredAmount ?>" required>
            </div>
            <div class="field-group" style="align-self:end;">
                <button class="btn btn-primary" type="submit">Bedarf berechnen</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($plan && $currentItem): ?>
        <div class="action-panel" style="margin-top:12px;">
            <h3>Bedarf für <?= (int)$desiredAmount ?>× <?= htmlspecialchars($currentItem['name']) ?></h3>
            <p class="muted">Ein Durchlauf ergibt <?= (int)$plan['output_per_batch'] ?> Stück. Dafür sind <?= (int)$plan['batches'] ?> Durchläufe nötig.</p>
            <?php if ($warehouses): ?>
                <form method="get" class="grid grid-3" style="gap:8px; align-items:end; margin:10px 0 0 0;">
                    <input type="hidden" name="item_id" value="<?= (int)$currentItemId ?>">
                    <input type="hidden" name="desired_amount" value="<?= (int)$desiredAmount ?>">
                    <div class="field-group">
                        <label for="warehouse_id">Lageransicht</label>
                        <select id="warehouse_id" name="warehouse_id" onchange="this.form.submit()">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= (int)$wh['id'] ?>" <?= $selectedWarehouseId === (int)$wh['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group" style="align-self:end; grid-column: 2 / span 2;">
                        <p class="muted" style="margin:0;">Zeigt Lagerbestände für das ausgewählte Lager und erlaubt direktes Ausbuchen.</p>
                    </div>
                </form>
            <?php endif; ?>
            <?php if ($plan['ingredients']): ?>
                <div class="table" role="table" aria-label="Materialbedarf">
                    <div class="table-row" role="row" style="display:grid; grid-template-columns: 1.2fr 0.6fr 0.6fr 0.8fr 1fr; gap:8px; font-weight:700;">
                        <div>Zutat</div>
                        <div>pro Durchlauf</div>
                        <div>Gesamtbedarf</div>
                        <div>Bestand gesamt</div>
                        <div>Bestand im ausgewählten Lager</div>
                    </div>
                    <?php foreach ($plan['ingredients'] as $ing): ?>
                        <div class="table-row" role="row" style="display:grid; grid-template-columns: 1.2fr 0.6fr 0.6fr 0.8fr 1fr; gap:8px; align-items:center;">
                            <div><?= htmlspecialchars($ing['name']) ?></div>
                            <div><?= (int)$ing['per_batch'] ?></div>
                            <div><?= (int)$ing['total_needed'] ?></div>
                            <div><?= (int)$ing['available_stock'] ?></div>
                            <div><?= isset($ing['warehouse_stock']) ? (int)$ing['warehouse_stock'] : '-' ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($warehouses): ?>
                    <div class="action-panel" style="margin-top:10px;">
                        <h4>Zutaten direkt buchen</h4>
                        <form method="post" class="grid grid-2" style="gap:10px; align-items:end;">
                            <input type="hidden" name="action" value="book_ingredients">
                            <input type="hidden" name="item_id" value="<?= (int)$currentItemId ?>">
                            <input type="hidden" name="desired_amount" value="<?= (int)$desiredAmount ?>">
                            <div class="field-group">
                                <label for="warehouse_id">Lager auswählen</label>
                                <select id="warehouse_id" name="warehouse_id" required>
                                    <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?= (int)$wh['id'] ?>" <?= $selectedWarehouseId === (int)$wh['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($wh['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="muted" style="margin:4px 0 0 0;">Die Lagerbestände beziehen sich auf die Auswahl.</p>
                            </div>
                            <div class="field-group">
                                <label for="note">Notiz</label>
                                <input id="note" name="note" type="text" placeholder="Optionaler Buchungsvermerk">
                            </div>
                            <div class="field-group" style="grid-column: 1 / span 2; align-self:end;">
                                <button class="btn btn-primary" type="submit">Zutaten aus Lager ausbuchen</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="muted" style="margin-top:8px;">Dir ist kein Lager zugeordnet. Bitte wende dich an einen Admin.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="muted" style="margin-top:6px;">Keine Zutaten hinterlegt.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="toolbar" style="margin-top:12px;">
        <a class="btn" href="/staff/warehouses.php">Zurück zur Lagerübersicht</a>
        <?php if (hasPermission('can_manage_warehouses')): ?>
            <a class="btn" href="/admin/processing_recipes.php">Rezepturen bearbeiten</a>
        <?php endif; ?>
    </div>
</div>
<?php
renderFooter();
?>