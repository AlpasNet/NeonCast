<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function assetVersion(string $path): string {
    return is_file($path) ? (string) filemtime($path) : '0';
}

$tools = [
    [
        'title' => 'Thumbnail Builder',
        'eyebrow' => 'YouTube / Twitch Visuals',
        'description' => 'Create 16:9 thumbnails with saved settings, replaceable images, neon color themes and PNG export.',
        'href' => 'tools/thumbnail-builder/index.php',
        'secondary_href' => '',
        'secondary_label' => '',
        'icon' => '▣',
        'accent' => 'pink',
    ],
    [
        'title' => 'Twitch Overlay',
        'eyebrow' => 'OBS / Streaming',
        'description' => 'Configure your streamer profile, social links, game covers, overlay assets and retro neon color themes.',
        'href' => 'tools/twitch-overlay/config.php',
        'secondary_href' => '',
        'secondary_label' => '',
        'icon' => '◫',
        'accent' => 'cyan',
    ],
    [
        'title' => 'Stream Status',
        'eyebrow' => 'Starting / Intro / Break / Ending',
        'description' => 'Create full-screen OBS status scenes with separate looping videos, optional looping music, custom bottom text and a configurable corner logo.',
        'href' => 'tools/stream-status/config.php',
        'secondary_href' => '',
        'secondary_label' => '',
        'icon' => '◉',
        'accent' => 'violet',
    ],
];

