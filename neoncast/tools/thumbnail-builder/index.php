<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function assetVersion(string $path): string {
    return is_file($path) ? (string)filemtime($path) : '0';
}

$themeCatalog = [
    'synthwave' => [
        'label' => 'Miami Synthwave',
        'group' => 'Signature Waves',
        'colors' => ['#ff2bd6', '#19f7ff', '#8b3dff'],
    ],
    'sunset' => [
        'label' => 'Sunset Boulevard',
        'group' => 'Signature Waves',
        'colors' => ['#ff3d7f', '#ffbf3f', '#9b37ff'],
    ],
    'purple-rain' => [
        'label' => 'Purple Rain',
        'group' => 'Signature Waves',
        'colors' => ['#ff4fe5', '#b47cff', '#7a35ff'],
    ],
    'neon-red' => [
        'label' => 'Crimson Rush',
        'group' => 'Neon Spectrum',
        'colors' => ['#ff1744', '#ff6b7f', '#ff3d00'],
    ],
    'neon-orange' => [
        'label' => 'Turbo Tangerine',
        'group' => 'Neon Spectrum',
        'colors' => ['#ff7a00', '#ffb347', '#ff4d00'],
    ],
    'neon-yellow' => [
        'label' => 'Solar Flare',
        'group' => 'Neon Spectrum',
        'colors' => ['#ffe600', '#fff47a', '#ffb300'],
    ],
    'laser-green' => [
        'label' => 'Laser Grid',
        'group' => 'Neon Spectrum',
        'colors' => ['#8dff19', '#00ffd5', '#00b88a'],
    ],
    'neon-teal' => [
        'label' => 'Aqua Circuit',
        'group' => 'Neon Spectrum',
        'colors' => ['#00ffc8', '#5ffff0', '#00a98f'],
    ],
    'neon-cyan' => [
        'label' => 'Cyber Ice',
        'group' => 'Neon Spectrum',
        'colors' => ['#00eaff', '#8af7ff', '#00a8ff'],
    ],
    'electric-blue' => [
        'label' => 'Electric Horizon',
        'group' => 'Neon Spectrum',
        'colors' => ['#2587ff', '#59f4ff', '#2455ff'],
    ],
    'neon-indigo' => [
        'label' => 'Midnight Voltage',
        'group' => 'Neon Spectrum',
        'colors' => ['#5d5cff', '#9d9cff', '#3840d8'],
    ],
    'neon-purple' => [
        'label' => 'Ultraviolet Drive',
        'group' => 'Neon Spectrum',
        'colors' => ['#9b4dff', '#d2a6ff', '#6c24d8'],
    ],
    'neon-magenta' => [
        'label' => 'Magenta Mirage',
        'group' => 'Neon Spectrum',
        'colors' => ['#ff00c8', '#ff7de8', '#b800ff'],
    ],
    'hot-pink' => [
        'label' => 'Hotline Pink',
        'group' => 'Neon Spectrum',
        'colors' => ['#ff1493', '#ff75ed', '#c000ff'],
    ],
    'neon-rose' => [
        'label' => 'Rose Reactor',
        'group' => 'Neon Spectrum',
        'colors' => ['#ff4f81', '#ff9fba', '#d92864'],
    ],
    'neon-gold' => [
        'label' => 'Golden Arcade',
        'group' => 'Neon Spectrum',
        'colors' => ['#ffc83d', '#ffe78f', '#ff8f00'],
    ],
    'neon-white' => [
        'label' => 'Chrome Moon',
        'group' => 'Neon Spectrum',
        'colors' => ['#f7fbff', '#b9d7ff', '#8a9bb7'],
    ],
];

$groupedThemes = [];
foreach ($themeCatalog as $id => $theme) {
    $groupedThemes[$theme['group']][$id] = $theme;
}

$assetsDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$configFile = $dataDir . DIRECTORY_SEPARATOR . 'thumbnail-config.json';

function ensureDirectory(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create required directory.');
    }
}

function defaultThumbnailConfig(): array {
    return [
        'zoom' => 1.0,
        'darkness' => 0.32,
        'backgroundMode' => 'gradient',
        'backgroundColor' => '#000000',
        'posX' => 0,
        'posY' => 0,
        'title' => 'Cozy Events',
        'subtitle' => 'Plan together. Play together. Create memories together.',
        'titleSize' => 72,
        'subtitleSize' => 28,
        'align' => 'left',
        'textVertical' => 'bottom',
        'fontTheme' => 'synthwave',
        'site' => 'alpasnet.eu',
        'twitch' => 'twitch.tv/alpasnet',
        'youtube' => '@AlpasNet',
        'showLinks' => true,
        'showSafe' => true,
        'showBrandLogo' => true,
        'brandLogoSize' => 230,
        'showPortrait' => true,
        'portraitSize' => 340,
        'portraitZoom' => 1.0,
        'portraitX' => 1000,
        'portraitY' => 340,
        'backgroundAsset' => '',
        'portraitAsset' => '',
        'brandLogoAsset' => '',
    ];
}

function loadThumbnailConfig(string $configFile): array {
    $defaults = defaultThumbnailConfig();
    if (!is_file($configFile)) return $defaults;
    $decoded = json_decode((string)file_get_contents($configFile), true);
    if (!is_array($decoded)) return $defaults;
    foreach ($defaults as $key => $value) {
        if (array_key_exists($key, $decoded)) $defaults[$key] = $decoded[$key];
    }
    return $defaults;
}

