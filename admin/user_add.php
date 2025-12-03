<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_users');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

// Ränge laden (nur wenn Berechtigung zum Zuweisen)
$ranks = [];
if (hasPermission('can_assign_ranks')) {
    $ranksStmt = $pdo->query("SELECT id, name FROM ranks ORDER BY name ASC");
    $ranks = $ranksStmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rankId = null;
    if (hasPermission('can_assign_ranks')) {
        $rankId = !empty($_POST['rank_id']) ? (int)$_POST['rank_id'] : null;
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, rank_id) VALUES (?,?,?,?,?)");
    $stmt->execute([
        $_POST['username'],
        $_POST['email'],
        password_hash($_POST['password'], PASSWORD_DEFAULT),
        $_POST['role'],
        $rankId
    ]);
    header("Location: /admin/users.php");
    exit;
}

renderHeader('Benutzer anlegen', 'admin');
?>
<div class="card">
    <h2>Neuen Benutzer anlegen</h2>
    <form method="post">
        <div class="field-group">
            <label for="username">Benutzername</label>
            <input id="username" name="username" required>
        </div>
        <div class="field-group">
            <label for="email">E-Mail</label>
            <input id="email" name="email" type="email">
        </div>
        <div class="field-group">
            <label for="password">Passwort</label>
            <input id="password" name="password" type="password" required>
        </div>
        <div class="field-group">
            <label for="role">Rolle</label>
            <select id="role" name="role">
                <option value="employee">Mitarbeiter</option>
                <option value="partner">Partner</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <?php if (!empty($ranks)): ?>
            <div class="field-group">
                <label for="rank_id">Rang</label>
                <select id="rank_id" name="rank_id">
                    <option value="">Kein Rang</option>
                    <?php foreach ($ranks as $rank): ?>
                        <option value="<?= (int)$rank['id'] ?>">
                            <?= htmlspecialchars($rank['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-secondary" href="/admin/users.php">Abbrechen</a>
    </form>
</div>
<?php
renderFooter();
