<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

function ensureThemeSettingsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ui_theme_settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function getDefaultThemeSettings(): array
{
    return [
        'brand_name' => 'ULTRA NEON PANEL',
        'brand_logo' => '',
        'brand_font' => 'neon-tech',
        'brand_font_style' => 'neon-depth',
        'brand_font_size' => '1.32rem',
        'brand_letter_spacing' => '0.08em',
        'radius_lg' => '26px',
        'radius_md' => '16px',
        'radius_pill' => '999px',
        'shadow_card' => '0 20px 55px rgba(0,0,0,0.85)',
        'shadow_card_light' => '0 18px 40px rgba(15,23,42,0.18)',
        'layout_density' => '1',
        'bg1' => '#050716',
        'bg2' => '#130624',
        'bg3' => '#04030a',
        'accent_cyan' => '#00f7ff',
        'accent_magenta' => '#ff00ff',
        'accent_purple' => '#7b5cff',
        'accent_yellow' => '#facc15',
        'text_main' => '#f9fafb',
        'text_muted' => '#9ca3af',
        'text_main_light' => '#020617',
        'text_muted_light' => '#4b5563',
        'surface' => 'rgba(5, 7, 24, 0.85)',
        'surface_border' => 'rgba(148,163,184,0.45)',
        'light_bg1' => '#e5e7eb',
        'light_bg2' => '#f9fafb',
        'header_bg_start' => 'rgba(15,23,42,0.9)',
        'header_bg_end' => 'rgba(15,23,42,0.98)',
        'header_border' => 'rgba(148,163,184,0.3)',
        'header_light_bg' => 'rgba(249,250,251,0.96)',
        'header_light_border' => 'rgba(148,163,184,0.45)',
        'header_light_shadow' => '0 10px 25px rgba(15,23,42,0.18)',
        'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.5)',
    ];
}

function getBrandFontOptions(): array
{
    return [
        'neon-tech' => [
            'label' => 'Neon Tech (Orbitron)',
            'stack' => "'Orbitron', 'Bank Gothic', 'Eurostile', 'Segoe UI', system-ui, sans-serif",
            'weight' => '800',
        ],
        'bungee' => [
            'label' => 'Bungee Display',
            'stack' => "'Bungee', 'Impact', 'Segoe UI', system-ui, sans-serif",
            'weight' => '800',
        ],
        'oswald' => [
            'label' => 'Oswald Condensed',
            'stack' => "'Oswald', 'Arial Narrow', 'Segoe UI', system-ui, sans-serif",
            'weight' => '700',
        ],
        'poppins' => [
            'label' => 'Poppins Bold',
            'stack' => "'Poppins', 'Segoe UI', system-ui, sans-serif",
            'weight' => '800',
        ],
        'audiowide' => [
            'label' => 'Audiowide Wide',
            'stack' => "'Audiowide', 'Eurostile', 'Segoe UI', system-ui, sans-serif",
            'weight' => '700',
        ],
        'teko' => [
            'label' => 'Teko Expanded',
            'stack' => "'Teko', 'Impact', 'Segoe UI', system-ui, sans-serif",
            'weight' => '700',
        ],
        'russo-one' => [
            'label' => 'Russo One Heavy',
            'stack' => "'Russo One', 'Exo 2', 'Segoe UI', system-ui, sans-serif",
            'weight' => '800',
        ],
        'monoton' => [
            'label' => 'Monoton Neon Outline',
            'stack' => "'Monoton', 'Arial Black', 'Segoe UI', system-ui, sans-serif",
            'weight' => '400',
        ],
        'black-ops' => [
            'label' => 'Black Ops One Stencil',
            'stack' => "'Black Ops One', 'Impact', 'Segoe UI', system-ui, sans-serif",
            'weight' => '400',
        ],
        'press-start' => [
            'label' => 'Press Start Pixel',
            'stack' => "'Press Start 2P', 'VT323', 'Courier New', monospace",
            'weight' => '400',
        ],
        'rajdhani' => [
            'label' => 'Rajdhani Wide',
            'stack' => "'Rajdhani', 'Arial Narrow', 'Segoe UI', system-ui, sans-serif",
            'weight' => '700',
        ],
        'exo-tech' => [
            'label' => 'Exo 2 Futuristic',
            'stack' => "'Exo 2', 'Eurostile', 'Segoe UI', system-ui, sans-serif",
            'weight' => '800',
        ],
        'cinzel-decorative' => [
            'label' => 'Cinzel Decorative',
            'stack' => "'Cinzel Decorative', 'Trajan', 'Georgia', serif",
            'weight' => '700',
        ],
        'montserrat-sub' => [
            'label' => 'Montserrat Subrayada',
            'stack' => "'Montserrat Subrayada', 'Montserrat', 'Segoe UI', system-ui, sans-serif",
            'weight' => '700',
        ],
    ];
}

function getBrandStyleOptions(): array
{
    return [
        'neon-depth' => 'Neon-Tiefe mit starkem Glow',
        'chrome-bevel' => 'Chrom-Kanten mit Glanz',
        'soft-glow' => 'Weiche Leuchtbuchstaben',
        'retro-wave' => 'Retro Gradient mit Schatten',
        'holo-foil' => 'Holografische Folienkante',
        'glitch-layers' => 'Digitale Glitch-Layer',
        'sunset-outline' => 'Abgesetzte Outline mit Verlauf',
        'laser-grid' => 'Laser-Gitter mit animiertem Scan',
        'aurora-veil' => 'Aurora-Schimmer (animiert)',
        'pulse-outline' => 'Pulsierende Neon-Outline',
        'prismatic-echo' => 'Prismatische Echoschichten',
    ];
}

function resolveBrandFontStack(string $key): string
{
    $options = getBrandFontOptions();
    return $options[$key]['stack'] ?? $options['neon-tech']['stack'];
}

function resolveBrandFontWeight(string $key): string
{
    $options = getBrandFontOptions();
    return $options[$key]['weight'] ?? $options['neon-tech']['weight'];
}

