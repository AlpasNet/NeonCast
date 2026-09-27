<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$config = [
    'avatar' => 'assets/avatar.jpg',

    // Configurable wallpaper for the overlay OUTSIDE the game display area.
    // Leave backgroundImage empty to use backgroundColor only.
    'backgroundImage' => 'assets/background.png',
    'backgroundColor' => '#071127',
    'backgroundOpacity' => 1.0,
    'backgroundFit' => 'cover',       // cover or contain
    'backgroundPosition' => 'center center',

    // IMPORTANT: the game display area is transparent in OBS.
    'gameAreaColor' => 'transparent',

    // Animated background inside the Twitch chat panel.
    'chatVideoMp4' => 'assets/twitch.mp4',
    'chatVideoWebm' => '',
    'chatVideoOpacity' => 0.62,
    'chatVideoFit' => 'cover',
];

function loadOverlayProfile(string $file): array {
    $profile = [
        'pseudo' => 'Seije',
        'youtube' => 'https://www.youtube.com/@AlpasNet',
        'twitch' => 'https://www.twitch.tv/alpasnet',
        'discord' => 'https://discord.gg/wtZwc7hHCr',
    ];
    if (!is_file($file)) return $profile;
    $decoded = json_decode((string)file_get_contents($file), true);
    if (!is_array($decoded)) return $profile;
    foreach ($profile as $key => $fallback) {
        if (array_key_exists($key, $decoded) && is_string($decoded[$key])) {
            $profile[$key] = trim($decoded[$key]);
        }
    }
    return $profile;
}

function socialDisplay(string $url, string $network): string {
    $url = trim($url);
    if ($url === '') return 'Not configured';
    $parts = parse_url($url);
    if (!is_array($parts)) return $url;
    $host = preg_replace('/^www\./i', '', (string)($parts['host'] ?? '')) ?? '';
    $path = rtrim((string)($parts['path'] ?? ''), '/');
    if ($network === 'youtube' && $host === 'youtu.be') return $host . $path;
    return $host . $path;
}

function twitchChannelFromUrl(string $url): string {
    $parts = parse_url(trim($url));
    if (!is_array($parts)) return '';
    $host = strtolower(preg_replace('/^www\./i', '', (string)($parts['host'] ?? '')) ?? '');
    if ($host !== 'twitch.tv') return '';
    $path = trim((string)($parts['path'] ?? ''), '/');
    if ($path === '') return '';
    $first = explode('/', $path, 2)[0];
    return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $first) ?? '');
}

$profile = loadOverlayProfile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'profile.json');
$themes = require __DIR__ . DIRECTORY_SEPARATOR . 'themes.php';

$gamesFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'games.json';
$games = [];
if (is_file($gamesFile)) {
    $decodedGames = json_decode((string)file_get_contents($gamesFile), true);
    if (is_array($decodedGames)) {
        foreach ($decodedGames as $code => $gameData) {
            if (!is_array($gameData)) continue;
            $safeCode = strtolower(trim((string)($gameData['code'] ?? $code)));
            $safeCode = preg_replace('/[^a-z0-9_-]+/', '-', $safeCode) ?? '';
            $safeCode = trim($safeCode, '-_');
            if ($safeCode === '') continue;
            $games[$safeCode] = [
                'title' => trim((string)($gameData['name'] ?? $safeCode)),
                'subtitle' => 'NOW PLAYING',
                'cover' => trim((string)($gameData['cover'] ?? '')),
            ];
        }
    }
}
if ($games === []) {
    $games['default'] = [
        'title' => 'NOW PLAYING',
        'subtitle' => 'GAME',
        'cover' => '',
    ];
}

$gameKey = strtolower(trim((string)($_GET['game'] ?? 'default')));
$gameKey = preg_replace('/[^a-z0-9_-]+/', '-', $gameKey) ?? 'default';
$gameKey = trim($gameKey, '-_');
if (!isset($games[$gameKey])) {
    $gameKey = isset($games['default']) ? 'default' : (string)array_key_first($games);
}
$game = $games[$gameKey];

$themeKey = strtolower(trim((string)($_GET['theme'] ?? 'synthwave')));
$themeKey = preg_replace('/[^a-z0-9_-]+/', '-', $themeKey) ?? 'synthwave';
$themeKey = trim($themeKey, '-_');
if (!isset($themes[$themeKey])) {
    $themeKey = 'synthwave';
}

