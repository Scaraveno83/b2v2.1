<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_create_tickets');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

// Kategorien laden
$catStmt = $pdo->query("SELECT id, name FROM ticket_categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if ($title === '') {
        $error = "Titel darf nicht leer sein.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO tickets (title, description, priority, category_id, created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([
            $title,
            $description,
            $priority,
            $categoryId,
            $_SESSION['user']['id'] ?? 0
        ]);
        $ticketId = $pdo->lastInsertId();

        // Log-Eintrag
        $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
        $log->execute([$ticketId, $_SESSION['user']['id'] ?? null, 'create', 'Ticket erstellt']);

        header("Location: /admin/ticket_view.php?id=" . (int)$ticketId);
        exit;
    }
}

renderHeader('Ticket erstellen', 'admin');
?>
<div class="card">
    <h2>Neues Ticket</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field-group">
            <label for="title">Titel</label>
            <input id="title" name="title" required>
        </div>
        <div class="field-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="5"></textarea>
        </div>
        <div class="field-group">
            <label for="priority">Priorität</label>
            <select id="priority" name="priority">
                <option value="low">Niedrig</option>
                <option value="medium" selected>Mittel</option>
                <option value="high">Hoch</option>
                <option value="urgent">Dringend</option>
            </select>
        </div>
        <div class="field-group">
            <label for="category_id">Kategorie</label>
            <select id="category_id" name="category_id">
                <option value="">Keine</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Ticket erstellen</button>
        <a class="btn btn-secondary" href="/admin/tickets.php">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();
