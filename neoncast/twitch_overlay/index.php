<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$config = [
    'pseudo' => 'Your_Pseudo',
    'avatar' => 'assets/avatar.jpg',

    // Configurable wallpaper for the overlay OUTSIDE the game display area.
    // Leave backgroundImage empty to use backgroundColor only.
    'backgroundImage' => 'assets/backgrounds/background.png',
    'backgroundColor' => '#071127',
    'backgroundOpacity' => 1.0,
    'backgroundFit' => 'cover',       // cover or contain
    'backgroundPosition' => 'center center',

    // IMPORTANT: the game display area is transparent in OBS.
    // Keep this set to 'transparent' so the Game Capture placed below the Browser Source remains visible.
    'gameAreaColor' => 'transparent',

    // Existing Twitch channel used by the overlay. Change it here if needed.
    'twitchChannel' => 'your_twitch_channel',

    // Animated background inside the Twitch chat panel.
    // MP4 and WebM are both supported by OBS Browser Source.
    // Leave both paths empty to disable the animated background.
    'chatVideoMp4' => 'assets/twitch.mp4',
    'chatVideoWebm' => '',
    'chatVideoOpacity' => 0.62, // 0 = invisible, 1 = fully visible
    'chatVideoFit' => 'cover', // cover or contain

    'socials' => [
        'youtube' => [
            'label' => 'YouTube',
            'display' => 'youtube.com/your_link',
            'url' => 'https://www.youtube.com/your_link',
        ],
        'twitch' => [
            'label' => 'Twitch',
            'display' => 'twitch.tv/your_link',
            'url' => 'https://www.twitch.tv/your_link',
        ],
        'discord' => [
            'label' => 'Discord',
            'display' => 'discord.gg/your_link',
            'url' => 'https://discord.gg/your_link',
        ],
    ],
];

$games = [
    'your_code' => [
        'title' => 'YOUR GAME NAME',
        'subtitle' => 'Online',
        'cover' => '../covers/your_picture_file.jpg',
    ],
];

$gameKey = strtolower(trim((string)($_GET['game'] ?? 'default')));
if (!isset($games[$gameKey])) {
    $gameKey = 'default';
}
$game = $games[$gameKey];

$twitchChannel = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$config['twitchChannel']));
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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Seija OBS — Game First</title>
    <link rel="stylesheet" href="style.css?v=6">
</head>
<body>
<main
    id="overlay"
    aria-label="Seija OBS Game First Overlay"
    style="--wallpaper-opacity: <?= e((string)$backgroundOpacity) ?>; --wallpaper-fit: <?= e($backgroundFit) ?>; --wallpaper-position: <?= e($backgroundPosition) ?>; --overlay-background-color: <?= e($backgroundColor) ?>; --game-area-color: <?= e($gameAreaColor) ?>;">

    <div id="wallpaper-layer" aria-hidden="true">
        <?php if ($backgroundImage !== ''): ?>
        <img id="overlayWallpaper" src="<?= e($backgroundImage) ?>" alt="">
        <?php endif; ?>
    </div>

    <!-- Transparent 16:9 game display area. In OBS, the Game Capture can stay BELOW the Browser Source. -->
    <section id="game-zone" aria-label="Game capture area"></section>

    <aside id="side-panel">
        <section class="panel profile-panel">
            <div class="avatar-shell">
                <img id="avatar" src="<?= e((string)$config['avatar']) ?>" alt="Seija avatar">
                <div id="avatarFallback" class="avatar-fallback" aria-hidden="true">S</div>
            </div>
            <div class="profile-copy">
                <span class="profile-label">LIVE AS</span>
                <strong class="pseudo"><?= e((string)$config['pseudo']) ?></strong>
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
                    <source src="<?= e($chatVideoWebm) ?>" type="video/webm">
                    <?php endif; ?>
                    <?php if ($chatVideoMp4 !== ''): ?>
                    <source src="<?= e($chatVideoMp4) ?>" type="video/mp4">
                    <?php endif; ?>
                </video>
                <?php endif; ?>

                <div class="chat-video-tint" aria-hidden="true"></div>
                <div id="chatStatus" class="chat-status">CONNECTING TO CHAT…</div>
            </div>
        </section>

        <section class="panel cover-panel" aria-label="Game cover">
            <div class="panel-heading">NOW PLAYING <span>✦</span></div>
            <div class="cover-stage">
                <img id="gameCover" src="<?= e((string)$game['cover']) ?>" alt="<?= e((string)$game['title']) ?> cover">
                <div id="coverFallback" class="cover-fallback" aria-hidden="true">✦</div>
            </div>
        </section>
    </aside>

    <nav id="bottom-bar" aria-label="Social links and game information">
        <?php foreach (['youtube', 'twitch', 'discord'] as $network):
            $social = $config['socials'][$network];
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
<script src="overlay.js?v=4"></script>
</body>
</html>
