<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$root = __DIR__;
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function readJson(string $file, array $fallback): array {
    $r = @file_get_contents($file);
    if ($r === false) return $fallback;
    $d = json_decode($r, true);
    return is_array($d) ? $d : $fallback;
}
function assetVersion(string $path): string { return is_file($path) ? (string)filemtime($path) : '0'; }
function versionedAsset(string $root, string $relative): string {
    if ($relative === '') return '';
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return $relative . '?v=' . rawurlencode(assetVersion($path));
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
$settings = array_merge($defaultSettings, readJson($root . '/data/settings.json', []));
if (($settings['background'] ?? '') === '' && is_file($root . '/assets/background.png')) {
    $settings['background'] = 'assets/background.png';
}
$themesFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'twitch-overlay' . DIRECTORY_SEPARATOR . 'themes.php';
$themes = is_file($themesFile) ? require $themesFile : [
    'synthwave' => ['label'=>'Miami Synthwave','colors'=>['#ff2bd6','#19f7ff','#8b3dff']],
];
if (!isset($themes[$settings['theme']])) $settings['theme'] = 'synthwave';
$theme = $themes[$settings['theme']];
$themeColors = array_values(array_slice((array)($theme['colors'] ?? ['#ff2bd6','#19f7ff','#8b3dff']), 0, 3));
while (count($themeColors) < 3) $themeColors[] = $themeColors[count($themeColors) - 1] ?? '#ffffff';

$squareSize = max(25, min(80, (int)$settings['square_size']));
$logoSize = max(15, min(90, (int)$settings['logo_size']));
$authorSize = max(12, min(64, (int)$settings['author_size']));
$playerTitle = trim((string)($settings['player_title'] ?? ''));
$playerSubtitle = trim((string)($settings['player_subtitle'] ?? ''));
$playerTitleSize = max(24, min(120, (int)($settings['player_title_size'] ?? 64)));
$playerSubtitleSize = max(14, min(72, (int)($settings['player_subtitle_size'] ?? 30)));
$eqOpacity = max(0, min(100, (int)$settings['equalizer_opacity'])) / 100;

$rawTracks = readJson($root . '/data/tracks.json', []);
$tracks = [];
foreach ($rawTracks as $track) {
    if (!is_array($track)) continue;
    $audio = (string)($track['audio'] ?? '');
    $video = (string)($track['video'] ?? '');
    if ($audio === '' || $video === '') continue;
    $tracks[] = [
        'id' => (string)($track['id'] ?? ''),
        'title' => (string)($track['title'] ?? ''),
        'author' => (string)($track['author'] ?? ''),
        'audio' => versionedAsset($root, $audio),
        'video' => versionedAsset($root, $video),
        'logo' => versionedAsset($root, (string)($track['logo'] ?? '')),
    ];
}
$bg = (string)($settings['background'] ?? '');
$first = $tracks[0] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NeonCast Music Equalizer</title>
<style>
@font-face{font-family:"Robot Stars";src:url("../thumbnail-builder/fonts/Radio%20Stars.otf") format("opentype");font-display:swap}
:root{
  --square-size:<?= $squareSize ?>;
  --logo-size:<?= $logoSize ?>%;
  --author-size:<?= $authorSize ?>px;
  --player-title-size:<?= $playerTitleSize ?>px;
  --player-subtitle-size:<?= $playerSubtitleSize ?>px;
  --eq-opacity:<?= number_format($eqOpacity, 2, '.', '') ?>;
  --theme-1:<?= h((string)$themeColors[0]) ?>;
  --theme-2:<?= h((string)$themeColors[1]) ?>;
  --theme-3:<?= h((string)$themeColors[2]) ?>;
}
*{box-sizing:border-box}
html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#020006;color:#fff;font-family:Inter,"Segoe UI",Arial,sans-serif}
body{position:relative}
.background{position:absolute;inset:0;background:radial-gradient(circle at 50% 30%,#2b0b54,#05000f 65%,#000);background-position:center;background-size:cover;background-repeat:no-repeat}
.background:after{content:"";position:absolute;inset:0;background:linear-gradient(to bottom,rgba(2,0,8,.12),rgba(2,0,8,.25) 58%,rgba(2,0,8,.62))}
.stage{position:relative;z-index:2;width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:2vh 3vw 17vh}
.content-stack{display:flex;flex-direction:column;align-items:center;justify-content:center;max-width:94vw}
.player-heading{text-align:center;margin:0 0 18px;max-width:92vw;line-height:1.02;font-family:"Robot Stars","Radio Stars",Inter,"Segoe UI",Arial,sans-serif;pointer-events:none}
.player-title{font-size:var(--player-title-size);font-weight:400;letter-spacing:.055em;overflow-wrap:anywhere;background:linear-gradient(90deg,var(--theme-1) 0%,var(--theme-2) 42%,var(--theme-3) 72%,var(--theme-1) 100%);background-size:160% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;filter:drop-shadow(0 3px 9px rgba(0,0,0,.95)) drop-shadow(0 0 7px color-mix(in srgb,var(--theme-1) 38%,transparent)) drop-shadow(0 0 10px color-mix(in srgb,var(--theme-2) 24%,transparent))}
.player-subtitle{font-size:var(--player-subtitle-size);color:#fffaf4;font-weight:400;letter-spacing:.065em;margin-top:7px;text-shadow:0 0 8px rgba(255,255,255,.18),0 2px 14px rgba(0,0,0,.95);overflow-wrap:anywhere}
.player-heading:empty{display:none}
.video-frame{position:relative;width:min(calc(var(--square-size)*1vw),calc(var(--square-size)*1vh));aspect-ratio:1/1;border-radius:24px;overflow:hidden;background:#020006;border:1px solid color-mix(in srgb,var(--theme-2) 58%,transparent);box-shadow:0 0 30px color-mix(in srgb,var(--theme-2) 20%,transparent),0 0 60px color-mix(in srgb,var(--theme-1) 18%,transparent),0 28px 70px rgba(0,0,0,.46)}
video{width:100%;height:100%;object-fit:cover;display:block}
.track-meta{position:absolute;left:0;right:0;bottom:0;z-index:3;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;text-align:center;padding:15% 5% 5%;background:linear-gradient(to top,rgba(0,0,0,.78),rgba(0,0,0,.38) 58%,transparent);pointer-events:none}
.title-logo{display:block;width:var(--logo-size);max-height:28%;height:auto;object-fit:contain;filter:drop-shadow(0 5px 8px rgba(0,0,0,.95)) drop-shadow(0 0 12px rgba(0,0,0,.82))}
.title-logo.hidden{display:none}
.author{font-family:"Robot Stars","Radio Stars",Inter,"Segoe UI",Arial,sans-serif;font-size:var(--author-size);margin-top:8px;color:#fffaf4;font-weight:400;letter-spacing:.075em;line-height:1.05;text-shadow:0 0 10px rgba(255,255,255,.24),0 2px 18px rgba(0,0,0,.95)}
.eq-wrap{position:absolute;left:0;right:0;bottom:0;height:16vh;z-index:3;pointer-events:none;background:linear-gradient(to top,rgba(2,0,8,.62),transparent);opacity:var(--eq-opacity)}
#eq{width:100%;height:100%;display:block}
.start{position:absolute;z-index:6;inset:0;display:none;place-items:center;background:rgba(2,0,8,.55);backdrop-filter:blur(4px)}
.start button{border:1px solid color-mix(in srgb,var(--theme-2) 55%,transparent);background:linear-gradient(90deg,var(--theme-1),var(--theme-2));color:#05000f;border-radius:14px;padding:14px 22px;font:900 16px Inter,"Segoe UI",Arial,sans-serif;cursor:pointer}
.empty{position:relative;z-index:4;height:100%;display:grid;place-items:center;text-align:center;color:#c4c8e3;padding:30px}.empty strong{display:block;color:#fff;font-size:28px;margin-bottom:8px}
</style>
</head>
<body>
<?php if ($first): ?>
<div class="background"<?php if ($bg !== ''): ?> style="background-image:url('<?= h(versionedAsset($root,$bg)) ?>')"<?php endif; ?>></div>
<div class="stage">
  <div class="content-stack">
    <?php if ($playerTitle !== '' || $playerSubtitle !== ''): ?>
    <div class="player-heading">
      <?php if ($playerTitle !== ''): ?><div class="player-title"><?= h($playerTitle) ?></div><?php endif; ?>
      <?php if ($playerSubtitle !== ''): ?><div class="player-subtitle"><?= h($playerSubtitle) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="video-frame">
    <video id="visual" src="<?= h((string)$first['video']) ?>" autoplay muted loop playsinline></video>
    <div class="track-meta">
      <img class="title-logo<?= ((string)($first['logo'] ?? '')) === '' ? ' hidden' : '' ?>" id="trackLogo" src="<?= h((string)($first['logo'] ?? '')) ?>" alt="<?= h((string)$first['title']) ?>">
      <div class="author" id="trackAuthor"><?= h((string)$first['author']) ?></div>
    </div>
    </div>
  </div>
</div>
<audio id="audio" src="<?= h((string)$first['audio']) ?>" autoplay></audio>
<div class="eq-wrap"><canvas id="eq"></canvas></div>
<div class="start" id="start"><button type="button">Start playlist</button></div>
<script>
const playlist=<?= json_encode($tracks, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const themeColors=<?= json_encode($themeColors, JSON_UNESCAPED_SLASHES) ?>;
const audio=document.getElementById('audio');
const video=document.getElementById('visual');
const logo=document.getElementById('trackLogo');
const author=document.getElementById('trackAuthor');
const canvas=document.getElementById('eq');
const ctx=canvas.getContext('2d');
const start=document.getElementById('start');
let current=0,ac=null,analyser=null,data=null,source=null,raf=0,barLevels=[];
function resize(){const dpr=Math.max(1,Math.min(2,window.devicePixelRatio||1));canvas.width=Math.round(canvas.clientWidth*dpr);canvas.height=Math.round(canvas.clientHeight*dpr)}
function setup(){
  if(ac)return;
  ac=new (window.AudioContext||window.webkitAudioContext)();
  analyser=ac.createAnalyser();
  // Fixed, classic audio spectrum: 20 Hz to 20 kHz.
  // A larger FFT gives enough precision for narrow low-frequency bands.
  analyser.fftSize=4096;
  analyser.smoothingTimeConstant=.62;
  analyser.minDecibels=-90;
  analyser.maxDecibels=-10;
  source=ac.createMediaElementSource(audio);source.connect(analyser);analyser.connect(ac.destination);
  data=new Uint8Array(analyser.frequencyBinCount);
}
function draw(){
  raf=requestAnimationFrame(draw);if(!analyser)return;
  analyser.getByteFrequencyData(data);ctx.clearRect(0,0,canvas.width,canvas.height);
  const w=canvas.width,h=canvas.height,count=72,gap=Math.max(2,w*.0019),barW=Math.max(1,(w-gap*(count+1))/count);
  if(barLevels.length!==count)barLevels=new Array(count).fill(0);
  const grad=ctx.createLinearGradient(0,h,0,0);
  grad.addColorStop(0,themeColors[0]||'#19f7ff');
  grad.addColorStop(.52,themeColors[1]||'#8b3dff');
  grad.addColorStop(1,themeColors[2]||'#ff2bd6');
  ctx.fillStyle=grad;
  const nyquist=(ac?.sampleRate||48000)/2;
  const minHz=20;
  const maxHz=Math.min(20000,nyquist);
  const binHz=nyquist/data.length;

  // 72 fixed logarithmic bands across the standard 20 Hz–20 kHz range.
  // Every bar is driven only by the energy inside its own frequency band.
  for(let i=0;i<count;i++){
    const t0=i/count,t1=(i+1)/count;
    const f0=minHz*Math.pow(maxHz/minHz,t0);
    const f1=minHz*Math.pow(maxHz/minHz,t1);
    let b0=Math.max(1,Math.floor(f0/binHz));
    let b1=Math.min(data.length-1,Math.ceil(f1/binHz));
    if(b1<b0)b1=b0;

    let sumSquares=0,peak=0,n=0;
    for(let b=b0;b<=b1;b++){
      const v=data[b]/255;
      sumSquares+=v*v;
      if(v>peak)peak=v;
      n++;
    }
    const rms=n?Math.sqrt(sumSquares/n):0;
    const raw=(rms*.82)+(peak*.18);

    // Remove the analyser noise floor, then apply a light visual compression.
    // No shared/fake pulse is injected: silent frequency bands stay quiet.
    const floor=0.025;
    const normalized=Math.max(0,(raw-floor)/(1-floor));
    const target=Math.min(1,Math.pow(normalized,0.82));

    const previous=barLevels[i]||0;
    const speed=target>previous ? .58 : .20;
    barLevels[i]=previous+(target-previous)*speed;

    const v=barLevels[i];
    const bh=v<=.004 ? 0 : Math.max(h*.012,Math.pow(v,1.08)*h*.94);
    const x=gap+i*(barW+gap);
    ctx.shadowBlur=16;
    ctx.shadowColor=themeColors[i%themeColors.length]||'#19f7ff';
    if(bh>0)ctx.fillRect(x,h-bh,barW,bh);
  }
  ctx.shadowBlur=0;
}
async function playCurrent(){
  const track=playlist[current]; if(!track)return;
  author.textContent=track.author||'';
  logo.alt=track.title||'Track title';
  if(track.logo){logo.src=track.logo;logo.classList.remove('hidden')}else{logo.removeAttribute('src');logo.classList.add('hidden')}
  if(video.src!==new URL(track.video,location.href).href){video.src=track.video;video.load();}
  audio.src=track.audio;audio.load();
  try{await video.play().catch(()=>{});setup();await ac.resume();await audio.play();start.style.display='none';if(!raf)draw()}catch(e){start.style.display='grid'}
}
function nextTrack(){current=(current+1)%playlist.length;playCurrent();}
async function begin(){try{setup();await ac.resume();await video.play().catch(()=>{});await audio.play();start.style.display='none';if(!raf)draw()}catch(e){start.style.display='grid'}}
window.addEventListener('resize',resize);resize();
audio.addEventListener('play',()=>{try{setup();ac.resume();if(!raf)draw()}catch(e){}});
audio.addEventListener('ended',nextTrack);
audio.addEventListener('error',()=>{setTimeout(nextTrack,500)});
start.addEventListener('click',()=>playCurrent());
document.addEventListener('pointerdown',()=>{if(ac&&ac.state==='suspended')ac.resume()},{once:true});
setTimeout(()=>{audio.play().then(()=>{setup();ac.resume();if(!raf)draw()}).catch(()=>{start.style.display='grid'})},120);
</script>
<?php else: ?>
<div class="empty"><div><strong>No music in playlist</strong>Add one or more tracks from <code>config.php</code>. The single player link will play them in the saved order.</div></div>
<?php endif; ?>
</body>
</html>
