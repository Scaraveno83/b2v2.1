<?php
if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
    // Session handling is managed by the caller.
}

require_once __DIR__ . '/theme_settings.php';

function ensurePartnerSchema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        contract_title VARCHAR(255) NOT NULL,
        billing_mode ENUM('standard','weekly') NOT NULL DEFAULT 'standard',
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_partner_contract (partner_id),
        CONSTRAINT fk_pc_partner FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_custom_prices (
        partner_id INT NOT NULL,
        service_id INT NOT NULL,
        custom_price DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (partner_id, service_id),
        CONSTRAINT fk_pcp_partner FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_pcp_service FOREIGN KEY (service_id) REFERENCES partner_services(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        vehicle_name VARCHAR(255) NOT NULL,
        tuning_details TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pv_partner FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        file_path VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pi_partner FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_invoice_settings (
        id TINYINT PRIMARY KEY DEFAULT 1,
        retention_weeks INT NOT NULL DEFAULT 4,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("INSERT IGNORE INTO partner_invoice_settings (id, retention_weeks) VALUES (1, 4)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_service_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        service_id INT NOT NULL,
        vehicle_id INT NULL,
        performed_by INT NOT NULL,
        performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT NULL,
        applied_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        invoice_id INT NULL,
        CONSTRAINT fk_psl_partner FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_psl_service FOREIGN KEY (service_id) REFERENCES partner_services(id) ON DELETE CASCADE,
        CONSTRAINT fk_psl_vehicle FOREIGN KEY (vehicle_id) REFERENCES partner_vehicles(id) ON DELETE SET NULL,
        CONSTRAINT fk_psl_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_psl_invoice FOREIGN KEY (invoice_id) REFERENCES partner_invoices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function getAllPartnerServices(PDO $pdo, bool $onlyActive = false): array
{
    ensurePartnerSchema($pdo);
    $sql = "SELECT * FROM partner_services";
    if ($onlyActive) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function createPartnerService(PDO $pdo, string $name, ?string $description, float $basePrice): void
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("INSERT INTO partner_services (name, description, base_price) VALUES (?,?,?)");
    $stmt->execute([$name, $description, $basePrice]);
}

function updatePartnerService(PDO $pdo, int $id, string $name, ?string $description, float $basePrice, bool $isActive): void
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("UPDATE partner_services SET name = ?, description = ?, base_price = ?, is_active = ? WHERE id = ?");
    $stmt->execute([$name, $description, $basePrice, $isActive ? 1 : 0, $id]);
}

function getPartnerContract(PDO $pdo, int $partnerId): ?array
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM partner_contracts WHERE partner_id = ? LIMIT 1");
    $stmt->execute([$partnerId]);
    return $stmt->fetch() ?: null;
}

function savePartnerContract(PDO $pdo, int $partnerId, string $title, string $billingMode, ?string $notes): void
{
    ensurePartnerSchema($pdo);
    $billingMode = in_array($billingMode, ['weekly', 'standard'], true) ? $billingMode : 'standard';
    $stmt = $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_title, billing_mode, notes)
        VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE contract_title = VALUES(contract_title), billing_mode = VALUES(billing_mode), notes = VALUES(notes)");
    $stmt->execute([$partnerId, $title, $billingMode, $notes]);
}

function getPartnerCustomPrices(PDO $pdo, int $partnerId): array
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("SELECT service_id, custom_price FROM partner_custom_prices WHERE partner_id = ?");
    $stmt->execute([$partnerId]);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['service_id']] = (float)$row['custom_price'];
    }
    return $map;
}

function savePartnerCustomPrices(PDO $pdo, int $partnerId, array $prices): void
{
    ensurePartnerSchema($pdo);
    $pdo->prepare("DELETE FROM partner_custom_prices WHERE partner_id = ?")->execute([$partnerId]);

    $stmt = $pdo->prepare("INSERT INTO partner_custom_prices (partner_id, service_id, custom_price) VALUES (?,?,?)");
    foreach ($prices as $serviceId => $price) {
        if ($price === null || $price === '') {
            continue;
        }
        $stmt->execute([$partnerId, (int)$serviceId, (float)$price]);
    }
}

function getPartnerVehicles(PDO $pdo, int $partnerId): array
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM partner_vehicles WHERE partner_id = ? ORDER BY created_at DESC");
    $stmt->execute([$partnerId]);
    return $stmt->fetchAll();
}

function addPartnerVehicle(PDO $pdo, int $partnerId, string $name, ?string $tuningDetails): void
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("INSERT INTO partner_vehicles (partner_id, vehicle_name, tuning_details) VALUES (?,?,?)");
    $stmt->execute([$partnerId, $name, $tuningDetails]);
}

