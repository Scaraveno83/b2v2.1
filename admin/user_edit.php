<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_users');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    echo "Benutzer nicht gefunden.";
    exit;
}

// Ränge laden
$ranks = [];
if (hasPermission('can_assign_ranks')) {
    $ranksStmt = $pdo->query("SELECT id, name FROM ranks ORDER BY name ASC");
    $ranks = $ranksStmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "UPDATE users SET username = ?, email = ?, role = ?";
    $params = [$_POST['username'], $_POST['email'], $_POST['role']];

    if (!empty($_POST['password'])) {
        $sql .= ", password_hash = ?";
        $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    if (hasPermission('can_assign_ranks')) {
        $rankId = !empty($_POST['rank_id']) ? (int)$_POST['rank_id'] : null;
        $sql .= ", rank_id = ?";
        $params[] = $rankId;
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: /admin/users.php");
    exit;
}

renderHeader('Benutzer bearbeiten', 'admin');
?>
<div class="card">
    <h2>Benutzer bearbeiten</h2>
    <form method="post">
        <div class="field-group">
            <label for="username">Benutzername</label>
            <input id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        <div class="field-group">
            <label for="email">E-Mail</label>
            <input id="email" name="email" type="email" value="<?= htmlspecialchars($user['email']) ?>">
        </div>
        <div class="field-group">
            <label for="password">Neues Passwort (leer lassen = unverändert)</label>
            <input id="password" name="password" type="password">
        </div>
        <div class="field-group">
            <label for="role">Rolle</label>
            <select id="role" name="role">
                <option value="employee" <?= $user['role']==='employee'?'selected':'' ?>>Mitarbeiter</option>
                <option value="partner" <?= $user['role']==='partner'?'selected':'' ?>>Partner</option>
                <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
            </select>
        </div>
        <?php if (!empty($ranks)): ?>
            <div class="field-group">
                <label for="rank_id">Rang</label>
                <select id="rank_id" name="rank_id">
                    <option value="">Kein Rang</option>
                    <?php foreach ($ranks as $rank): ?>
                        <option value="<?= (int)$rank['id'] ?>" <?= $user['rank_id'] == $rank['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rank['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <p class="muted">Keine Ränge verfügbar oder keine Berechtigung zum Zuweisen.</p>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-secondary" href="/admin/users.php">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();
