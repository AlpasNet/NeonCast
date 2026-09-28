<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$assetsDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
$coverDir = __DIR__ . DIRECTORY_SEPARATOR . 'cover';
$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$gamesFile = $dataDir . DIRECTORY_SEPARATOR . 'games.json';
$profileFile = $dataDir . DIRECTORY_SEPARATOR . 'profile.json';

$targets = [
    'avatar' => [
        'label' => 'Avatar',
        'filename' => 'avatar.jpg',
        'path' => $assetsDir . DIRECTORY_SEPARATOR . 'avatar.jpg',
        'accept' => 'image/jpeg,.jpg,.jpeg',
        'max' => 15 * 1024 * 1024,
        'kind' => 'image-jpeg',
        'help' => 'JPEG/JPG only. Replaces assets/avatar.jpg while keeping the exact filename.',
    ],
    'background' => [
        'label' => 'Background',
        'filename' => 'background.png',
        'path' => $assetsDir . DIRECTORY_SEPARATOR . 'background.png',
        'accept' => 'image/png,.png',
        'max' => 30 * 1024 * 1024,
        'kind' => 'image-png',
        'help' => 'PNG only. Replaces assets/background.png while keeping the exact filename.',
    ],
    'video' => [
        'label' => 'Chat video',
        'filename' => 'twitch.mp4',
        'path' => $assetsDir . DIRECTORY_SEPARATOR . 'twitch.mp4',
        'accept' => 'video/mp4,.mp4',
        'max' => 250 * 1024 * 1024,
        'kind' => 'video-mp4',
        'help' => 'MP4 only. Saved as assets/twitch.mp4 and used as the Twitch chat background.',
    ],
];

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function iniBytes(string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $last = strtolower($value[strlen($value) - 1]);
    $number = (float)$value;
    return match ($last) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function uploadErrorMessage(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE => 'The file exceeds the form size limit.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted before completion.',
        UPLOAD_ERR_NO_FILE => 'No file selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
        default => 'Unknown upload error.',
    };
}

function ensureDirectory(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create directory: ' . basename($dir));
    }
}

function safeReplace(string $source, string $target): bool {
    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $tmpTarget = $target . '.upload-' . bin2hex(random_bytes(5));
    if (!rename($source, $tmpTarget)) {
        return false;
    }

    if (is_file($target) && !unlink($target)) {
        @unlink($tmpTarget);
        return false;
    }

    if (!rename($tmpTarget, $target)) {
        @unlink($tmpTarget);
        return false;
    }

    @chmod($target, 0664);
    clearstatcache(true, $target);
    return true;
}

function validateImage(string $tmpPath, int $expectedType, string $label): void {
    $info = @getimagesize($tmpPath);
    if ($info === false || empty($info[0]) || empty($info[1])) {
        throw new RuntimeException("The uploaded {$label} is not a valid image.");
    }

    $width = (int)$info[0];
    $height = (int)$info[1];
    $type = (int)($info[2] ?? 0);
    if ($type !== $expectedType) {
        $wanted = $expectedType === IMAGETYPE_JPEG ? 'JPEG/JPG' : 'PNG';
        throw new RuntimeException("The {$label} must be a {$wanted} file.");
    }

    if ($width > 12000 || $height > 12000 || ($width * $height) > 50000000) {
        throw new RuntimeException("The {$label} is too large. Maximum: 50 megapixels.");
    }
}

function installUploadedFile(string $tmpPath, string $target): void {
    $staged = $target . '.incoming-' . bin2hex(random_bytes(5));
    if (!move_uploaded_file($tmpPath, $staged)) {
        throw new RuntimeException('Unable to store the uploaded file.');
    }
    if (!safeReplace($staged, $target)) {
        @unlink($staged);
        throw new RuntimeException('Unable to replace the current file. Check file permissions.');
    }
}

function looksLikeMp4(string $tmpPath): bool {
    $handle = @fopen($tmpPath, 'rb');
    if ($handle === false) return false;
    $head = fread($handle, 64);
    fclose($handle);
    return is_string($head) && strlen($head) >= 12 && strpos($head, 'ftyp') !== false;
}