function saveThumbnailConfig(string $configFile, array $config): void {
    ensureDirectory(dirname($configFile));
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Unable to encode the configuration.');
    $tmp = $configFile . '.tmp-' . bin2hex(random_bytes(5));
    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the configuration file.');
    }
    if (is_file($configFile) && !unlink($configFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to replace the configuration file.');
    }
    if (!rename($tmp, $configFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to install the configuration file.');
    }
    @chmod($configFile, 0664);
}

function postBool(string $key): bool {
    return isset($_POST[$key]) && in_array(strtolower((string)$_POST[$key]), ['1', 'true', 'yes', 'on'], true);
}

function clampFloatValue(string $key, float $default, float $min, float $max): float {
    $raw = $_POST[$key] ?? null;
    if ($raw === null || !is_numeric($raw)) return $default;
    return max($min, min($max, (float)$raw));
}

function clampIntValue(string $key, int $default, int $min, int $max): int {
    $raw = $_POST[$key] ?? null;
    if ($raw === null || !is_numeric($raw)) return $default;
    return max($min, min($max, (int)round((float)$raw)));
}

function safeTextValue(string $key, string $default, int $maxLength): string {
    $value = trim((string)($_POST[$key] ?? $default));
    if (function_exists('mb_substr')) return mb_substr($value, 0, $maxLength);
    return substr($value, 0, $maxLength);
}

function replaceUploadedImage(string $field, string $baseName, string $assetsDir): ?string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('An image upload failed.');

    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Invalid image upload source.');
    if ($size <= 0 || $size > 30 * 1024 * 1024) throw new RuntimeException('Images must be between 1 byte and 30 MB.');

    $info = @getimagesize($tmp);
    if ($info === false || empty($info[0]) || empty($info[1])) throw new RuntimeException('The uploaded file is not a valid image.');
    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w > 12000 || $h > 12000 || ($w * $h) > 50000000) throw new RuntimeException('The uploaded image is too large. Maximum: 50 megapixels.');

    $type = (int)($info[2] ?? 0);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (defined('IMAGETYPE_WEBP')) $extensions[IMAGETYPE_WEBP] = 'webp';
    if (!isset($extensions[$type])) throw new RuntimeException('Only JPG, PNG and WEBP images are supported.');

    ensureDirectory($assetsDir);
    $filename = $baseName . '.' . $extensions[$type];
    $target = $assetsDir . DIRECTORY_SEPARATOR . $filename;
    $staged = $assetsDir . DIRECTORY_SEPARATOR . $baseName . '.incoming-' . bin2hex(random_bytes(5)) . '.' . $extensions[$type];
    if (!move_uploaded_file($tmp, $staged)) throw new RuntimeException('Unable to save an uploaded image.');

    foreach (glob($assetsDir . DIRECTORY_SEPARATOR . $baseName . '.*') ?: [] as $old) {
        if ($old !== $staged && is_file($old)) @unlink($old);
    }
    if (!rename($staged, $target)) {
        @unlink($staged);
        throw new RuntimeException('Unable to replace the previously saved image.');
    }
    @chmod($target, 0664);
    clearstatcache(true, $target);
    return 'assets/' . $filename;
}

function assetUrlFromRelative(string $relative): string {
    $relative = trim($relative);
    if ($relative === '') return '';
    $safe = str_replace(['\\', '..'], ['/', ''], $relative);
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safe);
    if (!is_file($path)) return '';
    return $safe . '?v=' . filemtime($path);
}

