<?php
require_once __DIR__ . '/auth/check_role.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/partner_service.php';
if (isset($_SESSION['user'])) {
    requireAbsenceAccess('services');
}

$services = getAllPartnerServices($pdo, true);
renderHeader('Services & Preise', 'services');
?>
<div class="card">
    <h2>Services & Preise</h2>
    <p class="muted">Unsere Leistungen mit Standardpreisen. Vertragspartner erhalten hierauf ggf. individuelle Konditionen.</p>
    <?php if (!$services): ?>
        <p>Aktuell sind keine Services hinterlegt.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Beschreibung</th>
                    <th>Standardpreis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['name']) ?></td>
                        <td><?= htmlspecialchars($service['description'] ?? '') ?></td>
                        <td><?= number_format((float)$service['base_price'], 2) ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
renderFooter();