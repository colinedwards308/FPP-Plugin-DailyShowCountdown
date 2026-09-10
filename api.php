<?php
require_once __DIR__ . '/lib/fpp.php';

function getEndpointsFPPCountdownAdvanced() {
    return [
        ['method' => 'GET', 'endpoint' => 'countdown/config', 'callback' => 'dailyCountdownSettings'],
        ['method' => 'POST', 'endpoint' => 'countdown/config', 'callback' => 'dailyCountdownSave'],
        ['method' => 'GET', 'endpoint' => 'countdown/status', 'callback' => 'dailyCountdownStatus'],
        ['method' => 'POST', 'endpoint' => 'countdown/preview', 'callback' => 'dailyCountdownPreview'],
    ];
}

function dailyCountdownResponse(callable $fn) {
    try { return json(['ok' => true, 'data' => $fn()]); }
    catch (Throwable $e) { http_response_code(400); return json(['ok' => false, 'error' => $e->getMessage()]); }
}

function dailyCountdownInput(): array {
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        throw new InvalidArgumentException('Send application/json.');
    }
    // Reject cross-origin browser writes, while retaining ordinary local API use.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        throw new InvalidArgumentException('Cross-origin writes are not allowed.');
    }
    $body = file_get_contents('php://input', false, null, 0, 8193);
    if (strlen($body) > 8192) throw new InvalidArgumentException('Settings request is too large.');
    $input = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Expected a settings object.');
    return $input;
}

function dailyCountdownSettings() {
    return dailyCountdownResponse(function () {
        return ['config' => DailyCountdown\loadConfig(), 'status' => DailyCountdown\status()];
    });
}
function dailyCountdownSave() {
    return dailyCountdownResponse(function () {
        $config = DailyCountdown\validate(dailyCountdownInput());
        DailyCountdown\checkFont($config['font']);
        if ($config['enabled']) {
            DailyCountdown\checkModel($config);
            if ($config['mode'] === 'event') DailyCountdown\target($config, new DateTimeImmutable());
        }
        return DailyCountdown\saveConfig($config);
    });
}
function dailyCountdownStatus() {
    return dailyCountdownResponse(fn() => DailyCountdown\status());
}
function dailyCountdownPreview() {
    return dailyCountdownResponse(function () {
        $config = DailyCountdown\validate(dailyCountdownInput());
        $now = new DateTimeImmutable();
        $end = DailyCountdown\target($config, $now, true);
        $remaining = $end->getTimestamp() - $now->getTimestamp();
        return ['text' => DailyCountdown\message($remaining, $config), 'target' => $end->format(DATE_ATOM),
            'timezone' => $now->getTimezone()->getName(),
            'inWindow' => DailyCountdown\target($config, $now) !== null];
    });
}
