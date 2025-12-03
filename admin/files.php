<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_upload_files');
require_once __DIR__ . '/../includes/layout.php';

renderHeader('Dateiverwaltung', 'admin');
?>
<div class="card">
    <h2>Dateiverwaltung (Platzhalter)</h2>
    <p class="muted">Hier kann später ein vollwertiger Dateimanager integriert werden.</p>
</div>
<?php
renderFooter();
