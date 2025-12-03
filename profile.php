<?php
require_once __DIR__ . '/auth/check_role.php';
checkRole(['admin', 'employee', 'partner']);
require_once __DIR__ . '/includes/layout.php';

$userId = $_SESSION['user']['id'];

$profileStmt = $pdo->prepare("SELECT id, username, email, password_hash, avatar_path FROM users WHERE id = ? LIMIT 1");
$profileStmt->execute([$userId]);
$user = $profileStmt->fetch();

if (!$user) {
    echo "Profil konnte nicht geladen werden.";
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $avatarPath = $user['avatar_path'] ?? null;
    $newPasswordHash = null;

    if ($username === '') {
        $errors[] = 'Der Benutzername darf nicht leer sein.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    }

    // Passwort ändern, falls ausgefüllt
    if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'Bitte fülle alle Felder aus, um das Passwort zu ändern.';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Das aktuelle Passwort ist nicht korrekt.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'Das neue Passwort und die Wiederholung stimmen nicht überein.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }

    // Avatar-Upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Fehler beim Hochladen des Avatars.';
        } else {
            $maxSize = 2 * 1024 * 1024; // 2 MB
            if ($_FILES['avatar']['size'] > $maxSize) {
                $errors[] = 'Der Avatar darf maximal 2 MB groß sein.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($_FILES['avatar']['tmp_name']);
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowed[$mimeType])) {
                    $errors[] = 'Bitte lade ein JPG, PNG oder WEBP hoch.';
                } else {
                    $targetDir = __DIR__ . '/assets/avatars';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }

                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $allowed[$mimeType];
                    $targetPath = $targetDir . '/' . $filename;

                    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                        $errors[] = 'Der Avatar konnte nicht gespeichert werden.';
                    } else {
                        if (!empty($avatarPath) && strpos($avatarPath, '/assets/avatars/') === 0) {
                            $existingPath = __DIR__ . $avatarPath;
                            if (file_exists($existingPath)) {
                                @unlink($existingPath);
                            }
                        }
                        $avatarPath = '/assets/avatars/' . $filename;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $updateSql = "UPDATE users SET username = ?, email = ?, avatar_path = ?";
        $params = [$username, $email !== '' ? $email : null, $avatarPath];

        if ($newPasswordHash !== null) {
            $updateSql .= ", password_hash = ?";
            $params[] = $newPasswordHash;
        }

        $updateSql .= " WHERE id = ?";
        $params[] = $userId;

        try {
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($params);

            $success = 'Profil wurde aktualisiert.';
            $user['username'] = $username;
            $user['email'] = $email !== '' ? $email : null;
            $user['avatar_path'] = $avatarPath;
            if ($newPasswordHash !== null) {
                $user['password_hash'] = $newPasswordHash;
            }

            refreshSessionUserFromDb($pdo);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Der Benutzername wird bereits verwendet.';
            } else {
                throw $e;
            }
        }
    }
}

renderHeader('Profil', 'profile');
?>
<div class="card">
    <h2>Mein Profil</h2>
    <p class="muted">Passe deine persönlichen Daten an, lade einen Avatar hoch und ändere dein Passwort.</p>

    <?php if (!empty($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="grid grid-2">
            <div class="field-group">
                <label for="username">Benutzername</label>
                <input id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div class="field-group">
                <label for="email">E-Mail-Adresse</label>
                <input id="email" name="email" type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="E-Mail (optional)">
            </div>
        </div>

        <div class="field-group">
            <label for="avatar">Avatar</label>
            <div class="avatar-upload">
                <div class="avatar-preview">
                    <?php if (!empty($user['avatar_path'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="Avatar Vorschau">
                    <?php else: ?>
                        <span><?= strtoupper(substr($user['username'], 0, 2)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="upload-controls">
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="muted">Maximal 2 MB. Erlaubt sind JPG, PNG oder WEBP.</p>
                </div>
            </div>
        </div>

        <div class="password-panel">
            <h3>Passwort ändern</h3>
            <div class="grid grid-2">
                <div class="field-group">
                    <label for="current_password">Aktuelles Passwort</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                </div>
                <div class="field-group">
                    <label for="new_password">Neues Passwort</label>
                    <input id="new_password" name="new_password" type="password" autocomplete="new-password">
                </div>
                <div class="field-group">
                    <label for="confirm_password">Neues Passwort wiederholen</label>
                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
                </div>
            </div>
            <p class="muted">Lasse die Felder leer, wenn du dein Passwort nicht ändern möchtest.</p>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Profil speichern</button>
        </div>
    </form>
</div>
<?php
renderFooter();