function getThemeTemplates(): array
{
    return [
        'neon_nightfall' => [
            'name' => 'Neon Nightfall',
            'description' => 'Dunkler, futuristischer Look mit Cyan- und Magenta-Glows.',
            'settings' => getDefaultThemeSettings(),
        ],
        'emerald_oasis' => [
            'name' => 'Emerald Oasis',
            'description' => 'Klare Grüntöne mit goldenen Akzenten für ein frisches Dashboard.',
            'settings' => [
                'bg1' => '#06110d',
                'bg2' => '#0b1f18',
                'bg3' => '#050c09',
                'accent_cyan' => '#1abc9c',
                'accent_magenta' => '#16a085',
                'accent_purple' => '#0f766e',
                'accent_yellow' => '#d1fa4d',
                'text_main' => '#ecfdf3',
                'text_muted' => '#a7f3d0',
                'text_main_light' => '#02140d',
                'text_muted_light' => '#2f5d4d',
                'surface' => 'rgba(5, 12, 9, 0.88)',
                'surface_border' => 'rgba(16,185,129,0.35)',
                'light_bg1' => '#e8f7ef',
                'light_bg2' => '#f5fff8',
                'header_bg_start' => 'rgba(8,20,16,0.92)',
                'header_bg_end' => 'rgba(7,31,24,0.98)',
                'header_border' => 'rgba(16,185,129,0.3)',
                'header_light_bg' => 'rgba(236,253,243,0.96)',
                'header_light_border' => 'rgba(16,185,129,0.35)',
                'header_light_shadow' => '0 10px 25px rgba(16,185,129,0.18)',
                'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.45)',
            ],
        ],
        'sunset_breeze' => [
            'name' => 'Sunset Breeze',
            'description' => 'Warme Orange-/Violett-Verläufe mit hellem Kontrast.',
            'settings' => [
                'bg1' => '#15050c',
                'bg2' => '#2b0a28',
                'bg3' => '#0d0409',
                'accent_cyan' => '#fb923c',
                'accent_magenta' => '#d946ef',
                'accent_purple' => '#a855f7',
                'accent_yellow' => '#fcd34d',
                'text_main' => '#fff7ed',
                'text_muted' => '#f5d0fe',
                'text_main_light' => '#180510',
                'text_muted_light' => '#5b2146',
                'surface' => 'rgba(21, 5, 12, 0.88)',
                'surface_border' => 'rgba(244,114,182,0.32)',
                'light_bg1' => '#fff0e6',
                'light_bg2' => '#fff7ed',
                'header_bg_start' => 'rgba(43,10,40,0.92)',
                'header_bg_end' => 'rgba(32,7,27,0.97)',
                'header_border' => 'rgba(244,114,182,0.3)',
                'header_light_bg' => 'rgba(255,247,237,0.96)',
                'header_light_border' => 'rgba(244,114,182,0.34)',
                'header_light_shadow' => '0 10px 25px rgba(244,114,182,0.2)',
                'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.45)',
            ],
        ],
        'cyber_rave' => [
            'name' => 'Cyber Rave',
            'description' => 'Extremer Neon-Look mit starkem Blau/Magenta-Glow.',
            'settings' => [
                'bg1' => '#030712',
                'bg2' => '#070f24',
                'bg3' => '#020510',
                'accent_cyan' => '#3cf4ff',
                'accent_magenta' => '#ff2fd2',
                'accent_purple' => '#9b7bff',
                'accent_yellow' => '#f5f50a',
                'text_main' => '#e5f4ff',
                'text_muted' => '#a5c4ff',
                'text_main_light' => '#030712',
                'text_muted_light' => '#1f2937',
                'surface' => 'rgba(7, 15, 36, 0.9)',
                'surface_border' => 'rgba(60, 244, 255, 0.4)',
                'light_bg1' => '#eaf2ff',
                'light_bg2' => '#f7fbff',
                'header_bg_start' => 'rgba(2,7,18,0.92)',
                'header_bg_end' => 'rgba(3,12,28,0.98)',
                'header_border' => 'rgba(60,244,255,0.32)',
                'header_light_bg' => 'rgba(247,251,255,0.96)',
                'header_light_border' => 'rgba(60,130,246,0.32)',
                'header_light_shadow' => '0 10px 25px rgba(60,244,255,0.18)',
                'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.55)',
            ],
        ],
        'acid_nebula' => [
            'name' => 'Acid Nebula',
            'description' => 'Knalliges Neon-Grün/Pink mit leuchtenden Kanten.',
            'settings' => [
                'bg1' => '#040910',
                'bg2' => '#0b1224',
                'bg3' => '#050b14',
                'accent_cyan' => '#7cfbde',
                'accent_magenta' => '#ff4cf5',
                'accent_purple' => '#b26bff',
                'accent_yellow' => '#d2ff52',
                'text_main' => '#f4fbff',
                'text_muted' => '#c7d2fe',
                'text_main_light' => '#040910',
                'text_muted_light' => '#27303f',
                'surface' => 'rgba(5, 11, 20, 0.9)',
                'surface_border' => 'rgba(255, 76, 245, 0.35)',
                'light_bg1' => '#edf2ff',
                'light_bg2' => '#f9fcff',
                'header_bg_start' => 'rgba(5,9,16,0.92)',
                'header_bg_end' => 'rgba(9,16,32,0.97)',
                'header_border' => 'rgba(255,76,245,0.33)',
                'header_light_bg' => 'rgba(248,251,255,0.96)',
                'header_light_border' => 'rgba(111,114,185,0.36)',
                'header_light_shadow' => '0 10px 25px rgba(255,76,245,0.2)',
                'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.5)',
            ],
        ],
        'slate_minimal' => [
            'name' => 'Slate Minimal',
            'description' => 'Neutrale Grautöne mit dezenten Akzenten und wenig Effekten.',
            'settings' => [
                'bg1' => '#0c111a',
                'bg2' => '#121826',
                'bg3' => '#0a0f17',
                'accent_cyan' => '#6ee7b7',
                'accent_magenta' => '#c7d2fe',
                'accent_purple' => '#94a3b8',
                'accent_yellow' => '#e5e7eb',
                'text_main' => '#e5e7eb',
                'text_muted' => '#94a3b8',
                'text_main_light' => '#0c111a',
                'text_muted_light' => '#334155',
                'surface' => 'rgba(12, 17, 26, 0.88)',
                'surface_border' => 'rgba(148, 163, 184, 0.24)',
                'light_bg1' => '#f1f5f9',
                'light_bg2' => '#ffffff',
                'header_bg_start' => 'rgba(12,17,26,0.95)',
                'header_bg_end' => 'rgba(18,24,38,0.98)',
                'header_border' => 'rgba(148,163,184,0.24)',
                'header_light_bg' => 'rgba(241,245,249,0.96)',
                'header_light_border' => 'rgba(148,163,184,0.3)',
                'header_light_shadow' => '0 10px 25px rgba(0,0,0,0.08)',
                'header_shadow_base' => '0 8px 26px rgba(0,0,0,0.32)',
            ],
        ],
        'sandstone_calm' => [
            'name' => 'Sandstone Calm',
            'description' => 'Warme neutrale Töne mit minimalen Effekten.',
            'settings' => [
                'bg1' => '#1a1611',
                'bg2' => '#231c14',
                'bg3' => '#120f0b',
                'accent_cyan' => '#c8ad7f',
                'accent_magenta' => '#a68a64',
                'accent_purple' => '#8b7355',
                'accent_yellow' => '#f2d8a2',
                'text_main' => '#f5ede1',
                'text_muted' => '#d6c6af',
                'text_main_light' => '#1b1710',
                'text_muted_light' => '#4b4336',
                'surface' => 'rgba(26, 22, 17, 0.9)',
                'surface_border' => 'rgba(200, 173, 127, 0.28)',
                'light_bg1' => '#f5ede1',
                'light_bg2' => '#faf6ed',
                'header_bg_start' => 'rgba(26,22,17,0.93)',
                'header_bg_end' => 'rgba(35,28,20,0.98)',
                'header_border' => 'rgba(200,173,127,0.28)',
                'header_light_bg' => 'rgba(245,237,225,0.96)',
                'header_light_border' => 'rgba(214,198,175,0.35)',
                'header_light_shadow' => '0 10px 25px rgba(200,173,127,0.18)',
                'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.48)',
            ],
        ],
        'hyper_aurora' => [
            'name' => 'Hyper Aurora',
            'description' => 'Intensiver Neonverlauf aus Grün, Pink und Blau mit starkem Glow.',
            'settings' => [
                'bg1' => '#02060f',
                'bg2' => '#071226',
                'bg3' => '#030815',
                'accent_cyan' => '#4ff0ff',
                'accent_magenta' => '#ff5fc1',
                'accent_purple' => '#7f6bff',
                'accent_yellow' => '#f9f871',
                'text_main' => '#e8f6ff',
                'text_muted' => '#b6d1ff',
                'text_main_light' => '#02060f',
                'text_muted_light' => '#1e2f4a',
                'surface' => 'rgba(3, 8, 21, 0.9)',
                'surface_border' => 'rgba(79, 240, 255, 0.36)',
                'light_bg1' => '#edf6ff',
                'light_bg2' => '#f8fbff',
                'header_bg_start' => 'rgba(3,8,21,0.93)',
                'header_bg_end' => 'rgba(7,18,38,0.98)',
                'header_border' => 'rgba(255,95,193,0.32)',
                'header_light_bg' => 'rgba(247,251,255,0.96)',
                'header_light_border' => 'rgba(125,129,255,0.34)',
                'header_light_shadow' => '0 10px 25px rgba(79,240,255,0.2)',
                'header_shadow_base' => '0 10px 32px rgba(0,0,0,0.55)',
            ],
        ],
        'ultraviolet_grid' => [
            'name' => 'Ultraviolet Grid',
            'description' => 'Violett-blaue Matrix mit Laserlinien und kaltem Neon.',
            'settings' => [
                'bg1' => '#0a0314',
                'bg2' => '#13072b',
                'bg3' => '#060012',
                'accent_cyan' => '#5ee0ff',
                'accent_magenta' => '#ff3cfa',
                'accent_purple' => '#a855f7',
                'accent_yellow' => '#fcd34d',
                'text_main' => '#eef2ff',
                'text_muted' => '#c7d2fe',
                'text_main_light' => '#0b0316',
                'text_muted_light' => '#311b52',
                'surface' => 'rgba(10, 3, 20, 0.9)',
                'surface_border' => 'rgba(94, 224, 255, 0.32)',
                'light_bg1' => '#f3f4ff',
                'light_bg2' => '#fafbff',
                'header_bg_start' => 'rgba(10,3,20,0.92)',
                'header_bg_end' => 'rgba(19,7,43,0.98)',
                'header_border' => 'rgba(168,85,247,0.32)',
                'header_light_bg' => 'rgba(238,242,255,0.96)',
                'header_light_border' => 'rgba(94,224,255,0.36)',
                'header_light_shadow' => '0 10px 25px rgba(94,224,255,0.22)',
                'header_shadow_base' => '0 10px 32px rgba(0,0,0,0.54)',
            ],
        ],
        'neon_mintwave' => [
            'name' => 'Neon Mintwave',
            'description' => 'Frisches Mint mit weißen Flächen und sanften Blurgefter.',
            'settings' => [
                'bg1' => '#03100e',
                'bg2' => '#0a1d1a',
                'bg3' => '#031411',
                'accent_cyan' => '#5ef5c8',
                'accent_magenta' => '#74f0ff',
                'accent_purple' => '#5eead4',
                'accent_yellow' => '#d5f9a6',
                'text_main' => '#e8fff6',
                'text_muted' => '#a7f3d0',
                'text_main_light' => '#03100e',
                'text_muted_light' => '#1d3b32',
                'surface' => 'rgba(3, 16, 14, 0.9)',
                'surface_border' => 'rgba(94, 245, 200, 0.32)',
                'light_bg1' => '#e9fff7',
                'light_bg2' => '#f7fffb',
                'header_bg_start' => 'rgba(3,16,14,0.9)',
                'header_bg_end' => 'rgba(10,29,26,0.96)',
                'header_border' => 'rgba(94,245,200,0.32)',
                'header_light_bg' => 'rgba(233,255,247,0.96)',
                'header_light_border' => 'rgba(94,245,200,0.34)',
                'header_light_shadow' => '0 10px 25px rgba(94,245,200,0.18)',
                'header_shadow_base' => '0 10px 28px rgba(0,0,0,0.48)',
            ],
        ],
        'plasma_orange' => [
            'name' => 'Plasma Orange',
            'description' => 'Strahlendes Orange mit pinken Flares und dunklem Hintergrund.',
            'settings' => [
                'bg1' => '#150802',
                'bg2' => '#2b0f04',
                'bg3' => '#0c0401',
                'accent_cyan' => '#ff7b3f',
                'accent_magenta' => '#ff3fa4',
                'accent_purple' => '#ff7ce7',
                'accent_yellow' => '#ffd166',
                'text_main' => '#fff4ec',
                'text_muted' => '#ffd9c1',
                'text_main_light' => '#170902',
                'text_muted_light' => '#552015',
                'surface' => 'rgba(21, 8, 2, 0.9)',
                'surface_border' => 'rgba(255, 123, 63, 0.32)',
                'light_bg1' => '#fff3e5',
                'light_bg2' => '#fff9f3',
                'header_bg_start' => 'rgba(21,8,2,0.9)',
                'header_bg_end' => 'rgba(43,15,4,0.96)',
                'header_border' => 'rgba(255,63,164,0.32)',
                'header_light_bg' => 'rgba(255,243,229,0.96)',
                'header_light_border' => 'rgba(255,123,63,0.32)',
                'header_light_shadow' => '0 10px 25px rgba(255,123,63,0.2)',
                'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.52)',
            ],
        ],
        'hologram_blueprint' => [
            'name' => 'Hologram Blueprint',
            'description' => 'Kaltes Blau mit digitalen Grid-Akzenten und feinen Glows.',
            'settings' => [
                'bg1' => '#030910',
                'bg2' => '#071523',
                'bg3' => '#02060d',
                'accent_cyan' => '#4fd1ff',
                'accent_magenta' => '#7dd3fc',
                'accent_purple' => '#60a5fa',
                'accent_yellow' => '#e0f2fe',
                'text_main' => '#e5f2ff',
                'text_muted' => '#bcdcff',
                'text_main_light' => '#030910',
                'text_muted_light' => '#1f364f',
                'surface' => 'rgba(3, 9, 16, 0.9)',
                'surface_border' => 'rgba(79, 209, 255, 0.32)',
                'light_bg1' => '#eaf3ff',
                'light_bg2' => '#f6faff',
                'header_bg_start' => 'rgba(3,9,16,0.92)',
                'header_bg_end' => 'rgba(7,21,35,0.98)',
                'header_border' => 'rgba(96,165,250,0.34)',
                'header_light_bg' => 'rgba(234,243,255,0.96)',
                'header_light_border' => 'rgba(79,209,255,0.32)',
                'header_light_shadow' => '0 10px 25px rgba(79,209,255,0.18)',
                'header_shadow_base' => '0 10px 30px rgba(0,0,0,0.52)',
            ],
        ],
        'retro_amber' => [
            'name' => 'Retro Amber',
            'description' => 'Neon-Orange auf tiefem Braun mit leichten Chrom-Highlights.',
            'settings' => [
                'bg1' => '#120807',
                'bg2' => '#1d0f0c',
                'bg3' => '#0b0504',
                'accent_cyan' => '#ffb347',
                'accent_magenta' => '#ff7f50',
                'accent_purple' => '#f97316',
                'accent_yellow' => '#fcd34d',
                'text_main' => '#fff4e6',
                'text_muted' => '#f5d0b5',
                'text_main_light' => '#160a07',
                'text_muted_light' => '#4a2f25',
                'surface' => 'rgba(18, 8, 7, 0.9)',
                'surface_border' => 'rgba(255, 179, 71, 0.3)',
                'light_bg1' => '#fff5eb',
                'light_bg2' => '#fffaf5',
                'header_bg_start' => 'rgba(18,8,7,0.9)',
                'header_bg_end' => 'rgba(29,15,12,0.96)',
                'header_border' => 'rgba(255,179,71,0.3)',
                'header_light_bg' => 'rgba(255,245,235,0.96)',
                'header_light_border' => 'rgba(249,115,22,0.32)',
                'header_light_shadow' => '0 10px 24px rgba(255,179,71,0.2)',
                'header_shadow_base' => '0 10px 28px rgba(0,0,0,0.48)',
            ],
        ],
        'fog_minimal' => [
            'name' => 'Fog Minimal',
            'description' => 'Sehr dezenter Grauverlauf mit reduzierten Effekten.',
            'settings' => [
                'bg1' => '#0f1217',
                'bg2' => '#151a21',
                'bg3' => '#0c0f14',
                'accent_cyan' => '#9ca3af',
                'accent_magenta' => '#94a3b8',
                'accent_purple' => '#a5b4fc',
                'accent_yellow' => '#e5e7eb',
                'text_main' => '#e5e7eb',
                'text_muted' => '#9ca3af',
                'text_main_light' => '#0f1217',
                'text_muted_light' => '#374151',
                'surface' => 'rgba(15, 18, 23, 0.9)',
                'surface_border' => 'rgba(148, 163, 184, 0.24)',
                'light_bg1' => '#f3f4f6',
                'light_bg2' => '#ffffff',
                'header_bg_start' => 'rgba(15,18,23,0.92)',
                'header_bg_end' => 'rgba(21,26,33,0.97)',
                'header_border' => 'rgba(148,163,184,0.22)',
                'header_light_bg' => 'rgba(243,244,246,0.96)',
                'header_light_border' => 'rgba(148,163,184,0.24)',
                'header_light_shadow' => '0 8px 22px rgba(0,0,0,0.12)',
                'header_shadow_base' => '0 8px 24px rgba(0,0,0,0.3)',
            ],
        ],
        'pearl_neutral' => [
            'name' => 'Pearl Neutral',
            'description' => 'Helle, fast weiße Flächen mit sehr feinen Akzenten.',
            'settings' => [
                'bg1' => '#111418',
                'bg2' => '#171c21',
                'bg3' => '#0c0f14',
                'accent_cyan' => '#cbd5e1',
                'accent_magenta' => '#e2e8f0',
                'accent_purple' => '#d1d5db',
                'accent_yellow' => '#f8fafc',
                'text_main' => '#f8fafc',
                'text_muted' => '#cbd5e1',
                'text_main_light' => '#111418',
                'text_muted_light' => '#3f4752',
                'surface' => 'rgba(17, 20, 24, 0.88)',
                'surface_border' => 'rgba(203, 213, 225, 0.18)',
                'light_bg1' => '#f7fafc',
                'light_bg2' => '#ffffff',
                'header_bg_start' => 'rgba(17,20,24,0.92)',
                'header_bg_end' => 'rgba(23,28,33,0.97)',
                'header_border' => 'rgba(203,213,225,0.22)',
                'header_light_bg' => 'rgba(247,250,252,0.96)',
                'header_light_border' => 'rgba(203,213,225,0.26)',
                'header_light_shadow' => '0 10px 24px rgba(0,0,0,0.08)',
                'header_shadow_base' => '0 8px 24px rgba(0,0,0,0.26)',
            ],
        ],
        'graphite_focus' => [
            'name' => 'Graphite Focus',
            'description' => 'Dunkles Grau, klare Typografie, kaum Effekte.',
            'settings' => [
                'bg1' => '#0b0d11',
                'bg2' => '#11141a',
                'bg3' => '#07090d',
                'accent_cyan' => '#6b7280',
                'accent_magenta' => '#4b5563',
                'accent_purple' => '#9ca3af',
                'accent_yellow' => '#d1d5db',
                'text_main' => '#e5e7eb',
                'text_muted' => '#9ca3af',
                'text_main_light' => '#0b0d11',
                'text_muted_light' => '#2f343d',
                'surface' => 'rgba(11, 13, 17, 0.9)',
                'surface_border' => 'rgba(107, 114, 128, 0.24)',
                'light_bg1' => '#f1f5f9',
                'light_bg2' => '#ffffff',
                'header_bg_start' => 'rgba(11,13,17,0.94)',
                'header_bg_end' => 'rgba(17,20,26,0.98)',
                'header_border' => 'rgba(75,85,99,0.22)',
                'header_light_bg' => 'rgba(241,245,249,0.96)',
                'header_light_border' => 'rgba(156,163,175,0.26)',
                'header_light_shadow' => '0 8px 22px rgba(0,0,0,0.1)',
                'header_shadow_base' => '0 8px 22px rgba(0,0,0,0.3)',
            ],
        ],
        'linen_workspace' => [
            'name' => 'Linen Workspace',
            'description' => 'Leichte Beige-Töne, weich und neutral für ruhige Oberflächen.',
            'settings' => [
                'bg1' => '#17130f',
                'bg2' => '#201a14',
                'bg3' => '#0f0c09',
                'accent_cyan' => '#d7c7ad',
                'accent_magenta' => '#c9b69a',
                'accent_purple' => '#b9a489',
                'accent_yellow' => '#f5e6c5',
                'text_main' => '#f7efe3',
                'text_muted' => '#d7c7ad',
                'text_main_light' => '#17130f',
                'text_muted_light' => '#463a2f',
                'surface' => 'rgba(23, 19, 15, 0.9)',
                'surface_border' => 'rgba(215, 199, 173, 0.22)',
                'light_bg1' => '#f7efe3',
                'light_bg2' => '#fdf9f1',
                'header_bg_start' => 'rgba(23,19,15,0.92)',
                'header_bg_end' => 'rgba(32,26,20,0.98)',
                'header_border' => 'rgba(215,199,173,0.26)',
                'header_light_bg' => 'rgba(247,239,227,0.96)',
                'header_light_border' => 'rgba(185,164,137,0.3)',
                'header_light_shadow' => '0 10px 25px rgba(215,199,173,0.16)',
                'header_shadow_base' => '0 10px 28px rgba(0,0,0,0.4)',
            ],
        ],
        'neon_orange_flux' => [
            'name' => 'Neon Orange Flux',
            'description' => 'Strahlende Orange-/Korall-Glows mit pulsierendem Randlicht.',
            'settings' => [
                'bg1' => '#140703',
                'bg2' => '#220c05',
                'bg3' => '#0b0301',
                'accent_cyan' => '#ff8a3d',
                'accent_magenta' => '#ff4f81',
                'accent_purple' => '#ff7ce7',
                'accent_yellow' => '#ffd166',
                'text_main' => '#fff4eb',
                'text_muted' => '#ffc3a1',
                'text_main_light' => '#140703',
                'text_muted_light' => '#4f1f10',
                'surface' => 'rgba(20, 7, 3, 0.92)',
                'surface_border' => 'rgba(255, 138, 61, 0.36)',
                'light_bg1' => '#fff2e3',
                'light_bg2' => '#fff9f2',
                'header_bg_start' => 'rgba(20,7,3,0.94)',
                'header_bg_end' => 'rgba(34,12,5,0.98)',
                'header_border' => 'rgba(255,79,129,0.32)',
                'header_light_bg' => 'rgba(255,244,235,0.96)',
                'header_light_border' => 'rgba(255,138,61,0.34)',
                'header_light_shadow' => '0 12px 28px rgba(255,138,61,0.22)',
                'header_shadow_base' => '0 12px 30px rgba(0,0,0,0.52)',
            ],
        ],
        'neon_crimson_streak' => [
            'name' => 'Neon Crimson Streak',
            'description' => 'Tiefes Rot mit Laserlinien und hartem Specular-Glare.',
            'settings' => [
                'bg1' => '#120205',
                'bg2' => '#21030a',
                'bg3' => '#090104',
                'accent_cyan' => '#ff1744',
                'accent_magenta' => '#ff4b91',
                'accent_purple' => '#ff6bcb',
                'accent_yellow' => '#ffd166',
                'text_main' => '#ffe8ee',
                'text_muted' => '#f8b4c8',
                'text_main_light' => '#120205',
                'text_muted_light' => '#4b0f1e',
                'surface' => 'rgba(18, 2, 5, 0.92)',
                'surface_border' => 'rgba(255, 23, 68, 0.34)',
                'light_bg1' => '#ffe6ee',
                'light_bg2' => '#fff5f9',
                'header_bg_start' => 'rgba(18,2,5,0.94)',
                'header_bg_end' => 'rgba(33,3,10,0.98)',
                'header_border' => 'rgba(255,75,145,0.34)',
                'header_light_bg' => 'rgba(255,230,238,0.96)',
                'header_light_border' => 'rgba(255,23,68,0.34)',
                'header_light_shadow' => '0 12px 30px rgba(255,23,68,0.24)',
                'header_shadow_base' => '0 12px 32px rgba(0,0,0,0.54)',
            ],
        ],
        'neon_emerald_pulse' => [
            'name' => 'Neon Emerald Pulse',
            'description' => 'Giftgrüne Neonlinien mit Nebelglow und Pulseffekt.',
            'settings' => [
                'bg1' => '#03100a',
                'bg2' => '#072016',
                'bg3' => '#020a07',
                'accent_cyan' => '#2efc7a',
                'accent_magenta' => '#6fffb5',
                'accent_purple' => '#3be89e',
                'accent_yellow' => '#c5ff80',
                'text_main' => '#e9fff5',
                'text_muted' => '#a5f3c7',
                'text_main_light' => '#03100a',
                'text_muted_light' => '#1b3f2f',
                'surface' => 'rgba(3, 16, 10, 0.9)',
                'surface_border' => 'rgba(46, 252, 122, 0.32)',
                'light_bg1' => '#e8fff5',
                'light_bg2' => '#f6fffb',
                'header_bg_start' => 'rgba(3,16,10,0.9)',
                'header_bg_end' => 'rgba(7,32,22,0.96)',
                'header_border' => 'rgba(63,232,158,0.34)',
                'header_light_bg' => 'rgba(232,255,245,0.96)',
                'header_light_border' => 'rgba(46,252,122,0.34)',
                'header_light_shadow' => '0 12px 30px rgba(46,252,122,0.2)',
                'header_shadow_base' => '0 12px 30px rgba(0,0,0,0.5)',
            ],
        ],
        'neon_cobalt_wave' => [
            'name' => 'Neon Cobalt Wave',
            'description' => 'Kaltes Blau mit weichen Motion-Blurs und Scanlines.',
            'settings' => [
                'bg1' => '#030913',
                'bg2' => '#0a1630',
                'bg3' => '#020712',
                'accent_cyan' => '#49c5ff',
                'accent_magenta' => '#6ae0ff',
                'accent_purple' => '#7fa8ff',
                'accent_yellow' => '#d6f5ff',
                'text_main' => '#e6f3ff',
                'text_muted' => '#b9d6ff',
                'text_main_light' => '#030913',
                'text_muted_light' => '#1f2f4c',
                'surface' => 'rgba(3, 9, 19, 0.9)',
                'surface_border' => 'rgba(73, 197, 255, 0.32)',
                'light_bg1' => '#e9f4ff',
                'light_bg2' => '#f6fbff',
                'header_bg_start' => 'rgba(3,9,19,0.92)',
                'header_bg_end' => 'rgba(10,22,48,0.98)',
                'header_border' => 'rgba(127,168,255,0.34)',
                'header_light_bg' => 'rgba(233,244,255,0.96)',
                'header_light_border' => 'rgba(73,197,255,0.32)',
                'header_light_shadow' => '0 12px 30px rgba(73,197,255,0.2)',
                'header_shadow_base' => '0 12px 30px rgba(0,0,0,0.5)',
            ],
        ],
        'neutral_forest_mist' => [
            'name' => 'Neutral Forest Mist',
            'description' => 'Gedämpftes Grün mit matter Oberfläche und minimalen Effekten.',
            'settings' => [
                'bg1' => '#0f1410',
                'bg2' => '#151d17',
                'bg3' => '#0a0f0b',
                'accent_cyan' => '#6b8f72',
                'accent_magenta' => '#88b090',
                'accent_purple' => '#5f7b64',
                'accent_yellow' => '#c9e3c9',
                'text_main' => '#e6f2e8',
                'text_muted' => '#b7cfc0',
                'text_main_light' => '#0f1410',
                'text_muted_light' => '#304137',
                'surface' => 'rgba(15, 20, 16, 0.9)',
                'surface_border' => 'rgba(107, 143, 114, 0.26)',
                'light_bg1' => '#e9f4ec',
                'light_bg2' => '#f7fbf8',
                'header_bg_start' => 'rgba(15,20,16,0.92)',
                'header_bg_end' => 'rgba(21,29,23,0.97)',
                'header_border' => 'rgba(107,143,114,0.26)',
                'header_light_bg' => 'rgba(233,244,236,0.96)',
                'header_light_border' => 'rgba(135,169,145,0.3)',
                'header_light_shadow' => '0 8px 22px rgba(0,0,0,0.12)',
                'header_shadow_base' => '0 8px 22px rgba(0,0,0,0.28)',
            ],
        ],
        'neutral_tangerine_sand' => [
            'name' => 'Neutral Tangerine Sand',
            'description' => 'Zurückhaltendes Orange mit weichem Korn und dezenten Highlights.',
            'settings' => [
                'bg1' => '#18130f',
                'bg2' => '#201a14',
                'bg3' => '#100c09',
                'accent_cyan' => '#c99b6c',
                'accent_magenta' => '#b7835f',
                'accent_purple' => '#a46d4c',
                'accent_yellow' => '#f5d2a6',
                'text_main' => '#f8ede0',
                'text_muted' => '#d9c5b0',
                'text_main_light' => '#18130f',
                'text_muted_light' => '#4a3a2d',
                'surface' => 'rgba(24, 19, 15, 0.9)',
                'surface_border' => 'rgba(201, 155, 108, 0.24)',
                'light_bg1' => '#f8eee2',
                'light_bg2' => '#fdf8f1',
                'header_bg_start' => 'rgba(24,19,15,0.92)',
                'header_bg_end' => 'rgba(32,26,20,0.97)',
                'header_border' => 'rgba(185,131,95,0.28)',
                'header_light_bg' => 'rgba(248,238,226,0.96)',
                'header_light_border' => 'rgba(201,155,108,0.3)',
                'header_light_shadow' => '0 8px 22px rgba(0,0,0,0.1)',
                'header_shadow_base' => '0 8px 22px rgba(0,0,0,0.28)',
            ],
        ],
        'neutral_cranberry_clay' => [
            'name' => 'Neutral Cranberry Clay',
            'description' => 'Gedämpftes Rot auf Tonerde-Basis mit mattem Finish.',
            'settings' => [
                'bg1' => '#1a1111',
                'bg2' => '#221616',
                'bg3' => '#0f0909',
                'accent_cyan' => '#b27c7c',
                'accent_magenta' => '#c58b8b',
                'accent_purple' => '#9f6a6a',
                'accent_yellow' => '#f0d7d0',
                'text_main' => '#f7edeb',
                'text_muted' => '#d6c1bd',
                'text_main_light' => '#1a1111',
                'text_muted_light' => '#4c3535',
                'surface' => 'rgba(26, 17, 17, 0.9)',
                'surface_border' => 'rgba(178, 124, 124, 0.24)',
                'light_bg1' => '#f7edeb',
                'light_bg2' => '#fdf7f6',
                'header_bg_start' => 'rgba(26,17,17,0.92)',
                'header_bg_end' => 'rgba(34,22,22,0.97)',
                'header_border' => 'rgba(178,124,124,0.26)',
                'header_light_bg' => 'rgba(247,237,235,0.96)',
                'header_light_border' => 'rgba(197,139,139,0.3)',
                'header_light_shadow' => '0 8px 22px rgba(0,0,0,0.1)',
                'header_shadow_base' => '0 8px 22px rgba(0,0,0,0.28)',
            ],
        ],
        'neutral_navy_mist' => [
            'name' => 'Neutral Navy Mist',
            'description' => 'Kühles Blau mit leichter Körnung und zurückhaltenden Rändern.',
            'settings' => [
                'bg1' => '#0e1118',
                'bg2' => '#151b23',
                'bg3' => '#0a0d13',
                'accent_cyan' => '#6c7c91',
                'accent_magenta' => '#8fa6c2',
                'accent_purple' => '#5b6a7f',
                'accent_yellow' => '#d9e6f5',
                'text_main' => '#e6eef7',
                'text_muted' => '#b7c6d8',
                'text_main_light' => '#0e1118',
                'text_muted_light' => '#303a4a',
                'surface' => 'rgba(14, 17, 24, 0.9)',
                'surface_border' => 'rgba(108, 124, 145, 0.24)',
                'light_bg1' => '#e9f0f8',
                'light_bg2' => '#f7fbff',
                'header_bg_start' => 'rgba(14,17,24,0.92)',
                'header_bg_end' => 'rgba(21,27,35,0.97)',
                'header_border' => 'rgba(108,124,145,0.26)',
                'header_light_bg' => 'rgba(233,240,248,0.96)',
                'header_light_border' => 'rgba(139,159,186,0.3)',
                'header_light_shadow' => '0 8px 22px rgba(0,0,0,0.1)',
                'header_shadow_base' => '0 8px 22px rgba(0,0,0,0.28)',
            ],
        ],
    ];
}

