<?php
require_once __DIR__ . '/../auth/check_role.php';
checkRole(['employee','admin']);
requirePermission('can_log_partner_services');
requireAbsenceAccess('staff');
requireAbsenceAccess('partner_services');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/partner_service.php';

$messages = [];
$weeklyPartners = getWeeklyPartners($pdo);
$selectedPartnerId = isset($_POST['partner_id']) ? (int)$_POST['partner_id'] : 0;
$vehicles = $selectedPartnerId ? getPartnerVehicles($pdo, $selectedPartnerId) : [];
$services = $selectedPartnerId ? getPartnerPricingTable($pdo, $selectedPartnerId) : getAllPartnerServices($pdo, true);
$services = array_map(static function ($svc) {
    if (!isset($svc['effective_price'])) {
        $svc['effective_price'] = (float)$svc['base_price'];
    }
    return $svc;
}, $services);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_service') {
    $partnerId = (int)($_POST['partner_id'] ?? 0);
    $serviceIds = $_POST['service_id'] ?? [];
    $vehicleIds = $_POST['vehicle_id'] ?? [];
    $notesList = $_POST['notes'] ?? [];
    $loggedCount = 0;

    if ($partnerId && is_array($serviceIds)) {
        foreach ($serviceIds as $idx => $serviceIdRaw) {
            $serviceId = (int)$serviceIdRaw;
            if (!$serviceId) {
                continue;
            }
            $vehicleId = isset($vehicleIds[$idx]) && $vehicleIds[$idx] !== '' ? (int)$vehicleIds[$idx] : null;
            $note = isset($notesList[$idx]) ? trim((string)$notesList[$idx]) : '';
            $logged = logPartnerService($pdo, $partnerId, $serviceId, $vehicleId, (int)$_SESSION['user']['id'], $note);
            if ($logged) {
                $loggedCount++;
            }
        }
    }

    if ($loggedCount > 0) {
        $messages[] = $loggedCount === 1 ? 'Service erfasst.' : $loggedCount . ' Services erfasst.';
        $selectedPartnerId = $partnerId;
        $vehicles = $partnerId ? getPartnerVehicles($pdo, $partnerId) : [];
        $services = $partnerId ? getPartnerPricingTable($pdo, $partnerId) : $services;
    }
}

