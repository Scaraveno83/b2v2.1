<?php
require_once __DIR__ . '/../auth/check_role.php';
checkRole(['employee', 'partner', 'admin']);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/calendar_service.php';
require_once __DIR__ . '/../includes/layout.php';

$user = $_SESSION['user'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $startRaw = trim($_POST['start_at'] ?? '');
        $endRaw = trim($_POST['end_at'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        $start = DateTime::createFromFormat('Y-m-d\TH:i', $startRaw) ?: DateTime::createFromFormat('Y-m-d H:i', $startRaw);
        $end = DateTime::createFromFormat('Y-m-d\TH:i', $endRaw) ?: DateTime::createFromFormat('Y-m-d H:i', $endRaw);

        if (!$start || !$end) {
            $errors[] = 'Bitte Start- und Endzeit angeben.';
        } elseif ($end <= $start) {
            $errors[] = 'Das Enddatum muss nach dem Startdatum liegen.';
        }

        if (!$errors) {
            createAbsenceEntry($pdo, (int)$user['id'], $start, $end, $reason);
            applyAbsenceContext($pdo);
            $success = 'Abmeldung wurde gespeichert.';
        }
    } elseif ($action === 'delete') {
        $absenceId = (int)($_POST['absence_id'] ?? 0);
        $absence = findUserAbsence($pdo, $absenceId, (int)$user['id']);
        if (!$absence) {
            $errors[] = 'Abmeldung wurde nicht gefunden.';
        } elseif (strtotime($absence['start_at']) <= time()) {
            $errors[] = 'Aktive Abmeldungen können nicht selbst aufgehoben werden.';
        } else {
            deleteAbsenceEntry($pdo, $absenceId, (int)$user['id']);
            applyAbsenceContext($pdo);
            $success = 'Eintrag wurde entfernt.';
        }
    } elseif ($action === 'request_release') {
        $absenceId = (int)($_POST['absence_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $absence = findUserAbsence($pdo, $absenceId, (int)$user['id']);

        if (!$absence) {
            $errors[] = 'Abmeldung wurde nicht gefunden.';
        } elseif (strtotime($absence['end_at']) < time()) {
            $errors[] = 'Diese Abmeldung ist bereits beendet.';
        } elseif (strtotime($absence['start_at']) > time()) {
            $errors[] = 'Für geplante Abmeldungen ist keine Entlassung nötig.';
        } elseif (getPendingReleaseRequest($pdo, $absenceId, (int)$user['id'])) {
            $errors[] = 'Es liegt bereits eine offene Anfrage vor.';
        } else {
            createReleaseRequest($pdo, $absenceId, (int)$user['id'], $message);
            $success = 'Anfrage zur vorzeitigen Rückkehr wurde gesendet.';
        }
    }
}

$absences = fetchUserAbsences($pdo, (int)$user['id']);
$activeAbsence = getActiveAbsence($pdo, (int)$user['id']);
$activeReleaseRequest = $activeAbsence ? getPendingReleaseRequest($pdo, (int)$activeAbsence['id'], (int)$user['id']) : null;
$roleRestrictions = getRoleRestrictions($pdo, $user['role']);
$areaCatalog = calendarAreaCatalog();

renderHeader('Kalender', 'calendar');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">Planung & Zugriff</p>
        <h1>Kalender & Abmeldungen</h1>
        <p class="muted">Plane Abwesenheiten und definiere, welche Bereiche währenddessen ausgeblendet werden.</p>
    </div>
    <div>
        <span class="pill pill-role-<?= htmlspecialchars($user['role']) ?>">Rolle: <?= strtoupper(htmlspecialchars($user['role'])) ?></span>
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
                <p class="eyebrow">Status</p>
                <h3>Aktuelle Abmeldung</h3>
            </div>
        </div>
        <?php if ($activeAbsence): ?>
            <p><strong>Zeitraum:</strong> <?= date('d.m.Y H:i', strtotime($activeAbsence['start_at'])) ?> bis <?= date('d.m.Y H:i', strtotime($activeAbsence['end_at'])) ?></p>
            <?php if (!empty($activeAbsence['reason'])): ?>
                <p><strong>Grund:</strong> <?= htmlspecialchars($activeAbsence['reason']) ?></p>
            <?php endif; ?>
            <?php if ($roleRestrictions): ?>
                <p><strong>Eingeschränkt:</strong> <?= implode(', ', array_map(fn($key) => $areaCatalog[$key] ?? $key, $roleRestrictions)) ?></p>
            <?php else: ?>
                <p><strong>Eingeschränkt:</strong> Keine zusätzlichen Sperren.</p>
            <?php endif; ?>
            <p class="muted">Du siehst nur Bereiche, die nicht durch deine Abmeldung blockiert sind. Nach Ablauf wird dein voller Zugriff automatisch wiederhergestellt.</p>
            <div class="card" style="margin-top:12px;">
                <div class="card-header">
                    <div>
                        <p class="eyebrow">Verwaltungsanfrage</p>
                        <h3>Vorzeitige Entlassung</h3>
                    </div>
                </div>
                <?php if ($activeReleaseRequest): ?>
                    <p class="muted">Du hast am <?= date('d.m.Y H:i', strtotime($activeReleaseRequest['created_at'])) ?> eine Anfrage gestellt. Sobald die Verwaltung zustimmt, wirst du aus der Abmeldung entlassen.</p>
                <?php else: ?>
                    <p>Du kannst keine aktive Abmeldung selbst aufheben. Bitte beantrage die Freigabe bei der Verwaltung.</p>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="request_release">
                        <input type="hidden" name="absence_id" value="<?= (int)$activeAbsence['id'] ?>">
                        <label>
                            <span>Notiz (optional)</span>
                            <input type="text" name="message" maxlength="500" placeholder="Grund für die vorzeitige Rückkehr">
                        </label>
                        <div>
                            <button type="submit" class="btn btn-primary">Freigabe anfragen</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>Derzeit bist du aktiv. Du kannst eine Abmeldung planen, um Sichtbarkeit und Zugriffe automatisch anzupassen.</p>
            <?php if ($roleRestrictions): ?>
                <p class="muted">Für deine Rolle werden während einer Abmeldung folgende Bereiche gesperrt: <?= implode(', ', array_map(fn($key) => $areaCatalog[$key] ?? $key, $roleRestrictions)) ?>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <p class="eyebrow">Planung</p>
                <h3>Abmeldung erstellen</h3>
            </div>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="create">
            <label>
                <span>Start</span>
                <input type="datetime-local" name="start_at" required>
            </label>
            <label>
                <span>Ende</span>
                <input type="datetime-local" name="end_at" required>
            </label>
            <label>
                <span>Grund (optional)</span>
                <input type="text" name="reason" maxlength="255" placeholder="z.B. Urlaub, Außentermin">
            </label>
            <div>
                <button type="submit" class="btn btn-primary">Abmeldung speichern</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow">Übersicht</p>
            <h3>Meine Abwesenheiten</h3>
        </div>
    </div>
    <?php if (!$absences): ?>
        <p>Noch keine Einträge vorhanden.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Start</th>
                        <th>Ende</th>
                        <th>Grund</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absences as $entry): ?>
                        <?php $isActive = $activeAbsence && $activeAbsence['id'] === $entry['id']; ?>
                        <?php $isPlanned = strtotime($entry['start_at']) > time(); ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime($entry['start_at'])) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($entry['end_at'])) ?></td>
                            <td><?= htmlspecialchars($entry['reason'] ?? '') ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge-live">Aktiv</span>
                                <?php elseif (strtotime($entry['start_at']) > time()): ?>
                                    <span class="badge-rank">Geplant</span>
                                <?php else: ?>
                                    <span class="muted">Abgeschlossen</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isPlanned): ?>
                                    <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="absence_id" value="<?= (int)$entry['id'] ?>">
                                        <button class="btn btn-secondary" type="submit">Löschen</button>
                                    </form>
                                <?php elseif ($isActive && $activeReleaseRequest): ?>
                                    <span class="muted">Freigabe angefragt</span>
                                <?php elseif ($isActive): ?>
                                    <span class="muted">Verwaltung kann dich freigeben</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
renderFooter();