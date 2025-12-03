<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_ticket_categories');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ticket_categories WHERE id = ?");
$stmt->execute([$id]);
$cat = $stmt->fetch();

if (!$cat) {
    echo "Kategorie nicht gefunden.";
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = "Name darf nicht leer sein.";
    } else {
        $up = $pdo->prepare("UPDATE ticket_categories SET name = ? WHERE id = ?");
        $up->execute([$name, $id]);
        header("Location: /admin/ticket_categories.php");
        exit;
    }
}

renderHeader('Kategorie bearbeiten', 'admin');
?>
<div class="card">
    <h2>Kategorie bearbeiten</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="name">Name der Kategorie</label>
            <input id="name" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
        </div>
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-secondary" href="/admin/ticket_categories.php">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();
