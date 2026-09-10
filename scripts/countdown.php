#!/usr/bin/php
<?php
declare(strict_types=1);

use function DailyCountdown\{loadConfig, dataDir, logPath, logEntry, api, checkModel, writeState, status, requestStop, target, message};

// No worker action, including uninstall, is an HTTP endpoint. Reject HTTP
// request context explicitly even if this file is invoked through a CLI wrapper.
if (isset($_SERVER['REQUEST_METHOD']) || PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$skipJSsettings = 1;
require_once (getenv('FPPDIR') ?: '/opt/fpp') . '/www/common.php';
require_once __DIR__ . '/../lib/fpp.php';
umask(0002);
ini_set('error_log', logPath());
ini_set('display_errors', '0');

$action = $argv[1] ?? 'run';
try {
    if ($action === 'stop') { requestStop(); exit(0); }
    if ($action === 'uninstall') {
        requestStop();
        $deadline = microtime(true) + 12;
        while (status()['running'] && microtime(true) < $deadline) usleep(100000);
        if (status()['running']) throw new RuntimeException('Countdown has not stopped; retry uninstall after it stops.');
        foreach (['stop', 'status.json', 'run.lock'] as $file) {
            $path = dataDir() . '/' . $file;
            if (is_file($path)) unlink($path);
        }
        if (is_dir(dataDir()) && !rmdir(dataDir())) throw new RuntimeException('Countdown data directory is not empty.');
        $configPath = $settings['configDirectory'] . '/plugin.' . DailyCountdown\PLUGIN;
        if (is_file($configPath)) unlink($configPath);
        exit(0);
    }
    if ($action === 'status') { echo json_encode(status(), JSON_PRETTY_PRINT) . "\n"; exit(0); }
    if (!in_array($action, ['run', 'start', 'check'], true)) throw new RuntimeException('Unknown countdown action.');
    $c = loadConfig();
    if ($action === 'check') { echo json_encode(checkModel($c)) . "\n"; exit(0); }
    if (!$c['enabled']) throw new RuntimeException('Enable the plugin and save its settings first.');
    $end = target($c, new DateTimeImmutable(), in_array('--force', $argv, true));
    if (!$end) { logEntry('SKIP outside configured countdown window'); exit(0); }
    $lock = fopen(dataDir() . '/run.lock', 'c+');
    if (!$lock) throw new RuntimeException('Run the plugin installer to prepare its data directory.');
    if (!flock($lock, LOCK_EX | LOCK_NB)) { logEntry('SKIP countdown already running'); exit(0); }
    if (is_file(dataDir() . '/stop')) unlink(dataDir() . '/stop');
    writeState(['phase' => 'starting', 'target' => $end->format(DATE_ATOM), 'model' => $c['model']]);
    if ($action === 'start') {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('Could not start countdown worker.');
        if ($pid > 0) exit(0); // Child keeps the inherited flock; parent must not unlock it.
        posix_setsid();
        fclose(STDIN); fclose(STDOUT); fclose(STDERR);
        $stdin = fopen('/dev/null', 'r');
        $stdout = fopen(logPath(), 'a');
        $stderr = fopen(logPath(), 'a');
    }
    $stop = false;
    $overlay = false;
    $phase = 'stopped';
    $error = null;
    $url = 'overlays/model/' . rawurlencode($c['model']);
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM, SIGHUP] as $signal) pcntl_signal($signal, function () use (&$stop) { $stop = true; });
    $shouldStop = function () use (&$stop): bool {
        clearstatcache(true, dataDir() . '/stop');
        return $stop || is_file(dataDir() . '/stop');
    };
    $pause = function (float $seconds) use ($shouldStop): void {
        $until = microtime(true) + $seconds;
        while (!$shouldStop() && microtime(true) < $until) usleep(100000);
    };
    register_shutdown_function(function () use (&$overlay, &$phase, &$error, $url, $c, $end, $lock) {
        if ($overlay) {
            try { api($url . '/fill', ['RGB' => [0, 0, 0]]); usleep(300000); }
            catch (Throwable $e) { $error = $e->getMessage(); logEntry('ERROR cleanup: ' . $error); }
            try { api($url . '/state', ['State' => 0]); }
            catch (Throwable $e) { $error = $e->getMessage(); logEntry('ERROR cleanup: ' . $error); }
        }
        writeState(['phase' => $error ? 'error' : $phase, 'error' => $error, 'target' => $end->format(DATE_ATOM), 'model' => $c['model']]);
        logEntry('STOP ' . ($error ?? $phase));
        flock($lock, LOCK_UN);
    });
    $dimensions = checkModel($c);
    $render = function (string $text) use ($c, $url, $dimensions): void {
        if (str_contains($text, "\n") && $c['headingColor'] !== $c['timerColor']) {
            $frame = DailyCountdown\colorFrame($text, $c, $dimensions);
            api($url . '/data?w=' . $dimensions['width'] . '&h=' . $dimensions['height'] . '&fmt=rgb', $frame);
            return;
        }
        api($url . '/text', ['Message' => $text, 'Color' => $c['timerColor'], 'Font' => $c['font'],
            'FontSize' => $c['fontSize'], 'AntiAlias' => true, 'Position' => 'Center', 'PixelsPerSecond' => 0]);
    };
    if ($shouldStop()) exit(0);
    // Refuse an already active overlay rather than erasing another effect.
    $model = api($url);
    if ((int)($model['isActive'] ?? $model['State'] ?? 0) !== 0 || !empty($model['effectRunning']) || !empty($model['isLocked'])) {
        throw new RuntimeException('Selected overlay is already active or locked. Stop its current effect first.');
    }
    $overlay = true;
    api($url . '/fill', ['RGB' => [0, 0, 0]]);
    api($url . '/state', ['State' => 1]);
    logEntry('START target=' . $end->format(DATE_ATOM) . ' model=' . $c['model']);
    writeState(['phase' => 'running', 'target' => $end->format(DATE_ATOM), 'model' => $c['model']]);
    $lastMessage = null;
    $nextLog = 0;
    while (!$shouldStop()) {
        $remaining = $end->getTimestamp() - time();
        if ($remaining <= 0) {
            $phase = 'complete';
            if ($c['holdSeconds'] > 0) {
                $render($c['completionMessage'] ?: message(0, $c));
                $pause($c['holdSeconds']);
            }
            break;
        }
        $text = message($remaining, $c);
        if ($text !== $lastMessage) { $render($text); $lastMessage = $text; }
        if (time() >= $nextLog) { logEntry('PROGRESS remaining=' . $remaining); $nextLog = time() + 60; }
        $pause(0.2);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    logEntry('ERROR ' . $error);
    exit(1);
}
