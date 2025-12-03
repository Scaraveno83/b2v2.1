<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

ensureWarehouseSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM warehouses WHERE id = ?');
$stmt->execute([$id]);
$warehouse = $stmt->fetch();

if (!$warehouse) {
    echo 'Lager nicht gefunden.';
    exit;
}

$error = '';
$ranks = getAllRanks($pdo);
$selectedRanks = getWarehouseRankIds($pdo, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $selectedRanks = array_map('intval', $_POST['ranks'] ?? []);

    if ($name === '') {
        $error = 'Name darf nicht leer sein.';
    } else {
        $upd = $pdo->prepare('UPDATE warehouses SET name = ?, description = ? WHERE id = ?');
        $upd->execute([$name, $description, $id]);

        $pdo->prepare('DELETE FROM warehouse_ranks WHERE warehouse_id = ?')->execute([$id]);
        if (!empty($selectedRanks)) {
            $ins = $pdo->prepare('INSERT INTO warehouse_ranks (warehouse_id, rank_id) VALUES (?, ?)');
            foreach ($selectedRanks as $rid) {
                $ins->execute([$id, $rid]);
            }
        }

        header('Location: /admin/warehouses.php');
        exit;
    }
}

renderHeader('Lager bearbeiten', 'admin');
?>
<div class="card">
    <h2>Lager bearbeiten</h2>
    <p class="muted">Passe Name, Beschreibung und Zugriffsrechte des Lagers an.</p>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="name">Name</label>
            <input id="name" name="name" value="<?= htmlspecialchars($warehouse['name']) ?>" required>
        </div>
        <div class="field-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="2"><?= htmlspecialchars($warehouse['description'] ?? '') ?></textarea>
        </div>
        <div class="field-group">
            <label>Zugriff für Ränge</label>
            <div class="perm-grid">
                <?php foreach ($ranks as $rank): ?>
                    <label class="perm-item">
                        <input type="checkbox" name="ranks[]" value="<?= (int)$rank['id'] ?>" <?= in_array((int)$rank['id'], $selectedRanks, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($rank['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="toolbar">
            <button class="btn btn-primary" type="submit">Speichern</button>
            <a class="btn btn-secondary" href="/admin/warehouses.php">Abbrechen</a>
            <a class="btn" href="/admin/warehouse_items.php?warehouse_id=<?= (int)$id ?>">Artikel verwalten</a>
        </div>
    </form>
</div>
<?php
renderFooter();