function getHexColorKeys(): array
{
    return [
        'bg1',
        'bg2',
        'bg3',
        'accent_cyan',
        'accent_magenta',
        'accent_purple',
        'accent_yellow',
        'text_main',
        'text_muted',
        'text_main_light',
        'text_muted_light',
        'light_bg1',
        'light_bg2',
    ];
}

function loadThemeSettings(PDO $pdo): array
{
    ensureThemeSettingsTable($pdo);
    $defaults = getDefaultThemeSettings();
    $stored = [];

    $stmt = $pdo->query('SELECT setting_key, setting_value FROM ui_theme_settings');
    foreach ($stmt as $row) {
        if ($row['setting_key'] === 'draft_theme') {
            continue;
        }
        $stored[$row['setting_key']] = $row['setting_value'];
    }

    return array_merge($defaults, $stored);
}

function normalizeThemeValues(array $values): array
{
    $defaults = getDefaultThemeSettings();
    $normalized = $defaults;

    foreach ($defaults as $key => $default) {
        $value = trim($values[$key] ?? $default);
        if ($value === '') {
            $value = $default;
        }
        if ($key === 'brand_font' && !array_key_exists($value, getBrandFontOptions())) {
            $value = $default;
        }
        if ($key === 'brand_font_style' && !array_key_exists($value, getBrandStyleOptions())) {
            $value = $default;
        }
        if ($key === 'brand_font_size' && !preg_match('/^(?:\d+|\d*\.\d+)(?:rem|em|px|%)$/', $value)) {
            $value = $default;
        }
        if ($key === 'brand_letter_spacing' && !preg_match('/^-?(?:\d+|\d*\.\d+)(?:em|rem|px|%)$/', $value)) {
            $value = $default;
        }
        if (in_array($key, getHexColorKeys(), true) && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            $value = $default;
        }
        if (in_array($key, ['radius_lg', 'radius_md', 'radius_pill'], true) && !preg_match('/^(?:\d+(?:\.\d+)?)(?:px|rem|em|%)$/', $value)) {
            $value = $default;
        }
        if (in_array($key, ['shadow_card', 'shadow_card_light'], true) && !preg_match('/^[^<>]{3,180}$/', $value)) {
            $value = $default;
        }
        if ($key === 'layout_density' && !preg_match('/^(0\.\d+|[1-3](?:\.\d+)?)$/', $value)) {
            $value = '1';
        }

        $normalized[$key] = $value;
    }

    return $normalized;
}

