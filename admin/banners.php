<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_banners');
requirePermission('can_upload_files');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/banner_service.php';

ensureBannerSchema($pdo);

$message = null;
$uploadDir = __DIR__ . '/../uploads/banners';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_banner'])) {
        $bannerId = (int)$_POST['delete_banner'];
        $banner = deleteHomepageBanner($pdo, $bannerId);
        if ($banner) {
            if (!empty($banner['image_path']) && strpos($banner['image_path'], '/uploads/banners/') === 0) {
                $path = __DIR__ . '/..' . $banner['image_path'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $message = ['type' => 'success', 'text' => 'Banner wurde gelöscht.'];
        } else {
            $message = ['type' => 'error', 'text' => 'Banner konnte nicht gefunden werden.'];
        }
    } elseif (isset($_POST['upload_banner'])) {
        $title = trim($_POST['title'] ?? 'Neues Banner');
        if ($title === '') {
            $title = 'Neues Banner';
        }

        if (!isset($_FILES['banner_file']) || $_FILES['banner_file']['error'] === UPLOAD_ERR_NO_FILE) {
            $message = ['type' => 'error', 'text' => 'Bitte wähle eine Datei aus.'];
        } elseif ($_FILES['banner_file']['error'] !== UPLOAD_ERR_OK) {
            $message = ['type' => 'error', 'text' => 'Beim Upload ist ein Fehler aufgetreten.'];
        } else {
            $maxSize = 4 * 1024 * 1024; // 4MB
            if ($_FILES['banner_file']['size'] > $maxSize) {
                $message = ['type' => 'error', 'text' => 'Das Banner darf maximal 4 MB groß sein.'];
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['banner_file']['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                    'image/svg+xml' => 'svg',
                ];

                if (!isset($allowedMimes[$mime])) {
                    $message = ['type' => 'error', 'text' => 'Erlaubt sind nur PNG, JPG, WEBP, SVG oder GIF.'];
                } else {
                    $extension = $allowedMimes[$mime];
                    $fileName = 'banner-' . time() . '-' . bin2hex(random_bytes(3));
                    $targetPath = $uploadDir . '/' . $fileName . '.' . $extension;

                    if ($extension === 'svg') {
                        $svgContent = file_get_contents($_FILES['banner_file']['tmp_name']);
                        if (stripos($svgContent, '<script') !== false) {
                            $message = ['type' => 'error', 'text' => 'SVG-Dateien mit Skripten sind nicht erlaubt.'];
                        } else {
                            file_put_contents($targetPath, $svgContent);
                        }
                    } else {
                        $optimizedPath = null;
                        if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
                            $image = @imagecreatefromstring(file_get_contents($_FILES['banner_file']['tmp_name']));
                            if ($image !== false) {
                                $webpPath = $uploadDir . '/' . $fileName . '.webp';
                                if (@imagewebp($image, $webpPath, 82)) {
                                    $optimizedPath = $webpPath;
                                    $extension = 'webp';
                                }
                                imagedestroy($image);
                            }
                        }

                        if ($optimizedPath) {
                            $targetPath = $optimizedPath;
                        } elseif (!move_uploaded_file($_FILES['banner_file']['tmp_name'], $targetPath)) {
                            $message = ['type' => 'error', 'text' => 'Die Datei konnte nicht gespeichert werden.'];
                        }
                    }

                    if (!$message) {
                        $webPath = '/uploads/banners/' . $fileName . '.' . $extension;
                        createHomepageBanner($pdo, $title, $webPath);
                        $message = ['type' => 'success', 'text' => 'Banner wurde hochgeladen und ist sofort sichtbar.'];
                    }
                }
            }
        }
    }
}

$banners = fetchHomepageBanners($pdo, 50);

renderHeader('Startseiten-Banner', 'admin');
?>
<style>
    .banner-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-top: 16px; }
    .banner-card { border: 1px solid var(--surface-border, rgba(255,255,255,0.08)); border-radius: 14px; background: var(--surface, rgba(7,9,26,0.92)); padding: 12px; box-shadow: 0 10px 28px rgba(0,0,0,0.16); position: relative; overflow: hidden; }
    .banner-card img { width: 100%; max-height: 140px; object-fit: contain; display: block; border-radius: 10px; background: var(--bg2); padding: 8px; }
    .banner-card h4 { margin: 10px 0 4px; }
    .banner-card .muted { margin: 0; }
    .banner-card form { position: absolute; top: 10px; right: 10px; }
    .banner-upload { display: grid; gap: 10px; margin-top: 12px; }
</style>
<div class="card">
    <div class="card-head">
        <div>
            <p class="eyebrow">Startseite</p>
            <h2>Werbe-Logos & Banner</h2>
            <p class="muted">Uploads landen rechts schwebend auf der Startseite und rotieren mit animierten Übergängen.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= htmlspecialchars($message['type']) ?>"><?= htmlspecialchars($message['text']) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="banner-upload">
        <div class="form-row">
            <label for="title">Label / Sponsor-Name</label>
            <input type="text" name="title" id="title" placeholder="z.B. Premium Partner" maxlength="120">
        </div>
        <div class="form-row">
            <label for="banner_file">Bilddatei (PNG, JPG, WEBP, SVG, GIF)</label>
            <input type="file" name="banner_file" id="banner_file" accept="image/*" required>
        </div>
        <div class="form-actions">
            <button type="submit" name="upload_banner" value="1" class="btn btn-primary">Banner hochladen</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Aktive Banner</h3>
    <?php if (!$banners): ?>
        <p class="muted">Noch keine Banner hochgeladen.</p>
    <?php else: ?>
        <div class="banner-grid">
            <?php foreach ($banners as $banner): ?>
                <div class="banner-card">
                    <img src="<?= htmlspecialchars($banner['image_path']) ?>" alt="<?= htmlspecialchars($banner['title'] ?? 'Banner') ?>">
                    <h4><?= htmlspecialchars($banner['title'] ?? 'Unbenannt') ?></h4>
                    <p class="muted">Seit <?= htmlspecialchars(date('d.m.Y H:i', strtotime($banner['created_at']))) ?></p>
                    <form method="post" onsubmit="return confirm('Banner wirklich entfernen?');">
                        <input type="hidden" name="delete_banner" value="<?= (int)$banner['id'] ?>">
                        <button type="submit" class="btn btn-secondary">Entfernen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
renderFooter();