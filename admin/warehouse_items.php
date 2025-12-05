<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

ensureWarehouseSchema($pdo);

$warehouseId = (int)($_GET['warehouse_id'] ?? $_POST['warehouse_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM warehouses WHERE id = ?');
$stmt->execute([$warehouseId]);
$warehouse = $stmt->fetch();

if (!$warehouse) {
    echo 'Lager nicht gefunden.';
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $min = (int)($_POST['min_stock'] ?? 0);
        $max = (int)($_POST['max_stock'] ?? 0);
        $stock = (int)($_POST['current_stock'] ?? 0);
        $farmable = isset($_POST['farmable']) && $_POST['farmable'] === '1';
        $price = max(0, (float)($_POST['price'] ?? 0));

        if ($name === '') {
            $message = 'Artikelname darf nicht leer sein.';
        } else {
            $itemId = createOrUpdateItem($pdo, $name, $description, $min, $max, $farmable, $price);
            ensureWarehouseItemLink($pdo, $warehouseId, $itemId);
            $note = 'Erstanlage';
            if ($stock !== 0) {
                adjustWarehouseStock($pdo, $warehouseId, $itemId, $stock, 'create', $_SESSION['user']['id'] ?? null, $note);
            } else {
                $currentStock = getWarehouseStock($pdo, $warehouseId, $itemId);
                logWarehouseChange($pdo, $warehouseId, $itemId, $_SESSION['user']['id'] ?? null, 0, 'create', $note, $currentStock);
            }
            syncFarmingTasksForItem($pdo, $itemId);
            $message = 'Artikel angelegt oder bestehender Artikel aktualisiert.';
        }
    }

    if ($action === 'update_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $min = (int)($_POST['min_stock'] ?? 0);
        $max = (int)($_POST['max_stock'] ?? 0);
        $farmable = isset($_POST['farmable']) && $_POST['farmable'] === '1';
        $price = max(0, (float)($_POST['price'] ?? 0));

        if ($name === '') {
            $message = 'Artikelname darf nicht leer sein.';
        } else {
            $stmt = $pdo->prepare('UPDATE items SET name = ?, description = ?, min_stock = ?, max_stock = ?, farmable = ?, price = ? WHERE id = ?');
            $stmt->execute([$name, $description, $min, $max, $farmable ? 1 : 0, $price, $itemId]);
            syncFarmingTasksForItem($pdo, $itemId);
            $message = 'Artikel aktualisiert (global).';
        }
    }

    if ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        deleteItemCompletely($pdo, $itemId);
        $message = 'Artikel gelöscht (entfernt aus allen Lagern).';
    }

    if (in_array($action, ['add_stock', 'remove_stock'], true)) {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $note = trim($_POST['note'] ?? '');
        if ($quantity > 0) {
            $delta = ($action === 'add_stock') ? $quantity : -$quantity;
            if (adjustWarehouseStock($pdo, $warehouseId, $itemId, $delta, $action, $_SESSION['user']['id'] ?? null, $note)) {
                $message = 'Bestand aktualisiert.';
            } else {
                $message = 'Artikel konnte nicht gefunden werden.';
            }
        }
    }
}

$items = getWarehouseItems($pdo, $warehouseId);
$searchQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

