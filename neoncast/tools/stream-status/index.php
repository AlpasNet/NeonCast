<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function defaultConfig(): array {
    return ['logo_position'=>'left','logo_width'=>280,'texts'=>['intro'=>'THE STREAM IS STARTING SOON','pause'=>'BE RIGHT BACK','end'=>'THANK YOU FOR WATCHING']];
}
function loadConfig(string $file): array {
    $default = defaultConfig();
    if (!is_file($file)) return $default;
    $decoded = json_decode((string)file_get_contents($file), true);
    if (!is_array($decoded)) return $default;
    $position = in_array(($decoded['logo_position'] ?? ''), ['left','right'], true) ? $decoded['logo_position'] : 'left';
    $width = max(100, min(400, (int)($decoded['logo_width'] ?? 280)));
    $texts = $default['texts'];
    if (isset($decoded['texts']) && is_array($decoded['texts'])) {
        foreach (['intro','pause','end'] as $key) if (isset($decoded['texts'][$key]) && is_string($decoded['texts'][$key])) $texts[$key] = trim($decoded['texts'][$key]);
    }
    return ['logo_position'=>$position,'logo_width'=>$width,'texts'=>$texts];
}
function versioned(string $relative): string {
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_file($path) ? $relative . '?v=' . filemtime($path) : '';
}

$modes = ['startup','intro','pause','end'];
$mode = strtolower((string)($_GET['mode'] ?? 'startup'));
if (!in_array($mode, $modes, true)) $mode = 'startup';
$config = loadConfig(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'status.json');
$video = versioned('assets/video-' . $mode . '.mp4');
$logo = versioned('assets/logo.png');
$audio = in_array($mode, ['intro','pause','end'], true) ? versioned('assets/music-' . $mode . '.mp3') : '';
$text = in_array($mode, ['intro','pause','end'], true) ? (string)($config['texts'][$mode] ?? '') : '';
$side = $config['logo_position'] === 'right' ? 'right' : 'left';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NeonCast Stream Status</title>
<style>
@font-face{font-family:"Radio Stars";src:url("../thumbnail-builder/fonts/Radio%20Stars.otf") format("opentype");font-display:swap}
html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#030008;font-family:Inter,"Segoe UI",Arial,sans-serif}.scene{position:relative;width:100vw;height:100vh;background:radial-gradient(circle at 50% 20%,rgba(255,43,214,.18),transparent 40%),linear-gradient(145deg,#030008,#120027 55%,#02050f)}.bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.shade{position:absolute;inset:0;pointer-events:none;background:linear-gradient(to bottom,rgba(0,0,0,.05),rgba(0,0,0,.02) 60%,rgba(0,0,0,.42))}.logo{position:absolute;top:3.2vh;<?= $side ?>:2.6vw;width:<?= (int)$config['logo_width'] ?>px;height:auto;max-width:45vw;object-fit:contain;filter:drop-shadow(0 0 18px rgba(255,43,214,.28)) drop-shadow(0 0 22px rgba(25,247,255,.16))}.status-text{font-family:"Radio Stars",Inter,"Segoe UI",Arial,sans-serif;position:absolute;left:50%;bottom:4.5vh;transform:translateX(-50%);width:min(92vw,1500px);text-align:center;color:#fffdf9;font-size:clamp(32px,4.8vw,84px);font-weight:900;letter-spacing:.04em;line-height:1.05;text-transform:none;text-shadow:0 0 10px rgba(255,255,255,.4),0 0 22px rgba(255,43,214,.35),0 0 34px rgba(25,247,255,.25);white-space:normal}
</style>
</head>
<body>
<div class="scene">
  <?php if ($video !== ''): ?><video class="bg" autoplay loop muted playsinline preload="auto"><source src="<?= h($video) ?>" type="video/mp4"></video><?php endif; ?>
  <div class="shade"></div>
  <?php if ($logo !== ''): ?><img class="logo" src="<?= h($logo) ?>" alt=""><?php endif; ?>
  <?php if ($text !== ''): ?><div class="status-text"><?= h($text) ?></div><?php endif; ?>
  <?php if ($audio !== ''): ?><audio id="music" autoplay loop preload="auto"><source src="<?= h($audio) ?>" type="audio/mpeg"></audio><?php endif; ?>
</div>
<script>
(() => {
  const video = document.querySelector('.bg');
  if (video) { video.muted = true; video.volume = 0; video.play().catch(() => {}); }
  const music = document.getElementById('music');
  if (music) { music.loop = true; music.volume = 1; music.play().catch(() => {}); }
})();
</script>
</body>
</html>