function deletePartnerVehicle(PDO $pdo, int $vehicleId, int $partnerId): void
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("DELETE FROM partner_vehicles WHERE id = ? AND partner_id = ?");
    $stmt->execute([$vehicleId, $partnerId]);
}

function getPartnerPriceForService(PDO $pdo, int $partnerId, int $serviceId): float
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("SELECT custom_price FROM partner_custom_prices WHERE partner_id = ? AND service_id = ? LIMIT 1");
    $stmt->execute([$partnerId, $serviceId]);
    $custom = $stmt->fetchColumn();
    if ($custom !== false) {
        return (float)$custom;
    }

    $stmt = $pdo->prepare("SELECT base_price FROM partner_services WHERE id = ? LIMIT 1");
    $stmt->execute([$serviceId]);
    $base = $stmt->fetchColumn();
    return $base !== false ? (float)$base : 0.0;
}

function getPartnerPricingTable(PDO $pdo, int $partnerId): array
{
    $services = getAllPartnerServices($pdo, true);
    $custom = getPartnerCustomPrices($pdo, $partnerId);
    foreach ($services as &$svc) {
        $svc['custom_price'] = $custom[$svc['id']] ?? null;
        $svc['effective_price'] = $svc['custom_price'] !== null ? (float)$svc['custom_price'] : (float)$svc['base_price'];
    }
    return $services;
}

function logPartnerService(PDO $pdo, int $partnerId, int $serviceId, ?int $vehicleId, int $userId, string $notes = '', ?string $performedAt = null): bool
{
    ensurePartnerSchema($pdo);
    $price = getPartnerPriceForService($pdo, $partnerId, $serviceId);
    $stmt = $pdo->prepare("INSERT INTO partner_service_logs (partner_id, service_id, vehicle_id, performed_by, performed_at, notes, applied_price) VALUES (?,?,?,?,?,?,?)");
    return $stmt->execute([
        $partnerId,
        $serviceId,
        $vehicleId ?: null,
        $userId,
        $performedAt ?: date('Y-m-d H:i:s'),
        $notes,
        $price,
    ]);
}

function getWeeklyPartners(PDO $pdo): array
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->query("SELECT u.id, u.username, u.email, pc.contract_title, pc.billing_mode
        FROM users u
        INNER JOIN partner_contracts pc ON pc.partner_id = u.id
        WHERE u.role = 'partner' AND pc.billing_mode = 'weekly'
        ORDER BY u.username ASC");
    return $stmt->fetchAll();
}

function getServiceLogsForPartner(PDO $pdo, int $partnerId, int $limit = 50): array
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("SELECT l.*, s.name AS service_name, v.vehicle_name, u.username
        FROM partner_service_logs l
        INNER JOIN partner_services s ON s.id = l.service_id
        LEFT JOIN partner_vehicles v ON v.id = l.vehicle_id
        LEFT JOIN users u ON u.id = l.performed_by
        WHERE l.partner_id = ?
        ORDER BY l.performed_at DESC
        LIMIT " . (int)$limit);
    $stmt->execute([$partnerId]);
    return $stmt->fetchAll();
}

function getInvoicesForPartner(PDO $pdo, int $partnerId): array
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM partner_invoices WHERE partner_id = ? ORDER BY created_at DESC");
    $stmt->execute([$partnerId]);
    return $stmt->fetchAll();
}

function getInvoiceRetentionWeeks(PDO $pdo): int
{
    ensurePartnerSchema($pdo);
    $weeks = $pdo->query("SELECT retention_weeks FROM partner_invoice_settings WHERE id = 1 LIMIT 1")->fetchColumn();
    return $weeks !== false ? (int)$weeks : 4;
}

function updateInvoiceRetentionWeeks(PDO $pdo, int $weeks): void
{
    ensurePartnerSchema($pdo);
    $weeks = max(1, min($weeks, 52));
    $stmt = $pdo->prepare("UPDATE partner_invoice_settings SET retention_weeks = ? WHERE id = 1");
    $stmt->execute([$weeks]);
}

function deletePartnerInvoice(PDO $pdo, int $invoiceId): bool
{
    ensurePartnerSchema($pdo);
    $stmt = $pdo->prepare("SELECT file_path FROM partner_invoices WHERE id = ? LIMIT 1");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        return false;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE partner_service_logs SET invoice_id = NULL WHERE invoice_id = ?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM partner_invoices WHERE id = ?")->execute([$invoiceId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }

    if (!empty($invoice['file_path']) && file_exists($invoice['file_path'])) {
        @unlink($invoice['file_path']);
    }

    return true;
}