renderHeader('Partner-Services erfassen', 'staff');
?>
<div class="card">
    <h2>Partner-Services erfassen</h2>
    <p class="muted">Für Partner mit Wochenabrechnung Leistungen dokumentieren.</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form-grid partner-services-form" id="service-form">
        <input type="hidden" name="action" value="log_service">
        <label>Partner
            <select name="partner_id" required onchange="this.form.submit()">
                <option value="">Bitte wählen</option>
                <?php foreach ($weeklyPartners as $partner): ?>
                    <option value="<?= (int)$partner['id'] ?>" <?= $selectedPartnerId === (int)$partner['id'] ? 'selected' : '' ?>><?= htmlspecialchars($partner['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div id="service-rows" class="full-width">
            <div class="service-row">
                <label>Service
                    <select name="service_id[]">
                        <option value="">Service wählen</option>
                        <?php foreach ($services as $service): ?>
                            <?php $priceLabel = number_format((float)$service['effective_price'], 2, ',', '.'); ?>
                            <option value="<?= (int)$service['id'] ?>"><?= htmlspecialchars($service['name']) ?> (<?= $priceLabel ?> €)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fahrzeug (optional)
                    <select name="vehicle_id[]">
                        <option value="">--</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= (int)$vehicle['id'] ?>"><?= htmlspecialchars($vehicle['vehicle_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Notiz
                    <textarea name="notes[]" rows="2"></textarea>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
    </form>
</div>
<?php
renderFooter();
?>
<script>
    (function () {
        const serviceRows = document.getElementById('service-rows');
        if (!serviceRows) {
            return;
        }

        function resetRowInputs(row) {
            row.querySelectorAll('select, textarea').forEach((el) => {
                if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                } else {
                    el.value = '';
                }
            });
        }

        function attachRowHandlers(row, isInitial = false) {
            const serviceSelect = row.querySelector('select[name="service_id[]"]');

            if (serviceSelect) {
                serviceSelect.addEventListener('change', () => {
                    const hasValue = serviceSelect.value !== '';
                    const isLastRow = row === serviceRows.lastElementChild;
                    if (hasValue && isLastRow) {
                        addServiceRow();
                    } else if (!hasValue) {
                        removeRowsAfter(row);
                    }
                    updateRequiredStates();
                });
            }
        }

        function addServiceRow() {
            const template = serviceRows.querySelector('.service-row');
            const clone = template.cloneNode(true);
            resetRowInputs(clone);
            attachRowHandlers(clone, false);
            serviceRows.appendChild(clone);
            updateRequiredStates();
        }

        function removeRowsAfter(row) {
            while (serviceRows.lastElementChild && serviceRows.lastElementChild !== row) {
                serviceRows.removeChild(serviceRows.lastElementChild);
            }
            if (!serviceRows.children.length) {
                addServiceRow();
            }
        }

        function updateRequiredStates() {
            const rows = Array.from(serviceRows.children);
            rows.forEach((r, idx) => {
                const select = r.querySelector('select[name="service_id[]"]');
                if (!select) return;
                const isLastRow = idx === rows.length - 1;
                select.required = !isLastRow || select.value !== '';
            });
        }

        function ensureTrailingEmptyRow() {
            if (!serviceRows.children.length) {
                addServiceRow();
                return;
            }
            const lastServiceSelect = serviceRows.lastElementChild.querySelector('select[name="service_id[]"]');
            if (lastServiceSelect && lastServiceSelect.value !== '') {
                addServiceRow();
            }
            updateRequiredStates();
        }

        const form = document.getElementById('service-form');
        if (form) {
            form.addEventListener('submit', (event) => {
                const rows = Array.from(serviceRows.children);
                let filledRows = 0;

                rows.slice().forEach((row) => {
                    const select = row.querySelector('select[name="service_id[]"]');
                    if (!select) {
                        return;
                    }
                    if (select.value === '' && serviceRows.children.length > 1) {
                        serviceRows.removeChild(row);
                    } else if (select.value !== '') {
                        filledRows++;
                    }
                });

                if (filledRows === 0) {
                    event.preventDefault();
                    alert('Bitte mindestens einen Service auswählen.');
                    ensureTrailingEmptyRow();
                    return;
                }

                updateRequiredStates();
            });
        }

        const firstRow = serviceRows.querySelector('.service-row');
        attachRowHandlers(firstRow, true);
        ensureTrailingEmptyRow();
    })();
</script>
<style>
    .partner-services-form {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .partner-services-form > label {
        max-width: 360px;
    }

    #service-rows {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .service-row {
        padding: 12px;
        border-radius: var(--radius-md);
        border: 1px solid rgba(148, 163, 184, 0.4);
        background: rgba(15, 23, 42, 0.6);
        display: grid;
        grid-template-columns: minmax(220px, 1.05fr) minmax(180px, 0.95fr) minmax(220px, 1fr);
        gap: 12px 14px;
        align-items: start;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.16);
    }

    body.light .service-row {
        background: #ffffff;
    }

    .service-row label {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin: 0;
    }

    .service-row select,
    .service-row textarea {
        width: 100%;
    }

    .service-row textarea {
        min-height: 68px;
        resize: vertical;
    }

    @media (max-width: 840px) {
        .service-row {
            grid-template-columns: 1fr;
        }
    }

    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 14px;
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .form-actions .btn {
        margin-top: 0;
        width: auto;
        min-width: 160px;
        align-self: flex-start;
        box-shadow: 0 8px 18px rgba(var(--accent-cyan-rgb), 0.6), 0 12px 30px rgba(var(--accent-magenta-rgb), 0.45);
    }

    .form-actions .btn-primary {
        box-shadow:
            0 8px 18px rgba(var(--accent-cyan-rgb), 0.65),
            0 12px 30px rgba(var(--accent-magenta-rgb), 0.45),
            0 12px 40px rgba(var(--accent-purple-rgb), 0.4);
        transition: box-shadow 0.14s ease, transform 0.12s ease;
    }

    .form-actions .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow:
            0 10px 22px rgba(var(--accent-cyan-rgb), 0.8),
            0 16px 36px rgba(var(--accent-magenta-rgb), 0.55),
            0 18px 50px rgba(var(--accent-purple-rgb), 0.5);
    }
</style>