function processUpload(string $field, array $spec): string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        throw new RuntimeException('Upload data is missing.');
    }

    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(uploadErrorMessage($error));
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid upload source.');
    }
    if ($size <= 0) {
        throw new RuntimeException('The uploaded file is empty.');
    }
    if ($size > (int)$spec['max']) {
        $maxMb = round(((int)$spec['max']) / 1024 / 1024);
        throw new RuntimeException("The file is too large. Maximum: {$maxMb} MB.");
    }

    if ($spec['kind'] === 'image-jpeg') {
        validateImage($tmpPath, IMAGETYPE_JPEG, 'avatar');
        installUploadedFile($tmpPath, (string)$spec['path']);
        return 'Avatar updated successfully.';
    }

    if ($spec['kind'] === 'image-png') {
        validateImage($tmpPath, IMAGETYPE_PNG, 'background');
        installUploadedFile($tmpPath, (string)$spec['path']);
        return 'Background updated successfully.';
    }

    if ($spec['kind'] === 'video-mp4') {
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmpPath);
        }
        $allowedMime = in_array($mime, ['video/mp4', 'application/mp4', 'application/octet-stream', ''], true);
        if ($extension !== 'mp4' || !$allowedMime || !looksLikeMp4($tmpPath)) {
            throw new RuntimeException('The chat video must be a valid MP4 file.');
        }

        $staged = (string)$spec['path'] . '.incoming-' . bin2hex(random_bytes(5));
        if (!move_uploaded_file($tmpPath, $staged)) {
            throw new RuntimeException('Unable to store the uploaded video.');
        }
        if (!safeReplace($staged, (string)$spec['path'])) {
            @unlink($staged);
            throw new RuntimeException('Unable to replace the current video. Check file permissions.');
        }
        return 'Chat video updated successfully.';
    }

    throw new RuntimeException('Unsupported upload type.');
}

function defaultProfile(): array {
    return [
        'pseudo' => 'Seije',
        'youtube' => 'https://www.youtube.com/@AlpasNet',
        'twitch' => 'https://www.twitch.tv/alpasnet',
        'discord' => 'https://discord.gg/wtZwc7hHCr',
    ];
}

function normalizeSocialUrl(string $network, string $value): string {
    $value = trim($value);
    if ($value === '') return '';

    // Friendly shortcuts: a handle/channel/invite code can be entered instead of a full URL.
    if (!preg_match('~^https?://~i', $value)) {
        $value = match ($network) {
            'twitch' => 'https://www.twitch.tv/' . ltrim($value, '@/ '),
            'youtube' => str_starts_with($value, '@')
                ? 'https://www.youtube.com/' . $value
                : 'https://www.youtube.com/@' . ltrim($value, '@/ '),
            'discord' => 'https://discord.gg/' . ltrim($value, '/ '),
            default => $value,
        };
    }

    $parts = parse_url($value);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) {
        throw new RuntimeException(ucfirst($network) . ' link is not a valid URL.');
    }

    $host = strtolower((string)$parts['host']);
    $allowedHosts = match ($network) {
        'twitch' => ['twitch.tv', 'www.twitch.tv'],
        'youtube' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
        'discord' => ['discord.gg', 'discord.com', 'www.discord.com'],
        default => [],
    };
    if (!in_array($host, $allowedHosts, true)) {
        throw new RuntimeException(ucfirst($network) . ' link must point to the official ' . ucfirst($network) . ' website.');
    }

    // Store HTTPS links consistently.
    $path = (string)($parts['path'] ?? '');
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    return 'https://' . $host . $path . $query;
}

function loadProfile(string $profileFile): array {
    $profile = defaultProfile();
    if (!is_file($profileFile)) return $profile;
    $decoded = json_decode((string)file_get_contents($profileFile), true);
    if (!is_array($decoded)) return $profile;
    foreach (['pseudo', 'youtube', 'twitch', 'discord'] as $key) {
        if (array_key_exists($key, $decoded) && is_string($decoded[$key])) {
            $profile[$key] = trim($decoded[$key]);
        }
    }
    return $profile;
}

