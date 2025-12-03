<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

ensureWarehouseSchema($pdo);

$error = '';
$ranks = getAllRanks($pdo);
$selectedRanks = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $selectedRanks = array_map('intval', $_POST['ranks'] ?? []);

    if ($name === '') {
        $error = 'Name darf nicht leer sein.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO warehouses (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $warehouseId = (int)$pdo->lastInsertId();

        if (!empty($selectedRanks)) {
            $ins = $pdo->prepare("INSERT INTO warehouse_ranks (warehouse_id, rank_id) VALUES (?, ?)");
            foreach ($selectedRanks as $rid) {
                $ins->execute([$warehouseId, $rid]);
            }
        }

        header('Location: /admin/warehouses.php');
        exit;
    }
}

renderHeader('Lager anlegen', 'admin');
?>
<div class="card">
    <h2>Neues Lager anlegen</h2>
    <p class="muted">Definiere ein Lager und entscheide, welche Ränge darauf zugreifen dürfen.</p>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="name">Name</label>
            <input id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="field-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
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
            <p class="muted">Admin-Ränge können jederzeit zugreifen, sofern sie das Recht zum Lagermanagement haben.</p>
        </div>
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-secondary" href="/admin/warehouses.php">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();