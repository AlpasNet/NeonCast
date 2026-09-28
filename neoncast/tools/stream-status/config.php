<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$baseDir = __DIR__;
$assetsDir = $baseDir . DIRECTORY_SEPARATOR . 'assets';
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$configFile = $dataDir . DIRECTORY_SEPARATOR . 'status.json';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function ensureDir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create required directory.');
    }
}
function defaultConfig(): array {
    return [
        'logo_position' => 'left',
        'logo_width' => 280,
        'texts' => [
            'intro' => 'THE STREAM IS STARTING SOON',
            'pause' => 'BE RIGHT BACK',
            'end' => 'THANK YOU FOR WATCHING',
        ],
    ];
}
function loadConfig(string $file): array {
    $default = defaultConfig();
    if (!is_file($file)) return $default;
    $decoded = json_decode((string)file_get_contents($file), true);
    if (!is_array($decoded)) return $default;
    $position = in_array(($decoded['logo_position'] ?? ''), ['left','right'], true) ? $decoded['logo_position'] : 'left';
    $width = (int)($decoded['logo_width'] ?? 280);
    $width = max(100, min(400, $width));
    $texts = $default['texts'];
    if (isset($decoded['texts']) && is_array($decoded['texts'])) {
        foreach (['intro','pause','end'] as $key) {
            if (isset($decoded['texts'][$key]) && is_string($decoded['texts'][$key])) {
                $texts[$key] = trim($decoded['texts'][$key]);
            }
        }
    }
    return ['logo_position' => $position, 'logo_width' => $width, 'texts' => $texts];
}
function saveConfig(string $file, array $config): void {
    ensureDir(dirname($file));
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Unable to encode configuration.');
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(5));
    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('Unable to save configuration.');
    if (is_file($file) && !unlink($file)) { @unlink($tmp); throw new RuntimeException('Unable to replace configuration.'); }
    if (!rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('Unable to install configuration.'); }
    @chmod($file, 0664);
}
function uploadError(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload size limit.',
        UPLOAD_ERR_FORM_SIZE => 'The file exceeds the form upload size limit.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted.',
        UPLOAD_ERR_NO_TMP_DIR => 'The temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
        default => 'Upload failed.',
    };
}
function replaceUploaded(string $field, string $target, string $extension, int $maxBytes): bool {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return false;
    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return false;
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException(uploadError($error));
    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    $name = (string)($file['name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload source.');
    if ($size <= 0) throw new RuntimeException('The uploaded file is empty.');
    if ($size > $maxBytes) throw new RuntimeException('The uploaded file is too large.');
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $extension) {
        throw new RuntimeException('Unexpected file format for ' . $field . '.');
    }
    ensureDir(dirname($target));
    $staged = $target . '.incoming-' . bin2hex(random_bytes(5));
    if (!move_uploaded_file($tmp, $staged)) throw new RuntimeException('Unable to store uploaded file.');
    if (is_file($target) && !unlink($target)) { @unlink($staged); throw new RuntimeException('Unable to replace current file.'); }
    if (!rename($staged, $target)) { @unlink($staged); throw new RuntimeException('Unable to install uploaded file.'); }
    @chmod($target, 0664);
    return true;
}
function isValidPngUpload(string $field): bool {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return true;
    if ((int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return true;
    $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
    $info = @getimagesize($tmp);
    return is_array($info) && (int)($info[2] ?? 0) === IMAGETYPE_PNG;
}
function existsLabel(string $path): string { return is_file($path) ? 'Installed' : 'Not uploaded'; }
function assetVersion(string $path): string { return is_file($path) ? (string)filemtime($path) : '0'; }

ensureDir($assetsDir);
ensureDir($dataDir);
$config = loadConfig($configFile);
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $position = (string)($_POST['logo_position'] ?? 'left');
        if (!in_array($position, ['left','right'], true)) $position = 'left';
        $width = max(100, min(400, (int)($_POST['logo_width'] ?? 280)));
        $texts = [];
        foreach (['intro','pause','end'] as $mode) {
            $value = trim((string)($_POST['text_' . $mode] ?? ''));
            if (strlen($value) > 180) throw new RuntimeException(ucfirst($mode) . ' text is too long. Maximum: 180 characters.');
            $texts[$mode] = $value;
        }

        if (!isValidPngUpload('logo_file')) throw new RuntimeException('The logo must be a valid PNG image.');
        $changed = [];
        if (replaceUploaded('logo_file', $assetsDir . DIRECTORY_SEPARATOR . 'logo.png', 'png', 30 * 1024 * 1024)) $changed[] = 'logo';
        foreach (['startup','intro','pause','end'] as $mode) {
            if (replaceUploaded('video_' . $mode, $assetsDir . DIRECTORY_SEPARATOR . 'video-' . $mode . '.mp4', 'mp4', 500 * 1024 * 1024)) $changed[] = $mode . ' video';
        }
        foreach (['intro','pause','end'] as $mode) {
            if (replaceUploaded('audio_' . $mode, $assetsDir . DIRECTORY_SEPARATOR . 'music-' . $mode . '.mp3', 'mp3', 100 * 1024 * 1024)) $changed[] = $mode . ' music';
        }
        $config = ['logo_position' => $position, 'logo_width' => $width, 'texts' => $texts];
        saveConfig($configFile, $config);
        $messages[] = 'Configuration saved successfully.' . ($changed ? ' Replaced: ' . implode(', ', $changed) . '.' : '');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
    $config = loadConfig($configFile);
}

$paths = [
    'logo' => $assetsDir . DIRECTORY_SEPARATOR . 'logo.png',
    'video-startup' => $assetsDir . DIRECTORY_SEPARATOR . 'video-startup.mp4',
    'video-intro' => $assetsDir . DIRECTORY_SEPARATOR . 'video-intro.mp4',
    'video-pause' => $assetsDir . DIRECTORY_SEPARATOR . 'video-pause.mp4',
    'video-end' => $assetsDir . DIRECTORY_SEPARATOR . 'video-end.mp4',
    'music-intro' => $assetsDir . DIRECTORY_SEPARATOR . 'music-intro.mp3',
    'music-pause' => $assetsDir . DIRECTORY_SEPARATOR . 'music-pause.mp3',
    'music-end' => $assetsDir . DIRECTORY_SEPARATOR . 'music-end.mp3',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NeonCast — Stream Status Configuration</title>
<style>
:root{--bg:#05000f;--panel:#100323;--panel2:#19043a;--text:#f9f7ff;--muted:#bfc5e3;--pink:#ff2bd6;--cyan:#19f7ff;--violet:#8b3dff;--ok:#63ffd0;--bad:#ff83a6}
*{box-sizing:border-box}html,body{margin:0;min-height:100%}body{color:var(--text);font-family:Inter,"Segoe UI",Arial,sans-serif;background:radial-gradient(circle at 20% 0,rgba(255,43,214,.22),transparent 34%),radial-gradient(circle at 90% 15%,rgba(25,247,255,.17),transparent 30%),linear-gradient(145deg,#020008,#0a001b 42%,#210043 100%);padding:34px 18px 50px}.wrap{width:min(1180px,100%);margin:auto}.head,.section{border:1px solid rgba(25,247,255,.4);border-radius:22px;background:linear-gradient(145deg,rgba(8,2,27,.94),rgba(22,4,48,.9));padding:25px 27px;margin-bottom:20px;box-shadow:0 20px 55px rgba(0,0,0,.3),0 0 35px rgba(255,43,214,.09)}.brand{width:min(390px,80vw);display:block;margin-bottom:12px}.kicker{margin:0;color:var(--cyan);font-size:12px;font-weight:900;letter-spacing:.18em;text-transform:uppercase;font-family:Inter,"Segoe UI",Arial,sans-serif}h1{font-size:30px;margin:8px 0 8px;font-family:Inter,"Segoe UI",Arial,sans-serif}.lead{margin:0;color:var(--muted);line-height:1.6}.notice{padding:12px 14px;border-radius:11px;margin-top:14px;font-weight:800}.ok{border:1px solid rgba(99,255,208,.45);background:rgba(99,255,208,.08);color:var(--ok)}.bad{border:1px solid rgba(255,131,166,.45);background:rgba(255,131,166,.08);color:var(--bad)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.card{border:1px solid rgba(25,247,255,.3);background:linear-gradient(145deg,rgba(10,3,32,.96),rgba(28,5,62,.94));border-radius:18px;padding:18px;position:relative;overflow:hidden}.card:before{content:"";position:absolute;inset:0 0 auto;height:3px;background:linear-gradient(90deg,var(--pink),var(--cyan))}.card h2{font-size:19px;margin:0 0 6px;font-family:Inter,"Segoe UI",Arial,sans-serif}.help{color:var(--muted);font-size:13px;line-height:1.5;margin:0 0 14px}.field{display:flex;flex-direction:column;gap:7px;margin-top:12px}.field label{font-size:11px;text-transform:uppercase;letter-spacing:.12em;font-weight:900;color:var(--cyan)}input[type=text],input[type=number],select{width:100%;border:1px solid rgba(25,247,255,.32);background:#080018;color:#fff;border-radius:10px;padding:11px 12px;outline:none}input:focus,select:focus{border-color:var(--pink);box-shadow:0 0 0 3px rgba(255,43,214,.09)}.file-row{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center}.file-picker{display:flex;align-items:center;gap:10px;width:100%;min-width:0;cursor:pointer}.file-picker input[type=file]{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}.file-button{flex:0 0 auto;border:1px solid rgba(25,247,255,.38);background:#170631;color:#fff;border-radius:9px;padding:9px 11px;font-size:13px;font-weight:800}.file-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:12px}.file-picker:hover .file-button{border-color:var(--pink);box-shadow:0 0 14px rgba(255,43,214,.12)}.badge{font-size:11px;font-weight:900;border-radius:999px;padding:6px 9px;white-space:nowrap}.on{color:var(--ok);background:rgba(99,255,208,.08);border:1px solid rgba(99,255,208,.34)}.off{color:#ff9ab5;background:rgba(255,100,145,.07);border:1px solid rgba(255,100,145,.28)}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}.btn{border:0;border-radius:10px;padding:11px 15px;font-weight:900;text-decoration:none;cursor:pointer;color:#05000e;background:linear-gradient(90deg,var(--pink),var(--cyan));display:inline-flex;align-items:center;justify-content:center;font-family:Inter,"Segoe UI",Arial,sans-serif}.btn.secondary{background:rgba(25,247,255,.08);color:var(--cyan);border:1px solid rgba(25,247,255,.33)}.preview-links{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.link-item{display:flex;flex-direction:column;gap:8px}.link-item .btn{width:100%;text-align:center}.copy-btn{background:rgba(255,43,214,.09)!important;color:#ffd8f8!important;border:1px solid rgba(255,43,214,.38)!important}.copy-btn.copied{color:var(--ok)!important;border-color:rgba(99,255,208,.45)!important;background:rgba(99,255,208,.08)!important}.wide{grid-column:1/-1}.small{font-size:12px;color:var(--muted);line-height:1.55}.save{width:100%;font-size:15px;min-height:48px}.top-links{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap}.top-links a{color:var(--text);text-decoration:none;border:1px solid rgba(255,43,214,.35);padding:9px 12px;border-radius:10px;background:rgba(255,43,214,.07);font-weight:800}.range-wrap{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center}.range-wrap input[type=range]{width:100%;accent-color:var(--pink);cursor:pointer}.range-value{min-width:72px;text-align:center;padding:9px 10px;border-radius:10px;border:1px solid rgba(25,247,255,.35);background:#080018;color:var(--cyan);font-weight:900;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}@media(max-width:800px){body{padding:18px 12px}.grid{grid-template-columns:1fr}.wide{grid-column:auto}.preview-links{grid-template-columns:1fr 1fr}.file-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="wrap">
  <header class="head">
    <img class="brand" src="../../assets/neoncast-logo.png?v=<?= h(assetVersion(dirname(__DIR__,2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'neoncast-logo.png')) ?>" alt="NeonCast">
    <p class="kicker">Stream status scenes</p>
    <h1>Status page configuration</h1>
    <p class="lead">Configure four full-screen status scenes for OBS. Background videos autoplay in a silent loop; intro, break and ending music loop independently.</p>
    <?php foreach ($messages as $message): ?><div class="notice ok"><?= h($message) ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="notice bad"><?= h($error) ?></div><?php endforeach; ?>
    <div class="top-links"><a href="../../index.php">Back to NeonCast Tools</a></div>
  </header>

  <form method="post" enctype="multipart/form-data">
    <section class="grid">
      <article class="card">
        <h2>Startup</h2>
        <p class="help">Background video only. It starts automatically, loops forever and is always muted.</p>
        <div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="video_startup" accept="video/mp4,.mp4"></label><span class="badge <?= is_file($paths['video-startup']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['video-startup'])) ?></span></div>
      </article>

      <article class="card">
        <h2>Intro</h2>
        <p class="help">Configure the intro background, bottom-center message and looping music.</p>
        <div class="field"><label>Bottom text</label><input type="text" name="text_intro" maxlength="180" value="<?= h($config['texts']['intro']) ?>"></div>
        <div class="field"><label>Background video · MP4</label><div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="video_intro" accept="video/mp4,.mp4"></label><span class="badge <?= is_file($paths['video-intro']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['video-intro'])) ?></span></div></div>
        <div class="field"><label>Looping music · MP3</label><div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="audio_intro" accept="audio/mpeg,.mp3"></label><span class="badge <?= is_file($paths['music-intro']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['music-intro'])) ?></span></div></div>
      </article>

      <article class="card">
        <h2>Break</h2>
        <p class="help">Configure the pause/BRB background, bottom-center message and looping music.</p>
        <div class="field"><label>Bottom text</label><input type="text" name="text_pause" maxlength="180" value="<?= h($config['texts']['pause']) ?>"></div>
        <div class="field"><label>Background video · MP4</label><div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="video_pause" accept="video/mp4,.mp4"></label><span class="badge <?= is_file($paths['video-pause']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['video-pause'])) ?></span></div></div>
        <div class="field"><label>Looping music · MP3</label><div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="audio_pause" accept="audio/mpeg,.mp3"></label><span class="badge <?= is_file($paths['music-pause']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['music-pause'])) ?></span></div></div>
      </article>

      <article class="card">
        <h2>Ending</h2>
        <p class="help">Configure the ending background, bottom-center message and looping music.</p>
        <div class="field"><label>Bottom text</label><input type="text" name="text_end" maxlength="180" value="<?= h($config['texts']['end']) ?>"></div>
        <div class="field"><label>Background video · MP4</label><div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="video_end" accept="video/mp4,.mp4"></label><span class="badge <?= is_file($paths['video-end']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['video-end'])) ?></span></div></div>
        <div class="field"><label>Looping music · MP3</label><div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="audio_end" accept="audio/mpeg,.mp3"></label><span class="badge <?= is_file($paths['music-end']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['music-end'])) ?></span></div></div>
      </article>

      <article class="card wide">
        <h2>Logo</h2>
        <p class="help">Upload one transparent PNG logo and choose its top corner and display size. The slider scales both the width and height proportionally. Uploading a new logo replaces the current file.</p>
        <div class="grid">
          <div class="field"><label>Logo file · PNG</label><div class="file-row"><label class="file-picker"><span class="file-button">Choose file</span><span class="file-name">No file selected</span><input type="file" name="logo_file" accept="image/png,.png"></label><span class="badge <?= is_file($paths['logo']) ? 'on' : 'off' ?>"><?= h(existsLabel($paths['logo'])) ?></span></div></div>
          <div class="field"><label>Position</label><select name="logo_position"><option value="left"<?= $config['logo_position']==='left'?' selected':'' ?>>Top left</option><option value="right"<?= $config['logo_position']==='right'?' selected':'' ?>>Top right</option></select></div>
          <div class="field"><label for="logo_width">Logo size</label><div class="range-wrap"><input id="logo_width" type="range" name="logo_width" min="100" max="400" step="10" value="<?= (int)$config['logo_width'] ?>"><output id="logo_width_value" class="range-value" for="logo_width"><?= (int)$config['logo_width'] ?> px</output></div><p class="small">Choose a logo size between 100 and 400 pixels. Width and height scale together while preserving the image ratio.</p></div>
        </div>
      </article>

      <article class="card wide">
        <h2>OBS links</h2>
        <p class="help">Add one of these URLs as a Browser Source. Use your normal website address before the relative path.</p>
        <div class="preview-links">
          <div class="link-item">
            <a class="btn secondary" href="index.php?mode=startup" target="_blank" rel="noopener">Open Startup</a>
            <button class="btn copy-btn" type="button" data-copy-url="index.php?mode=startup">Copy Startup link</button>
          </div>
          <div class="link-item">
            <a class="btn secondary" href="index.php?mode=intro" target="_blank" rel="noopener">Open Intro</a>
            <button class="btn copy-btn" type="button" data-copy-url="index.php?mode=intro">Copy Intro link</button>
          </div>
          <div class="link-item">
            <a class="btn secondary" href="index.php?mode=pause" target="_blank" rel="noopener">Open Break</a>
            <button class="btn copy-btn" type="button" data-copy-url="index.php?mode=pause">Copy Break link</button>
          </div>
          <div class="link-item">
            <a class="btn secondary" href="index.php?mode=end" target="_blank" rel="noopener">Open Ending</a>
            <button class="btn copy-btn" type="button" data-copy-url="index.php?mode=end">Copy Ending link</button>
          </div>
        </div>
        <p class="small">Videos: MP4 · Music: MP3 · Logo: PNG. New uploads overwrite the previous file for the same scene.</p>
      </article>
    </section>
    <div class="actions"><button class="btn save" type="submit">Save configuration</button></div>
  </form>
</main>
<script>
document.querySelectorAll('.file-picker input[type="file"]').forEach((input) => {
  input.addEventListener('change', () => {
    const label = input.closest('.file-picker');
    const name = label ? label.querySelector('.file-name') : null;
    if (name) name.textContent = input.files && input.files[0] ? input.files[0].name : 'No file selected';
  });
});
const logoWidth = document.getElementById('logo_width');
const logoWidthValue = document.getElementById('logo_width_value');
if (logoWidth && logoWidthValue) {
  const syncLogoWidth = () => { logoWidthValue.textContent = `${logoWidth.value} px`; };
  logoWidth.addEventListener('input', syncLogoWidth);
  syncLogoWidth();
}

document.querySelectorAll('[data-copy-url]').forEach((button) => {
  button.addEventListener('click', async () => {
    const relative = button.getAttribute('data-copy-url') || '';
    const url = new URL(relative, window.location.href).href;
    const original = button.textContent;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
      } else {
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
      }
      button.textContent = 'Copied!';
      button.classList.add('copied');
      setTimeout(() => { button.textContent = original; button.classList.remove('copied'); }, 1400);
    } catch (error) {
      button.textContent = 'Copy failed';
      setTimeout(() => { button.textContent = original; }, 1400);
    }
  });
});
</script>
</body>
</html>
