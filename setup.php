<?php
// افتح: setup.php?key=setup123
if (($_GET['key'] ?? '') !== 'setup123') {
    http_response_code(403); exit('forbidden');
}
$config = require 'config.php';
$url = "https://api.telegram.org/bot{$config['telegram_bot_token']}/getUpdates";

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
$resp = curl_exec($ch);
curl_close($ch);

echo '<pre style="white-space:pre-wrap;padding:20px;background:#111;color:#0f0;font-size:13px">';
echo htmlspecialchars((string)$resp);
echo '</pre>';