$twitchChannel = twitchChannelFromUrl((string)$profile['twitch']);
$socials = [
    'youtube' => ['label' => 'YouTube', 'url' => (string)$profile['youtube'], 'display' => socialDisplay((string)$profile['youtube'], 'youtube')],
    'twitch' => ['label' => 'Twitch', 'url' => (string)$profile['twitch'], 'display' => socialDisplay((string)$profile['twitch'], 'twitch')],
    'discord' => ['label' => 'Discord', 'url' => (string)$profile['discord'], 'display' => socialDisplay((string)$profile['discord'], 'discord')],
];
$backgroundImage = trim((string)($config['backgroundImage'] ?? ''));
$backgroundColor = trim((string)($config['backgroundColor'] ?? '#071127'));
$backgroundOpacity = max(0.0, min(1.0, (float)($config['backgroundOpacity'] ?? 1.0)));
$backgroundFit = in_array((string)($config['backgroundFit'] ?? 'cover'), ['cover', 'contain'], true)
    ? (string)$config['backgroundFit']
    : 'cover';
$backgroundPosition = trim((string)($config['backgroundPosition'] ?? 'center center'));
$gameAreaColor = trim((string)($config['gameAreaColor'] ?? 'transparent'));
$chatVideoMp4 = trim((string)($config['chatVideoMp4'] ?? ''));
$chatVideoWebm = trim((string)($config['chatVideoWebm'] ?? ''));
$chatVideoOpacity = max(0.0, min(1.0, (float)($config['chatVideoOpacity'] ?? 0.62)));
$chatVideoFit = in_array((string)($config['chatVideoFit'] ?? 'cover'), ['cover', 'contain'], true)
    ? (string)$config['chatVideoFit']
    : 'cover';

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function versionedAsset(string $path): string {
    $path = trim($path);
    if ($path === '' || preg_match('~^(?:https?:)?//~i', $path)) {
        return $path;
    }

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    if (!is_file($fullPath)) {
        return $path;
    }

    $separator = str_contains($path, '?') ? '&' : '?';
    return $path . $separator . 'v=' . (string)filemtime($fullPath);
}

$avatarUrl = versionedAsset((string)$config['avatar']);
$backgroundImageUrl = versionedAsset($backgroundImage);
$chatVideoMp4Url = versionedAsset($chatVideoMp4);
$chatVideoWebmUrl = versionedAsset($chatVideoWebm);
$gameCoverUrl = versionedAsset((string)($game['cover'] ?? ''));
$gameCoverPath = trim((string)($game['cover'] ?? ''));
$gameCoverExists = $gameCoverPath !== '' && is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $gameCoverPath));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>NeonCast OBS Overlay</title>
    <link rel="stylesheet" href="style.css?v=9">