function saveProfile(string $profileFile, array $profile): void {
    ensureDirectory(dirname($profileFile));
    $json = json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Unable to encode profile configuration.');
    $tmp = $profileFile . '.tmp-' . bin2hex(random_bytes(5));
    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write profile configuration.');
    }
    if (is_file($profileFile) && !unlink($profileFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to replace profile configuration.');
    }
    if (!rename($tmp, $profileFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to install profile configuration.');
    }
    @chmod($profileFile, 0664);
}

function normalizeGameCode(string $code): string {
    $code = strtolower(trim($code));
    $code = preg_replace('/[^a-z0-9_-]+/', '-', $code) ?? '';
    return trim($code, '-_');
}

function loadGames(string $gamesFile): array {
    if (!is_file($gamesFile)) return [];
    $decoded = json_decode((string)file_get_contents($gamesFile), true);
    if (!is_array($decoded)) return [];
    $games = [];
    foreach ($decoded as $code => $game) {
        if (!is_array($game)) continue;
        $safeCode = normalizeGameCode((string)($game['code'] ?? $code));
        if ($safeCode === '') continue;
        $games[$safeCode] = [
            'code' => $safeCode,
            'name' => trim((string)($game['name'] ?? $safeCode)),
            'cover' => trim((string)($game['cover'] ?? '')),
        ];
    }
    ksort($games, SORT_NATURAL | SORT_FLAG_CASE);
    return $games;
}

function saveGames(string $gamesFile, array $games): void {
    ensureDirectory(dirname($gamesFile));
    ksort($games, SORT_NATURAL | SORT_FLAG_CASE);
    $json = json_encode($games, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Unable to encode game configuration.');
    $tmp = $gamesFile . '.tmp-' . bin2hex(random_bytes(5));
    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write game configuration.');
    }
    if (is_file($gamesFile) && !unlink($gamesFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to replace game configuration.');
    }
    if (!rename($tmp, $gamesFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to install game configuration.');
    }
    @chmod($gamesFile, 0664);
}

function validateAndInstallCover(string $field, string $code, string $coverDir, string $oldRelative = ''): string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return $oldRelative;
    }
    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return $oldRelative;
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException(uploadErrorMessage($error));

    $tmpPath = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) throw new RuntimeException('Invalid cover upload source.');
    if ($size <= 0) throw new RuntimeException('The uploaded cover is empty.');
    if ($size > 30 * 1024 * 1024) throw new RuntimeException('The cover is too large. Maximum: 30 MB.');

    $info = @getimagesize($tmpPath);
    if ($info === false || empty($info[0]) || empty($info[1])) throw new RuntimeException('The uploaded cover is not a valid image.');
    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width > 12000 || $height > 12000 || ($width * $height) > 50000000) {
        throw new RuntimeException('The cover is too large. Maximum: 50 megapixels.');
    }

    $type = (int)($info[2] ?? 0);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (defined('IMAGETYPE_WEBP')) $extensions[IMAGETYPE_WEBP] = 'webp';
    if (!isset($extensions[$type])) throw new RuntimeException('Game covers must be JPG, PNG or WEBP images.');

    ensureDirectory($coverDir);
    $extension = $extensions[$type];
    $filename = $code . '.' . $extension;
    $target = $coverDir . DIRECTORY_SEPARATOR . $filename;
    installUploadedFile($tmpPath, $target);

    if ($oldRelative !== '' && $oldRelative !== 'cover/' . $filename) {
        $oldPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldRelative);
        if (is_file($oldPath)) @unlink($oldPath);
    }

    return 'cover/' . $filename;
}

function renameCoverForCode(string $relative, string $newCode, string $coverDir): string {
    if ($relative === '') return '';
    $oldPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (!is_file($oldPath)) return '';
    $extension = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) return '';
    if ($extension === 'jpeg') $extension = 'jpg';
    ensureDirectory($coverDir);
    $newPath = $coverDir . DIRECTORY_SEPARATOR . $newCode . '.' . $extension;
    if (realpath($oldPath) === realpath($newPath)) return 'cover/' . basename($newPath);
    if (is_file($newPath)) @unlink($newPath);
    if (!rename($oldPath, $newPath)) throw new RuntimeException('Unable to rename the game cover.');
    return 'cover/' . basename($newPath);
}

function deleteCover(string $relative): void {
    if ($relative === '') return;
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $coverRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'cover');
    $parent = realpath(dirname($path));
    if ($coverRoot !== false && $parent === $coverRoot && is_file($path)) @unlink($path);
}

ensureDirectory($coverDir);
ensureDirectory($dataDir);
$games = loadGames($gamesFile);
if ($games === []) {
    $games['default'] = ['code' => 'default', 'name' => 'NOW PLAYING', 'cover' => ''];
    saveGames($gamesFile, $games);
}

