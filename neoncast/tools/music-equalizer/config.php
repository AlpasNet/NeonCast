<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

const MAX_UPLOAD_BYTES = 250 * 1024 * 1024;

$root = __DIR__;
$dataDir = $root . DIRECTORY_SEPARATOR . 'data';
$assetsDir = $root . DIRECTORY_SEPARATOR . 'assets';
$fixedBackgroundPath = $assetsDir . DIRECTORY_SEPARATOR . 'background.png';
$uploadRoot = $assetsDir . DIRECTORY_SEPARATOR . 'uploads';
$audioDir = $uploadRoot . DIRECTORY_SEPARATOR . 'audio';
$videoDir = $uploadRoot . DIRECTORY_SEPARATOR . 'video';
$logoDir = $uploadRoot . DIRECTORY_SEPARATOR . 'logo';
$settingsFile = $dataDir . DIRECTORY_SEPARATOR . 'settings.json';
$tracksFile = $dataDir . DIRECTORY_SEPARATOR . 'tracks.json';

foreach ([$dataDir, $assetsDir, $uploadRoot, $audioDir, $videoDir, $logoDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}
$defaultSettings = [
    'background' => '',
    'square_size' => 52,
    'logo_size' => 58,
    'author_size' => 28,
    'player_title' => '',
    'player_subtitle' => '',
    'player_title_size' => 64,
    'player_subtitle_size' => 30,
    'theme' => 'synthwave',
    'equalizer_opacity' => 85,
];
if (!is_file($settingsFile)) file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
if (!is_file($tracksFile)) file_put_contents($tracksFile, "[]\n");

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function readJson(string $file, array $fallback): array {
    $raw = @file_get_contents($file);
    if ($raw === false) return $fallback;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}
function writeJson(string $file, array $data): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save configuration.');
    }
}
function assetVersion(string $path): string { return is_file($path) ? (string) filemtime($path) : '0'; }
function randomId(): string { return bin2hex(random_bytes(6)); }
function extensionForUpload(array $file, array $allowed): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed.');
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) throw new RuntimeException('File is empty or too large.');
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) throw new RuntimeException('Unsupported file format.');
    return $ext;
}
function validateBackgroundImage(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed.');
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) throw new RuntimeException('File is empty or too large.');
    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) throw new RuntimeException('Invalid upload source.');
    $info = @getimagesize($tmpPath);
    if ($info === false || empty($info[0]) || empty($info[1])) throw new RuntimeException('The background must be a valid image.');
    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width > 12000 || $height > 12000 || ($width * $height) > 50000000) throw new RuntimeException('The background is too large. Maximum: 50 megapixels.');
    $type = (int)($info[2] ?? 0);
    if (!in_array($type, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) throw new RuntimeException('Unsupported image format. Use PNG, JPG, WEBP or GIF.');
    return $tmpPath;
}
function replaceFile(string $source, string $target): void {
    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Unable to create destination folder.');
    $staged = $target . '.incoming-' . bin2hex(random_bytes(5));
    if (!rename($source, $staged)) throw new RuntimeException('Unable to stage the uploaded file.');
    if (is_file($target) && !@unlink($target)) { @unlink($staged); throw new RuntimeException('Unable to replace the previous background.'); }
    if (!rename($staged, $target)) { @unlink($staged); throw new RuntimeException('Unable to finalize the background upload.'); }
    @chmod($target, 0664);
    clearstatcache(true, $target);
}
function saveBackgroundAsPng(array $file, string $target): string {
    $tmpPath = validateBackgroundImage($file);
    $tmpTarget = $target . '.tmp-' . bin2hex(random_bytes(5));
    $written = false;
    if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
        $raw = @file_get_contents($tmpPath);
        $image = $raw !== false ? @imagecreatefromstring($raw) : false;
        if ($image !== false) {
            if (function_exists('imagealphablending')) @imagealphablending($image, false);
            if (function_exists('imagesavealpha')) @imagesavealpha($image, true);
            $written = @imagepng($image, $tmpTarget);
            @imagedestroy($image);
        }
    }
    if (!$written) {
        $info = @getimagesize($tmpPath);
        $type = (int)($info[2] ?? 0);
        if ($type !== IMAGETYPE_PNG) {
            throw new RuntimeException('This server cannot convert this image automatically. Please upload a PNG image.');
        }
        if (!move_uploaded_file($tmpPath, $tmpTarget)) throw new RuntimeException('Unable to store the uploaded file.');
    }
    replaceFile($tmpTarget, $target);
    return 'assets/background.png';
}
function removeBackgroundFiles(string $root, string $fixedPath, string $relative): void {
    removeRelative($root, $relative);
    if (is_file($fixedPath)) @unlink($fixedPath);
}
function purgeTrackVariants(string $dir, string $baseName, ?string $except = null): void {
    $safeBase = preg_replace('/[^a-z0-9_-]/i', '-', $baseName) ?: 'file';
    foreach (glob($dir . DIRECTORY_SEPARATOR . $safeBase . '.*') ?: [] as $old) {
        if ($except !== null && realpath($old) === realpath($except)) continue;
        if (is_file($old) && !@unlink($old)) {
            throw new RuntimeException('Unable to remove the previous file version. Check file permissions.');
        }
    }
}
function saveUpload(array $file, string $dir, string $baseName, array $allowed): string {
    $ext = extensionForUpload($file, $allowed);
    $safeBase = preg_replace('/[^a-z0-9_-]/i', '-', $baseName) ?: 'file';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create upload folder.');
    }

    // Stage the new upload first. The current file remains intact if the upload fails.
    $staged = $dir . DIRECTORY_SEPARATOR . '.incoming-' . $safeBase . '-' . bin2hex(random_bytes(5));
    if (!move_uploaded_file((string)$file['tmp_name'], $staged)) {
        throw new RuntimeException('Unable to store uploaded file.');
    }

    $dest = $dir . DIRECTORY_SEPARATOR . $safeBase . '.' . $ext;
    try {
        // One track = one current file per media type. Remove every older extension/version.
        purgeTrackVariants($dir, $safeBase);
        if (!rename($staged, $dest)) {
            throw new RuntimeException('Unable to install the new file.');
        }
        @chmod($dest, 0664);
        clearstatcache(true, $dest);
    } catch (Throwable $e) {
        @unlink($staged);
        throw $e;
    }

    return 'assets/uploads/' . basename($dir) . '/' . basename($dest);
}
function purgeTrackMedia(string $id, string $audioDir, string $videoDir, string $logoDir): void {
    purgeTrackVariants($audioDir, $id);
    purgeTrackVariants($videoDir, $id);
    purgeTrackVariants($logoDir, $id);
}
function removeRelative(string $root, string $relative): void {
    if ($relative === '') return;
    $full = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    $allowed = realpath($root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads');
    if ($full && $allowed && str_starts_with($full, $allowed) && is_file($full)) @unlink($full);
}
function trackIndexById(array $tracks, string $id): int {
    foreach ($tracks as $i => $track) if (($track['id'] ?? '') === $id) return (int)$i;
    return -1;
}

$themesFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'twitch-overlay' . DIRECTORY_SEPARATOR . 'themes.php';
$themes = is_file($themesFile) ? require $themesFile : [
    'synthwave' => ['label' => 'Miami Synthwave', 'group' => 'Signature Waves', 'colors' => ['#ff2bd6','#19f7ff','#8b3dff']],
];
$settings = array_merge($defaultSettings, readJson($settingsFile, []));
if (($settings['background'] ?? '') === '' && is_file($fixedBackgroundPath)) {
    $settings['background'] = 'assets/background.png';
}
if (!isset($themes[$settings['theme']])) $settings['theme'] = 'synthwave';
$tracks = readJson($tracksFile, []);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_background') {
            if (isset($_FILES['background']) && ($_FILES['background']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                removeBackgroundFiles($root, $fixedBackgroundPath, (string)($settings['background'] ?? ''));
                $settings['background'] = saveBackgroundAsPng($_FILES['background'], $fixedBackgroundPath);
                writeJson($settingsFile, $settings);
                $message = 'Background updated as assets/background.png.';
            } else {
                throw new RuntimeException('Choose an image first.');
            }
        } elseif ($action === 'remove_background') {
            removeBackgroundFiles($root, $fixedBackgroundPath, (string)($settings['background'] ?? ''));
            $settings['background'] = '';
            writeJson($settingsFile, $settings);
            $message = 'Background removed.';
        } elseif ($action === 'save_appearance') {
            $squareSize = max(25, min(80, (int)($_POST['square_size'] ?? 52)));
            $logoSize = max(15, min(90, (int)($_POST['logo_size'] ?? 58)));
            $authorSize = max(12, min(64, (int)($_POST['author_size'] ?? 28)));
            $playerTitle = trim((string)($_POST['player_title'] ?? ''));
            $playerSubtitle = trim((string)($_POST['player_subtitle'] ?? ''));
            $playerTitleSize = max(24, min(120, (int)($_POST['player_title_size'] ?? 64)));
            $playerSubtitleSize = max(14, min(72, (int)($_POST['player_subtitle_size'] ?? 30)));
            if (function_exists('mb_substr')) {
                $playerTitle = mb_substr($playerTitle, 0, 120);
                $playerSubtitle = mb_substr($playerSubtitle, 0, 180);
            } else {
                $playerTitle = substr($playerTitle, 0, 120);
                $playerSubtitle = substr($playerSubtitle, 0, 180);
            }
            $opacity = max(0, min(100, (int)($_POST['equalizer_opacity'] ?? 85)));
            $theme = (string)($_POST['theme'] ?? 'synthwave');
            if (!isset($themes[$theme])) $theme = 'synthwave';
            $settings['square_size'] = $squareSize;
            $settings['logo_size'] = $logoSize;
            $settings['author_size'] = $authorSize;
            $settings['player_title'] = $playerTitle;
            $settings['player_subtitle'] = $playerSubtitle;
            $settings['player_title_size'] = $playerTitleSize;
            $settings['player_subtitle_size'] = $playerSubtitleSize;
            $settings['theme'] = $theme;
            $settings['equalizer_opacity'] = $opacity;
            writeJson($settingsFile, $settings);
            $message = 'Player appearance updated.';
        } elseif ($action === 'create_track') {
            $title = trim((string)($_POST['title'] ?? ''));
            $author = trim((string)($_POST['author'] ?? ''));
            if ($title === '' || $author === '') throw new RuntimeException('Title and author are required.');
            if (!isset($_FILES['audio']) || ($_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose an audio file.');
            if (!isset($_FILES['video']) || ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a video file.');
            if (!isset($_FILES['title_logo']) || ($_FILES['title_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a title logo image.');
            $id = randomId();
            $audio = saveUpload($_FILES['audio'], $audioDir, $id, ['mp3','wav','ogg','m4a','aac']);
            $video = saveUpload($_FILES['video'], $videoDir, $id, ['mp4','webm','mov']);
            $logo = saveUpload($_FILES['title_logo'], $logoDir, $id, ['jpg','jpeg','png','webp','gif']);
            $tracks[] = ['id'=>$id,'title'=>$title,'author'=>$author,'audio'=>$audio,'video'=>$video,'logo'=>$logo,'created_at'=>date(DATE_ATOM)];
            writeJson($tracksFile, $tracks);
            $message = 'Music added.';
        } elseif ($action === 'update_track') {
            $id = (string)($_POST['id'] ?? '');
            $index = trackIndexById($tracks, $id);
            if ($index < 0) throw new RuntimeException('Music not found.');
            $title = trim((string)($_POST['title'] ?? ''));
            $author = trim((string)($_POST['author'] ?? ''));
            if ($title === '' || $author === '') throw new RuntimeException('Title and author are required.');
            $tracks[$index]['title'] = $title;
            $tracks[$index]['author'] = $author;
            if (isset($_FILES['audio']) && ($_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $tracks[$index]['audio'] = saveUpload($_FILES['audio'], $audioDir, $id, ['mp3','wav','ogg','m4a','aac']);
            }
            if (isset($_FILES['video']) && ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $tracks[$index]['video'] = saveUpload($_FILES['video'], $videoDir, $id, ['mp4','webm','mov']);
            }
            if (isset($_FILES['title_logo']) && ($_FILES['title_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $tracks[$index]['logo'] = saveUpload($_FILES['title_logo'], $logoDir, $id, ['jpg','jpeg','png','webp','gif']);
            }
            $tracks[$index]['updated_at'] = date(DATE_ATOM);
            writeJson($tracksFile, $tracks);
            $message = 'Music updated.';
        } elseif ($action === 'save_order') {
            $orderRaw = (string)($_POST['order'] ?? '');
            $order = json_decode($orderRaw, true);
            if (!is_array($order)) throw new RuntimeException('Invalid playlist order.');

            $byId = [];
            foreach ($tracks as $track) {
                $trackId = (string)($track['id'] ?? '');
                if ($trackId !== '') $byId[$trackId] = $track;
            }

            $reordered = [];
            $seen = [];
            foreach ($order as $trackId) {
                if (!is_string($trackId) || !isset($byId[$trackId]) || isset($seen[$trackId])) continue;
                $reordered[] = $byId[$trackId];
                $seen[$trackId] = true;
            }
            // Keep any tracks not present in the submitted list instead of losing data.
            foreach ($tracks as $track) {
                $trackId = (string)($track['id'] ?? '');
                if ($trackId !== '' && !isset($seen[$trackId])) $reordered[] = $track;
            }
            $tracks = $reordered;
            writeJson($tracksFile, $tracks);
            $message = 'Playlist order updated.';
        } elseif ($action === 'move_track') {
            $id = (string)($_POST['id'] ?? '');
            $direction = (string)($_POST['direction'] ?? '');
            $index = trackIndexById($tracks, $id);
            if ($index < 0) throw new RuntimeException('Music not found.');
            $target = $direction === 'up' ? $index - 1 : ($direction === 'down' ? $index + 1 : $index);
            if ($target >= 0 && $target < count($tracks) && $target !== $index) {
                $tmp = $tracks[$index];
                $tracks[$index] = $tracks[$target];
                $tracks[$target] = $tmp;
                writeJson($tracksFile, $tracks);
                $message = 'Playlist order updated.';
            }
        } elseif ($action === 'delete_track') {
            $id = (string)($_POST['id'] ?? '');
            $index = trackIndexById($tracks, $id);
            if ($index < 0) throw new RuntimeException('Music not found.');
            // Delete every stored version for this track, including leftovers with an older extension.
            purgeTrackMedia($id, $audioDir, $videoDir, $logoDir);
            array_splice($tracks, $index, 1);
            writeJson($tracksFile, $tracks);
            $message = 'Music deleted.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $settings = array_merge($defaultSettings, readJson($settingsFile, []));
    if (!isset($themes[$settings['theme']])) $settings['theme'] = 'synthwave';
    $tracks = readJson($tracksFile, []);
}

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$playerRelative = $basePath . '/index.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NeonCast — Music Equalizer</title>
<style>
:root{--bg:#05000f;--panel:#110323;--panel2:#17062f;--text:#faf8ff;--muted:#bec4df;--pink:#ff2bd6;--cyan:#19f7ff;--violet:#8b3dff;--line:rgba(25,247,255,.28)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%}body{font-family:Inter,"Segoe UI",Arial,sans-serif;color:var(--text);background:radial-gradient(circle at 18% 5%,rgba(255,43,214,.18),transparent 32%),radial-gradient(circle at 90% 10%,rgba(25,247,255,.13),transparent 30%),linear-gradient(145deg,#030008,#0a001b 45%,#210043);padding:28px 16px 44px}.wrap{width:min(1180px,100%);margin:auto}.head,.card{border:1px solid var(--line);background:linear-gradient(145deg,rgba(8,2,27,.95),rgba(22,4,48,.92));border-radius:22px;box-shadow:0 18px 60px rgba(0,0,0,.35),inset 0 0 30px rgba(25,247,255,.025)}.head{padding:26px 28px;margin-bottom:20px}.brand{display:flex;align-items:center;justify-content:flex-start;margin-bottom:8px}.brand img{display:block;width:min(420px,100%);height:auto;filter:drop-shadow(0 0 20px rgba(255,43,214,.25)) drop-shadow(0 0 26px rgba(25,247,255,.16))}.head h1{margin:10px 0 6px;font-size:24px}.lead{margin:0;color:var(--muted);line-height:1.55}.links{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}.links a{color:var(--text);text-decoration:none;border:1px solid rgba(255,43,214,.42);background:rgba(255,43,214,.08);padding:10px 14px;border-radius:10px;font-weight:800}.links a:hover{border-color:var(--cyan)}.notice{padding:12px 14px;border-radius:12px;margin-bottom:16px;font-weight:800}.notice.ok{border:1px solid rgba(80,255,180,.35);background:rgba(80,255,180,.08);color:#91ffd0}.notice.err{border:1px solid rgba(255,95,135,.42);background:rgba(255,95,135,.09);color:#ff9db9}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.card{padding:22px;margin-bottom:20px}.card h2{margin:0 0 6px;font-size:22px}.card .desc{margin:0 0 18px;color:var(--muted);font-size:14px;line-height:1.55}.field{display:grid;gap:7px;margin-bottom:14px}.field label{font-size:12px;color:#e7e9fb;font-weight:800;letter-spacing:.03em}.field input[type=text],.field input[type=file],.field select{width:100%;padding:12px 13px;border-radius:11px;border:1px solid rgba(25,247,255,.24);background:#080016;color:var(--text)}.field input[type=range]{width:100%;accent-color:var(--cyan)}.range-line{display:flex;align-items:center;gap:12px}.range-line input{flex:1}.range-value{min-width:72px;text-align:right;color:var(--cyan);font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-weight:800}.theme-swatches{display:flex;gap:6px;margin-top:7px}.theme-swatch{width:22px;height:22px;border-radius:50%;border:1px solid rgba(255,255,255,.45);box-shadow:0 0 10px currentColor}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{appearance:none;border:0;border-radius:11px;min-height:43px;padding:10px 15px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;color:#05000e;background:linear-gradient(90deg,var(--pink),var(--cyan))}.btn.secondary{background:rgba(25,247,255,.08);color:var(--cyan);border:1px solid rgba(25,247,255,.32)}.btn.danger{background:rgba(255,70,115,.11);color:#ff8baa;border:1px solid rgba(255,70,115,.35)}.preview{border-radius:14px;overflow:hidden;border:1px solid rgba(255,43,214,.26);background:#05000d;aspect-ratio:16/7;margin-bottom:14px;display:grid;place-items:center;color:#777}.preview img{width:100%;height:100%;object-fit:cover}.tracks{display:grid;gap:9px}.track{border:1px solid rgba(255,43,214,.22);background:rgba(5,0,16,.62);border-radius:14px;padding:10px 12px;transition:border-color .15s,background .15s,transform .15s,opacity .15s}.track.dragging{opacity:.48;transform:scale(.995);border-color:var(--cyan)}.track.drop-target{border-color:var(--pink);background:rgba(255,43,214,.07)}.track-summary{display:grid;grid-template-columns:auto 64px minmax(0,1fr) auto auto;gap:11px;align-items:center}.drag-handle{width:34px;height:42px;border-radius:9px;border:1px solid rgba(25,247,255,.28);background:rgba(25,247,255,.07);color:var(--cyan);font-size:20px;line-height:1;cursor:grab;display:grid;place-items:center;user-select:none}.drag-handle:active{cursor:grabbing}.track-thumb{width:64px;height:48px;border-radius:8px;border:1px solid rgba(255,43,214,.2);background:#05000d;display:grid;place-items:center;overflow:hidden;color:#727994;font-size:18px}.track-thumb img{width:100%;height:100%;object-fit:contain;padding:4px}.track-info{min-width:0}.track-title{font-size:15px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.track-author{font-size:12px;color:var(--muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pill{font-size:11px;color:var(--cyan);border:1px solid rgba(25,247,255,.28);padding:5px 8px;border-radius:999px;white-space:nowrap}.edit-toggle{min-height:34px!important;padding:7px 10px!important;font-size:12px!important;white-space:nowrap}.edit-panel{margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.08)}.edit-panel[hidden]{display:none}.compact-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 10px}.compact-grid .field{margin-bottom:0;gap:5px}.compact-grid .field label{font-size:11px}.compact-grid .field input[type=text],.compact-grid .field input[type=file]{padding:8px 9px;border-radius:9px;font-size:12px}.compact-actions{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:9px}.compact-actions .btn{min-height:34px;padding:7px 11px;font-size:12px}.copyrow{display:flex;gap:8px;margin:12px 0}.linkbox{flex:1;min-width:0;padding:10px;border:1px solid rgba(25,247,255,.22);background:#060011;color:#aeb7db;border-radius:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px}.muted{color:var(--muted);font-size:12px}.empty{padding:26px;text-align:center;color:var(--muted);border:1px dashed rgba(25,247,255,.25);border-radius:14px}.footer{color:#888fae;text-align:center;font-size:12px;margin-top:16px}@media(max-width:850px){.grid,.compact-grid{grid-template-columns:1fr}.track-summary{grid-template-columns:auto 52px minmax(0,1fr) auto auto}.track-thumb{width:52px;height:42px}.pill{font-size:10px}.edit-toggle{padding:7px 8px!important}}
</style>
</head>
<body>
<main class="wrap">
  <header class="head">
    <div class="brand"><img src="assets/config-logo.png?v=<?= h(assetVersion(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'config-logo.png')) ?>" alt="NeonCast"></div>
    <h1>Music Equalizer configuration</h1>
    <p class="lead">Manage the background and your ordered music playlist for OBS. Each track can have its own audio, square video, title logo and author, and the player automatically follows the order defined below.</p>
    <?php if ($message !== ''): ?><div class="notice ok"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?= h($error) ?></div><?php endif; ?>
    <div class="links"><a href="../../index.php">Back to NeonCast Tools</a></div>
  </header>

  <section class="card">
    <h2>OBS playlist player</h2>
    <p class="desc">One link plays all <?= count($tracks) ?> saved music track<?= count($tracks) === 1 ? '' : 's' ?> in the playlist order below. After the last track, playback returns to the first track automatically.</p>
    <div class="copyrow"><div class="linkbox"><?= h($playerRelative) ?></div><button class="btn secondary copy" type="button" data-relative="<?= h($playerRelative) ?>">Copy player link</button><a class="btn secondary" href="<?= h($playerRelative) ?>" target="_blank" rel="noopener">Open player</a></div>
  </section>

  <section class="card">
    <h2>Player appearance</h2>
    <p class="desc">These settings apply to the whole playlist. The player title and subtitle are centered above the square video. The song logo and author remain inside the bottom of the square video.</p>
    <form method="post">
      <input type="hidden" name="action" value="save_appearance">
      <div class="grid">
        <div class="field">
          <label for="player-title">Player title</label>
          <input id="player-title" type="text" name="player_title" maxlength="120" value="<?= h((string)$settings['player_title']) ?>" placeholder="Example: Now Playing">
        </div>
        <div class="field">
          <label for="player-subtitle">Player subtitle</label>
          <input id="player-subtitle" type="text" name="player_subtitle" maxlength="180" value="<?= h((string)$settings['player_subtitle']) ?>" placeholder="Optional subtitle">
        </div>
        <div class="field">
          <label for="player-title-size">Player title text size</label>
          <div class="range-line"><input id="player-title-size" type="range" name="player_title_size" min="24" max="120" step="1" value="<?= (int)$settings['player_title_size'] ?>" data-unit=" px"><span class="range-value"><?= (int)$settings['player_title_size'] ?> px</span></div>
        </div>
        <div class="field">
          <label for="player-subtitle-size">Player subtitle text size</label>
          <div class="range-line"><input id="player-subtitle-size" type="range" name="player_subtitle_size" min="14" max="72" step="1" value="<?= (int)$settings['player_subtitle_size'] ?>" data-unit=" px"><span class="range-value"><?= (int)$settings['player_subtitle_size'] ?> px</span></div>
        </div>
        <div class="field">
          <label for="square-size">Square area size</label>
          <div class="range-line"><input id="square-size" type="range" name="square_size" min="25" max="80" step="1" value="<?= (int)$settings['square_size'] ?>" data-unit="%"><span class="range-value"><?= (int)$settings['square_size'] ?>%</span></div>
        </div>
        <div class="field">
          <label for="logo-size">Common title logo size</label>
          <div class="range-line"><input id="logo-size" type="range" name="logo_size" min="15" max="90" step="1" value="<?= (int)$settings['logo_size'] ?>" data-unit="%"><span class="range-value"><?= (int)$settings['logo_size'] ?>%</span></div>
        </div>
        <div class="field">
          <label for="author-size">Common author text size</label>
          <div class="range-line"><input id="author-size" type="range" name="author_size" min="12" max="64" step="1" value="<?= (int)$settings['author_size'] ?>" data-unit=" px"><span class="range-value"><?= (int)$settings['author_size'] ?> px</span></div>
        </div>
        <div class="field">
          <label for="eq-opacity">Equalizer opacity</label>
          <div class="range-line"><input id="eq-opacity" type="range" name="equalizer_opacity" min="0" max="100" step="1" value="<?= (int)$settings['equalizer_opacity'] ?>" data-unit="%"><span class="range-value"><?= (int)$settings['equalizer_opacity'] ?>%</span></div>
        </div>
        <div class="field">
          <label for="theme">Equalizer color theme</label>
          <select id="theme" name="theme">
            <?php $currentGroup=''; foreach ($themes as $themeId => $themeData): $group=(string)($themeData['group'] ?? 'Themes'); if ($group !== $currentGroup): if ($currentGroup !== '') echo '</optgroup>'; $currentGroup=$group; ?><optgroup label="<?= h($group) ?>"><?php endif; ?>
              <option value="<?= h((string)$themeId) ?>"<?= $settings['theme'] === $themeId ? ' selected' : '' ?>><?= h((string)($themeData['label'] ?? $themeId)) ?></option>
            <?php endforeach; if ($currentGroup !== '') echo '</optgroup>'; ?>
          </select>
          <?php $activeTheme=$themes[$settings['theme']] ?? reset($themes); ?><div class="theme-swatches" id="theme-swatches"><?php foreach (($activeTheme['colors'] ?? []) as $c): ?><span class="theme-swatch" style="background:<?= h((string)$c) ?>;color:<?= h((string)$c) ?>"></span><?php endforeach; ?></div>
        </div>
      </div>
      <div class="actions"><button class="btn" type="submit">Save appearance</button></div>
    </form>
  </section>

  <section class="grid">
    <article class="card">
      <h2>Background</h2>
      <p class="desc">Upload the image used behind the square video and equalizer. A new upload replaces the previous one.</p>
      <div class="preview">
        <?php $currentBackground = (($settings['background'] ?? '') !== '' ? (string)$settings['background'] : (is_file($fixedBackgroundPath) ? 'assets/background.png' : '')); ?>
        <?php if ($currentBackground !== ''): ?>
          <img src="<?= h($currentBackground) ?>?v=<?= h(assetVersion($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentBackground))) ?>" alt="Current background">
        <?php else: ?>No background selected<?php endif; ?>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_background">
        <div class="field"><label for="background">Background image</label><input id="background" type="file" name="background" accept="image/png,image/jpeg,image/webp,image/gif" required></div><div class="muted">The uploaded image is always installed as <strong>assets/background.png</strong>.</div>
        <div class="actions"><button class="btn" type="submit">Replace background</button></div>
      </form>
      <?php if ($currentBackground !== ''): ?>
      <form method="post" style="margin-top:10px" onsubmit="return confirm('Remove the current background?')"><input type="hidden" name="action" value="remove_background"><button class="btn danger" type="submit">Remove background</button></form>
      <?php endif; ?>
    </article>

    <article class="card">
      <h2>Add music</h2>
      <p class="desc">Add a new track to the playlist. Its square video is shown while its audio drives the equalizer, and its title is displayed as an image logo.</p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_track">
        <div class="field"><label for="new-title">Track name <span class="muted">(configuration only)</span></label><input id="new-title" type="text" name="title" required></div>
        <div class="field"><label for="new-author">Author</label><input id="new-author" type="text" name="author" required></div>
        <div class="field"><label for="new-audio">Music file</label><input id="new-audio" type="file" name="audio" accept="audio/*,.m4a,.aac" required></div>
        <div class="field"><label for="new-video">Square video</label><input id="new-video" type="file" name="video" accept="video/mp4,video/webm,video/quicktime" required></div>
        <div class="field"><label for="new-logo">Title logo</label><input id="new-logo" type="file" name="title_logo" accept="image/png,image/jpeg,image/webp,image/gif" required></div>
        <button class="btn" type="submit">Add music</button>
      </form>
    </article>
  </section>

  <section class="card">
    <h2>Music library</h2>
    <p class="desc">Drag tracks with the grip to change playback order. Open <strong>Edit music</strong> only when you need to change a track, keeping the playlist compact. Empty replacement fields keep the existing files.</p>
    <form id="playlist-order-form" method="post" hidden><input type="hidden" name="action" value="save_order"><input type="hidden" id="playlist-order" name="order" value=""></form>
    <div class="tracks">
      <?php if (!$tracks): ?><div class="empty">No music has been added yet.</div><?php endif; ?>
      <?php foreach ($tracks as $position => $track): $id=(string)($track['id']??''); ?>
      <article class="track" data-track-id="<?= h($id) ?>">
        <div class="track-summary">
          <button class="drag-handle" type="button" draggable="true" title="Drag to reorder" aria-label="Drag <?= h((string)($track['title']??'music')) ?> to reorder">⋮⋮</button>
          <div class="track-thumb">
            <?php if (($track['logo'] ?? '') !== ''): ?><img src="<?= h((string)$track['logo']) ?>?v=<?= h(assetVersion($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$track['logo']))) ?>" alt="Title logo"><?php else: ?>♪<?php endif; ?>
          </div>
          <div class="track-info">
            <div class="track-title"><?= h((string)($track['title']??'')) ?></div>
            <div class="track-author"><?= h((string)($track['author']??'')) ?></div>
          </div>
          <span class="pill playlist-position">#<?= (int)$position + 1 ?></span>
          <button class="btn secondary edit-toggle" type="button" aria-expanded="false">Edit music</button>
        </div>
        <div class="edit-panel" hidden>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_track"><input type="hidden" name="id" value="<?= h($id) ?>">
            <div class="muted" style="margin-bottom:8px">Uploading a replacement audio, video or title logo deletes the previous file automatically.</div><div class="compact-grid">
              <div class="field"><label>Track name <span class="muted">(configuration only)</span></label><input type="text" name="title" value="<?= h((string)($track['title']??'')) ?>" required></div>
              <div class="field"><label>Author</label><input type="text" name="author" value="<?= h((string)($track['author']??'')) ?>" required></div>
              <div class="field"><label>Replace music <span class="muted">(optional)</span></label><input type="file" name="audio" accept="audio/*,.m4a,.aac"></div>
              <div class="field"><label>Replace square video <span class="muted">(optional)</span></label><input type="file" name="video" accept="video/mp4,video/webm,video/quicktime"></div>
              <div class="field"><label>Replace title logo <span class="muted">(optional)</span></label><input type="file" name="title_logo" accept="image/png,image/jpeg,image/webp,image/gif"></div>
            </div>
            <div class="compact-actions"><button class="btn" type="submit">Save changes</button></div>
          </form>
          <form method="post" class="compact-actions" onsubmit="return confirm('Delete this music and its files?')"><input type="hidden" name="action" value="delete_track"><input type="hidden" name="id" value="<?= h($id) ?>"><button class="btn danger" type="submit">Delete music</button></form>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <div class="footer">NeonCast · Music Equalizer</div>
</main>
<script>
for(const btn of document.querySelectorAll('.copy')){
  btn.addEventListener('click',async()=>{
    const url=new URL(btn.dataset.relative,window.location.href).href;
    try{await navigator.clipboard.writeText(url);const old=btn.textContent;btn.textContent='Copied!';setTimeout(()=>btn.textContent=old,1200)}catch(e){window.prompt('Copy this link:',url)}
  });
}
for(const slider of document.querySelectorAll('input[type="range"][data-unit]')){
  const output=slider.parentElement.querySelector('.range-value');
  const sync=()=>{if(output)output.textContent=slider.value+slider.dataset.unit};
  slider.addEventListener('input',sync);sync();
}
const themeCatalog=<?= json_encode($themes, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const themeSelect=document.getElementById('theme');
const themeSwatches=document.getElementById('theme-swatches');
function paintThemeSwatches(){
  if(!themeSelect||!themeSwatches)return;
  const colors=(themeCatalog[themeSelect.value]?.colors)||[];
  themeSwatches.innerHTML='';
  for(const color of colors){const dot=document.createElement('span');dot.className='theme-swatch';dot.style.background=color;dot.style.color=color;themeSwatches.appendChild(dot)}
}
if(themeSelect){themeSelect.addEventListener('change',paintThemeSwatches);paintThemeSwatches()}

for(const toggle of document.querySelectorAll('.edit-toggle')){
  toggle.addEventListener('click',()=>{
    const card=toggle.closest('.track');
    const panel=card?.querySelector('.edit-panel');
    if(!panel)return;
    const opening=panel.hasAttribute('hidden');
    if(opening)panel.removeAttribute('hidden');else panel.setAttribute('hidden','');
    toggle.setAttribute('aria-expanded',opening?'true':'false');
    toggle.textContent=opening?'Close':'Edit music';
  });
}

const playlistBox=document.querySelector('.tracks');
const orderForm=document.getElementById('playlist-order-form');
const orderInput=document.getElementById('playlist-order');
let draggedTrack=null;
let originalOrder='';
function playlistIds(){return [...document.querySelectorAll('.track[data-track-id]')].map(el=>el.dataset.trackId)}
function refreshPositions(){document.querySelectorAll('.track[data-track-id] .playlist-position').forEach((pill,index)=>pill.textContent='#'+(index+1))}
function afterElement(container,y){
  const items=[...container.querySelectorAll('.track[data-track-id]:not(.dragging)')];
  return items.reduce((closest,child)=>{const box=child.getBoundingClientRect();const offset=y-box.top-box.height/2;return offset<0&&offset>closest.offset?{offset,element:child}:closest},{offset:Number.NEGATIVE_INFINITY,element:null}).element;
}
if(playlistBox&&orderForm&&orderInput){
  for(const handle of playlistBox.querySelectorAll('.drag-handle')){
    handle.addEventListener('dragstart',e=>{
      draggedTrack=handle.closest('.track[data-track-id]');
      if(!draggedTrack)return;
      originalOrder=playlistIds().join('|');
      draggedTrack.classList.add('dragging');
      e.dataTransfer.effectAllowed='move';
      e.dataTransfer.setData('text/plain',draggedTrack.dataset.trackId||'');
    });
    handle.addEventListener('dragend',()=>{
      if(draggedTrack)draggedTrack.classList.remove('dragging');
      playlistBox.querySelectorAll('.drop-target').forEach(el=>el.classList.remove('drop-target'));
      const ids=playlistIds();
      refreshPositions();
      if(draggedTrack&&ids.join('|')!==originalOrder){
        orderInput.value=JSON.stringify(ids);
        orderForm.requestSubmit();
      }
      draggedTrack=null;
    });
  }
  playlistBox.addEventListener('dragover',e=>{
    if(!draggedTrack)return;
    e.preventDefault();
    e.dataTransfer.dropEffect='move';
    const after=afterElement(playlistBox,e.clientY);
    playlistBox.querySelectorAll('.drop-target').forEach(el=>el.classList.remove('drop-target'));
    if(after){playlistBox.insertBefore(draggedTrack,after);after.classList.add('drop-target')}else{playlistBox.appendChild(draggedTrack)}
    refreshPositions();
  });
  playlistBox.addEventListener('drop',e=>{if(draggedTrack)e.preventDefault()});
}
</script>
</body>
</html>
