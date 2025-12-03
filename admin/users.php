<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_users');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';

// Ränge für Filter & Anzeige laden
$ranksStmt = $pdo->query("SELECT id, name FROM ranks ORDER BY name ASC");
$ranks = $ranksStmt->fetchAll();

$selectedRankId = isset($_GET['rank_id']) ? (int)$_GET['rank_id'] : 0;

$sql = "SELECT u.*, r.name AS rank_name
        FROM users u
        LEFT JOIN ranks r ON u.rank_id = r.id";
$params = [];

if ($selectedRankId > 0) {
    $sql .= " WHERE u.rank_id = ?";
    $params[] = $selectedRankId;
}

$sql .= " ORDER BY u.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

renderHeader('Benutzerverwaltung', 'admin');
?>
<div class="card">
    <h2>Benutzerverwaltung</h2>
    <p class="muted">Mitarbeiter, Partner und Admins verwalten.</p>

    <div class="filter-bar">
        <form method="get">
            <label for="rank_id">Nach Rang filtern:</label>
            <select id="rank_id" name="rank_id" onchange="this.form.submit()">
                <option value="0">Alle Ränge</option>
                <?php foreach ($ranks as $rank): ?>
                    <option value="<?= (int)$rank['id'] ?>" <?= $selectedRankId === (int)$rank['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rank['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <a class="btn btn-primary" href="/admin/user_add.php">Neuen Benutzer anlegen</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>E-Mail</th>
                <th>Rolle</th>
                <th>Rang</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php $roleClass = 'pill-role-' . htmlspecialchars($u['role']); ?>
                        <span class="pill <?= $roleClass ?>"><?= htmlspecialchars($u['role']) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($u['rank_name'])): ?>
                            <span class="badge-rank"><?= htmlspecialchars($u['rank_name']) ?></span>
                        <?php else: ?>
                            <span class="muted">kein Rang</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn-link" href="/admin/user_edit.php?id=<?= (int)$u['id'] ?>">Bearbeiten</a> |
                        <a class="btn-link" href="/admin/user_delete.php?id=<?= (int)$u['id'] ?>" onclick="return confirm('Benutzer wirklich löschen?');">Löschen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();
