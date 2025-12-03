<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_assign_ranks');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../includes/layout.php';

$allPerms = getAllPermissions();
$error = "";

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ranks WHERE id = ?");
$stmt->execute([$id]);
$rank = $stmt->fetch();

if (!$rank) {
    echo "Rang nicht gefunden.";
    exit;
}

// Administrator-Rang ist geschützt
if ($rank['name'] === 'Administrator') {
    echo "Der Rang 'Administrator' kann nicht bearbeitet werden.";
    exit;
}

$permsCurrent = [];
foreach ($allPerms as $key => $label) {
    $permsCurrent[$key] = !empty($rank[$key]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $permKeys = array_keys($allPerms);
    $perms = [];

    foreach ($permKeys as $key) {
        $perms[$key] = !empty($_POST['perm'][$key]) ? 1 : 0;
    }

    if ($name === '') {
        $error = "Name darf nicht leer sein.";
    } else {
        $setParts = ['name = ?', 'description = ?'];
        foreach ($permKeys as $pk) {
            $setParts[] = $pk . ' = ?';
        }

        $sql = 'UPDATE ranks SET ' . implode(",\n            ", $setParts) . ' WHERE id = ?';

        $values = array_merge(
            [$name, $description],
            array_map(fn($key) => $perms[$key] ?? 0, $permKeys),
            [$id]
        );

        $stmtUp = $pdo->prepare($sql);
        $stmtUp->execute($values);
        header("Location: /admin/ranks.php");
        exit;
    }
}

renderHeader('Rang bearbeiten', 'admin');
?>
<div class="card">
    <h2>Rang bearbeiten</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="name">Name</label>
            <input id="name" name="name" value="<?= htmlspecialchars($rank['name']) ?>" required>
        </div>
        <div class="field-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="2"><?= htmlspecialchars($rank['description']) ?></textarea>
        </div>

        <div class="field-group">
            <label>Rechte</label>
            <div class="perm-grid">
                <?php foreach ($allPerms as $key => $label): ?>
                    <label class="perm-item">
                        <input type="checkbox" name="perm[<?= htmlspecialchars($key) ?>]" value="1"
                            <?= !empty($permsCurrent[$key]) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-secondary" href="/admin/ranks.php">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();