function saveThemeSettings(PDO $pdo, array $values): array
{
    ensureThemeSettingsTable($pdo);
    $normalized = normalizeThemeValues($values);
    $stmt = $pdo->prepare('REPLACE INTO ui_theme_settings (setting_key, setting_value) VALUES (:key, :value)');

    foreach ($normalized as $key => $value) {
        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    }

    return $normalized;
}

function resetThemeSettings(PDO $pdo): array
{
    ensureThemeSettingsTable($pdo);
    $pdo->exec('TRUNCATE ui_theme_settings');
    return getDefaultThemeSettings();
}

function saveThemeDraft(PDO $pdo, array $values): array
{
    ensureThemeSettingsTable($pdo);
    $normalized = normalizeThemeValues($values);
    $payload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare('REPLACE INTO ui_theme_settings (setting_key, setting_value) VALUES (:key, :value)');
    $stmt->execute([
        ':key' => 'draft_theme',
        ':value' => $payload,
    ]);

    return $normalized;
}

function loadThemeDraft(PDO $pdo): ?array
{
    ensureThemeSettingsTable($pdo);
    $stmt = $pdo->prepare('SELECT setting_value FROM ui_theme_settings WHERE setting_key = :key');
    $stmt->execute([':key' => 'draft_theme']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $decoded = json_decode($row['setting_value'], true);
    if (!is_array($decoded)) {
        return null;
    }

    return normalizeThemeValues($decoded);
}

function discardThemeDraft(PDO $pdo): void
{
    $stmt = $pdo->prepare('DELETE FROM ui_theme_settings WHERE setting_key = :key');
    $stmt->execute([':key' => 'draft_theme']);
}

function hexToRgbString(string $hex, string $fallback): string
{
    $hex = trim($hex);
    if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
        return $fallback;
    }

    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return $r . ',' . $g . ',' . $b;
}

