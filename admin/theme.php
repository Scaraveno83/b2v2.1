<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_change_settings');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/theme_settings.php';

$settings = loadThemeSettings($pdo);
$draftSettings = loadThemeDraft($pdo);
$templates = getThemeTemplates();
$fontOptions = getBrandFontOptions();
$styleOptions = getBrandStyleOptions();
$layoutOptions = getLayoutVariantOptions();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brandingDir = __DIR__ . '/../uploads/branding';
    if (!is_dir($brandingDir)) {
        mkdir($brandingDir, 0775, true);
    }

    $updatedValues = $_POST;
    $currentLogo = $settings['brand_logo'] ?? '';

    if (!empty($_POST['remove_logo']) && $currentLogo && strpos($currentLogo, '/uploads/branding/') === 0) {
        $existingPath = __DIR__ . '/..' . $currentLogo;
        if (is_file($existingPath)) {
            @unlink($existingPath);
        }
        $currentLogo = '';
    }

    if (isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['brand_logo']['error'] === UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($_FILES['brand_logo']['size'] > $maxSize) {
                $message = ['type' => 'error', 'text' => 'Das Logo darf maximal 2 MB groß sein.'];
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['brand_logo']['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                    'image/svg+xml' => 'svg',
                ];

                if (!isset($allowedMimes[$mime])) {
                    $message = ['type' => 'error', 'text' => 'Bitte lade nur PNG, JPG, WEBP, SVG oder GIF-Dateien hoch.'];
                } else {
                    $extension = $allowedMimes[$mime];
                    $fileName = 'logo-' . time() . '-' . bin2hex(random_bytes(4));
                    $targetPath = $brandingDir . '/' . $fileName . '.' . $extension;

                    if ($extension === 'svg') {
                        $svgContent = file_get_contents($_FILES['brand_logo']['tmp_name']);
                        if (stripos($svgContent, '<script') !== false) {
                            $message = ['type' => 'error', 'text' => 'SVG-Dateien mit Skriptinhalten sind nicht erlaubt.'];
                        } else {
                            file_put_contents($targetPath, $svgContent);
                        }
                    } else {
                        $optimizedPath = null;
                        if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
                            $image = @imagecreatefromstring(file_get_contents($_FILES['brand_logo']['tmp_name']));
                            if ($image !== false) {
                                $webpPath = $brandingDir . '/' . $fileName . '.webp';
                                if (@imagewebp($image, $webpPath, 82)) {
                                    $optimizedPath = $webpPath;
                                    $extension = 'webp';
                                }
                                imagedestroy($image);
                            }
                        }

                        if ($optimizedPath) {
                            $targetPath = $optimizedPath;
                        } elseif (!move_uploaded_file($_FILES['brand_logo']['tmp_name'], $targetPath)) {
                            $message = ['type' => 'error', 'text' => 'Das Logo konnte nicht gespeichert werden.'];
                        }
                    }

                    if (!$message) {
                        if ($currentLogo && strpos($currentLogo, '/uploads/branding/') === 0) {
                            $oldPath = __DIR__ . '/..' . $currentLogo;
                            if (is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $currentLogo = '/uploads/branding/' . $fileName . '.' . $extension;
                    }
                }
            }
        } else {
            $message = ['type' => 'error', 'text' => 'Beim Hochladen des Logos ist ein Fehler aufgetreten.'];
        }
    }

    $updatedValues['brand_logo'] = $currentLogo;
    $updatedValues['brand_name'] = trim($_POST['brand_name'] ?? $settings['brand_name']);

    if (!$message) {
        if (isset($_POST['save_draft'])) {
            $draftSettings = saveThemeDraft($pdo, array_merge($settings, $updatedValues));
            $message = ['type' => 'success', 'text' => 'Entwurf gespeichert. Nutze "Entwurf übernehmen", um ihn zu veröffentlichen.'];
        } elseif (isset($_POST['apply_draft']) && $draftSettings) {
            $settings = saveThemeSettings($pdo, $draftSettings);
            discardThemeDraft($pdo);
            $draftSettings = null;
            $message = ['type' => 'success', 'text' => 'Entwurf übernommen und als aktives Theme gespeichert.'];
        } elseif (isset($_POST['discard_draft'])) {
            discardThemeDraft($pdo);
            $draftSettings = null;
            $message = ['type' => 'success', 'text' => 'Entwurf verworfen.'];
        } elseif (isset($_POST['apply_template']) && isset($templates[$_POST['apply_template']])) {
            $template = $templates[$_POST['apply_template']];
            $templateSettings = array_merge($settings, $template['settings']);
            $settings = saveThemeSettings($pdo, array_merge($templateSettings, $updatedValues));
            $message = ['type' => 'success', 'text' => 'Template "' . htmlspecialchars($template['name']) . '" wurde geladen und gespeichert.'];
        } elseif (isset($_POST['reset_defaults'])) {
           $branding = [
                'brand_name' => $updatedValues['brand_name'],
                'brand_logo' => $updatedValues['brand_logo'],
                'brand_font' => $updatedValues['brand_font'] ?? $settings['brand_font'],
                'brand_font_style' => $updatedValues['brand_font_style'] ?? $settings['brand_font_style'],
                'brand_font_size' => $updatedValues['brand_font_size'] ?? $settings['brand_font_size'],
                'brand_letter_spacing' => $updatedValues['brand_letter_spacing'] ?? $settings['brand_letter_spacing'],
                'layout_variant' => $updatedValues['layout_variant'] ?? $settings['layout_variant'],
            ];
            $settings = saveThemeSettings($pdo, array_merge(getDefaultThemeSettings(), $branding));
            $message = ['type' => 'success', 'text' => 'Farben wurden auf die Standardwerte zurückgesetzt.'];
        } else {
            $settings = saveThemeSettings($pdo, $updatedValues);
            $message = ['type' => 'success', 'text' => 'Design-Anpassungen gespeichert.'];
        }
    }
}

