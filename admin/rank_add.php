<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_assign_ranks');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../includes/layout.php';

$allPerms = getAllPermissions();
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $perms = [];

    $permKeys = array_keys($allPerms);

    foreach ($permKeys as $key) {
        $perms[$key] = !empty($_POST['perm'][$key]) ? 1 : 0;
    }

    if ($name === '') {
        $error = "Name darf nicht leer sein.";
    } else {
        $columns = array_merge(['name', 'description'], $permKeys);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = 'INSERT INTO ranks (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';

        $values = array_merge(
            [$name, $description],
            array_map(fn($key) => $perms[$key] ?? 0, $permKeys)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        header("Location: /admin/ranks.php");
        exit;
    }
}

renderHeader('Rang anlegen', 'admin');
?>
<div class="card">
    <h2>Neuen Rang anlegen</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="name">Name</label>
            <input id="name" name="name" required>
        </div>
        <div class="field-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="2"></textarea>
        </div>

        <div class="field-group">
            <label>Rechte</label>
            <div class="perm-grid">
                <?php foreach ($allPerms as $key => $label): ?>
                    <label class="perm-item">
                        <input type="checkbox" name="perm[<?= htmlspecialchars($key) ?>]" value="1">
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