function buildThemeCssVariables(array $settings): array
{
    $accentCyanRgb = hexToRgbString($settings['accent_cyan'], '0,247,255');
    $accentMagentaRgb = hexToRgbString($settings['accent_magenta'], '255,0,255');
    $accentPurpleRgb = hexToRgbString($settings['accent_purple'], '123,92,255');
    $accentYellowRgb = hexToRgbString($settings['accent_yellow'], '250,204,21');

   return [
        'bg1' => $settings['bg1'],
        'bg2' => $settings['bg2'],
        'bg3' => $settings['bg3'],
        'radius-lg' => $settings['radius_lg'] ?? '26px',
        'radius-md' => $settings['radius_md'] ?? '16px',
        'radius-pill' => $settings['radius_pill'] ?? '999px',
        'shadow-card' => $settings['shadow_card'] ?? '0 20px 55px rgba(0,0,0,0.85)',
        'shadow-card-light' => $settings['shadow_card_light'] ?? '0 18px 40px rgba(15,23,42,0.18)',
        'layout-density' => $settings['layout_density'] ?? '1',
        'accent-cyan' => $settings['accent_cyan'],
        'accent-cyan-rgb' => $accentCyanRgb,
        'accent-magenta' => $settings['accent_magenta'],
        'accent-magenta-rgb' => $accentMagentaRgb,
        'accent-purple' => $settings['accent_purple'],
        'accent-purple-rgb' => $accentPurpleRgb,
        'accent-yellow' => $settings['accent_yellow'],
        'accent-yellow-rgb' => $accentYellowRgb,
        'text-main' => $settings['text_main'],
        'text-muted' => $settings['text_muted'],
        'text-main-light' => $settings['text_main_light'],
        'text-muted-light' => $settings['text_muted_light'],
        'surface' => $settings['surface'],
        'surface-border' => $settings['surface_border'],
        'light-bg1' => $settings['light_bg1'],
        'light-bg2' => $settings['light_bg2'],
        'glow-radial-cyan' => 'rgba(' . $accentCyanRgb . ',0.28)',
        'glow-radial-magenta' => 'rgba(' . $accentMagentaRgb . ',0.26)',
        'glow-radial-purple' => 'rgba(' . $accentPurpleRgb . ',0.3)',
        'glow-line-cyan' => 'rgba(' . $accentCyanRgb . ',0.08)',
        'glow-line-magenta' => 'rgba(' . $accentMagentaRgb . ',0.07)',
        'header-bg-start' => $settings['header_bg_start'],
        'header-bg-end' => $settings['header_bg_end'],
        'header-border' => $settings['header_border'],
        'header-light-bg' => $settings['header_light_bg'],
        'header-light-border' => $settings['header_light_border'],
        'header-light-shadow' => $settings['header_light_shadow'],
        'header-shadow-base' => $settings['header_shadow_base'],
        'header-shadow-glow' => '0 0 20px rgba(' . $accentCyanRgb . ',0.4)',
    ];
}

