<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_generate_partner_invoices');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/partner_service.php';

$messages = [];

$weeklyPartners = getWeeklyPartners($pdo);
$defaultStart = date('Y-m-d', strtotime('monday this week'));
$defaultEnd = date('Y-m-d', strtotime('sunday this week'));
$retentionWeeks = getInvoiceRetentionWeeks($pdo);
$storageDir = __DIR__ . '/../partner/invoices';

$purged = purgeOldPartnerInvoices($pdo, $storageDir);
if ($purged > 0) {
    $messages[] = $purged . ' alte Rechnung(en) automatisch entfernt.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'generate';
    if ($action === 'generate') {
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $start = $_POST['period_start'] ?? $defaultStart;
        $end = $_POST['period_end'] ?? $defaultEnd;
        if ($partnerId > 0) {
            $invoiceId = generatePartnerInvoice($pdo, $partnerId, $start, $end, $storageDir);
            if ($invoiceId) {
                $messages[] = 'Rechnung #' . $invoiceId . ' erstellt.';
            } else {
                $messages[] = 'Keine abrechenbaren Leistungen im Zeitraum oder falscher Abrechnungsmodus.';
            }
        }
    } elseif ($action === 'save_settings') {
        $weeks = (int)($_POST['retention_weeks'] ?? $retentionWeeks);
        updateInvoiceRetentionWeeks($pdo, $weeks);
        $retentionWeeks = getInvoiceRetentionWeeks($pdo);
        $messages[] = 'Aufbewahrungszeitraum auf ' . $retentionWeeks . ' Woche(n) aktualisiert.';
    } elseif ($action === 'delete_invoice') {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        if ($invoiceId > 0 && deletePartnerInvoice($pdo, $invoiceId)) {
            $messages[] = 'Rechnung #' . $invoiceId . ' gelöscht.';
        } else {
            $messages[] = 'Rechnung konnte nicht gelöscht werden.';
        }
    }
}

$recentInvoices = $pdo->query("SELECT pi.*, u.username FROM partner_invoices pi INNER JOIN users u ON u.id = pi.partner_id ORDER BY pi.created_at DESC LIMIT 20")->fetchAll();

renderHeader('Wochenabrechnung', 'admin');
?>
<div class="card">
    <h2>Wochenabrechnung</h2>

    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="generate">
        <label>Partner
            <select name="partner_id" required>
                <option value="">Partner wählen</option>
                <?php foreach ($weeklyPartners as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['username']) ?> (<?= htmlspecialchars($p['contract_title']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Zeitraum von
            <input type="date" name="period_start" value="<?= htmlspecialchars($defaultStart) ?>">
        </label>
        <label>bis
            <input type="date" name="period_end" value="<?= htmlspecialchars($defaultEnd) ?>">
        </label>
        <button type="submit" class="btn btn-primary">Rechnung erzeugen</button>
    </form>

    <h3>Aufbewahrungszeitraum</h3>
    <form method="post" class="form-grid" style="max-width: 320px;">
        <input type="hidden" name="action" value="save_settings">
        <label>Wochen
            <input type="number" name="retention_weeks" min="1" max="52" value="<?= (int)$retentionWeeks ?>" required>
        </label>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>

    <h3>Neueste Rechnungen</h3>
    <?php if (!$recentInvoices): ?>
        <p class="muted">Noch keine Rechnungen.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Partner</th>
                    <th>Zeitraum</th>
                    <th>Summe</th>
                    <th>Download</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentInvoices as $inv): ?>
                    <tr>
                        <td><?= (int)$inv['id'] ?></td>
                        <td><?= htmlspecialchars($inv['username']) ?></td>
                        <td><?= htmlspecialchars($inv['period_start']) ?> - <?= htmlspecialchars($inv['period_end']) ?></td>
                        <td><?= number_format((float)$inv['total_amount'], 2) ?> €</td>
                        <td>
                            <?php if (!empty($inv['file_path'])): ?>
                                <a class="btn-link" href="/partner/download_invoice.php?id=<?= (int)$inv['id'] ?>">Download</a>
                            <?php else: ?>
                                <span class="muted">keine Datei</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('Rechnung wirklich löschen?');">
                                <input type="hidden" name="action" value="delete_invoice">
                                <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-small">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
renderFooter();