foreach ($tools as &$tool) {
    $target = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tool['href']);
    $tool['available'] = is_file($target);
}
unset($tool);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NeonCast — Tools</title>
<style>
:root{
  --bg:#05000f;
  --panel:#100323;
  --panel2:#16052f;
  --text:#f9f7ff;
  --muted:#bfc5e3;
  --pink:#ff2bd6;
  --cyan:#19f7ff;
  --violet:#8b3dff;
  --line:rgba(25,247,255,.35);
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{
  color:var(--text);
  font-family:Inter,"Segoe UI",Arial,sans-serif;
  background:
    radial-gradient(circle at 18% 10%,rgba(255,43,214,.22),transparent 32%),
    radial-gradient(circle at 88% 15%,rgba(25,247,255,.17),transparent 30%),
    linear-gradient(145deg,#020008,#0a001b 42%,#210043 100%);
  padding:34px 18px 48px;
  overflow-x:hidden;
}
body:before{
  content:"";
  position:fixed;
  inset:auto 0 0;
  height:32vh;
  pointer-events:none;
  opacity:.22;
  background:
    linear-gradient(rgba(25,247,255,.22) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,43,214,.22) 1px,transparent 1px);
  background-size:58px 34px;
  transform:perspective(300px) rotateX(61deg) scale(1.35);
  transform-origin:bottom;
  mask-image:linear-gradient(to top,#000,transparent 85%);
}
.wrap{width:min(1180px,100%);margin:0 auto;position:relative;z-index:1}
.hero{
  text-align:center;
  border:1px solid rgba(25,247,255,.44);
  border-radius:24px;
  background:linear-gradient(145deg,rgba(8,2,27,.91),rgba(22,4,48,.88));
  padding:30px 28px 28px;
  box-shadow:0 24px 70px rgba(0,0,0,.35),0 0 40px rgba(255,43,214,.12),inset 0 0 36px rgba(25,247,255,.04);
}
.logo{display:block;width:min(510px,88vw);height:auto;margin:0 auto 15px;filter:drop-shadow(0 0 18px rgba(255,43,214,.25)) drop-shadow(0 0 22px rgba(25,247,255,.18))}
.kicker{margin:0 0 7px;color:var(--cyan);font-size:12px;font-weight:900;letter-spacing:.22em;text-transform:uppercase}
h1{font-size:clamp(28px,5vw,48px);margin:0 0 10px;letter-spacing:-.03em}
.lead{max-width:750px;margin:0 auto;color:var(--muted);line-height:1.65;font-size:15px}
.tools-title{display:flex;align-items:center;gap:14px;margin:30px 2px 16px;font-size:20px}
.tools-title:before,.tools-title:after{content:"";height:1px;flex:1;background:linear-gradient(90deg,transparent,var(--pink),var(--cyan),transparent)}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.card{
  position:relative;
  overflow:hidden;
  min-height:290px;
  padding:24px;
  border-radius:22px;
  border:1px solid rgba(25,247,255,.31);
  background:linear-gradient(150deg,rgba(11,3,34,.96),rgba(29,5,61,.93));
  box-shadow:0 18px 48px rgba(0,0,0,.3),inset 0 0 30px rgba(255,43,214,.035);
  display:flex;
  flex-direction:column;
}
.card:before{content:"";position:absolute;inset:0 0 auto;height:4px;background:linear-gradient(90deg,var(--pink),var(--cyan))}
.card.pink{--card-accent:var(--pink)}
.card.cyan{--card-accent:var(--cyan)}
.card.violet{--card-accent:var(--violet)}
.icon{
  width:62px;height:62px;border-radius:17px;display:grid;place-items:center;
  border:1px solid color-mix(in srgb,var(--card-accent) 55%, transparent);
  background:color-mix(in srgb,var(--card-accent) 10%, #070015);
  color:var(--card-accent);font-size:34px;font-weight:900;
  box-shadow:0 0 24px color-mix(in srgb,var(--card-accent) 20%, transparent);
  margin-bottom:22px;
}
.eyebrow{color:var(--card-accent);font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;margin-bottom:8px}
.card h2{font-size:27px;margin:0 0 10px}
.card p{color:var(--muted);line-height:1.6;margin:0 0 22px}
.actions{margin-top:auto;display:flex;gap:10px;flex-wrap:wrap}
.btn{
  display:inline-flex;align-items:center;justify-content:center;min-height:44px;
  padding:11px 16px;border-radius:11px;text-decoration:none;font-weight:900;letter-spacing:.02em;
  color:#05000e;background:linear-gradient(90deg,var(--pink),var(--cyan));
  border:0;box-shadow:0 0 20px rgba(255,43,214,.13);transition:.15s ease;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn.secondary{color:var(--cyan);background:rgba(25,247,255,.07);border:1px solid rgba(25,247,255,.35);box-shadow:none}
.btn.disabled{pointer-events:none;opacity:.38;filter:grayscale(.7)}
.status{margin-top:12px;font-size:12px;color:#79ffd8;font-weight:800}
.status.off{color:#ff8aa7}
.footer{text-align:center;color:#8e94b6;font-size:12px;margin-top:28px;letter-spacing:.04em}
@media(max-width:980px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){body{padding:18px 12px 34px}.hero{padding:25px 18px}.grid{grid-template-columns:1fr}.card{min-height:260px}.tools-title{margin-top:24px}}
</style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <img class="logo" src="assets/neoncast-logo.png?v=<?= h(assetVersion(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'neoncast-logo.png')) ?>" alt="NeonCast">
    <p class="kicker">Retro tools for OBS & Twitch</p>
    <h1>NeonCast Tools</h1>
    <p class="lead">Choose a tool below. Each workspace keeps its own configuration and assets while sharing the same retro neon identity.</p>
  </section>

  <div class="tools-title">Available tools</div>

  <section class="grid">
    <?php foreach ($tools as $tool): ?>
      <article class="card <?= h($tool['accent']) ?>">
        <div class="icon" aria-hidden="true"><?= h($tool['icon']) ?></div>
        <div class="eyebrow"><?= h($tool['eyebrow']) ?></div>
        <h2><?= h($tool['title']) ?></h2>
        <p><?= h($tool['description']) ?></p>
        <div class="actions">
          <a class="btn<?= $tool['available'] ? '' : ' disabled' ?>" href="<?= h($tool['href']) ?>">Open tool</a>
          <?php if ($tool['secondary_href'] !== ''): ?>
            <a class="btn secondary" href="<?= h($tool['secondary_href']) ?>" target="_blank" rel="noopener"><?= h($tool['secondary_label']) ?></a>
          <?php endif; ?>
        </div>
        <div class="status<?= $tool['available'] ? '' : ' off' ?>"><?= $tool['available'] ? 'Ready' : 'Tool files not found' ?></div>
      </article>
    <?php endforeach; ?>
  </section>

  <footer class="footer">NeonCast · Create. Stream. Chat. Repeat.</footer>
</main>
</body>
</html>
