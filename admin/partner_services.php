<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_partner_services');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/partner_service.php';

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['base_price'] ?? 0);
        if ($name === '') {
            $messages[] = 'Name darf nicht leer sein.';
        } else {
            createPartnerService($pdo, $name, $description, $price);
            $messages[] = 'Service angelegt.';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['base_price'] ?? 0);
        $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : false;
        if ($id > 0 && $name !== '') {
            updatePartnerService($pdo, $id, $name, $description, $price, $isActive);
            $messages[] = 'Service aktualisiert.';
        }
    }
}

$services = getAllPartnerServices($pdo);

renderHeader('Partner Services', 'admin');
?>
<div class="card">
    <h2>Services & Preise verwalten</h2>
    <p class="muted">Basisleistungen und Standardpreise für alle Vertragspartner.</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <h3>Neuen Service anlegen</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Name
            <input type="text" name="name" required>
        </label>
        <label>Beschreibung
            <textarea name="description" rows="2"></textarea>
        </label>
        <label>Standardpreis (€)
            <input type="number" name="base_price" step="0.01" min="0" value="0">
        </label>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>

    <h3>Services</h3>
    <?php if (!$services): ?>
        <p>Keine Services vorhanden.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th>Standardpreis</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <form method="post">
                            <td>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                                <input type="text" name="name" value="<?= htmlspecialchars($service['name']) ?>" required>
                            </td>
                            <td>
                                <textarea name="description" rows="2"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
                            </td>
                            <td><input type="number" name="base_price" step="0.01" min="0" value="<?= htmlspecialchars($service['base_price']) ?>"></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" value="1" <?= $service['is_active'] ? 'checked' : '' ?>>
                                    aktiv
                                </label>
                            </td>
                            <td><button type="submit" class="btn btn-secondary">Aktualisieren</button></td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
renderFooter();