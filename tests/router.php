<?php
require_once __DIR__ . '/fixtures/fpp/www/common.php';
require_once __DIR__ . '/../api.php';
function json($value) { header('Content-Type: application/json'); return json_encode($value); }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/api/command') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Daily Show Countdown Stop complete\n";
    return;
}
foreach (getEndpointsFPPPluginDailyShowCountdown() as $endpoint) {
    if ($path === '/api/plugin/FPP-Plugin-DailyShowCountdown/' . $endpoint['endpoint'] && $_SERVER['REQUEST_METHOD'] === $endpoint['method']) {
        echo call_user_func($endpoint['callback']);
        return;
    }
}
if ($path === '/') {
    echo '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
    require __DIR__ . '/../plugin_setup.php';
    echo '</body></html>';
    return;
}
http_response_code(404);
