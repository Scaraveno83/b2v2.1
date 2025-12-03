<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_partners');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/partner_service.php';

ensurePartnerSchema($pdo);

$stmt = $pdo->query("SELECT u.id, u.username, u.email, pc.contract_title, pc.billing_mode, pc.created_at
    FROM users u
    LEFT JOIN partner_contracts pc ON pc.partner_id = u.id
    WHERE u.role = 'partner'
    ORDER BY u.username ASC");
$partners = $stmt->fetchAll();

renderHeader('Partnerverwaltung', 'admin');
?>
<div class="card">
    <h2>Vertragspartner</h2>
    <p class="muted">Partner erfassen, Abrechnungsmodus pflegen und Sonderpreise definieren.</p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Partner</th>
                <th>Email</th>
                <th>Vertrag</th>
                <th>Abrechnung</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partners as $p): ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><?= htmlspecialchars($p['username']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= htmlspecialchars($p['contract_title'] ?? 'Kein Vertrag hinterlegt') ?></td>
                    <td>
                        <?php if (!empty($p['billing_mode'])): ?>
                            <span class="pill pill-role-employee"><?= $p['billing_mode'] === 'weekly' ? 'Wochenabrechnung' : 'Standard' ?></span>
                        <?php else: ?>
                            <span class="muted">nicht gesetzt</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn-link" href="/admin/partner_edit.php?id=<?= (int)$p['id'] ?>">Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();