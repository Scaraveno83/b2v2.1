<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_calendar');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/calendar_service.php';
require_once __DIR__ . '/../includes/layout.php';

$success = '';
$errors = [];
$areaCatalog = calendarAreaCatalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_rules';

    if ($action === 'approve_release') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (approveReleaseRequest($pdo, $requestId, (int)$_SESSION['user']['id'])) {
            $success = 'Vorzeitige Freigabe wurde erteilt.';
        } else {
            $errors[] = 'Freigabe konnte nicht erteilt werden (bereits erledigt?).';
        }
    } elseif ($action === 'decline_release') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (declineReleaseRequest($pdo, $requestId, (int)$_SESSION['user']['id'])) {
            $success = 'Anfrage wurde abgelehnt.';
        } else {
            $errors[] = 'Anfrage konnte nicht abgelehnt werden (bereits erledigt?).';
        }
    } else {
        $employeeAreas = $_POST['employee_areas'] ?? [];
        $partnerAreas = $_POST['partner_areas'] ?? [];

        saveRoleRestrictions($pdo, 'employee', $employeeAreas);
        saveRoleRestrictions($pdo, 'partner', $partnerAreas);

        $success = 'Kalendersperren wurden aktualisiert.';
    }
}

$employeeRestrictions = getRoleRestrictions($pdo, 'employee');
$partnerRestrictions = getRoleRestrictions($pdo, 'partner');
$absences = fetchAbsenceOverview($pdo, 50);
$releaseRequests = fetchReleaseRequests($pdo, 'pending', 100);

renderHeader('Kalender-Administration', 'admin');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">Regeln & Übersicht</p>
        <h1>Kalender-Administration</h1>
        <p class="muted">Lege fest, welche Bereiche bei Abmeldungen ausgeblendet oder gesperrt werden. Behalte Abwesenheiten im Blick.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="card error">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php elseif ($success): ?>
    <div class="card success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <div>
                <p class="eyebrow">Regeln</p>
                <h3>Sichtbarkeiten pro Rolle</h3>
            </div>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="update_rules">
            <div>
                <h4>Mitarbeiter</h4>
                <p class="muted">Bereiche, die während der Abmeldung ausgeblendet werden.</p>
                <?php foreach ($areaCatalog as $key => $label): ?>
                    <label style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="employee_areas[]" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $employeeRestrictions, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div>
                <h4>Partner</h4>
                <p class="muted">Bereiche, die Partner während ihrer Abmeldung nicht sehen dürfen.</p>
                <?php foreach ($areaCatalog as $key => $label): ?>
                    <label style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="partner_areas[]" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $partnerRestrictions, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Sperrbereiche speichern</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <p class="eyebrow">Monitoring</p>
                <h3>Aktive & neue Abmeldungen</h3>
            </div>
        </div>
        <?php if (!$absences): ?>
            <p>Keine Abmeldungen gefunden.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>Rolle</th>
                            <th>Start</th>
                            <th>Ende</th>
                            <th>Grund</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars($entry['username'] ?? 'Gelöscht') ?></td>
                                <td><?= htmlspecialchars($entry['role'] ?? '-') ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($entry['start_at'])) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($entry['end_at'])) ?></td>
                                <td><?= htmlspecialchars($entry['reason'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
          </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <p class="eyebrow">Freigaben</p>
                <h3>Offene Entlassungsanfragen</h3>
            </div>
        </div>
        <?php if (!$releaseRequests): ?>
            <p>Keine offenen Anfragen.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>Rolle</th>
                            <th>Abmeldung</th>
                            <th>Grund</th>
                            <th>Nachricht</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($releaseRequests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['username'] ?? 'Gelöscht') ?></td>
                                <td><?= htmlspecialchars($request['role'] ?? '-') ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($request['start_at'])) ?> – <?= date('d.m.Y H:i', strtotime($request['end_at'])) ?></td>
                                <td><?= htmlspecialchars($request['reason'] ?? '') ?></td>
                                <td><?= htmlspecialchars($request['message'] ?? '—') ?></td>
                                <td style="display:flex;gap:6px;">
                                    <form method="post">
        
                                        <input type="hidden" name="action" value="approve_release">
                                        <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                        <button class="btn btn-primary" type="submit">Freigeben</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="decline_release">
                                        <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                        <button class="btn btn-secondary" type="submit">Ablehnen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
renderFooter();