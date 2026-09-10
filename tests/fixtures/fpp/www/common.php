<?php
// Isolated FPP adapter: never loads or writes the real FPP configuration/API.
namespace {
    $root = getenv('COUNTDOWN_TEST_MEDIA');
    if (!$root || !str_contains($root, 'countdown-test-')) throw new \RuntimeException('Missing isolated test directory');
    $settings = ['mediaDirectory' => $root, 'logDirectory' => $root . '/logs', 'configDirectory' => $root];
    date_default_timezone_set('America/Los_Angeles');
    function ReadSettingFromFile($key, $plugin = '') { return @file_get_contents(getenv('COUNTDOWN_TEST_MEDIA') . '/plugin.' . $plugin) ?: false; }
    function WriteSettingToFile($key, $value, $plugin = '') { return file_put_contents(getenv('COUNTDOWN_TEST_MEDIA') . '/plugin.' . $plugin, $value) !== false; }
}
namespace DailyCountdown {
    function curl_init($url) { return (object)['url' => $url, 'options' => []]; }
    function curl_setopt_array($ch, $options) { $ch->options = $options; return true; }
    function curl_exec($ch) {
        $root = getenv('COUNTDOWN_TEST_MEDIA');
        $path = parse_url($ch->url, PHP_URL_PATH);
        $payload = $ch->options[CURLOPT_POSTFIELDS] ?? null;
        $data = $payload ? json_decode($payload, true) : null;
        if (in_array('Content-Type: application/octet-stream', $ch->options[CURLOPT_HTTPHEADER] ?? [], true)) {
            $data = ['bytes' => strlen($payload), 'red' => substr_count($payload, "\xff\x00\x00"), 'green' => substr_count($payload, "\x00\xff\x00")];
        }
        file_put_contents($root . '/requests.jsonl', json_encode([$path, $data]) . "\n", FILE_APPEND | LOCK_EX);
        if ($path === '/api/overlays/fonts') return '["NimbusSans-Regular"]';
        if ($path === '/api/overlays/model/Small%20Matrix') return json_encode(['Name' => 'Small Matrix', 'width' => 128, 'height' => 96, 'isActive' => is_file($root . '/active') ? 1 : 0]);
        return '{}';
    }
    function curl_getinfo($ch, $option) { return is_file(getenv('COUNTDOWN_TEST_MEDIA') . '/fail-text') && str_ends_with($ch->url, '/text') ? 500 : 200; }
    function curl_error($ch) { return ''; }
    function curl_close($ch) {}
}