</head>
<body>
<main
    id="overlay"
    data-theme="<?= e($themeKey) ?>"
    aria-label="NeonCast OBS Game First Overlay"
    style="--wallpaper-opacity: <?= e((string)$backgroundOpacity) ?>; --wallpaper-fit: <?= e($backgroundFit) ?>; --wallpaper-position: <?= e($backgroundPosition) ?>; --overlay-background-color: <?= e($backgroundColor) ?>; --game-area-color: <?= e($gameAreaColor) ?>;">

    <div id="wallpaper-layer" aria-hidden="true">
        <?php if ($backgroundImage !== ''): ?>
        <img id="overlayWallpaper" src="<?= e($backgroundImageUrl) ?>" alt="">
        <?php endif; ?>
    </div>

    <!-- Transparent 16:9 game display area. In OBS, the Game Capture can stay BELOW the Browser Source. -->
    <section id="game-zone" aria-label="Game capture area"></section>

    <aside id="side-panel">
        <section class="panel profile-panel">
            <div class="avatar-shell">
                <img id="avatar" src="<?= e($avatarUrl) ?>" alt="<?= e((string)$profile['pseudo']) ?> avatar">
                <div id="avatarFallback" class="avatar-fallback" aria-hidden="true"><?= e(function_exists('mb_substr') ? mb_substr((string)$profile['pseudo'], 0, 1) : substr((string)$profile['pseudo'], 0, 1)) ?></div>
            </div>
            <div class="profile-copy">
                <span class="profile-label">LIVE AS</span>
                <strong class="pseudo"><?= e((string)$profile['pseudo']) ?></strong>
            </div>
        </section>

        <section class="panel chat-panel" aria-label="Twitch chat">
            <div
                id="twitchChat"
                class="twitch-chat"
                data-channel="<?= e($twitchChannel) ?>"
                style="--chat-video-opacity: <?= e((string)$chatVideoOpacity) ?>; --chat-video-fit: <?= e($chatVideoFit) ?>;"
                aria-live="polite"
                aria-label="Live Twitch chat">

                <?php if ($chatVideoMp4 !== '' || $chatVideoWebm !== ''): ?>
                <video
                    id="chatBackgroundVideo"
                    class="chat-background-video"
                    autoplay
                    muted
                    loop
                    playsinline
                    preload="auto"
                    aria-hidden="true">
                    <?php if ($chatVideoWebm !== ''): ?>
                    <source src="<?= e($chatVideoWebmUrl) ?>" type="video/webm">
                    <?php endif; ?>
                    <?php if ($chatVideoMp4 !== ''): ?>
                    <source src="<?= e($chatVideoMp4Url) ?>" type="video/mp4">
                    <?php endif; ?>
                </video>
                <?php endif; ?>

                <div class="chat-video-tint" aria-hidden="true"></div>
                <div id="chatStatus" class="chat-status"><?= $twitchChannel !== '' ? 'CONNECTING TO CHAT…' : 'TWITCH CHAT DISABLED' ?></div>
            </div>
        </section>

        <section class="panel cover-panel" aria-label="Game cover">
            <div class="panel-heading">NOW PLAYING <span>✦</span></div>
            <div class="cover-stage">
                <?php if ($gameCoverExists): ?>
                <img id="gameCover" src="<?= e($gameCoverUrl) ?>" alt="<?= e((string)$game['title']) ?> cover">
                <?php else: ?>
                <img id="gameCover" src="" alt="" style="display:none">
                <?php endif; ?>
                <div id="coverFallback" class="cover-fallback" aria-hidden="true"<?= $gameCoverExists ? ' style="display:none"' : '' ?>>✦</div>
            </div>
        </section>
    </aside>

    <nav id="bottom-bar" aria-label="Social links and game information">
        <?php foreach (['youtube', 'twitch', 'discord'] as $network):
            $social = $socials[$network];
            $href = trim((string)$social['url']);
        ?>
        <a class="bottom-card social-card <?= e($network) ?><?= $href === '' ? ' disabled' : '' ?>"
           <?= $href !== '' ? 'href="' . e($href) . '" target="_blank" rel="noopener noreferrer"' : '' ?>>
            <span class="social-icon" aria-hidden="true">
                <?php if ($network === 'youtube'): ?>
                    <svg viewBox="0 0 24 24"><path d="M23 7.1a3 3 0 0 0-2.1-2.1C19 4.5 12 4.5 12 4.5s-7 0-8.9.5A3 3 0 0 0 1 7.1 31 31 0 0 0 .5 4.9A31 31 0 0 0 1 16.9 3 3 0 0 0 3.1 19c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.9A31 31 0 0 0 23 7.1ZM9.7 15.2V8.8l5.8 3.2-5.8 3.2Z"/></svg>
                <?php elseif ($network === 'twitch'): ?>
                    <svg viewBox="0 0 24 24"><path d="M4.3 2 1 5.3v12h4v4.2L9.2 18H13l6.7-6.7V2H4.3Zm13.4 8.4-3.8 3.8H10l-2.3 2.3v-2.3H4V4h13.7v6.4ZM15 6.2h-2v5h2v-5Zm-5 0H8v5h2v-5Z"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24"><path d="M19.5 5.3A16 16 0 0 0 15.4 4l-.5 1a14 14 0 0 0-5.8 0l-.5-1a16 16 0 0 0-4.1 1.3C1.9 9.1 1.2 12.8 1.5 16.4a16 16 0 0 0 5 2.5l1.2-1.6-1.9-.9.5-.4c3.7 1.7 7.7 1.7 11.4 0l.5.4-1.9.9 1.2 1.6a16 16 0 0 0 5-2.5c.4-4.2-.7-7.8-3-11.1ZM8.6 14.4c-1.1 0-2-1-2-2.2 0-1.2.9-2.2 2-2.2s2 1 2 2.2c0 1.2-.9 2.2-2 2.2Zm6.8 0c-1.1 0-2-1-2-2.2 0-1.2.9-2.2 2-2.2s2 1 2 2.2c0 1.2-.9 2.2-2 2.2Z"/></svg>
                <?php endif; ?>
            </span>
            <span class="card-copy">
                <small><?= e((string)$social['label']) ?></small>
                <strong><?= e((string)$social['display']) ?></strong>
            </span>
        </a>
        <?php endforeach; ?>

        <section class="bottom-card game-info-card">
            <span class="game-symbol" aria-hidden="true">✦</span>
            <span class="card-copy game-copy">
                <small><?= e((string)$game['subtitle']) ?></small>
                <strong id="gameTitle"><?= e((string)$game['title']) ?></strong>
                <span class="datetime"><span id="currentDate">--</span><b>•</b><span id="currentTime">--:--</span></span>
            </span>
        </section>
    </nav>

</main>
<script src="overlay.js?v=5"></script>
</body>
</html>