$themes = require __DIR__ . DIRECTORY_SEPARATOR . 'themes.php';
$profile = loadProfile($profileFile);
if (!is_file($profileFile)) {
    saveProfile($profileFile, $profile);
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = (string)($_POST['form_action'] ?? 'asset_upload');
    try {
        if ($formAction === 'profile_save') {
            $pseudo = trim((string)($_POST['pseudo'] ?? ''));
            if ($pseudo === '') throw new RuntimeException('The streamer name is required.');
            if ((function_exists('mb_strlen') ? mb_strlen($pseudo) : strlen($pseudo)) > 50) {
                throw new RuntimeException('The streamer name is too long. Maximum: 50 characters.');
            }
            $profile = [
                'pseudo' => $pseudo,
                'youtube' => normalizeSocialUrl('youtube', (string)($_POST['youtube_url'] ?? '')),
                'twitch' => normalizeSocialUrl('twitch', (string)($_POST['twitch_url'] ?? '')),
                'discord' => normalizeSocialUrl('discord', (string)($_POST['discord_url'] ?? '')),
            ];
            saveProfile($profileFile, $profile);
            $messages[] = 'Streamer profile and social links updated successfully.';
        } elseif ($formAction === 'asset_upload') {
            $action = (string)($_POST['asset'] ?? '');
            if (!isset($targets[$action])) throw new RuntimeException('Unknown upload target.');
            $messages[] = processUpload($action, $targets[$action]);
        } elseif ($formAction === 'game_save') {
            $originalCode = normalizeGameCode((string)($_POST['original_code'] ?? ''));
            $code = normalizeGameCode((string)($_POST['game_code'] ?? ''));
            $name = trim((string)($_POST['game_name'] ?? ''));
            if ($code === '') throw new RuntimeException('The game code is required. Use letters, numbers, dashes or underscores.');
            if ($name === '') throw new RuntimeException('The game name is required.');
            if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 120) throw new RuntimeException('The game name is too long. Maximum: 120 characters.');
            if ($originalCode !== '' && !isset($games[$originalCode])) throw new RuntimeException('The game to edit no longer exists.');
            if (($originalCode === '' || $originalCode !== $code) && isset($games[$code])) throw new RuntimeException('This game code already exists.');

            $oldCover = $originalCode !== '' ? (string)($games[$originalCode]['cover'] ?? '') : '';
            if ($originalCode !== '' && $originalCode !== $code && $oldCover !== '') {
                $oldCover = renameCoverForCode($oldCover, $code, $coverDir);
            }
            $cover = validateAndInstallCover('game_cover', $code, $coverDir, $oldCover);

            if ($originalCode !== '' && $originalCode !== $code) unset($games[$originalCode]);
            $games[$code] = ['code' => $code, 'name' => $name, 'cover' => $cover];
            saveGames($gamesFile, $games);
            $messages[] = $originalCode === '' ? 'Game created successfully.' : 'Game updated successfully.';
        } elseif ($formAction === 'game_delete') {
            $code = normalizeGameCode((string)($_POST['game_code'] ?? ''));
            if ($code === '' || !isset($games[$code])) throw new RuntimeException('Unknown game.');
            deleteCover((string)($games[$code]['cover'] ?? ''));
            unset($games[$code]);
            if ($games === []) $games['default'] = ['code' => 'default', 'name' => 'NOW PLAYING', 'cover' => ''];
            saveGames($gamesFile, $games);
            $messages[] = 'Game and its cover deleted successfully.';
        } elseif ($formAction === 'cover_delete') {
            $code = normalizeGameCode((string)($_POST['game_code'] ?? ''));
            if ($code === '' || !isset($games[$code])) throw new RuntimeException('Unknown game.');
            deleteCover((string)($games[$code]['cover'] ?? ''));
            $games[$code]['cover'] = '';
            saveGames($gamesFile, $games);
            $messages[] = 'Game cover deleted successfully.';
        } else {
            throw new RuntimeException('Unknown form action.');
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
    $games = loadGames($gamesFile);
    $profile = loadProfile($profileFile);
}

function assetVersion(string $path): string {
    return is_file($path) ? (string)filemtime($path) : '0';
}

function coverUrl(array $game): string {
    $relative = trim((string)($game['cover'] ?? ''));
    if ($relative === '') return '';
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) return '';
    return $relative . '?v=' . filemtime($path);
}