renderHeader('Lagerartikel', 'admin');
?>
<div class="card">
    <h2>Artikel für <?= htmlspecialchars($warehouse['name']) ?></h2>
    <p class="muted">Artikel werden global mit einem Min-/Max-Bestand angelegt. Die Bestandswarnungen beziehen den Gesamtbestand über alle Lager ein.</p>
    <?php if ($message): ?>
        <div class="notice"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <style>
        .item-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 12px; }
        .item-card { border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; background: rgba(255,255,255,0.02); overflow: hidden; }
        .item-card header { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 12px; cursor: pointer; }
        .item-card .card-title { display: flex; flex-direction: column; gap: 4px; }
        .item-card .stock { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0; align-items: center; }
        .item-card .badge { background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 6px; font-size: 12px; }
        .action-group { display: grid; gap: 8px; }
        .action-group form { display: grid; gap: 6px; background: rgba(255,255,255,0.03); padding: 8px; border-radius: 0 0 8px 8px; border-top: 1px solid rgba(255,255,255,0.05); }
        .action-group label { font-size: 12px; color: #cfd8dc; }
        .action-group input[type="number"], .action-group input[type="text"] { width: 100%; }
        .item-body { display: none; padding: 0 12px 12px 12px; border-top: 1px solid rgba(255,255,255,0.05); }
        .item-card.open .item-body { display: block; }
        .toggle-indicator { font-size: 12px; color: #cfd8dc; display: inline-flex; align-items: center; gap: 6px; }
        .muted { color: #a0a8b3; }
    </style>

    <h3>Neuen Artikel hinzufügen</h3>
    <form method="post" class="grid grid-2" style="gap:12px;">
        <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId ?>">
        <input type="hidden" name="action" value="add_item">
        <div class="field-group">
            <label for="name">Name</label>
            <input id="name" name="name" required>
        </div>
        <div class="field-group">
            <label for="description">Beschreibung</label>
            <input id="description" name="description">
        </div>
        <div class="field-group">
            <label for="price">Stückpreis (€)</label>
            <input id="price" name="price" type="number" min="0" step="0.01" value="0">
        </div>
        <div class="field-group">
            <label for="min_stock">Mindestbestand</label>
            <input id="min_stock" name="min_stock" type="number" min="0" value="0">
        </div>
        <div class="field-group">
            <label for="max_stock">Höchstbestand</label>
            <input id="max_stock" name="max_stock" type="number" min="0" value="0">
        </div>
        <div class="field-group" style="display:flex; flex-direction:column; justify-content:center; gap:4px;">
            <label class="checkbox">
                <input type="checkbox" name="farmable" value="1">
                <span>Artikel ist farmbar</span>
            </label>
            <small class="muted">Nur farmbare Artikel erzeugen Farming-Aufgaben, wenn der Mindestbestand unterschritten wird.</small>
        </div>
        <div class="field-group">
            <label for="current_stock">Startbestand</label>
            <input id="current_stock" name="current_stock" type="number" min="0" value="0">
        </div>
        <div class="field-group" style="align-self:end;">
            <button class="btn btn-primary" type="submit">Artikel anlegen</button>
        </div>
    </form>

    <h3 style="margin-top:20px;">Bestehende Artikel</h3>
    <form method="get" class="grid grid-3" style="gap:12px; align-items:end; margin-bottom:12px;">
        <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId ?>">
        <div class="field-group">
            <label for="q">Artikel filtern</label>
            <input id="q" name="q" placeholder="Name oder Beschreibung" value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
        <div></div>
        <div>
            <button class="btn" type="submit">Filtern</button>
        </div>
    </form>

    <?php
    if ($searchQuery !== '') {
        $items = array_filter($items, static function ($item) use ($searchQuery) {
            $haystack = strtolower(($item['name'] ?? '') . ' ' . ($item['description'] ?? ''));
            return strpos($haystack, strtolower($searchQuery)) !== false;
        });
    }
    ?>

    <?php if (!$items): ?>
        <div class="muted">Noch keine Artikel angelegt oder keine Treffer.</div>
    <?php else: ?>
        <div class="item-grid">
            <?php foreach ($items as $item): ?>
                <div class="item-card" data-item-card>
                    <header data-toggle-card>
                        <div class="card-title">
                            <div><strong><?= htmlspecialchars($item['name']) ?></strong></div>
                            <div class="muted" style="margin-top:2px;">
                                <?= $item['description'] ? htmlspecialchars($item['description']) : 'Keine Beschreibung' ?>
                            </div>
                        </div>
                        <div class="toggle-indicator">
                            <span>ID: <?= (int)$item['id'] ?></span>
                            <span aria-hidden="true">▾</span>
                        </div>
                    </header>

                   <div class="item-body">
                        <div class="stock">
                            <div><strong><?= (int)$item['current_stock'] ?></strong> Stück im Lager</div>
                            <div><strong><?= (int)$item['total_stock'] ?></strong> Stück gesamt</div>
                            <div class="badge">Min <?= (int)$item['min_stock'] ?></div>
                            <div class="badge">Max <?= (int)$item['max_stock'] ?></div>
                            <div class="badge">Preis <?= number_format((float)($item['price'] ?? 0), 2, ',', '.') ?> €</div>
                            <?php if ($item['farmable']): ?>
                                <div class="badge" style="background:rgba(76,175,80,0.15); color:#b2ffb2;">Farmbar</div>
                            <?php endif; ?>
                            <?php if ($item['total_stock'] < $item['min_stock']): ?>
                                <div class="error" style="margin-top:6px;">Unter Mindestbestand (gesamt)!</div>
                            <?php elseif ($item['max_stock'] > 0 && $item['total_stock'] > $item['max_stock']): ?>
                                <div class="notice" style="margin-top:6px;">Über Höchstbestand (gesamt).</div>
                            <?php endif; ?>
                        </div>

                        <div class="action-group">
                            <form method="post">
                                <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId ?>">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="action" value="add_stock">
                                <label>Menge hinzufügen</label>
                                <input type="number" name="quantity" min="1" value="1">
                                <input type="text" name="note" placeholder="Notiz (optional)">
                                <button class="btn btn-primary" type="submit">+ Hinzufügen</button>
                            </form>

                            <form method="post">
                                <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId ?>">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="action" value="remove_stock">
                                <label>Menge entnehmen</label>
                                <input type="number" name="quantity" min="1" value="1">
                                <input type="text" name="note" placeholder="Notiz (optional)">
                                <button class="btn" type="submit">- Entnehmen</button>
                            </form>

                            <form method="post">
                                <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId ?>">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="action" value="update_item">
                                <label>Details bearbeiten</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
                                <div class="grid grid-2" style="gap:8px;">
                                    <input type="number" name="min_stock" min="0" value="<?= (int)$item['min_stock'] ?>" title="Mindestbestand">
                                    <input type="number" name="max_stock" min="0" value="<?= (int)$item['max_stock'] ?>" title="Höchstbestand">
                                </div>
                                <label for="price_<?= (int)$item['id'] ?>">Stückpreis (€)</label>
                                <input id="price_<?= (int)$item['id'] ?>" type="number" name="price" min="0" step="0.01" value="<?= htmlspecialchars(number_format((float)($item['price'] ?? 0), 2, '.', '')) ?>" title="Stückpreis in Euro">
                                <input type="text" name="description" value="<?= htmlspecialchars($item['description'] ?? '') ?>" placeholder="Beschreibung">
                                <label class="checkbox" style="margin:4px 0;">
                                    <input type="checkbox" name="farmable" value="1" <?= $item['farmable'] ? 'checked' : '' ?>>
                                    <span>Artikel ist farmbar</span>
                                </label>
                                <button class="btn" type="submit">Details speichern</button>
                            </form>

                            <form method="post" onsubmit="return confirm('Artikel wirklich löschen? Er wird in allen Lagern entfernt.');">
                                <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId ?>">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="action" value="delete_item">
                                <button class="btn btn-secondary" type="submit">Artikel löschen</button>
                            </form>

                            <?php $distribution = getItemDistribution($pdo, (int)$item['id']); ?>
                            <div class="muted" style="font-size:12px; margin-top:6px;">
                                <strong>Bestandsverteilung:</strong>
                                <?php foreach ($distribution as $dist): ?>
                                    <div><?= htmlspecialchars($dist['name']) ?>: <?= (int)$dist['current_stock'] ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="toolbar" style="margin-top:12px;">
        <a class="btn" href="/admin/warehouses.php">Zurück zur Übersicht</a>
        <a class="btn" href="/admin/warehouse_logs.php?warehouse_id=<?= (int)$warehouseId ?>">Historie ansehen</a>
    </div>
</div>
<?php
renderFooter();
?>
<script>
    document.querySelectorAll('[data-item-card]').forEach(function (card) {
        var toggleArea = card.querySelector('[data-toggle-card]');
        var indicator = toggleArea ? toggleArea.querySelector('.toggle-indicator span:last-child') : null;
        function updateIndicator() {
            if (indicator) {
                indicator.textContent = card.classList.contains('open') ? '▾' : '▸';
            }
        }
        if (toggleArea) {
            toggleArea.addEventListener('click', function () {
                card.classList.toggle('open');
                updateIndicator();
            });
        }
        updateIndicator();
    });
</script>