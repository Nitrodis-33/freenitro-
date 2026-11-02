<?php
$config = require 'config.php';

$params = http_build_query([
    'client_id'        => $config['discord_client_id'],
    'scope'            => implode(' ', $config['scopes']),
    'redirect_uri'     => $config['redirect_uri'],
    'response_type'    => 'code',
    'prompt'           => 'consent',
    'integration_type' => $config['integration_type'],
]);

header("Location: https://discord.com/oauth2/authorize?{$params}");
exit;