function renderThemeStyles(PDO $pdo): void
{
     $settings = loadThemeSettings($pdo);
    $cssVars = buildThemeCssVariables($settings);

    $brandFont = $settings['brand_font'] ?? 'neon-tech';
    $cssVars['brand-font-family'] = resolveBrandFontStack($brandFont);
    $cssVars['brand-font-weight'] = resolveBrandFontWeight($brandFont);
    $cssVars['brand-font-size'] = $settings['brand_font_size'] ?? '1.32rem';
    $cssVars['brand-letter-spacing'] = $settings['brand_letter_spacing'] ?? '0.08em';

    echo "<style>\n:root {\n";
    foreach ($cssVars as $key => $value) {
        $safeValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '    --' . $key . ': ' . $safeValue . ";\n";
    }
    echo "}\n</style>\n";
}

function getThemeTokens(PDO $pdo, bool $includeDraft = false): array
{
    $active = loadThemeSettings($pdo);
    $draft = $includeDraft ? loadThemeDraft($pdo) : null;

    return [
        'active_settings' => $active,
        'active_css_variables' => buildThemeCssVariables($active),
        'draft_settings' => $draft,
        'draft_css_variables' => $draft ? buildThemeCssVariables($draft) : null,
        'brand' => [
            'font_family' => resolveBrandFontStack($active['brand_font'] ?? 'neon-tech'),
            'font_weight' => resolveBrandFontWeight($active['brand_font'] ?? 'neon-tech'),
            'font_style' => $active['brand_font_style'] ?? 'neon-depth',
            'font_size' => $active['brand_font_size'] ?? '1.32rem',
            'letter_spacing' => $active['brand_letter_spacing'] ?? '0.08em',
        ],
    ];
}