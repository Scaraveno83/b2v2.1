<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_handle_live_support');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/live_support.php';

ensureLiveSupportTables($pdo);

$statusFilter = $_GET['status'] ?? 'pending';

$sql = "SELECT lsr.*, t.title AS ticket_title, req.username AS requester_name, asg.username AS assignee_name
        FROM live_support_requests lsr
        JOIN tickets t ON lsr.ticket_id = t.id
        LEFT JOIN users req ON lsr.requested_by = req.id
        LEFT JOIN users asg ON lsr.assigned_to = asg.id
        WHERE 1=1";
$params = [];

if ($statusFilter !== '') {
    $sql .= " AND lsr.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY lsr.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

renderHeader('Live-Co-Browsing', 'admin');
?>
<div class="card">
    <h2>Live-Co-Browsing</h2>
    <p class="muted">Offene Anfragen aus dem Ticketsystem annehmen, verschieben oder finalisieren.</p>

    <form method="get" class="filter-bar" style="margin-bottom:14px;">
        <label>
            Status:
            <select name="status">
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>alle</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>wartet auf Bearbeitung</option>
                <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>angenommen</option>
                <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>terminiert</option>
                <option value="declined" <?= $statusFilter === 'declined' ? 'selected' : '' ?>>abgelehnt</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>abgeschlossen</option>
            </select>
        </label>
        <button class="btn btn-secondary" type="submit">Filtern</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Ticket</th>
                <th>Status</th>
                <th>Gewünscht von</th>
                <th>Zugewiesen an</th>
                <th>Termin</th>
                <th>Aktualisiert</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td>
                        <a class="btn-link" href="/admin/ticket_view.php?id=<?= (int)$r['ticket_id'] ?>">#<?= (int)$r['ticket_id'] ?></a>
                        – <?= htmlspecialchars($r['ticket_title'] ?? 'Ticket') ?>
                    </td>
                    <td class="ticket-status-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></td>
                    <td><?= htmlspecialchars($r['requester_name'] ?? 'Unbekannt') ?></td>
                    <td><?= htmlspecialchars($r['assignee_name'] ?? '–') ?></td>
                    <td>
                        <?php if (!empty($r['scheduled_for'])): ?>
                            <?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['scheduled_for']))) ?>
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['updated_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="6" class="muted">Keine Anfragen im ausgewählten Filter.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
renderFooter();