$phpUploadLimit = iniBytes((string)ini_get('upload_max_filesize'));
$phpPostLimit = iniBytes((string)ini_get('post_max_size'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NeonCast Overlay Configuration</title>
<style>
:root{--bg:#060014;--panel:#120326;--cyan:#19f7ff;--pink:#ff2bd6;--violet:#8b3dff;--text:#f8f5ff;--muted:#bfc5e3;--ok:#64ffd1;--bad:#ff789d}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:radial-gradient(circle at 80% 0,rgba(255,43,214,.22),transparent 32%),radial-gradient(circle at 10% 90%,rgba(25,247,255,.18),transparent 28%),linear-gradient(145deg,#03000c,#13002c 50%,#26004b);color:var(--text);font-family:Inter,"Segoe UI",Arial,sans-serif}body{padding:36px 18px}.wrap{width:min(1180px,100%);margin:0 auto}.head,.section-head{border:1px solid rgba(25,247,255,.55);background:rgba(8,3,28,.86);padding:26px 28px;border-radius:20px;box-shadow:0 0 34px rgba(255,43,214,.15),inset 0 0 28px rgba(25,247,255,.05);margin-bottom:20px}.brand{display:flex;align-items:center;justify-content:flex-start;margin-bottom:8px}.brand img{display:block;width:min(420px,100%);height:auto;filter:drop-shadow(0 0 20px rgba(255,43,214,.25)) drop-shadow(0 0 26px rgba(25,247,255,.16))}h1{margin:10px 0 6px;font-size:24px}.lead{margin:0;color:var(--muted);line-height:1.55}.notice{padding:14px 16px;margin:14px 0;border-radius:12px;font-weight:700}.ok{border:1px solid rgba(100,255,209,.5);background:rgba(100,255,209,.08);color:var(--ok)}.bad{border:1px solid rgba(255,120,157,.5);background:rgba(255,120,157,.08);color:var(--bad)}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.card{position:relative;overflow:hidden;border:1px solid rgba(25,247,255,.35);background:linear-gradient(145deg,rgba(12,4,38,.95),rgba(35,5,71,.92));border-radius:18px;padding:18px;box-shadow:0 12px 30px rgba(0,0,0,.28),inset 0 0 24px rgba(255,43,214,.05)}.card:before{content:"";position:absolute;inset:0;height:3px;background:linear-gradient(90deg,var(--pink),var(--cyan))}.preview{height:190px;border-radius:12px;overflow:hidden;background:#05000e;border:1px solid rgba(255,43,214,.35);display:flex;align-items:center;justify-content:center;margin-bottom:15px}.preview img,.preview video{width:100%;height:100%;object-fit:cover}.preview.cover-preview img{object-fit:contain}.preview .file{padding:20px;text-align:center;color:var(--muted)}h2{font-size:18px;margin:0 0 8px}.filename{display:inline-block;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;color:var(--cyan);font-size:12px;background:rgba(25,247,255,.08);border:1px solid rgba(25,247,255,.22);padding:5px 8px;border-radius:8px}.help{color:var(--muted);font-size:13px;line-height:1.5;min-height:58px}.file-picker{display:flex;align-items:center;gap:10px;margin:8px 0 12px;min-width:0}.file-input{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}.file-button{flex:0 0 auto;border:1px solid rgba(25,247,255,.4);background:#180632;color:#fff;padding:9px 11px;border-radius:9px;cursor:pointer;font-weight:700}.file-button:hover{border-color:var(--pink);box-shadow:0 0 14px rgba(255,43,214,.16)}.file-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:13px}.btn{width:100%;border:0;border-radius:10px;padding:11px 14px;font-weight:900;letter-spacing:.04em;color:#070012;background:linear-gradient(90deg,var(--pink),var(--cyan));cursor:pointer}.btn.secondary{background:rgba(25,247,255,.12);color:var(--cyan);border:1px solid rgba(25,247,255,.4)}.btn.danger{background:rgba(255,70,110,.12);color:#ff8ca9;border:1px solid rgba(255,70,110,.45)}.meta{margin-top:20px;color:var(--muted);font-size:13px;line-height:1.6}.links{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}.links a{color:var(--text);text-decoration:none;border:1px solid rgba(255,43,214,.42);background:rgba(255,43,214,.08);padding:10px 14px;border-radius:10px;font-weight:800}.links a:hover{border-color:var(--cyan)}.section-head{margin-top:26px}.section-head h2{font-size:25px;margin:0 0 6px}.game-create{display:grid;grid-template-columns:180px 1fr 1fr auto;gap:12px;align-items:end}.field{display:flex;flex-direction:column;gap:7px}.field label{font-size:12px;font-weight:800;color:var(--cyan);letter-spacing:.05em;text-transform:uppercase}.field input[type=text],.field input[type=url]{width:100%;padding:11px 12px;border-radius:10px;border:1px solid rgba(25,247,255,.35);background:#080018;color:#fff;outline:none}.field input[type=text]:focus,.field input[type=url]:focus{border-color:var(--pink);box-shadow:0 0 0 3px rgba(255,43,214,.1)}.profile-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.profile-grid .full{grid-column:1/-1}.profile-save{display:flex;justify-content:flex-end;margin-top:14px}.profile-save .btn{width:auto;min-width:220px}.profile-help{margin-top:12px;color:var(--muted);font-size:12px;line-height:1.55}.game-list{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}.game-card{display:grid;grid-template-columns:180px 1fr;gap:16px}.game-card .preview{height:240px;margin:0}.game-form{display:grid;gap:10px}.actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.overlay-url{margin-top:8px;font-size:12px;color:var(--muted);word-break:break-all}.overlay-url code{color:var(--cyan)}.link-builder{margin-bottom:18px}.link-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field select,.final-url{width:100%;padding:12px 13px;border-radius:10px;border:1px solid rgba(25,247,255,.35);background:#080018;color:#fff;outline:none}.field select:focus,.final-url:focus{border-color:var(--pink);box-shadow:0 0 0 3px rgba(255,43,214,.1)}.theme-preview{display:flex;align-items:center;gap:8px;min-height:38px}.theme-dot{width:24px;height:24px;border-radius:50%;border:1px solid rgba(255,255,255,.45);box-shadow:0 0 14px currentColor}.link-output{display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center;margin-top:14px}.link-output .btn{width:auto;white-space:nowrap}.open-link{display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.copy-status{min-height:22px;margin-top:8px;color:var(--ok);font-size:13px;font-weight:800}.theme-note{margin-top:8px;color:var(--muted);font-size:12px;line-height:1.5}@media(max-width:900px){.grid,.game-list,.profile-grid{grid-template-columns:1fr}.profile-grid .full{grid-column:auto}.game-create,.link-grid,.link-output{grid-template-columns:1fr}.game-card{grid-template-columns:1fr}.game-card .preview{height:280px}.preview{height:240px}.help{min-height:0}.link-output .btn,.profile-save .btn{width:100%}.profile-save{display:block}}
</style>
</head>
<body>
<div class="wrap">
  <header class="head">
    <div class="brand"><img src="assets/config-logo.png?v=<?= assetVersion(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'config-logo.png') ?>" alt="NeonCast"></div>
    <h1>Overlay configuration</h1>
    <p class="lead">Configure your streamer identity and social links, replace the main overlay assets, manage game covers and generate the final OBS link for any game and neon color theme.</p>
    <?php foreach ($messages as $message): ?><div class="notice ok"><?= h($message) ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="notice bad"><?= h($error) ?></div><?php endforeach; ?>
    <div class="links"><a href="../../index.php">Back to NeonCast Tools</a></div>
  </header>

  <section class="section-head">
    <h2>Streamer profile & social links</h2>
    <p class="lead">Set the name displayed on the overlay and the links shown in the YouTube, Twitch and Discord cards. The Twitch link also determines which channel is used for the live chat.</p>
  </section>

  <article class="card" style="margin-bottom:18px">
    <form method="post">
      <input type="hidden" name="form_action" value="profile_save">
      <div class="profile-grid">
        <div class="field full">
          <label for="profile-pseudo">Streamer name / nickname</label>
          <input id="profile-pseudo" type="text" name="pseudo" value="<?= h((string)$profile['pseudo']) ?>" maxlength="50" placeholder="Seije" required>
        </div>
        <div class="field">
          <label for="profile-twitch">Twitch link</label>
          <input id="profile-twitch" type="text" name="twitch_url" value="<?= h((string)$profile['twitch']) ?>" maxlength="255" placeholder="https://www.twitch.tv/alpasnet">
        </div>
        <div class="field">
          <label for="profile-youtube">YouTube link</label>
          <input id="profile-youtube" type="text" name="youtube_url" value="<?= h((string)$profile['youtube']) ?>" maxlength="255" placeholder="https://www.youtube.com/@AlpasNet">
        </div>
        <div class="field full">
          <label for="profile-discord">Discord link</label>
          <input id="profile-discord" type="text" name="discord_url" value="<?= h((string)$profile['discord']) ?>" maxlength="255" placeholder="https://discord.gg/yourinvite">
        </div>
      </div>
      <p class="profile-help">You can also enter a Twitch channel name, a YouTube @handle or a Discord invite code. NeonCast converts those shortcuts into official links automatically. Leave a social link empty to disable that card.</p>
      <div class="profile-save"><button class="btn" type="submit">Save profile & links</button></div>
    </form>
  </article>

  <section class="section-head">
    <h2>Overlay media</h2>
    <p class="lead">Replace the avatar, the retro background and the animated Twitch chat video while keeping their original filenames.</p>
  </section>

  <section class="grid">
    <article class="card">
      <div class="preview"><img src="assets/avatar.jpg?v=<?= h(assetVersion($targets['avatar']['path'])) ?>" alt="Current avatar"></div>
      <h2>Avatar</h2>
      <span class="filename">assets/avatar.jpg</span>
      <p class="help"><?= h($targets['avatar']['help']) ?></p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="form_action" value="asset_upload">
        <input type="hidden" name="asset" value="avatar">
        <div class="file-picker"><input class="file-input" id="avatar-file" type="file" name="avatar" accept="<?= h($targets['avatar']['accept']) ?>" required><label class="file-button" for="avatar-file">Choose file</label><span class="file-name" data-for="avatar-file">No file selected</span></div>
        <button class="btn" type="submit">Replace avatar</button>
      </form>
    </article>

    <article class="card">
      <div class="preview"><img src="assets/background.png?v=<?= h(assetVersion($targets['background']['path'])) ?>" alt="Current background"></div>
      <h2>Background</h2>
      <span class="filename">assets/background.png</span>
      <p class="help"><?= h($targets['background']['help']) ?></p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="form_action" value="asset_upload">
        <input type="hidden" name="asset" value="background">
        <div class="file-picker"><input class="file-input" id="background-file" type="file" name="background" accept="<?= h($targets['background']['accept']) ?>" required><label class="file-button" for="background-file">Choose file</label><span class="file-name" data-for="background-file">No file selected</span></div>
        <button class="btn" type="submit">Replace background</button>
      </form>
    </article>

    <article class="card">
      <div class="preview">
        <?php if (is_file($targets['video']['path'])): ?>
          <video src="assets/twitch.mp4?v=<?= h(assetVersion($targets['video']['path'])) ?>" muted autoplay loop playsinline controls></video>
        <?php else: ?>
          <div class="file">No MP4 currently installed.</div>
        <?php endif; ?>
      </div>
      <h2>Twitch chat video</h2>
      <span class="filename">assets/twitch.mp4</span>
      <p class="help"><?= h($targets['video']['help']) ?></p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="form_action" value="asset_upload">
        <input type="hidden" name="asset" value="video">
        <div class="file-picker"><input class="file-input" id="video-file" type="file" name="video" accept="<?= h($targets['video']['accept']) ?>" required><label class="file-button" for="video-file">Choose file</label><span class="file-name" data-for="video-file">No file selected</span></div>
        <button class="btn" type="submit">Replace video</button>
      </form>
    </article>
  </section>

  <section class="section-head">
    <h2>Final overlay link</h2>
    <p class="lead">Choose the game and an 80s neon color theme. The generated URL can be pasted directly into an OBS Browser Source.</p>
  </section>

  <article class="card link-builder">
    <div class="link-grid">
      <div class="field">
        <label for="link-game">Game</label>
        <select id="link-game">
          <?php foreach ($games as $code => $game): ?>
            <option value="<?= h($code) ?>"><?= h((string)$game['name']) ?> — <?= h($code) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="link-theme">Neon color theme</label>
        <select id="link-theme">
          <?php
          $themeGroups = [];
          foreach ($themes as $themeCode => $theme) {
              $group = (string)($theme['group'] ?? 'Other themes');
              $themeGroups[$group][$themeCode] = $theme;
          }
          foreach ($themeGroups as $groupLabel => $groupThemes):
          ?>
            <optgroup label="<?= h($groupLabel) ?>">
              <?php foreach ($groupThemes as $themeCode => $theme): ?>
                <option value="<?= h($themeCode) ?>"><?= h((string)$theme['label']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <div id="theme-preview" class="theme-preview" aria-hidden="true"></div>
      </div>
    </div>
    <div class="link-output">
      <input id="final-overlay-url" class="final-url" type="text" readonly aria-label="Final overlay URL">
      <button id="copy-overlay-link" class="btn" type="button">Copy link</button>
      <a id="open-overlay-link" class="btn secondary open-link" href="index.php" target="_blank" rel="noopener">Open</a>
    </div>
    <div id="copy-status" class="copy-status" aria-live="polite"></div>
    <p class="theme-note">Includes signature palettes plus the main neon spectrum: red, orange, yellow, green, teal, cyan, blue, indigo, purple, magenta, pink, rose, gold and white/silver. The selected theme is stored in the URL with <code>theme=...</code>.</p>
  </article>

  <section class="section-head">
    <h2>Game covers</h2>
    <p class="lead">Create a game, choose its code and display name, then upload its cover. Covers are stored in the <strong>cover/</strong> directory and can be replaced or deleted at any time.</p>
  </section>

  <article class="card" style="margin-bottom:18px">
    <h2>Create a game</h2>
    <form class="game-create" method="post" enctype="multipart/form-data">
      <input type="hidden" name="form_action" value="game_save">
      <input type="hidden" name="original_code" value="">
      <div class="field"><label for="new-code">Code name</label><input id="new-code" type="text" name="game_code" placeholder="ffxiv" pattern="[A-Za-z0-9_-]+" maxlength="60" required></div>
      <div class="field"><label for="new-name">Game name</label><input id="new-name" type="text" name="game_name" placeholder="FINAL FANTASY XIV" maxlength="120" required></div>
      <div class="field"><label for="new-cover">Cover image</label><div class="file-picker"><input class="file-input" id="new-cover" type="file" name="game_cover" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"><label class="file-button" for="new-cover">Choose file</label><span class="file-name" data-for="new-cover">No file selected</span></div></div>
      <button class="btn" type="submit">Create game</button>
    </form>
  </article>

  <section class="game-list">
    <?php foreach ($games as $code => $game): $url = coverUrl($game); ?>
    <article class="card game-card">
      <div class="preview cover-preview">
        <?php if ($url !== ''): ?><img src="<?= h($url) ?>" alt="<?= h((string)$game['name']) ?> cover"><?php else: ?><div class="file">No cover image</div><?php endif; ?>
      </div>
      <div>
        <form class="game-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="form_action" value="game_save">
          <input type="hidden" name="original_code" value="<?= h($code) ?>">
          <div class="field"><label>Code name</label><input type="text" name="game_code" value="<?= h($code) ?>" pattern="[A-Za-z0-9_-]+" maxlength="60" required></div>
          <div class="field"><label>Game name</label><input type="text" name="game_name" value="<?= h((string)$game['name']) ?>" maxlength="120" required></div>
          <div class="field"><label for="cover-<?= h($code) ?>">Replace cover</label><div class="file-picker"><input class="file-input" id="cover-<?= h($code) ?>" type="file" name="game_cover" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"><label class="file-button" for="cover-<?= h($code) ?>">Choose file</label><span class="file-name" data-for="cover-<?= h($code) ?>">No file selected</span></div></div>
          <button class="btn" type="submit">Save changes</button>
        </form>
        <div class="actions" style="margin-top:8px">
          <form method="post"><input type="hidden" name="form_action" value="cover_delete"><input type="hidden" name="game_code" value="<?= h($code) ?>"><button class="btn secondary" type="submit" <?= $url === '' ? 'disabled' : '' ?>>Delete cover</button></form>
          <form method="post" onsubmit="return confirm('Delete this game and its cover?');"><input type="hidden" name="form_action" value="game_delete"><input type="hidden" name="game_code" value="<?= h($code) ?>"><button class="btn danger" type="submit">Delete game</button></form>
        </div>
        <div class="overlay-url">Base overlay URL: <code>index.php?game=<?= h(rawurlencode($code)) ?>&amp;theme=synthwave</code></div>
        <?php if ((string)($game['cover'] ?? '') !== ''): ?><div class="overlay-url">Cover file: <code><?= h((string)$game['cover']) ?></code></div><?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </section>

  <p class="meta">Server limits: upload_max_filesize = <?= h((string)ini_get('upload_max_filesize')) ?>, post_max_size = <?= h((string)ini_get('post_max_size')) ?>. The <strong>assets</strong>, <strong>cover</strong> and <strong>data</strong> directories must be writable by PHP. Streamer identity and social links are saved in <strong>data/profile.json</strong>.</p>
</div>
<script>
(() => {
  const gameSelect = document.getElementById('link-game');
  const themeSelect = document.getElementById('link-theme');
  const output = document.getElementById('final-overlay-url');
  const copyButton = document.getElementById('copy-overlay-link');
  const openLink = document.getElementById('open-overlay-link');
  const status = document.getElementById('copy-status');
  const preview = document.getElementById('theme-preview');
  const themes = <?= json_encode($themes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  function buildUrl() {
    const url = new URL('index.php', window.location.href);
    url.search = '';
    url.searchParams.set('game', gameSelect.value);
    url.searchParams.set('theme', themeSelect.value);
    output.value = url.href;
    openLink.href = url.href;

    const theme = themes[themeSelect.value] || themes.synthwave;
    preview.replaceChildren();
    (theme.colors || []).forEach(color => {
      const dot = document.createElement('span');
      dot.className = 'theme-dot';
      dot.style.background = color;
      dot.style.color = color;
      preview.appendChild(dot);
    });
    status.textContent = '';
  }

  async function copyFinalLink() {
    buildUrl();
    try {
      await navigator.clipboard.writeText(output.value);
      status.textContent = 'Link copied to clipboard.';
    } catch (_) {
      output.focus();
      output.select();
      const ok = document.execCommand('copy');
      status.textContent = ok ? 'Link copied to clipboard.' : 'Select the URL and copy it manually.';
    }
  }

  gameSelect.addEventListener('change', buildUrl);
  themeSelect.addEventListener('change', buildUrl);
  copyButton.addEventListener('click', copyFinalLink);
  output.addEventListener('click', () => output.select());

  document.querySelectorAll('.file-input').forEach(input => {
    const statusNode = document.querySelector(`.file-name[data-for="${input.id}"]`);
    if (!statusNode) return;
    input.addEventListener('change', () => {
      statusNode.textContent = input.files && input.files.length
        ? input.files[0].name
        : 'No file selected';
    });
  });

  buildUrl();
})();
</script>
</body>
</html>
