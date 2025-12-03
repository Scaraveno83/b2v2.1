<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_partners');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/partner_service.php';

$partnerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$partnerUser = getPartnerUser($pdo, $partnerId);
if (!$partnerUser) {
    http_response_code(404);
    exit('Partner nicht gefunden.');
}

$messages = [];
$contract = getPartnerContract($pdo, $partnerId);
if (!$contract) {
    savePartnerContract($pdo, $partnerId, 'Standardvertrag', 'standard', null);
    $contract = getPartnerContract($pdo, $partnerId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_contract') {
        $title = trim($_POST['contract_title'] ?? '');
        $billingMode = $_POST['billing_mode'] ?? 'standard';
        $notes = trim($_POST['notes'] ?? '');
        savePartnerContract($pdo, $partnerId, $title ?: 'Standardvertrag', $billingMode, $notes);
        $messages[] = 'Vertrag gespeichert.';
        $contract = getPartnerContract($pdo, $partnerId);
    } elseif ($action === 'save_prices') {
        $prices = $_POST['custom_price'] ?? [];
        savePartnerCustomPrices($pdo, $partnerId, $prices);
        $messages[] = 'Preise aktualisiert.';
    } elseif ($action === 'add_vehicle') {
        $name = trim($_POST['vehicle_name'] ?? '');
        $tuning = trim($_POST['tuning_details'] ?? '');
        if ($name !== '') {
            addPartnerVehicle($pdo, $partnerId, $name, $tuning);
            $messages[] = 'Fahrzeug hinzugefügt.';
        }
    } elseif ($action === 'delete_vehicle') {
        $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
        deletePartnerVehicle($pdo, $vehicleId, $partnerId);
        $messages[] = 'Fahrzeug entfernt.';
    }
}

$services = getAllPartnerServices($pdo, true);
$customPrices = getPartnerCustomPrices($pdo, $partnerId);
$vehicles = getPartnerVehicles($pdo, $partnerId);
$logs = getServiceLogsForPartner($pdo, $partnerId, 10);
$invoices = getInvoicesForPartner($pdo, $partnerId);

renderHeader('Partner bearbeiten', 'admin');
?>
<div class="card">
    <h2>Partner: <?= htmlspecialchars($partnerUser['username']) ?></h2>
    <p class="muted">Vertrag, Abrechnung und Konditionen bearbeiten.</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <h3>Vertrag</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="save_contract">
        <label>Titel
            <input type="text" name="contract_title" value="<?= htmlspecialchars($contract['contract_title'] ?? '') ?>" required>
        </label>
        <label>Abrechnung
            <select name="billing_mode">
                <option value="standard" <?= ($contract['billing_mode'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard (sofort)</option>
                <option value="weekly" <?= ($contract['billing_mode'] ?? '') === 'weekly' ? 'selected' : '' ?>>Wochenabrechnung</option>
            </select>
        </label>
        <label>Notizen
            <textarea name="notes" rows="3"><?= htmlspecialchars($contract['notes'] ?? '') ?></textarea>
        </label>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>

    <h3>Preisgestaltung</h3>
    <?php if (!$services): ?>
        <p>Keine Services vorhanden.</p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="save_prices">
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Standardpreis</th>
                        <th>Partnerpreis (optional)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?= htmlspecialchars($service['name']) ?></td>
                            <td><?= number_format((float)$service['base_price'], 2) ?> €</td>
                            <td>
                                <input type="number" step="0.01" name="custom_price[<?= (int)$service['id'] ?>]" value="<?= isset($customPrices[$service['id']]) ? htmlspecialchars($customPrices[$service['id']]) : '' ?>" placeholder="Standard übernehmen">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-secondary">Preise speichern</button>
        </form>
    <?php endif; ?>

    <h3>Fahrzeuge & Tunings</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add_vehicle">
        <label>Fahrzeug
            <input type="text" name="vehicle_name" required>
        </label>
        <label>Tuning / Besonderheiten
            <textarea name="tuning_details" rows="2"></textarea>
        </label>
        <button type="submit" class="btn btn-secondary">Hinzufügen</button>
    </form>
    <?php if ($vehicles): ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Tuning</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                        <td><?= htmlspecialchars($vehicle['vehicle_name']) ?></td>
                        <td><?= nl2br(htmlspecialchars($vehicle['tuning_details'] ?? '')) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Fahrzeug löschen?');">
                                <input type="hidden" name="action" value="delete_vehicle">
                                <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
                                <button type="submit" class="btn btn-link">Entfernen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Noch keine Fahrzeuge hinterlegt.</p>
    <?php endif; ?>

    <h3>Letzte erfasste Services</h3>
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
                    <th>Mitarbeiter</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['performed_at']) ?></td>
                        <td><?= htmlspecialchars($log['service_name']) ?></td>
                        <td><?= htmlspecialchars($log['vehicle_name'] ?? '-') ?></td>
                        <td><?= number_format((float)$log['applied_price'], 2) ?> €</td>
                        <td><?= htmlspecialchars($log['username'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>Rechnungen</h3>
    <?php if (!$invoices): ?>
        <p class="muted">Noch keine Rechnungen erzeugt.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Zeitraum</th>
                    <th>Summe</th>
                    <th>Erstellt</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= (int)$inv['id'] ?></td>
                        <td><?= htmlspecialchars($inv['period_start']) ?> - <?= htmlspecialchars($inv['period_end']) ?></td>
                        <td><?= number_format((float)$inv['total_amount'], 2) ?> €</td>
                        <td><?= htmlspecialchars($inv['created_at']) ?></td>
                        <td>
                            <?php if (!empty($inv['file_path'])): ?>
                                <a class="btn-link" href="/partner/download_invoice.php?id=<?= (int)$inv['id'] ?>">Download</a>
                            <?php else: ?>
                                <span class="muted">keine Datei</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
renderFooter();