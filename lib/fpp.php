<?php
declare(strict_types=1);
namespace DailyCountdown;
require_once __DIR__ . '/countdown.php';

function loadConfig(): array
{
    $raw = \ReadSettingFromFile('CONFIG', PLUGIN);
    if ($raw === false || $raw === '') return defaults();
    $decoded = base64_decode($raw, true);
    if ($decoded === false) throw new \RuntimeException('Saved configuration is invalid.');
    return validate(json_decode($decoded, true, 32, JSON_THROW_ON_ERROR));
}

function saveConfig(array $input): array
{
    $c = validate($input);
    // One FPP-managed setting prevents workers reading a partly saved form.
    if (!\WriteSettingToFile('CONFIG', base64_encode(json_encode($c, JSON_THROW_ON_ERROR)), PLUGIN)) {
        throw new \RuntimeException('FPP could not save the countdown settings.');
    }
    return $c;
}

function dataDir(): string
{
    global $settings;
    return $settings['mediaDirectory'] . '/plugindata/' . PLUGIN;
}

function logPath(): string
{
    global $settings;
    return $settings['logDirectory'] . '/plugin-' . PLUGIN . '.log';
}

function logEntry(string $message): void
{
    file_put_contents(logPath(), date('c') . ' ' . str_replace(["\r", "\n"], ' ', $message) . "\n", FILE_APPEND | LOCK_EX);
}

function api(string $path, array|string|null $payload = null): array
{
    $ch = curl_init('http://127.0.0.1/api/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 3]);
    if ($payload !== null) {
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_HTTPHEADER => [is_string($payload) ? 'Content-Type: application/octet-stream' : 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR)]);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) throw new \RuntimeException("FPP request failed ($code): $path $error");
    $value = json_decode($body, true);
    return is_array($value) ? $value : [];
}

function checkModel(array $c): array
{
    $m = api('overlays/model/' . rawurlencode($c['model']));
    if (($m['Name'] ?? '') !== $c['model'] || ($m['width'] ?? 0) <= 0 || ($m['height'] ?? 0) <= 0) {
        throw new \RuntimeException('The selected model is missing or has no pixel dimensions.');
    }
    checkFont($c['font']);
    return ['model' => $c['model'], 'width' => $m['width'], 'height' => $m['height']];
}

function checkFont(string $font): void
{
    if (!in_array($font, api('overlays/fonts'), true)) {
        throw new \RuntimeException('The selected font is not available in FPP. Choose a font from the list.');
    }
}

function colorFrame(string $text, array $c, array $model): string
{
    $width = (int)$model['width'];
    $height = (int)$model['height'];
    if ($width < 1 || $height < 1 || $width * $height > 1048576) throw new \RuntimeException('Separate colors require a model of at most 1,048,576 pixels.');
    // An argv array avoids shell interpretation of user text and font names.
    $process = proc_open(['timeout', '4s', 'python3', __DIR__ . '/../scripts/render-colors.py'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new \RuntimeException('Could not start the color renderer.');
    fwrite($pipes[0], json_encode(['text' => $text, 'font' => $c['font'], 'fontSize' => $c['fontSize'],
        'headingColor' => $c['headingColor'], 'timerColor' => $c['timerColor'], 'width' => $width, 'height' => $height], JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $frame = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0 || strlen($frame) !== $width * $height * 3) throw new \RuntimeException('Color renderer failed: ' . trim($error ?: 'check python3-pil and fontconfig are installed'));
    return $frame;
}

function writeState(array $state): void
{
    $tmp = tempnam(dataDir(), '.state-');
    if ($tmp === false) throw new \RuntimeException('Cannot create countdown status.');
    try {
        if (file_put_contents($tmp, json_encode($state, JSON_THROW_ON_ERROR)) === false) throw new \RuntimeException('Cannot write countdown status.');
        chmod($tmp, 0664);
        if (!rename($tmp, dataDir() . '/status.json')) throw new \RuntimeException('Cannot publish countdown status.');
    } finally {
        if (is_file($tmp)) unlink($tmp);
    }
}

function status(): array
{
    $running = false;
    $lock = @fopen(dataDir() . '/run.lock', 'r');
    if ($lock) {
        $running = !flock($lock, LOCK_EX | LOCK_NB);
        if (!$running) flock($lock, LOCK_UN);
        fclose($lock);
    }
    $state = json_decode(@file_get_contents(dataDir() . '/status.json') ?: '{}', true) ?: [];
    return ['running' => $running, 'state' => $state, 'deviceTime' => date('c'), 'timezone' => date_default_timezone_get()];
}

function requestStop(): void
{
    if (!is_dir(dataDir())) return;
    if (file_put_contents(dataDir() . '/stop', 'stop', LOCK_EX) === false) throw new \RuntimeException('Could not request stop.');
}