function purgeOldPartnerInvoices(PDO $pdo, string $storageDir): int
{
    ensurePartnerSchema($pdo);
    $weeks = getInvoiceRetentionWeeks($pdo);
    if ($weeks <= 0) {
        return 0;
    }

    $cutoff = (new DateTime())->modify('-' . $weeks . ' weeks')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT id FROM partner_invoices WHERE created_at < ?");
    $stmt->execute([$cutoff]);
    $invoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $deleted = 0;
    foreach ($invoiceIds as $id) {
        if (deletePartnerInvoice($pdo, (int)$id)) {
            $deleted++;
        }
    }

    return $deleted;
}

function generatePartnerInvoice(PDO $pdo, int $partnerId, string $startDate, string $endDate, string $storageDir): ?int
{
    ensurePartnerSchema($pdo);

    $contract = getPartnerContract($pdo, $partnerId);
    if (!$contract || $contract['billing_mode'] !== 'weekly') {
        return null;
    }

    $logsStmt = $pdo->prepare("SELECT l.*, s.name AS service_name
        FROM partner_service_logs l
        INNER JOIN partner_services s ON s.id = l.service_id
        WHERE l.partner_id = ? AND l.invoice_id IS NULL AND l.performed_at BETWEEN ? AND ?");
    $logsStmt->execute([$partnerId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $logs = $logsStmt->fetchAll();
    if (!$logs) {
        return null;
    }

    $total = 0;
    $serviceBreakdown = [];
    foreach ($logs as $log) {
        $total += (float)$log['applied_price'];
        $serviceBreakdown[$log['service_id']]['name'] = $log['service_name'];
        $serviceBreakdown[$log['service_id']]['count'] = ($serviceBreakdown[$log['service_id']]['count'] ?? 0) + 1;
        $serviceBreakdown[$log['service_id']]['sum'] = ($serviceBreakdown[$log['service_id']]['sum'] ?? 0) + (float)$log['applied_price'];
    }

    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0777, true);
    }

    try {
        $pdo->beginTransaction();
        $stmtInv = $pdo->prepare("INSERT INTO partner_invoices (partner_id, period_start, period_end, total_amount) VALUES (?,?,?,?)");
        $stmtInv->execute([$partnerId, $startDate, $endDate, $total]);
        $invoiceId = (int)$pdo->lastInsertId();

        $updateLogs = $pdo->prepare("UPDATE partner_service_logs SET invoice_id = ? WHERE id = ?");
        foreach ($logs as $log) {
            $updateLogs->execute([$invoiceId, $log['id']]);
        }

        $filePath = rtrim($storageDir, '/') . '/invoice_' . $invoiceId . '.html';
        $invoiceHtml = renderInvoiceTemplate($pdo, [
            'invoiceId' => $invoiceId,
            'partnerId' => $partnerId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'createdAt' => date('Y-m-d H:i'),
            'total' => $total,
            'serviceBreakdown' => $serviceBreakdown,
        ]);

        if (file_put_contents($filePath, $invoiceHtml) === false) {
            $pdo->rollBack();
            return null;
        }

        $pdo->prepare("UPDATE partner_invoices SET file_path = ? WHERE id = ?")->execute([$filePath, $invoiceId]);
        $pdo->commit();

        return $invoiceId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return null;
    }
}

function getPartnerUser(PDO $pdo, int $partnerId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'partner' LIMIT 1");
    $stmt->execute([$partnerId]);
    return $stmt->fetch() ?: null;
    }

function renderInvoiceTemplate(PDO $pdo, array $data): string
{
    $theme = loadThemeSettings($pdo);
    $vars = buildThemeCssVariables($theme);

    $cssVars = [];
    foreach ([
        'bg1', 'bg2', 'surface', 'surface-border', 'text-main', 'text-muted',
        'accent-cyan', 'accent-magenta', 'accent-purple', 'accent-yellow', 'header-shadow-base',
    ] as $key) {
        $cssVars[$key] = htmlspecialchars($vars[$key] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $serviceRows = '';
    foreach ($data['serviceBreakdown'] as $item) {
        $serviceRows .= '<tr>'
            . '<td>' . htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>'
            . '<td class="text-right">' . (int)$item['count'] . 'x</td>'
            . '<td class="text-right">' . number_format((float)$item['sum'], 2) . ' €</td>'
            . '</tr>';
    }

    $logoPlaceholder = '<div class="logo-placeholder">Firmenlogo</div>';

    return '<!DOCTYPE html>'
        . '<html lang="de">'
        . '<head>'
        . '<meta charset="utf-8">'
        . '<title>Rechnung #' . (int)$data['invoiceId'] . '</title>'
        . '<style>'
        . ':root{'
        . ' --bg1:' . $cssVars['bg1'] . ';'
        . ' --bg2:' . $cssVars['bg2'] . ';'
        . ' --surface:' . $cssVars['surface'] . ';'
        . ' --surface-border:' . $cssVars['surface-border'] . ';'
        . ' --text-main:' . $cssVars['text-main'] . ';'
        . ' --text-muted:' . $cssVars['text-muted'] . ';'
        . ' --accent-cyan:' . $cssVars['accent-cyan'] . ';'
        . ' --accent-magenta:' . $cssVars['accent-magenta'] . ';'
        . ' --accent-purple:' . $cssVars['accent-purple'] . ';'
        . ' --accent-yellow:' . $cssVars['accent-yellow'] . ';'
        . ' --shadow-base:' . $cssVars['header-shadow-base'] . ';'
        . '}'
        . 'body{margin:0;padding:24px;font-family:"Inter",system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,var(--bg1),var(--bg2));color:var(--text-main);}'
        . '.invoice{max-width:840px;margin:0 auto;padding:28px;border-radius:16px;background:var(--surface);border:1px solid var(--surface-border);box-shadow:var(--shadow-base),0 12px 40px rgba(0,0,0,0.35);}'
        . '.invoice-header{display:flex;justify-content:space-between;align-items:center;gap:16px;padding-bottom:16px;border-bottom:1px solid var(--surface-border);}'
        . '.brand{display:flex;align-items:center;gap:12px;}'
        . '.brand-accent{width:14px;height:48px;border-radius:12px;background:linear-gradient(180deg,var(--accent-cyan),var(--accent-magenta));box-shadow:0 0 12px rgba(255,255,255,0.08);}'
        . '.brand-title{font-size:20px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;}'
        . '.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;background:rgba(255,255,255,0.06);border:1px solid var(--surface-border);color:var(--text-main);}'
        . '.meta{margin-top:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;font-size:14px;color:var(--text-muted);}'
        . '.meta strong{display:block;color:var(--text-main);font-size:15px;margin-bottom:2px;}'
        . '.table{width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;}'
        . '.table th,.table td{padding:12px;border-bottom:1px solid var(--surface-border);}'
        . '.table th{font-weight:700;text-align:left;color:var(--text-main);}'
        . '.table tr:last-child td{border-bottom:0;}'
        . '.text-right{text-align:right;}'
        . '.total{display:flex;justify-content:flex-end;margin-top:12px;}'
        . '.total-box{padding:14px 18px;border-radius:12px;border:1px solid var(--surface-border);background:linear-gradient(120deg,rgba(255,255,255,0.04),rgba(255,255,255,0.02));box-shadow:0 10px 30px rgba(0,0,0,0.25);}'
        . '.total-label{color:var(--text-muted);font-size:13px;}'
        . '.total-amount{font-size:22px;font-weight:700;color:var(--accent-yellow);}'
        . '.logo-placeholder{width:120px;height:60px;border-radius:12px;border:1px dashed var(--surface-border);display:flex;align-items:center;justify-content:center;font-weight:600;color:var(--text-muted);background:linear-gradient(135deg,rgba(255,255,255,0.04),rgba(255,255,255,0.02));}'
        . '@media print{body{background:#fff;} .invoice{box-shadow:none;}}'
        . '</style>'
        . '</head>'
        . '<body>'
        . '<div class="invoice">'
        . '<div class="invoice-header">'
        . '<div class="brand">'
        . '<div class="brand-accent"></div>'
        . '<div>'
        . '<div class="brand-title">Ultra Neon Panel</div>'
        . '<div class="badge">Rechnung #' . (int)$data['invoiceId'] . '</div>'
        . '</div>'
        . '</div>'
        . $logoPlaceholder
        . '</div>'
        . '<div class="meta">'
        . '<div><strong>Partner</strong> #' . (int)$data['partnerId'] . '</div>'
        . '<div><strong>Zeitraum</strong> ' . htmlspecialchars($data['startDate'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' – ' . htmlspecialchars($data['endDate'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'
        . '<div><strong>Erstellt am</strong> ' . htmlspecialchars($data['createdAt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'
        . '</div>'
        . '<table class="table" aria-label="Leistungsübersicht">'
        . '<thead><tr><th>Service</th><th class="text-right">Menge</th><th class="text-right">Summe</th></tr></thead>'
        . '<tbody>' . $serviceRows . '</tbody>'
        . '</table>'
        . '<div class="total">'
        . '<div class="total-box">'
        . '<div class="total-label">Gesamtbetrag</div>'
        . '<div class="total-amount">' . number_format((float)$data['total'], 2) . ' €</div>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</body>'
        . '</html>';
}