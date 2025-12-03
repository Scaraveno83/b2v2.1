<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_edit_tickets');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo "Ticket nicht gefunden.";
    exit;
}

// Kategorien
$catStmt = $pdo->query("SELECT id, name FROM ticket_categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? $ticket['priority'];
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if ($title === '') {
        $error = "Titel darf nicht leer sein.";
    } else {
        $up = $pdo->prepare("UPDATE tickets SET title = ?, description = ?, priority = ?, category_id = ? WHERE id = ?");
        $up->execute([$title, $description, $priority, $categoryId, $id]);

        $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
        $log->execute([$id, $_SESSION['user']['id'] ?? null, 'edit', 'Ticketdetails aktualisiert']);

        header("Location: /admin/ticket_view.php?id=" . $id);
        exit;
    }
}

renderHeader('Ticket bearbeiten', 'admin');
?>
<div class="card">
    <h2>Ticket bearbeiten</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="title">Titel</label>
            <input id="title" name="title" value="<?= htmlspecialchars($ticket['title']) ?>" required>
        </div>
        <div class="field-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="5"><?= htmlspecialchars($ticket['description']) ?></textarea>
        </div>
        <div class="field-group">
            <label for="priority">Priorität</label>
            <select id="priority" name="priority">
                <option value="low" <?= $ticket['priority']==='low'?'selected':'' ?>>Niedrig</option>
                <option value="medium" <?= $ticket['priority']==='medium'?'selected':'' ?>>Mittel</option>
                <option value="high" <?= $ticket['priority']==='high'?'selected':'' ?>>Hoch</option>
                <option value="urgent" <?= $ticket['priority']==='urgent'?'selected':'' ?>>Dringend</option>
            </select>
        </div>
        <div class="field-group">
            <label for="category_id">Kategorie</label>
            <select id="category_id" name="category_id">
                <option value="">Keine</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $ticket['category_id']==$cat['id']?'selected':'' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-secondary" href="/admin/ticket_view.php?id=<?= (int)$ticket['id'] ?>">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();
