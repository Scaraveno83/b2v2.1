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

$processableItems = getProcessableItems($pdo);
$processableIds = array_map(static fn($row) => (int)$row['id'], $processableItems);
$currentItemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? ($processableIds[0] ?? 0));
$desiredAmount = max(1, (int)($_POST['desired_amount'] ?? 1));
$message = '';
$plan = null;

if ($currentItemId && in_array($currentItemId, $processableIds, true)) {
    $plan = calculateProcessingNeeds($pdo, $currentItemId, $desiredAmount);
    if (!$plan) {
        $message = 'Für diesen Artikel ist noch keine Rezeptur hinterlegt. Bitte kontaktiere einen Admin.';
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
            <?php if ($plan['ingredients']): ?>
                <div class="table" role="table" aria-label="Materialbedarf">
                    <div class="table-row" role="row" style="display:grid; grid-template-columns: 1.2fr 0.6fr 0.6fr 0.8fr; gap:8px; font-weight:700;">
                        <div>Zutat</div>
                        <div>pro Durchlauf</div>
                        <div>Gesamtbedarf</div>
                        <div>Bestand gesamt</div>
                    </div>
                    <?php foreach ($plan['ingredients'] as $ing): ?>
                        <div class="table-row" role="row" style="display:grid; grid-template-columns: 1.2fr 0.6fr 0.6fr 0.8fr; gap:8px; align-items:center;">
                            <div><?= htmlspecialchars($ing['name']) ?></div>
                            <div><?= (int)$ing['per_batch'] ?></div>
                            <div><?= (int)$ing['total_needed'] ?></div>
                            <div><?= (int)$ing['available_stock'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
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