ensureDirectory($assetsDir);
ensureDirectory($dataDir);
$savedConfig = loadThumbnailConfig($configFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_config') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $config = loadThumbnailConfig($configFile);
        $config['zoom'] = clampFloatValue('zoom', 1.0, 1.0, 3.0);
        $config['darkness'] = clampFloatValue('darkness', 0.32, 0.0, 0.8);
        $mode = (string)($_POST['backgroundMode'] ?? 'gradient');
        $config['backgroundMode'] = in_array($mode, ['gradient', 'solid', 'none'], true) ? $mode : 'gradient';
        $color = strtoupper((string)($_POST['backgroundColor'] ?? '#000000'));
        $config['backgroundColor'] = preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : '#000000';
        $config['posX'] = clampIntValue('posX', 0, -640, 640);
        $config['posY'] = clampIntValue('posY', 0, -360, 360);
        $config['title'] = safeTextValue('title', 'Cozy Events', 180);
        $config['subtitle'] = safeTextValue('subtitle', '', 260);
        $config['titleSize'] = clampIntValue('titleSize', 72, 24, 140);
        $config['subtitleSize'] = clampIntValue('subtitleSize', 28, 14, 80);
        $config['align'] = 'left';
        $config['textVertical'] = 'bottom';
        $theme = (string)($_POST['fontTheme'] ?? 'synthwave');
        $config['fontTheme'] = array_key_exists($theme, $themeCatalog) ? $theme : 'synthwave';
        $config['site'] = safeTextValue('site', '', 200);
        $config['twitch'] = safeTextValue('twitch', '', 200);
        $config['youtube'] = safeTextValue('youtube', '', 200);
        $config['showLinks'] = postBool('showLinks');
        $config['showSafe'] = postBool('showSafe');
        $config['showBrandLogo'] = postBool('showBrandLogo');
        $config['brandLogoSize'] = clampIntValue('brandLogoSize', 230, 100, 360);
        $config['showPortrait'] = postBool('showPortrait');
        $config['portraitSize'] = clampIntValue('portraitSize', 340, 180, 480);
        $config['portraitZoom'] = clampFloatValue('portraitZoom', 1.0, 1.0, 3.0);
        $config['portraitX'] = clampIntValue('portraitX', 1000, 700, 1180);
        $config['portraitY'] = clampIntValue('portraitY', 340, 170, 550);

        $newBackground = replaceUploadedImage('background_image', 'saved-background', $assetsDir);
        $newPortrait = replaceUploadedImage('portrait_image', 'saved-portrait', $assetsDir);
        $newBrandLogo = replaceUploadedImage('brand_logo_image', 'saved-brand-logo', $assetsDir);
        if ($newBackground !== null) $config['backgroundAsset'] = $newBackground;
        if ($newPortrait !== null) $config['portraitAsset'] = $newPortrait;
        if ($newBrandLogo !== null) $config['brandLogoAsset'] = $newBrandLogo;

        saveThumbnailConfig($configFile, $config);
        echo json_encode([
            'ok' => true,
            'message' => 'Configuration saved. It will load automatically next time.',
            'assets' => [
                'background' => assetUrlFromRelative((string)$config['backgroundAsset']),
                'portrait' => assetUrlFromRelative((string)$config['portraitAsset']),
                'brandLogo' => assetUrlFromRelative((string)$config['brandLogoAsset']),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$savedConfig = loadThumbnailConfig($configFile);
$savedAssets = [
    'background' => assetUrlFromRelative((string)($savedConfig['backgroundAsset'] ?? '')),
    'portrait' => assetUrlFromRelative((string)($savedConfig['portraitAsset'] ?? '')),
    'brandLogo' => assetUrlFromRelative((string)($savedConfig['brandLogoAsset'] ?? '')),
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NeonCast Thumbnail Builder</title>
<style>
@font-face{
  font-family:"Radio Stars";
  src:url("fonts/Radio Stars.otf?v=<?= assetVersion(__DIR__ . '/fonts/Radio Stars.otf') ?>") format("opentype");
  font-style:normal;
  font-weight:400;
  font-display:swap;
}
:root{
  --bg:#060014;--panel:#120326;--panel2:#180632;--panel3:#080018;
  --text:#f8f5ff;--muted:#bfc5e3;--line:rgba(25,247,255,.35);
  --cyan:#19f7ff;--pink:#ff2bd6;--violet:#8b3dff;--ok:#64ffd1;--bad:#ff789d;
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;background:radial-gradient(circle at 80% 0,rgba(255,43,214,.22),transparent 32%),radial-gradient(circle at 10% 90%,rgba(25,247,255,.18),transparent 28%),linear-gradient(145deg,#03000c,#13002c 50%,#26004b);color:var(--text);font-family:Inter,"Segoe UI",Arial,sans-serif}
body{padding:36px 18px}
.wrap{width:min(1180px,100%);margin:0 auto}
.head,.section-head{border:1px solid rgba(25,247,255,.55);background:rgba(8,3,28,.86);padding:26px 28px;border-radius:20px;box-shadow:0 0 34px rgba(255,43,214,.15),inset 0 0 28px rgba(25,247,255,.05);margin-bottom:20px}
.brand{display:flex;align-items:center;justify-content:flex-start;margin-bottom:8px}
.brand img{display:block;width:min(360px,82vw);height:auto;filter:drop-shadow(0 0 20px rgba(255,43,214,.25)) drop-shadow(0 0 26px rgba(25,247,255,.16))}
h1{margin:10px 0 6px;font-size:24px}
.lead{margin:0;color:var(--muted);line-height:1.55}
.section-head{margin-top:26px}
.section-head h2{font-size:25px;margin:0 0 6px}
.card{position:relative;overflow:hidden;border:1px solid var(--line);background:linear-gradient(145deg,rgba(12,4,38,.95),rgba(35,5,71,.92));border-radius:18px;padding:18px;box-shadow:0 12px 30px rgba(0,0,0,.28),inset 0 0 24px rgba(255,43,214,.05)}
.card:before{content:"";position:absolute;inset:0;height:3px;background:linear-gradient(90deg,var(--pink),var(--cyan))}
.preview-card{padding:14px}
.preview-stage{width:min(100%,1280px);margin:0 auto;aspect-ratio:16/9;position:relative;background:#05000e;border-radius:12px;overflow:hidden;border:1px solid rgba(255,43,214,.35);box-shadow:0 0 30px rgba(25,247,255,.07)}
#thumbCanvas{display:block;width:100%;height:100%;background:#05070b}
.safe-zone{position:absolute;inset:6%;border:1px dashed rgba(230,240,255,.28);border-radius:10px;pointer-events:none}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.grid.three{grid-template-columns:repeat(3,1fr)}
.card h3{margin:0 0 14px;font-size:18px}
.group{display:flex;flex-direction:column;gap:8px;margin-bottom:14px}
.group:last-child{margin-bottom:0}
.group label,.field-label{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--cyan);font-weight:800}
input[type=text],input[type=number],select{width:100%;border:1px solid rgba(25,247,255,.35);border-radius:10px;background:var(--panel3);color:var(--text);padding:11px 12px;outline:none}
input[type=text]:focus,input[type=number]:focus,select:focus{border-color:var(--pink);box-shadow:0 0 0 3px rgba(255,43,214,.1)}
input[type=color]{width:100%;height:44px;border:1px solid rgba(25,247,255,.35);border-radius:10px;background:var(--panel3);padding:5px;cursor:pointer}
input[type=range]{width:100%;accent-color:var(--pink)}
.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
button,.file-btn{border:0;border-radius:10px;padding:11px 14px;font-weight:900;letter-spacing:.03em;cursor:pointer}
button.primary{background:linear-gradient(90deg,var(--pink),var(--cyan));color:#070012}
button.secondary,.file-btn{background:rgba(25,247,255,.12);color:var(--cyan);border:1px solid rgba(25,247,255,.4)}
button:hover,.file-btn:hover{filter:brightness(1.08)}
.small{font-size:12px;color:var(--muted);line-height:1.5}
.value{font-size:12px;color:var(--muted);text-align:right}
.checkbox{display:flex;gap:8px;align-items:center;font-size:13px;color:var(--text);text-transform:none!important;letter-spacing:0!important;font-weight:600!important}
.checkbox input{accent-color:var(--pink)}
.font-status{padding:10px 12px;border-radius:10px;background:#0d0620;border:1px solid rgba(25,247,255,.25);font-size:12px;color:#c9d7ea}
.font-status.ok{border-color:rgba(100,255,209,.45);box-shadow:0 0 18px rgba(100,255,209,.08);color:var(--ok)}
.font-status.warn{border-color:rgba(255,194,113,.42);color:#efd6ad}
.file-control{display:grid;grid-template-columns:auto 1fr;align-items:center;gap:10px}
.file-control input{display:none}
.file-btn{display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}
.file-name{min-width:0;padding:10px 12px;border:1px solid rgba(25,247,255,.2);background:#080018;border-radius:10px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}
.theme-preview{display:flex;align-items:center;gap:10px;min-height:36px}
.theme-dot{width:24px;height:24px;border-radius:50%;border:1px solid rgba(255,255,255,.5);box-shadow:0 0 14px currentColor}
.theme-name{font-size:13px;color:var(--muted)}
.actions{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.config-status{min-height:22px;margin-top:12px;font-size:13px;font-weight:800;color:var(--ok)}.config-status.error{color:var(--bad)}
.meta{margin-top:14px;color:var(--muted);font-size:12px;line-height:1.55}
@media(max-width:900px){.grid,.grid.three{grid-template-columns:1fr}.row{grid-template-columns:1fr}}
@media(max-width:620px){body{padding:14px 10px}.head,.section-head{padding:20px 16px}.card{padding:14px}.file-control{grid-template-columns:1fr}.actions{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <header class="head">
    <div class="brand"><img src="assets/config-logo.png?v=<?= assetVersion(__DIR__ . '/assets/config-logo.png') ?>" alt="NeonCast"></div>
    <h1>Thumbnail Builder</h1>
    <p class="lead">Create a 1280 × 720 thumbnail with custom artwork, branding, portrait, a neon title, pearl-white supporting text and social links. Everything is configured directly on this page.</p>
  </header>

  <section class="section-head">
    <h2>Live preview</h2>
    <p class="lead">The canvas below is the exact 16:9 image that will be exported as PNG.</p>
  </section>

  <section class="card preview-card">
    <div class="preview-stage">
      <canvas id="thumbCanvas" width="1280" height="720"></canvas>
      <div class="safe-zone" id="safeZone"></div>
    </div>
  </section>

  <section class="section-head">
    <h2>Media & branding</h2>
    <p class="lead">Choose the main artwork, the optional portrait and the logo displayed in the upper-left corner. When the configuration is saved, uploaded images replace the previously saved images instead of creating duplicates.</p>
  </section>

  <div class="grid three">
    <article class="card">
      <h3>Brand logo</h3>
      <div class="group">
        <label class="checkbox"><input id="showBrandLogo" type="checkbox" checked>Show the logo in the upper-left corner</label>
        <label class="file-control">
          <span class="file-btn">Choose file</span>
          <span class="file-name" id="brandLogoFileName">No file selected</span>
          <input id="brandLogoInput" type="file" accept="image/png,image/jpeg,image/webp">
        </label>
        <div class="small">If no custom file is selected, the NeonCast logo is used.</div>
        <input id="brandLogoSize" type="range" min="100" max="360" step="1" value="230">
        <div class="value" id="brandLogoSizeValue">230 px</div>
      </div>
    </article>

    <article class="card">
      <h3>Main artwork</h3>
      <div class="group">
        <label>Main background image</label>
        <label class="file-control">
          <span class="file-btn">Choose file</span>
          <span class="file-name" id="imageFileName">No file selected</span>
          <input id="imageInput" type="file" accept="image/png,image/jpeg,image/webp">
        </label>
        <div class="small">PNG, JPG/JPEG or WEBP. The image is automatically cropped to fill the 16:9 canvas.</div>
      </div>
    </article>

    <article class="card">
      <h3>Right portrait</h3>
      <div class="group">
        <label class="checkbox"><input id="showPortrait" type="checkbox" checked>Show the circular portrait</label>
        <label class="file-control">
          <span class="file-btn">Choose file</span>
          <span class="file-name" id="portraitFileName">No file selected</span>
          <input id="portraitInput" type="file" accept="image/png,image/jpeg,image/webp">
        </label>
        <div class="small">Disable this option when no circular portrait is required.</div>
      </div>
    </article>
  </div>

  <section class="section-head">
    <h2>Image composition</h2>
    <p class="lead">Fine-tune the main image, background treatment and circular portrait position.</p>
  </section>

  <div class="grid">
    <article class="card">
      <h3>Background image</h3>
      <div class="row">
        <div class="group"><label>Zoom</label><input id="zoom" type="range" min="1" max="3" step="0.01" value="1"><div class="value" id="zoomValue">1.00×</div></div>
        <div class="group"><label>Darkness</label><input id="darkness" type="range" min="0" max="0.8" step="0.01" value="0.32"><div class="value" id="darknessValue">32%</div></div>
      </div>
      <div class="row">
        <div class="group"><label>Position X</label><input id="posX" type="range" min="-640" max="640" step="1" value="0"><div class="value" id="posXValue">0 px</div></div>
        <div class="group"><label>Position Y</label><input id="posY" type="range" min="-360" max="360" step="1" value="0"><div class="value" id="posYValue">0 px</div></div>
      </div>
      <div class="row">
        <div class="group">
          <label>Background overlay</label>
          <select id="backgroundMode">
            <option value="gradient" selected>Dark → light gradient</option>
            <option value="solid">Solid color</option>
            <option value="none">None</option>
          </select>
          <div class="small">Use a directional gradient, a uniform color overlay, or the raw image.</div>
        </div>
        <div class="group" id="backgroundColorGroup">
          <label>Solid overlay color</label>
          <input id="backgroundColor" type="color" value="#000000">
          <div class="small">Used only when “Solid color” is selected.</div>
        </div>
      </div>
    </article>

    <article class="card">
      <h3>Circular portrait</h3>
      <div class="row">
        <div class="group"><label>Size</label><input id="portraitSize" type="range" min="180" max="480" step="2" value="340"><div class="value" id="portraitSizeValue">340 px</div></div>
        <div class="group"><label>Portrait zoom</label><input id="portraitZoom" type="range" min="1" max="3" step="0.01" value="1"><div class="value" id="portraitZoomValue">1.00×</div></div>
      </div>
      <div class="row">
        <div class="group"><label>Portrait X</label><input id="portraitX" type="range" min="700" max="1180" step="1" value="1000"><div class="value" id="portraitXValue">1000 px</div></div>
        <div class="group"><label>Portrait Y</label><input id="portraitY" type="range" min="170" max="550" step="1" value="340"><div class="value" id="portraitYValue">340 px</div></div>
      </div>
    </article>
  </div>

  <section class="section-head">
    <h2>Typography & title neon theme</h2>
    <p class="lead">Edit the title and subtitle. The selected neon palette applies to the title and the WEBSITE / TWITCH / YOUTUBE labels. Subtitle and social-link values stay pearl white.</p>
  </section>

  <div class="grid">
    <article class="card">
      <h3>Text content</h3>
      <div class="group"><label>Title</label><input id="title" type="text" value="Cozy Events"></div>
      <div class="group"><label>Subtitle</label><input id="subtitle" type="text" value="Plan together. Play together. Create memories together."></div>
      <div class="row">
        <div class="group"><label>Title size</label><input id="titleSize" type="number" min="24" max="140" value="72"></div>
        <div class="group"><label>Subtitle size</label><input id="subtitleSize" type="number" min="14" max="80" value="28"></div>
      </div>
      <div class="row">
        <div class="group"><label>Alignment</label><select id="align" disabled><option value="left" selected>Bottom-left — fixed</option></select></div>
        <div class="group"><label>Vertical position</label><select id="textVertical" disabled><option value="bottom" selected>Bottom — fixed</option></select></div>
      </div>
      <div id="fontStatus" class="font-status">Loading Radio Stars…</div>
    </article>

    <article class="card">
      <h3>Title neon theme</h3>
      <div class="group">
        <label>Color theme</label>
        <select id="fontTheme">
          <?php foreach ($groupedThemes as $group => $themes): ?>
            <optgroup label="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($themes as $id => $theme): ?>
              <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"<?= $id === 'synthwave' ? ' selected' : '' ?>><?= htmlspecialchars($theme['label'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <div class="theme-preview" id="themePreview"></div>
        <div class="small">These palettes match the NeonCast overlay color range and affect the title plus WEBSITE / TWITCH / YOUTUBE labels. Subtitle and social-link values remain pearl white.</div>
      </div>
    </article>
  </div>

  <section class="section-head">
    <h2>Links & visibility</h2>
    <p class="lead">Set the links printed in the lower-right corner and choose which helper elements remain visible while editing.</p>
  </section>

  <div class="grid">
    <article class="card">
      <h3>Social links</h3>
      <div class="group"><label>Website</label><input id="site" type="text" value="alpasnet.eu"></div>
      <div class="group"><label>Twitch</label><input id="twitch" type="text" value="twitch.tv/alpasnet"></div>
      <div class="group"><label>YouTube</label><input id="youtube" type="text" value="@AlpasNet"></div>
    </article>

    <article class="card">
      <h3>Display options</h3>
      <div class="group">
        <label class="checkbox"><input id="showLinks" type="checkbox" checked>Show links in the exported image</label>
        <label class="checkbox"><input id="showSafe" type="checkbox" checked>Show the safe zone while editing</label>
      </div>
      <div class="small">The safe zone is an editor guide only. It is never drawn into the exported PNG.</div>
    </article>
  </div>

  <section class="section-head">
    <h2>Export</h2>
    <p class="lead">Save the final composition or restore every control to its default value.</p>
  </section>

  <article class="card">
    <div class="actions">
      <button id="saveConfigBtn" class="primary">Save configuration</button>
      <button id="exportBtn" class="secondary">Save PNG</button>
      <button id="resetBtn" class="secondary">Reset all settings</button>
    </div>
    <div id="configStatus" class="config-status"></div>
    <div class="meta">Saved settings and uploaded images are loaded automatically the next time this page is opened. Output size: <strong>1280 × 720 PNG</strong>. Radio Stars is loaded from <strong>fonts/Radio Stars.otf</strong>.</div>
  </article>
</div>

<script>
(() => {
  const THEME_CATALOG = <?= json_encode($themeCatalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const SAVED_CONFIG = <?= json_encode($savedConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const SAVED_ASSETS = <?= json_encode($savedAssets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const DEFAULT_LOGO = 'assets/logo.png?v=<?= assetVersion(__DIR__ . '/assets/logo.png') ?>';
  const canvas = document.getElementById('thumbCanvas');
  const ctx = canvas.getContext('2d');
  const $ = id => document.getElementById(id);
  const ui = {
    imageInput:$('imageInput'), imageFileName:$('imageFileName'), zoom:$('zoom'), darkness:$('darkness'), backgroundMode:$('backgroundMode'), backgroundColor:$('backgroundColor'), backgroundColorGroup:$('backgroundColorGroup'), posX:$('posX'), posY:$('posY'),
    title:$('title'), subtitle:$('subtitle'), titleSize:$('titleSize'), subtitleSize:$('subtitleSize'),
    align:$('align'), textVertical:$('textVertical'), fontTheme:$('fontTheme'), themePreview:$('themePreview'), site:$('site'), twitch:$('twitch'), youtube:$('youtube'),
    showLinks:$('showLinks'), showSafe:$('showSafe'), safeZone:$('safeZone'), saveConfigBtn:$('saveConfigBtn'), configStatus:$('configStatus'), exportBtn:$('exportBtn'), resetBtn:$('resetBtn'),
    zoomValue:$('zoomValue'), darknessValue:$('darknessValue'), posXValue:$('posXValue'), posYValue:$('posYValue'),
    fontStatus:$('fontStatus'), showBrandLogo:$('showBrandLogo'), brandLogoInput:$('brandLogoInput'), brandLogoFileName:$('brandLogoFileName'), brandLogoSize:$('brandLogoSize'), brandLogoSizeValue:$('brandLogoSizeValue'),
    showPortrait:$('showPortrait'), portraitInput:$('portraitInput'), portraitFileName:$('portraitFileName'), portraitSize:$('portraitSize'), portraitZoom:$('portraitZoom'),
    portraitX:$('portraitX'), portraitY:$('portraitY'), portraitSizeValue:$('portraitSizeValue'), portraitZoomValue:$('portraitZoomValue'),
    portraitXValue:$('portraitXValue'), portraitYValue:$('portraitYValue')
  };

  const defaults = {
    zoom:1,darkness:.32,backgroundMode:'gradient',backgroundColor:'#000000',posX:0,posY:0,
    title:'Cozy Events',subtitle:'Plan together. Play together. Create memories together.',
    titleSize:72,subtitleSize:28,align:'left',textVertical:'bottom',fontTheme:'synthwave',
    site:'alpasnet.eu',twitch:'twitch.tv/alpasnet',youtube:'@AlpasNet',showLinks:true,showSafe:true,
    showBrandLogo:true,brandLogoSize:230,showPortrait:true,portraitSize:340,portraitZoom:1,portraitX:1000,portraitY:340
  };

  function applyConfig(cfg){
    const valueKeys=['zoom','darkness','backgroundMode','backgroundColor','posX','posY','title','subtitle','titleSize','subtitleSize','align','textVertical','fontTheme','site','twitch','youtube','brandLogoSize','portraitSize','portraitZoom','portraitX','portraitY'];
    valueKeys.forEach(key=>{if(ui[key] && cfg[key] !== undefined && cfg[key] !== null) ui[key].value=String(cfg[key]);});
    ['showLinks','showSafe','showBrandLogo','showPortrait'].forEach(key=>{if(ui[key] && cfg[key] !== undefined) ui[key].checked=Boolean(cfg[key]);});
  }

  applyConfig(SAVED_CONFIG);

  let image=null, objectUrl=null, portraitImage=null, portraitObjectUrl=null, brandLogoObjectUrl=null, fontReady=false;
  const brandLogo=new Image();
  let brandLogoReady=false;
  brandLogo.onload=()=>{brandLogoReady=true;draw();};
  brandLogo.onerror=()=>{brandLogoReady=false;};
  brandLogo.src=SAVED_ASSETS.brandLogo||DEFAULT_LOGO;

  async function ensureFont(){
    if(fontReady) return true;
    try{
      await document.fonts.load('72px "Radio Stars"');
      await document.fonts.ready;
      fontReady=document.fonts.check('72px "Radio Stars"');
    }catch(e){fontReady=false;}
    ui.fontStatus.textContent=fontReady ? 'Radio Stars loaded — neon pearl rendering active' : 'Radio Stars not found — check fonts/Radio Stars.otf';
    ui.fontStatus.className='font-status '+(fontReady?'ok':'warn');
    return fontReady;
  }

  function setFileName(el,file){el.textContent=file?file.name:'No file selected';}

  function savedFileLabel(relative){
    if(!relative) return 'No saved image';
    const clean=String(relative).split('?')[0];
    return 'Saved: '+clean.split('/').pop();
  }

  function loadSavedImage(url,onLoad){
    if(!url) return;
    const img=new Image();
    img.onload=()=>{onLoad(img);draw();};
    img.onerror=()=>{};
    img.src=url;
  }

  if(SAVED_ASSETS.background){
    ui.imageFileName.textContent=savedFileLabel(SAVED_CONFIG.backgroundAsset);
    loadSavedImage(SAVED_ASSETS.background,img=>{image=img;});
  }
  if(SAVED_ASSETS.portrait){
    ui.portraitFileName.textContent=savedFileLabel(SAVED_CONFIG.portraitAsset);
    loadSavedImage(SAVED_ASSETS.portrait,img=>{portraitImage=img;});
  }
  if(SAVED_ASSETS.brandLogo){
    ui.brandLogoFileName.textContent=savedFileLabel(SAVED_CONFIG.brandLogoAsset);
  }

  function updateLabels(){
    ui.zoomValue.textContent=Number(ui.zoom.value).toFixed(2)+'×';
    ui.darknessValue.textContent=Math.round(Number(ui.darkness.value)*100)+'%';
    const bgMode=ui.backgroundMode.value;
    ui.backgroundColor.disabled=bgMode!=='solid';
    ui.backgroundColorGroup.style.opacity=bgMode==='solid'?'1':'.48';
    ui.darkness.disabled=bgMode==='none';
    ui.posXValue.textContent=ui.posX.value+' px';
    ui.posYValue.textContent=ui.posY.value+' px';
    ui.safeZone.style.display=ui.showSafe.checked?'block':'none';
    ui.brandLogoSizeValue.textContent=ui.brandLogoSize.value+' px';
    ui.portraitSizeValue.textContent=ui.portraitSize.value+' px';
    ui.portraitZoomValue.textContent=Number(ui.portraitZoom.value).toFixed(2)+'×';
    ui.portraitXValue.textContent=ui.portraitX.value+' px';
    ui.portraitYValue.textContent=ui.portraitY.value+' px';
    updateThemePreview();
  }

  function coverRect(iw,ih,bw,bh,zoom=1){const s=Math.max(bw/iw,bh/ih)*zoom;return{w:iw*s,h:ih*s};}

  function drawPlaceholder(){
    const g=ctx.createLinearGradient(0,0,1280,720);g.addColorStop(0,'#071127');g.addColorStop(.62,'#13234a');g.addColorStop(1,'#250c18');ctx.fillStyle=g;ctx.fillRect(0,0,1280,720);
    const rg=ctx.createRadialGradient(980,250,20,980,250,470);rg.addColorStop(0,'rgba(110,165,255,.25)');rg.addColorStop(1,'rgba(0,0,0,0)');ctx.fillStyle=rg;ctx.fillRect(0,0,1280,720);
  }

  function font(size){return `${size}px "Radio Stars", Georgia, serif`;}

  function wrapText(text,maxWidth,fontValue){
    ctx.font=fontValue;const words=text.trim().split(/\s+/).filter(Boolean),lines=[];let line='';
    for(const word of words){const test=line?line+' '+word:word;if(ctx.measureText(test).width>maxWidth&&line){lines.push(line);line=word}else line=test;}
    if(line)lines.push(line);return lines;
  }

  function hexToRgb(hex){
    const clean=String(hex||'#000000').replace('#','');
    const value=clean.length===3?clean.split('').map(c=>c+c).join(''):clean.padEnd(6,'0').slice(0,6);
    const n=parseInt(value,16);return {r:(n>>16)&255,g:(n>>8)&255,b:n&255};
  }

  function rgbString(hex){const c=hexToRgb(hex);return `${c.r},${c.g},${c.b}`;}

  function mixHex(a,b,t){
    const A=hexToRgb(a),B=hexToRgb(b);const m=v=>Math.round(v);
    return '#'+[m(A.r+(B.r-A.r)*t),m(A.g+(B.g-A.g)*t),m(A.b+(B.b-A.b)*t)].map(v=>v.toString(16).padStart(2,'0')).join('');
  }

  function currentTheme(){
    const meta=THEME_CATALOG[ui.fontTheme.value]||THEME_CATALOG.synthwave;
    const [primary,secondary,accent]=meta.colors;
    return {
      name:meta.label,primary,secondary,accent,
      top:mixHex(primary,'#ffffff',.72),
      mid1:mixHex(primary,'#ffffff',.28),
      mid2:primary,
      warm:secondary,
      low:accent,
      glow:rgbString(primary),
      outline:rgbString(secondary),
      dark:rgbString(mixHex(accent,'#000000',.70)),
      label:rgbString(secondary)
    };
  }

  function updateThemePreview(){
    const meta=THEME_CATALOG[ui.fontTheme.value]||THEME_CATALOG.synthwave;
    ui.themePreview.innerHTML='';
    meta.colors.forEach(color=>{const dot=document.createElement('span');dot.className='theme-dot';dot.style.background=color;dot.style.color=color;ui.themePreview.appendChild(dot);});
    const name=document.createElement('span');name.className='theme-name';name.textContent=meta.label;ui.themePreview.appendChild(name);
  }

  function drawPearlText(text,x,y,size,align='left',strength=1){
    ctx.save();ctx.font=font(size);ctx.textAlign=align;ctx.textBaseline='alphabetic';ctx.lineJoin='round';
    ctx.shadowColor=`rgba(0,0,0,${.82*strength})`;ctx.shadowBlur=10*strength;ctx.shadowOffsetY=3*strength;ctx.lineWidth=Math.max(1,size*.035);ctx.strokeStyle=`rgba(28,34,48,${.82*strength})`;ctx.strokeText(text,x,y);
    ctx.shadowOffsetY=0;ctx.shadowColor=`rgba(220,235,255,${.48*strength})`;ctx.shadowBlur=16*strength;ctx.lineWidth=Math.max(.8,size*.018);ctx.strokeStyle=`rgba(228,236,248,${.92*strength})`;ctx.strokeText(text,x,y);
    const g=ctx.createLinearGradient(0,y-size,0,y+size*.18);g.addColorStop(0,'#ffffff');g.addColorStop(.24,'#f7f4f0');g.addColorStop(.50,'#e9edf4');g.addColorStop(.72,'#fffdf8');g.addColorStop(1,'#cfd7e5');
    ctx.fillStyle=g;ctx.shadowBlur=7*strength;ctx.shadowColor=`rgba(235,244,255,${.34*strength})`;ctx.fillText(text,x,y);
    ctx.globalAlpha=.36*strength;ctx.fillStyle='#ffffff';ctx.shadowBlur=0;ctx.fillText(text,x,y-1);ctx.restore();
  }

  function drawTitleThemeText(text,x,y,size,align='left',theme=null){
    ctx.save();const T=theme||currentTheme();ctx.font=font(size);ctx.textAlign=align;ctx.textBaseline='alphabetic';ctx.lineJoin='round';
    ctx.shadowColor='rgba(0,0,0,.82)';ctx.shadowBlur=11;ctx.shadowOffsetY=3;ctx.lineWidth=Math.max(1,size*.032);ctx.strokeStyle=`rgba(${T.dark},.76)`;ctx.strokeText(text,x,y);
    ctx.shadowOffsetY=0;ctx.shadowColor=`rgba(${T.glow},.62)`;ctx.shadowBlur=22;
    const g=ctx.createLinearGradient(x,y-size,x+Math.max(220,size*5),y);g.addColorStop(0,T.primary);g.addColorStop(.52,T.secondary);g.addColorStop(1,T.accent);ctx.fillStyle=g;ctx.fillText(text,x,y);
    ctx.globalAlpha=.26;ctx.shadowBlur=0;ctx.fillStyle='#ffffff';ctx.fillText(text,x,y-1);ctx.restore();
  }

  function drawPortrait(){
    if(!ui.showPortrait.checked||!portraitImage)return;
    const size=Math.max(180,Math.min(480,Number(ui.portraitSize.value)||340)),radius=size/2,cx=Number(ui.portraitX.value)||1000,cy=Number(ui.portraitY.value)||340,zoom=Math.max(1,Number(ui.portraitZoom.value)||1);
    ctx.save();ctx.beginPath();ctx.arc(cx,cy,radius+10,0,Math.PI*2);ctx.shadowColor='rgba(0,0,0,.72)';ctx.shadowBlur=28;ctx.shadowOffsetY=8;ctx.fillStyle='rgba(0,0,0,.18)';ctx.fill();ctx.restore();
    ctx.save();ctx.beginPath();ctx.arc(cx,cy,radius,0,Math.PI*2);ctx.clip();const r=coverRect(portraitImage.naturalWidth,portraitImage.naturalHeight,size,size,zoom);ctx.drawImage(portraitImage,cx-r.w/2,cy-r.h/2,r.w,r.h);ctx.restore();
    const T=currentTheme();ctx.save();const border=12,pearl=ctx.createLinearGradient(cx-radius,cy-radius,cx+radius,cy+radius);pearl.addColorStop(0,T.top);pearl.addColorStop(.32,T.primary);pearl.addColorStop(.56,T.secondary);pearl.addColorStop(.80,T.accent);pearl.addColorStop(1,T.top);ctx.beginPath();ctx.arc(cx,cy,radius-border/2,0,Math.PI*2);ctx.lineWidth=border;ctx.strokeStyle=pearl;ctx.shadowColor=`rgba(${T.glow},.5)`;ctx.shadowBlur=18;ctx.stroke();ctx.restore();
  }

  function draw(){
    ctx.clearRect(0,0,1280,720);
    if(image){const r=coverRect(image.naturalWidth,image.naturalHeight,1280,720,Number(ui.zoom.value));const x=(1280-r.w)/2+Number(ui.posX.value),y=(720-r.h)/2+Number(ui.posY.value);ctx.drawImage(image,x,y,r.w,r.h);}else drawPlaceholder();

    const d=Number(ui.darkness.value),backgroundMode=ui.backgroundMode.value;
    if(backgroundMode==='gradient'){
      const grad=ctx.createLinearGradient(0,0,1280,0);grad.addColorStop(0,`rgba(0,0,0,${Math.min(.88,d+.30)})`);grad.addColorStop(.48,`rgba(0,0,0,${d*.52})`);grad.addColorStop(.80,`rgba(0,0,0,${d*.12})`);grad.addColorStop(1,`rgba(0,0,0,${d*.04})`);ctx.fillStyle=grad;ctx.fillRect(0,0,1280,720);
      const bg=ctx.createLinearGradient(0,420,0,720);bg.addColorStop(0,'rgba(0,0,0,0)');bg.addColorStop(1,`rgba(0,0,0,${Math.min(.84,d+.27)})`);ctx.fillStyle=bg;ctx.fillRect(0,420,1280,300);
    }else if(backgroundMode==='solid'){
      const c=hexToRgb(ui.backgroundColor.value);ctx.fillStyle=`rgba(${c.r},${c.g},${c.b},${d})`;ctx.fillRect(0,0,1280,720);
    }

    const textTheme=currentTheme();

    if(ui.showBrandLogo.checked&&brandLogoReady){
      const size=Math.max(100,Math.min(360,Number(ui.brandLogoSize.value)||230));ctx.save();ctx.shadowColor='rgba(0,0,0,.55)';ctx.shadowBlur=16;ctx.shadowOffsetY=4;ctx.drawImage(brandLogo,28,24,size,size);ctx.restore();
    }

    drawPortrait();

    const edgeMarginX=60,maxTextWidth=820,titleSize=Math.max(24,Number(ui.titleSize.value)||72),subtitleSize=Math.max(14,Number(ui.subtitleSize.value)||28),align='left',textX=edgeMarginX;
    const titleLines=wrapText(ui.title.value,maxTextWidth,font(titleSize)),subtitleLines=wrapText(ui.subtitle.value,maxTextWidth,font(subtitleSize));
    const titleLH=titleSize*.96,subtitleLH=subtitleSize*1.02;
    const fontMetrics=size=>{ctx.save();ctx.font=font(size);const m=ctx.measureText('Mg');ctx.restore();return{ascent:m.actualBoundingBoxAscent||size*.78,descent:m.actualBoundingBoxDescent||size*.22};};
    const tm=fontMetrics(titleSize),sm=fontMetrics(subtitleSize),youtubeValueBaseline=693;let nextTop=null;

    if(ui.subtitle.value.trim()&&subtitleLines.length){const last=youtubeValueBaseline,first=last-Math.max(0,subtitleLines.length-1)*subtitleLH;subtitleLines.forEach((line,i)=>drawPearlText(line,textX,first+i*subtitleLH,subtitleSize,align,.82));nextTop=first-sm.ascent;}
    if(titleLines.length){const last=nextTop===null?youtubeValueBaseline:nextTop-tm.descent,first=last-Math.max(0,titleLines.length-1)*titleLH;titleLines.forEach((line,i)=>drawTitleThemeText(line,textX,first+i*titleLH,titleSize,align,textTheme));}

    if(ui.showLinks.checked){
      const links=[['WEBSITE',ui.site.value],['TWITCH',ui.twitch.value],['YOUTUBE',ui.youtube.value]].filter(([,v])=>v.trim());let y=720-48-(links.length-1)*42;
      for(const [label,value] of links){const linksX=1280-edgeMarginX;drawTitleThemeText(label,linksX,y,13,'right',currentTheme());drawPearlText(value,linksX,y+21,20,'right',.72);y+=42;}
    }
  }

  function bind(el,event='input'){el.addEventListener(event,()=>{updateLabels();draw();});}
  [ui.zoom,ui.darkness,ui.backgroundColor,ui.posX,ui.posY,ui.title,ui.subtitle,ui.titleSize,ui.subtitleSize,ui.site,ui.twitch,ui.youtube,ui.brandLogoSize,ui.portraitSize,ui.portraitZoom,ui.portraitX,ui.portraitY].forEach(el=>bind(el));
  [ui.align,ui.textVertical,ui.fontTheme,ui.showLinks,ui.showSafe,ui.showBrandLogo,ui.showPortrait,ui.backgroundMode].forEach(el=>bind(el,'change'));

  ui.brandLogoInput.addEventListener('change',()=>{
    const file=ui.brandLogoInput.files&&ui.brandLogoInput.files[0];setFileName(ui.brandLogoFileName,file);if(!file||!file.type.startsWith('image/'))return;
    if(brandLogoObjectUrl)URL.revokeObjectURL(brandLogoObjectUrl);brandLogoObjectUrl=URL.createObjectURL(file);brandLogoReady=false;brandLogo.onload=()=>{brandLogoReady=true;ui.showBrandLogo.checked=true;draw();};brandLogo.onerror=()=>{brandLogoReady=false;};brandLogo.src=brandLogoObjectUrl;
  });

  ui.imageInput.addEventListener('change',()=>{
    const file=ui.imageInput.files&&ui.imageInput.files[0];setFileName(ui.imageFileName,file);if(!file||!file.type.startsWith('image/'))return;
    if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=URL.createObjectURL(file);const img=new Image();img.onload=()=>{image=img;draw();};img.src=objectUrl;
  });

  ui.portraitInput.addEventListener('change',()=>{
    const file=ui.portraitInput.files&&ui.portraitInput.files[0];setFileName(ui.portraitFileName,file);if(!file||!file.type.startsWith('image/'))return;
    if(portraitObjectUrl)URL.revokeObjectURL(portraitObjectUrl);portraitObjectUrl=URL.createObjectURL(file);const img=new Image();img.onload=()=>{portraitImage=img;ui.showPortrait.checked=true;draw();};img.src=portraitObjectUrl;
  });

  ui.saveConfigBtn.addEventListener('click',async()=>{
    const fd=new FormData();
    fd.append('action','save_config');
    const valueKeys=['zoom','darkness','backgroundMode','backgroundColor','posX','posY','title','subtitle','titleSize','subtitleSize','fontTheme','site','twitch','youtube','brandLogoSize','portraitSize','portraitZoom','portraitX','portraitY'];
    valueKeys.forEach(key=>fd.append(key,String(ui[key].value)));
    fd.append('showLinks',ui.showLinks.checked?'1':'0');
    fd.append('showSafe',ui.showSafe.checked?'1':'0');
    fd.append('showBrandLogo',ui.showBrandLogo.checked?'1':'0');
    fd.append('showPortrait',ui.showPortrait.checked?'1':'0');
    const backgroundFile=ui.imageInput.files&&ui.imageInput.files[0];
    const portraitFile=ui.portraitInput.files&&ui.portraitInput.files[0];
    const brandFile=ui.brandLogoInput.files&&ui.brandLogoInput.files[0];
    if(backgroundFile)fd.append('background_image',backgroundFile);
    if(portraitFile)fd.append('portrait_image',portraitFile);
    if(brandFile)fd.append('brand_logo_image',brandFile);

    ui.saveConfigBtn.disabled=true;
    ui.configStatus.className='config-status';
    ui.configStatus.textContent='Saving configuration…';
    try{
      const response=await fetch(window.location.pathname,{method:'POST',body:fd,cache:'no-store'});
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||'Unable to save the configuration.');
      ui.configStatus.className='config-status';
      ui.configStatus.textContent=data.message||'Configuration saved.';
      setTimeout(()=>window.location.reload(),550);
    }catch(error){
      ui.configStatus.className='config-status error';
      ui.configStatus.textContent=error instanceof Error?error.message:'Unable to save the configuration.';
      ui.saveConfigBtn.disabled=false;
    }
  });

  ui.exportBtn.addEventListener('click',async()=>{
    await ensureFont();draw();
    canvas.toBlob(blob=>{if(!blob){alert('Unable to generate the PNG.');return;}const url=URL.createObjectURL(blob),a=document.createElement('a');const name=(ui.title.value||'thumbnail').trim().toLowerCase().replace(/[^a-z0-9_-]+/gi,'-').replace(/^-+|-+$/g,'')||'thumbnail';a.href=url;a.download=`${name}-1280x720.png`;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);},'image/png');
  });

  ui.resetBtn.addEventListener('click',()=>{
    for(const k of ['zoom','darkness','posX','posY','title','subtitle','titleSize','subtitleSize','align','textVertical','fontTheme','site','twitch','youtube','brandLogoSize','portraitSize','portraitZoom','portraitX','portraitY'])ui[k].value=defaults[k];
    ui.showLinks.checked=defaults.showLinks;ui.showSafe.checked=defaults.showSafe;ui.showBrandLogo.checked=defaults.showBrandLogo;ui.showPortrait.checked=defaults.showPortrait;ui.backgroundMode.value=defaults.backgroundMode;ui.backgroundColor.value=defaults.backgroundColor;
    image=null;portraitImage=null;ui.imageInput.value='';ui.portraitInput.value='';ui.brandLogoInput.value='';setFileName(ui.imageFileName,null);setFileName(ui.portraitFileName,null);setFileName(ui.brandLogoFileName,null);
    if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}if(portraitObjectUrl){URL.revokeObjectURL(portraitObjectUrl);portraitObjectUrl=null;}if(brandLogoObjectUrl){URL.revokeObjectURL(brandLogoObjectUrl);brandLogoObjectUrl=null;}
    brandLogoReady=false;brandLogo.onload=()=>{brandLogoReady=true;draw();};brandLogo.onerror=()=>{brandLogoReady=false;};brandLogo.src=DEFAULT_LOGO;updateLabels();draw();
  });

  updateLabels();draw();ensureFont().then(draw);
})();
</script>
</body>
</html>