renderHeader('Design & Branding', 'admin');
?>
<style>
    .template-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 18px; }
    .template-card { border: 1px solid var(--surface-border, #1f2937); border-radius: 12px; padding: 14px; background: var(--surface, rgba(5,7,24,0.85)); box-shadow: 0 8px 24px rgba(0,0,0,0.18); }
    .template-card h4 { margin: 0 0 6px; }
    .template-card .muted { margin: 0 0 10px; display: block; }
    .swatch-row { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; margin: 10px 0; }
    .swatch { height: 32px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.08); box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08); }
    .template-card form { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 10px; }
    .branding-preview { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .branding-preview img { width: 56px; height: 56px; object-fit: contain; border-radius: 14px; background: var(--surface); padding: 6px; border: 1px solid var(--surface-border); }
</style>

<div class="card">
    <h2>Design Templates</h2>
    <p class="muted">Lade vordefinierte Farbwelten und passe sie anschließend nach Bedarf an.</p>
    <div class="template-grid">
        <?php foreach ($templates as $key => $template): $tpl = $template['settings']; ?>
            <div class="template-card">
                <div>
                    <h4><?= htmlspecialchars($template['name']) ?></h4>
                    <span class="muted"><?= htmlspecialchars($template['description']) ?></span>
                    <?php if (!empty($tpl['layout_variant']) && isset($layoutOptions[$tpl['layout_variant']])): ?>
                        <span class="badge">Layout: <?= htmlspecialchars($layoutOptions[$tpl['layout_variant']]) ?></span>
                    <?php endif; ?>
                </div>
                <div class="swatch-row">
                    <div class="swatch" style="background: <?= htmlspecialchars($tpl['bg1']) ?>;"></div>
                    <div class="swatch" style="background: <?= htmlspecialchars($tpl['bg2']) ?>;"></div>
                    <div class="swatch" style="background: <?= htmlspecialchars($tpl['surface']) ?>;"></div>
                    <div class="swatch" style="background: <?= htmlspecialchars($tpl['accent_cyan']) ?>;"></div>
                    <div class="swatch" style="background: <?= htmlspecialchars($tpl['accent_magenta']) ?>;"></div>
                </div>
                <form method="post">
                    <div class="muted">Akzent: <?= htmlspecialchars($tpl['accent_cyan']) ?> / <?= htmlspecialchars($tpl['accent_magenta']) ?></div>
                    <button type="submit" name="apply_template" value="<?= htmlspecialchars($key) ?>" class="btn btn-secondary">Template laden</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="card">
    <div class="card-header-row">
        <div>
            <h2>Live-Vorschau & Entwürfe</h2>
            <p class="muted">Passe Farben an und beobachte Änderungen sofort. Speichere einen Entwurf, teste ihn und übernehme ihn erst nach Freigabe.</p>
        </div>
        <div class="card-header-actions">
            <a class="btn btn-secondary" href="/api/theme.php?includeDraft=1" target="_blank" rel="noreferrer">Design-Tokens (JSON)</a>
            <a class="btn btn-secondary" href="/api/theme.php?format=css" target="_blank" rel="noreferrer">CSS-Variablen exportieren</a>
        </div>
    </div>
    <?php if ($draftSettings): ?>
        <div class="alert info">Es existiert ein gespeicherter Entwurf mit <?= count(array_keys($draftSettings)) ?> Werten.</div>
    <?php else: ?>
        <div class="alert muted">Kein Entwurf gespeichert. Nutze "Als Entwurf sichern", um gefahrlos zu testen.</div>
    <?php endif; ?>
    <div class="form-section">
        <h3>Entwurfsaktionen</h3>
        <p class="muted">Speichere die aktuellen Eingaben als Draft oder übernimm einen vorhandenen Entwurf.</p>
        <div class="form-actions">
            <button type="submit" form="theme-form" name="save_draft" value="1" class="btn btn-secondary">Als Entwurf sichern</button>
            <button type="submit" form="theme-form" name="apply_draft" value="1" class="btn btn-primary" <?= $draftSettings ? '' : 'disabled' ?>>Entwurf übernehmen</button>
            <button type="submit" form="theme-form" name="discard_draft" value="1" class="btn btn-secondary" <?= $draftSettings ? '' : 'disabled' ?>>Entwurf verwerfen</button>
        </div>
    </div>
    <div class="alert muted" id="preview-status">Live-Vorschau aktiv. Werte werden erst mit "Speichern" oder "Entwurf sichern" übernommen.</div>
    <div class="alert warning" id="contrast-warnings" style="display:none"></div>
</div>
<div class="card">
    <h2>Design & Branding</h2>
    <p class="muted">Passe Hintergrundfarben, Akzent-Verläufe und Textfarben an das Corporate Design deines Kunden an.</p>

    <?php if ($message): ?>
        <div class="alert <?= htmlspecialchars($message['type']) ?>">
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid" enctype="multipart/form-data" id="theme-form">
        <div class="form-section">
            <h3>Branding</h3>
            <label>Panel-Name
                <input type="text" name="brand_name" value="<?= htmlspecialchars($settings['brand_name']) ?>" maxlength="128" required>
            </label>
            <label>Schriftart für den Namen␊
                <select name="brand_font">␊
                    <?php foreach ($fontOptions as $key => $font): ?>␊
                        <option value="<?= htmlspecialchars($key) ?>" <?= ($settings['brand_font'] ?? '') === $key ? 'selected' : '' ?>>␊
                            <?= htmlspecialchars($font['label']) ?>␊
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="muted">Wähle aus breiten, Outline-, Pixel- oder Serif-Display-Schriften für markante Logos.</span>
            </label>
            <label>Style-Effekt␊
                <select name="brand_font_style">␊
                    <?php foreach ($styleOptions as $key => $label): ?>␊
                        <option value="<?= htmlspecialchars($key) ?>" <?= ($settings['brand_font_style'] ?? '') === $key ? 'selected' : '' ?>>␊
                            <?= htmlspecialchars($label) ?>␊
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="muted">Neon, Chrom, Retro, Hologramm, Glitch, Laser-Scan, Aurora-Schimmer oder pulsierende Outline – auch animiert – stehen zur Auswahl.</span>
            </label>
            <label>Layout-Modus
                <select name="layout_variant">
                    <?php foreach ($layoutOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= ($settings['layout_variant'] ?? 'standard') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="muted">Steuert Navigation, Kartenlayout und Form-Feeling (Standard, Split Panels, Terminal Rail, Floating Board).</span>
            </label>
            <label>Schriftgröße
                <input type="text" name="brand_font_size" value="<?= htmlspecialchars($settings['brand_font_size']) ?>" required>
                <span class="muted">z. B. 1.24rem, 22px oder 110%.</span>
            </label>
            <label>Buchstaben-Abstand
                <input type="text" name="brand_letter_spacing" value="<?= htmlspecialchars($settings['brand_letter_spacing']) ?>" required>
                <span class="muted">Akzeptiert Einheiten wie em, rem, px oder % (z. B. 0.08em).</span>
            </label>
            <label>Logo hochladen
                <input type="file" name="brand_logo" accept="image/png,image/jpeg,image/webp,image/gif">
                <span class="muted">Hinweis: Quadratische Dateien (z. B. 1024×1024) werden im Header automatisch auf ca. 40–50 px skaliert. Max. 2 MB.</span>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="remove_logo" value="1"> Logo entfernen
            </label>
            <?php if (!empty($settings['brand_logo'])): ?>
                <div class="branding-preview">
                    <img src="<?= htmlspecialchars($settings['brand_logo']) ?>" alt="Aktuelles Logo">
                    <div>
                        <div class="muted">Aktuelles Logo</div>
                        <div><?= htmlspecialchars($settings['brand_name']) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-section">
            <h3>Abstände & Kanten</h3>
            <label>Karten-Radius groß
                <input type="text" name="radius_lg" value="<?= htmlspecialchars($settings['radius_lg']) ?>" required>
            </label>
            <label>Kanten-Radius mittel
                <input type="text" name="radius_md" value="<?= htmlspecialchars($settings['radius_md']) ?>" required>
            </label>
            <label>Buttons / Pill-Radius
                <input type="text" name="radius_pill" value="<?= htmlspecialchars($settings['radius_pill']) ?>" required>
            </label>
            <label>Karten-Schatten (Dark)
                <input type="text" name="shadow_card" value="<?= htmlspecialchars($settings['shadow_card']) ?>" required>
                <span class="muted">Box-Shadow Notation, z. B. 0 20px 55px rgba(0,0,0,0.85).</span>
            </label>
            <label>Karten-Schatten (Light)
                <input type="text" name="shadow_card_light" value="<?= htmlspecialchars($settings['shadow_card_light']) ?>" required>
            </label>
            <label>Layout-Dichte Faktor
                <input type="number" step="0.05" min="0.7" max="2" name="layout_density" value="<?= htmlspecialchars($settings['layout_density']) ?>" required>
                <span class="muted">1 = Standard. Höher = großzügiger Abstand, niedriger = kompakt.</span>
            </label>
        </div>
        <div class="form-section">
            <h3>Grundflächen</h3>
            <label>Primärer Hintergrund 1
                <input type="color" name="bg1" value="<?= htmlspecialchars($settings['bg1']) ?>" required>
            </label>
            <label>Primärer Hintergrund 2
                <input type="color" name="bg2" value="<?= htmlspecialchars($settings['bg2']) ?>" required>
            </label>
            <label>Primärer Hintergrund 3
                <input type="color" name="bg3" value="<?= htmlspecialchars($settings['bg3']) ?>" required>
            </label>
            <label>Panel-Flächen (RGBA möglich)
                <input type="text" name="surface" value="<?= htmlspecialchars($settings['surface']) ?>" required>
            </label>
            <label>Panel-Randfarbe (RGBA möglich)
                <input type="text" name="surface_border" value="<?= htmlspecialchars($settings['surface_border']) ?>" required>
            </label>
        </div>

        <div class="form-section">
            <h3>Akzentfarben & Verläufe</h3>
            <label>Akzentfarbe Cyan
                <input type="color" name="accent_cyan" value="<?= htmlspecialchars($settings['accent_cyan']) ?>" required>
            </label>
            <label>Akzentfarbe Magenta
                <input type="color" name="accent_magenta" value="<?= htmlspecialchars($settings['accent_magenta']) ?>" required>
            </label>
            <label>Akzentfarbe Purple
                <input type="color" name="accent_purple" value="<?= htmlspecialchars($settings['accent_purple']) ?>" required>
            </label>
            <label>Hinweis-/Badge-Farbe Gelb
                <input type="color" name="accent_yellow" value="<?= htmlspecialchars($settings['accent_yellow']) ?>" required>
            </label>
        </div>

        <div class="form-section">
            <h3>Textfarben</h3>
            <label>Text Hellmodus - Primär
                <input type="color" name="text_main_light" value="<?= htmlspecialchars($settings['text_main_light']) ?>" required>
            </label>
            <label>Text Hellmodus - Sekundär
                <input type="color" name="text_muted_light" value="<?= htmlspecialchars($settings['text_muted_light']) ?>" required>
            </label>
            <label>Text Dunkelmodus - Primär
                <input type="color" name="text_main" value="<?= htmlspecialchars($settings['text_main']) ?>" required>
            </label>
            <label>Text Dunkelmodus - Sekundär
                <input type="color" name="text_muted" value="<?= htmlspecialchars($settings['text_muted']) ?>" required>
            </label>
        </div>

        <div class="form-section">
            <h3>Header & Transparenzen</h3>
            <label>Header-Hintergrund (Start)
                <input type="text" name="header_bg_start" value="<?= htmlspecialchars($settings['header_bg_start']) ?>" required>
            </label>
            <label>Header-Hintergrund (Ende)
                <input type="text" name="header_bg_end" value="<?= htmlspecialchars($settings['header_bg_end']) ?>" required>
            </label>
            <label>Header-Randfarbe
                <input type="text" name="header_border" value="<?= htmlspecialchars($settings['header_border']) ?>" required>
            </label>
            <label>Header-Schattenbasis
                <input type="text" name="header_shadow_base" value="<?= htmlspecialchars($settings['header_shadow_base']) ?>" required>
            </label>
            <label>Header (Hellmodus) Hintergrund
                <input type="text" name="header_light_bg" value="<?= htmlspecialchars($settings['header_light_bg']) ?>" required>
            </label>
            <label>Header (Hellmodus) Rand
                <input type="text" name="header_light_border" value="<?= htmlspecialchars($settings['header_light_border']) ?>" required>
            </label>
            <label>Header (Hellmodus) Schatten
                <input type="text" name="header_light_shadow" value="<?= htmlspecialchars($settings['header_light_shadow']) ?>" required>
            </label>
        </div>

        <div class="form-section">
            <h3>Hellmodus-Hintergrund</h3>
            <label>Verlauf Hellmodus Start
                <input type="color" name="light_bg1" value="<?= htmlspecialchars($settings['light_bg1']) ?>" required>
            </label>
            <label>Verlauf Hellmodus Mitte
                <input type="color" name="light_bg2" value="<?= htmlspecialchars($settings['light_bg2']) ?>" required>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <button type="submit" name="reset_defaults" value="1" class="btn btn-secondary">Auf Standardwerte zurücksetzen</button>
        </div>
    </form>
</div>
<script>
(function() {
    const form = document.getElementById('theme-form');
    const warningBox = document.getElementById('contrast-warnings');
    const logoText = document.querySelector('.logo-text');
    if (!form) return;

    const varMap = {
        bg1: '--bg1',
        bg2: '--bg2',
        bg3: '--bg3',
        surface: '--surface',
        surface_border: '--surface-border',
        accent_cyan: '--accent-cyan',
        accent_magenta: '--accent-magenta',
        accent_purple: '--accent-purple',
        accent_yellow: '--accent-yellow',
        text_main: '--text-main',
        text_muted: '--text-muted',
        text_main_light: '--text-main-light',
        text_muted_light: '--text-muted-light',
        light_bg1: '--light-bg1',
        light_bg2: '--light-bg2',
        header_bg_start: '--header-bg-start',
        header_bg_end: '--header-bg-end',
        header_border: '--header-border',
        header_light_bg: '--header-light-bg',
        header_light_border: '--header-light-border',
        header_light_shadow: '--header-light-shadow',
        header_shadow_base: '--header-shadow-base',
        radius_lg: '--radius-lg',
        radius_md: '--radius-md',
        radius_pill: '--radius-pill',
        shadow_card: '--shadow-card',
        shadow_card_light: '--shadow-card-light',
        layout_density: '--layout-density'
    };

    function setVar(name, value) {
        document.documentElement.style.setProperty(name, value);
    }

    function hexToRgb(hex) {
        const sanitized = (hex || '').trim();
        if (!/^#?[0-9a-fA-F]{6}$/.test(sanitized.replace('#',''))) return null;
        const normalized = sanitized.replace('#','');
        const bigint = parseInt(normalized, 16);
        return [(bigint >> 16) & 255, (bigint >> 8) & 255, bigint & 255];
    }

    function applyPreview() {
        const formData = new FormData(form);
        Object.keys(varMap).forEach(function(key){
            const value = formData.get(key);
            if (value !== null && value !== '') {
                setVar(varMap[key], value);
            }
        });

        const accentCyan = hexToRgb(formData.get('accent_cyan'));
        const accentMagenta = hexToRgb(formData.get('accent_magenta'));
        const accentPurple = hexToRgb(formData.get('accent_purple'));
        const accentYellow = hexToRgb(formData.get('accent_yellow'));
        if (accentCyan) {
            setVar('--accent-cyan-rgb', accentCyan.join(','));
            setVar('--glow-radial-cyan', `rgba(${accentCyan.join(',')},0.28)`);
            setVar('--glow-line-cyan', `rgba(${accentCyan.join(',')},0.08)`);
            setVar('--header-shadow-glow', `0 0 20px rgba(${accentCyan.join(',')},0.4)`);
        }
        if (accentMagenta) {
            setVar('--accent-magenta-rgb', accentMagenta.join(','));
            setVar('--glow-radial-magenta', `rgba(${accentMagenta.join(',')},0.26)`);
            setVar('--glow-line-magenta', `rgba(${accentMagenta.join(',')},0.07)`);
        }
        if (accentPurple) {
            setVar('--accent-purple-rgb', accentPurple.join(','));
            setVar('--glow-radial-purple', `rgba(${accentPurple.join(',')},0.3)`);
        }
        if (accentYellow) {
            setVar('--accent-yellow-rgb', accentYellow.join(','));
        }

        const layoutVariant = formData.get('layout_variant') || 'standard';
        document.body.dataset.layoutVariant = layoutVariant;

        const brandName = formData.get('brand_name') || '';
        if (logoText && brandName) {
            logoText.textContent = brandName;
        }
        const brandStyle = formData.get('brand_font_style');
        if (logoText && brandStyle) {
            logoText.className = 'logo-text brand-style-' + brandStyle;
        }

        runContrastChecks(formData);
    }

    function relativeLuminance([r, g, b]) {
        const srgb = [r, g, b].map(v => {
            const val = v / 255;
            return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
        });
        return srgb[0] * 0.2126 + srgb[1] * 0.7152 + srgb[2] * 0.0722;
    }

    function contrastRatio(rgb1, rgb2) {
        const l1 = relativeLuminance(rgb1);
        const l2 = relativeLuminance(rgb2);
        const [bright, dark] = l1 > l2 ? [l1, l2] : [l2, l1];
        return (bright + 0.05) / (dark + 0.05);
    }

    function runContrastChecks(formData) {
        const checks = [
            { fg: 'text_main', bg: 'bg1', label: 'Primärtext Dunkelmodus auf Hintergrund 1' },
            { fg: 'text_muted', bg: 'bg1', label: 'Sekundärtext Dunkelmodus auf Hintergrund 1' },
            { fg: 'text_main_light', bg: 'light_bg1', label: 'Primärtext Hellmodus auf Hintergrund' },
            { fg: 'text_muted_light', bg: 'light_bg1', label: 'Sekundärtext Hellmodus auf Hintergrund' }
        ];
        const messages = [];
        checks.forEach(function(check){
            const fg = hexToRgb(formData.get(check.fg));
            const bg = hexToRgb(formData.get(check.bg));
            if (!fg || !bg) return;
            const ratio = contrastRatio(fg, bg);
            if (ratio < 4.5) {
                messages.push(`${check.label}: Kontrast ${ratio.toFixed(2)}:1 unter 4.5:1`);
            }
        });

        if (messages.length) {
            warningBox.style.display = 'block';
            warningBox.textContent = messages.join(' · ');
        } else {
            warningBox.style.display = 'none';
        }
    }

    form.querySelectorAll('input, select').forEach(function(input){
        input.addEventListener('input', applyPreview);
        input.addEventListener('change', applyPreview);
    });

    applyPreview();
})();
</script>
<?php
renderFooter();