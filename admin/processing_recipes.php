<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

ensureWarehouseSchema($pdo);

$processableItems = getProcessableItems($pdo);
$processableIds = array_map(static fn($row) => (int)$row['id'], $processableItems);
$currentItemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? ($processableIds[0] ?? 0));
$message = '';

function findItemById(array $items, int $id): ?array {
    foreach ($items as $item) {
        if ((int)$item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($currentItemId, $processableIds, true)) {
        $message = 'Bitte zuerst einen Artikel auswählen, der für die Weiterverarbeitung freigegeben ist.';
    } else {
        if ($action === 'set_output') {
            $output = max(1, (int)($_POST['output_quantity'] ?? 1));
            setProcessingOutputQuantity($pdo, $currentItemId, $output);
            $message = 'Ausbeute pro Durchlauf gespeichert.';
        }

        if ($action === 'add_ingredient') {
            $ingredientItemId = (int)($_POST['ingredient_item_id'] ?? 0);
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            $allItems = getAllItems($pdo);
            $ingredient = findItemById($allItems, $ingredientItemId);
            if ($ingredient) {
                $recipeId = ensureProcessingRecipe($pdo, $currentItemId);
                upsertProcessingIngredient($pdo, $recipeId, $ingredientItemId, $quantity);
                $message = 'Zutat gespeichert.';
            } else {
                $message = 'Gewählte Zutat konnte nicht gefunden werden.';
            }
        }

        if ($action === 'delete_ingredient') {
            $ingredientItemId = (int)($_POST['ingredient_item_id'] ?? 0);
            $recipe = getProcessingRecipeDetails($pdo, $currentItemId);
            if ($recipe) {
                deleteProcessingIngredient($pdo, $recipe['id'], $ingredientItemId);
                $message = 'Zutat entfernt.';
            }
        }
    }

    $processableItems = getProcessableItems($pdo);
    $processableIds = array_map(static fn($row) => (int)$row['id'], $processableItems);
    if (!in_array($currentItemId, $processableIds, true)) {
        $currentItemId = $processableIds[0] ?? 0;
    }
}

$currentItem = $currentItemId ? findItemById($processableItems, $currentItemId) : null;
$recipe = $currentItemId ? getProcessingRecipeDetails($pdo, $currentItemId) : null;
$allItems = getAllItems($pdo);

renderHeader('Weiterverarbeitungsmodul', 'admin');
?>
<div class="card">
    <h2>Weiterverarbeitungsmodul</h2>
    <p class="muted">Markiere Artikel als weiterverarbeitbar und lege fest, welche Zutaten pro Durchlauf benötigt werden.</p>

    <?php if ($message): ?>
        <div class="notice"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!$processableItems): ?>
        <div class="error">Noch keine Artikel für die Weiterverarbeitung freigegeben. Setze die Option in der Artikelverwaltung.</div>
        <div class="toolbar" style="margin-top:10px;">
            <a class="btn" href="/admin/warehouses.php">Zur Lagerverwaltung</a>
        </div>
    <?php else: ?>
        <form method="get" class="grid grid-2" style="gap:12px; align-items:end; margin-bottom:12px;">
            <div class="field-group">
                <label for="item_id">Artikel auswählen</label>
                <select id="item_id" name="item_id" onchange="this.form.submit()">
                    <?php foreach ($processableItems as $item): ?>
                        <option value="<?= (int)$item['id'] ?>" <?= $currentItemId === (int)$item['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button class="btn" type="submit">Ansicht laden</button>
            </div>
        </form>

        <?php if ($currentItem): ?>
            <div class="grid grid-2" style="gap:12px; align-items:start;">
                <div class="action-panel">
                    <h3><?= htmlspecialchars($currentItem['name']) ?></h3>
                    <p class="muted">Definiere, wie viele Einheiten pro Durchlauf entstehen.</p>
                    <form method="post" class="grid grid-2" style="gap:8px; align-items:end;">
                        <input type="hidden" name="item_id" value="<?= (int)$currentItemId ?>">
                        <input type="hidden" name="action" value="set_output">
                        <div class="field-group">
                            <label for="output_quantity">Ausbeute pro Durchlauf</label>
                            <input id="output_quantity" name="output_quantity" type="number" min="1" value="<?= $recipe ? (int)$recipe['output_quantity'] : 1 ?>" required>
                        </div>
                        <div class="field-group" style="align-self:end;">
                            <button class="btn btn-primary" type="submit">Speichern</button>
                        </div>
                    </form>
                    <?php if ($recipe && $recipe['ingredients']): ?>
                        <div class="muted" style="margin-top:6px;">
                            1 Durchlauf ergibt <?= (int)$recipe['output_quantity'] ?>× <?= htmlspecialchars($currentItem['name']) ?>.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-panel">
                    <h3>Zutaten festlegen</h3>
                    <p class="muted">Hinterlege die benötigten Bauteile, Rohstoffe oder Werkzeuge pro Durchlauf.</p>

                    <?php if ($recipe && $recipe['ingredients']): ?>
                        <div class="table" role="table" aria-label="Zutatenliste">
                            <?php foreach ($recipe['ingredients'] as $ingredient): ?>
                                <div class="table-row" role="row" style="display:grid; grid-template-columns: 1fr 120px 160px; gap:8px; align-items:center;">
                                    <div><?= htmlspecialchars($ingredient['ingredient_name']) ?></div>
                                    <div><?= (int)$ingredient['quantity'] ?> pro Durchlauf</div>
                                    <div style="display:flex; gap:6px;">
                                        <form method="post" class="grid grid-2" style="gap:6px; align-items:center; margin:0;">
                                            <input type="hidden" name="item_id" value="<?= (int)$currentItemId ?>">
                                            <input type="hidden" name="ingredient_item_id" value="<?= (int)$ingredient['ingredient_item_id'] ?>">
                                            <input type="hidden" name="action" value="add_ingredient">
                                            <input type="number" name="quantity" min="1" value="<?= (int)$ingredient['quantity'] ?>" style="width:90px;">
                                            <button class="btn" type="submit">Aktualisieren</button>
                                        </form>
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="item_id" value="<?= (int)$currentItemId ?>">
                                            <input type="hidden" name="ingredient_item_id" value="<?= (int)$ingredient['ingredient_item_id'] ?>">
                                            <input type="hidden" name="action" value="delete_ingredient">
                                            <button class="btn btn-secondary" type="submit">Entfernen</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="muted" style="margin:6px 0;">Noch keine Zutaten hinterlegt.</div>
                    <?php endif; ?>

                    <form method="post" class="grid grid-3" style="gap:8px; align-items:end; margin-top:10px;">
                        <input type="hidden" name="item_id" value="<?= (int)$currentItemId ?>">
                        <input type="hidden" name="action" value="add_ingredient">
                        <div class="field-group">
                            <label for="ingredient_item_id">Neue Zutat</label>
                            <select id="ingredient_item_id" name="ingredient_item_id" required>
                                <option value="" disabled selected>Artikel wählen</option>
                                <?php foreach ($allItems as $item): ?>
                                    <?php if ((int)$item['id'] === $currentItemId) { continue; } ?>
                                    <option value="<?= (int)$item['id'] ?>"><?= htmlspecialchars($item['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="quantity">Menge pro Durchlauf</label>
                            <input id="quantity" name="quantity" type="number" min="1" value="1" required>
                        </div>
                        <div class="field-group" style="align-self:end;">
                            <button class="btn btn-primary" type="submit">Zutat hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($recipe): ?>
                <div class="action-panel" style="margin-top:12px;">
                    <h4>Beispielkalkulation</h4>
                    <p class="muted">Nutze diese Übersicht, um den Materialbedarf vorab zu planen.</p>
                    <?php $preview = calculateProcessingNeeds($pdo, $currentItemId, max(1, (int)$recipe['output_quantity'])); ?>
                    <?php if ($preview && $preview['ingredients']): ?>
                        <div class="table" role="table" aria-label="Kalkulation">
                            <?php foreach ($preview['ingredients'] as $need): ?>
                                <div class="table-row" role="row" style="display:grid; grid-template-columns: 1fr 160px 200px; gap:8px; align-items:center;">
                                    <div><?= htmlspecialchars($need['name']) ?></div>
                                    <div><?= (int)$need['per_batch'] ?> pro Durchlauf</div>
                                    <div>Aktueller Gesamtbestand: <?= (int)$need['available_stock'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="muted">Keine Zutaten hinterlegt.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="toolbar" style="margin-top:12px;">
        <a class="btn" href="/admin/warehouses.php">Zurück zur Lagerübersicht</a>
        <a class="btn" href="/admin/warehouse_items.php">Artikel verwalten</a>
    </div>
</div>
<?php
renderFooter();
?>