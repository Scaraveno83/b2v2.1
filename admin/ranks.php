<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_assign_ranks');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../includes/layout.php';

$allPerms = getAllPermissions();

$stmt = $pdo->query("SELECT * FROM ranks ORDER BY name ASC");
$ranks = $stmt->fetchAll();

renderHeader('Ränge verwalten', 'admin');
?>
<div class="card">
    <h2>Ränge</h2>
    <p class="muted">Ränge sind Gruppen von Rechten, die Benutzern zugewiesen werden können.</p>
    <a class="btn btn-primary" href="/admin/rank_add.php">Neuen Rang anlegen</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Aktive Rechte</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ranks as $rank): ?>
                <?php
                $activeCount = 0;
                foreach ($allPerms as $key => $label) {
                    if (!empty($rank[$key])) {
                        $activeCount++;
                    }
                }
                ?>
                <tr>
                    <td><?= (int)$rank['id'] ?></td>
                    <td><?= htmlspecialchars($rank['name']) ?></td>
                    <td><?= $activeCount ?> / <?= count($allPerms) ?></td>
                    <td>
                        <?php if ($rank['name'] === 'Administrator'): ?>
                            <span class="muted">Systemrang</span>
                        <?php else: ?>
                            <a class="btn-link" href="/admin/rank_edit.php?id=<?= (int)$rank['id'] ?>">Bearbeiten</a> |
                            <a class="btn-link" href="/admin/rank_delete.php?id=<?= (int)$rank['id'] ?>" onclick="return confirm('Rang wirklich löschen?');">Löschen</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();
