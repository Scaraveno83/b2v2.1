<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/partner_service.php';
checkRole(['partner','admin']);
requireAbsenceAccess('partner');

$partnerId = (int)$_SESSION['user']['id'];
$contract = getPartnerContract($pdo, $partnerId);
if (!$contract) {
    savePartnerContract($pdo, $partnerId, 'Standardvertrag', 'standard', null);
    $contract = getPartnerContract($pdo, $partnerId);
}

$pricing = getPartnerPricingTable($pdo, $partnerId);
$vehicles = getPartnerVehicles($pdo, $partnerId);
$logs = getServiceLogsForPartner($pdo, $partnerId, 20);
$invoices = getInvoicesForPartner($pdo, $partnerId);

renderHeader('Partnerbereich', 'partner');
?>
<div class="card">
    <h2>Dein Vertrag</h2>
    <p class="muted">Vertragliche Konditionen und Abrechnung.</p>
    <div class="grid grid-2">
        <div>
            <strong>Titel:</strong> <?= htmlspecialchars($contract['contract_title']) ?><br>
            <strong>Abrechnung:</strong> <?= $contract['billing_mode'] === 'weekly' ? 'Wochenabrechnung' : 'Standard' ?><br>
        </div>
        <div>
            <strong>Notizen:</strong><br>
            <div class="muted"><?= nl2br(htmlspecialchars($contract['notes'] ?? '–')) ?></div>
        </div>
    </div>
</div>

<div class="card news-ticker-card">
    <div class="card-header">
        <div>
            <p class="eyebrow">Live</p>
            <h3>Partner &amp; interne News</h3>
        </div>
        <a class="btn btn-secondary" href="/news/index.php">News öffnen</a>
    </div>
    <div class="news-ticker" data-news-ticker data-scope="auto"></div>
</div>

<div class="card">
    <h3>Services & Preise</h3>
    <?php if (!$pricing): ?>
        <p class="muted">Noch keine Services verfügbar.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Standardpreis</th>
                    <th>Partnerpreis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pricing as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= number_format((float)$row['base_price'], 2) ?> €</td>
                        <td>
                            <?php if ($row['custom_price'] !== null): ?>
                                <strong><?= number_format((float)$row['custom_price'], 2) ?> €</strong>
                            <?php else: ?>
                                <span class="muted">Standard</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Fahrzeuge & Tunings</h3>
    <?php if (!$vehicles): ?>
        <p class="muted">Keine Fahrzeuge hinterlegt.</p>
    <?php else: ?>
        <div class="grid grid-2">
            <?php foreach ($vehicles as $vehicle): ?>
                <div class="stat">
                    <div class="stat-label"><?= htmlspecialchars($vehicle['vehicle_name']) ?></div>
                    <div class="stat-sub"><?= nl2br(htmlspecialchars($vehicle['tuning_details'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Rechnungen</h3>
    <?php if (!$invoices): ?>
        <p class="muted">Keine Rechnungen vorhanden.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Zeitraum</th>
                    <th>Summe</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= (int)$inv['id'] ?></td>
                        <td><?= htmlspecialchars($inv['period_start']) ?> - <?= htmlspecialchars($inv['period_end']) ?></td>
                        <td><?= number_format((float)$inv['total_amount'], 2) ?> €</td>
                        <td>
                            <?php if (!empty($inv['file_path'])): ?>
                                <a class="btn-link" href="/partner/download_invoice.php?id=<?= (int)$inv['id'] ?>">Download</a>
                            <?php else: ?>
                                <span class="muted">in Vorbereitung</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Zuletzt erfasste Leistungen</h3>
    <?php if (!$logs): ?>
        <p class="muted">Keine Einträge.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Service</th>
                    <th>Fahrzeug</th>
                    <th>Preis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['performed_at']) ?></td>
                        <td><?= htmlspecialchars($log['service_name']) ?></td>
                        <td><?= htmlspecialchars($log['vehicle_name'] ?? '-') ?></td>
                        <td><?= number_format((float)$log['applied_price'], 2) ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
renderFooter();
