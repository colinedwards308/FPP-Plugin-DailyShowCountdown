<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/countdown.php';
use function DailyCountdown\{defaults, validate, formatRemaining, target};

$checks = 0;
function check(bool $condition, string $description): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $checks++;
}
function rejects(callable $fn, string $description): void {
    try { $fn(); } catch (InvalidArgumentException $e) { check(true, $description); return; }
    check(false, $description);
}
$plugin = 'FPP-Plugin-DailyShowCountdown';
$menu = 'output';
ob_start();
require __DIR__ . '/../menu.inc';
$menuHtml = ob_get_clean();
check(count($menuEntries) === 1 && $menuEntries[0]['type'] === 'output', 'one documented output menu entry');
check(str_contains($menuHtml, 'plugin=' . $plugin . '&amp;page=plugin_setup.php'), 'menu links to installed plugin');
$c = defaults();
check($c['headingColor'] === '#FFFFFF' && $c['timerColor'] === '#FFFFFF', 'existing configurations default to white');
check(validate(['headingColor' => '#aa11bb'])['headingColor'] === '#AA11BB', 'normalize color hex');
rejects(fn() => validate(['timerColor' => 'red;evil']), 'reject malformed color');
check(formatRemaining(5400, $c) === '01:30:00', 'full timer');
check(formatRemaining(-1, $c) === '00:00:00', 'negative clamp');
check(formatRemaining(90061, $c) === '25:01:01', 'event hours do not wrap at 24');
check(formatRemaining(5400, array_replace($c, ['showHours' => false])) === '90:00', 'total minutes');
check(formatRemaining(5400, array_replace($c, ['showHours' => false, 'showMinutes' => false])) === '5400s', 'total seconds');
check(formatRemaining(5400, array_replace($c, ['showMinutes' => false])) === '01h 1800s', 'non-adjacent units labeled');
check(formatRemaining(1, array_replace($c, ['showSeconds' => false])) === '00:01', 'no early zero when seconds hidden');
check(formatRemaining(1, array_replace($c, ['showSeconds' => false, 'showMinutes' => false])) === '01h', 'hours round up');
foreach (['targetTime' => '25:00', 'fontSize' => 201, 'holdSeconds' => -1, 'heading' => "bad\ntext", 'enabled' => 'true'] as $key => $value) {
    rejects(fn() => validate(array_replace($c, [$key => $value])), 'reject invalid ' . $key);
}
rejects(fn() => validate(array_replace($c, ['showHours' => false, 'showMinutes' => false, 'showSeconds' => false])), 'one unit required');
rejects(fn() => validate(array_replace($c, ['mode' => 'event', 'eventDate' => '2027-02-30'])), 'invalid event day');
$zone = new DateTimeZone('America/Los_Angeles');
$at = fn($date) => new DateTimeImmutable($date, $zone);
check(target($c, $at('2026-09-10 15:59:59')) === null, 'before daily window');
check(target($c, $at('2026-09-10 16:00:00'))->format('H:i:s') === '17:30:00', 'window start included');
check(target($c, $at('2026-09-10 17:30:00')) === null, 'window end excluded');
check(target($c, $at('2026-09-10 18:00:00'), true)->format('Y-m-d H:i:s') === '2026-09-11 17:30:00', 'force rolls forward');
$overnight = array_replace($c, ['startTime' => '23:00:00', 'targetTime' => '01:00:00']);
check(target($overnight, $at('2026-09-10 23:30:00'))->format('Y-m-d H:i:s') === '2026-09-11 01:00:00', 'overnight evening');
check(target($overnight, $at('2026-09-11 00:30:00'))->format('Y-m-d H:i:s') === '2026-09-11 01:00:00', 'overnight morning');
check(target($overnight, $at('2026-09-11 12:00:00')) === null, 'overnight outside window');
check(target($c, $at('2026-03-07 18:00:00'), true)->format('Y-m-d H:i:s P') === '2026-03-08 17:30:00 -07:00', 'DST calendar day');
rejects(fn() => target(array_replace($c, ['mode' => 'event', 'eventDate' => '2026-01-01']), $at('2026-09-10'), true), 'force never rolls event date');

$root = sys_get_temp_dir() . '/countdown-test-' . bin2hex(random_bytes(6));
mkdir($root . '/plugindata/FPP-Plugin-DailyShowCountdown', 0775, true);
mkdir($root . '/logs');
putenv('COUNTDOWN_TEST_MEDIA=' . $root);
putenv('FPPDIR=' . __DIR__ . '/fixtures/fpp');
require_once __DIR__ . '/fixtures/fpp/www/common.php';
require_once __DIR__ . '/../lib/fpp.php';
$worker = __DIR__ . '/../scripts/countdown.php';
$server = null;
function runWorker(string $action, bool $force = false): int {
    global $worker;
    $args = [PHP_BINARY, $worker, $action];
    if ($force) $args[] = '--force';
    if ($action === 'start') $args = ['/usr/bin/bash', __DIR__ . '/../commands/' . ($force ? 'start-now.sh' : 'start.sh')];
    if ($action === 'stop') $args = ['/usr/bin/bash', __DIR__ . '/../commands/stop.sh'];
    $p = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    return proc_close($p);
}
function waitStopped(): void {
    $until = microtime(true) + 8;
    while (DailyCountdown\status()['running'] && microtime(true) < $until) usleep(50000);
    check(!DailyCountdown\status()['running'], 'worker stopped and released lock');
}
function waitRendered(): void {
    global $root;
    $until = microtime(true) + 5;
    while (microtime(true) < $until) {
        $requests = @file_get_contents($root . '/requests.jsonl') ?: '';
        if (str_contains($requests, '/text') || str_contains($requests, '/data')) return;
        usleep(50000);
    }
    check(false, 'worker rendered');
}
try {
    DailyCountdown\saveConfig(array_replace($c, ['heading' => 'Show "begins" \\ soon:']));
    check(DailyCountdown\loadConfig()['heading'] === 'Show "begins" \\ soon:', 'settings encoding round trip');
    check(runWorker('start', true) === 1, 'disabled start rejected');
    check(!is_file($root . '/requests.jsonl'), 'disabled start leaves display alone');
    $config = array_replace($c, ['enabled' => true, 'model' => 'Small Matrix']);
    DailyCountdown\saveConfig($config);
    check(runWorker('start', true) === 0, 'background start returns');
    waitRendered();
    check(DailyCountdown\status()['running'], 'worker has lock');
    check(runWorker('start', true) === 0, 'duplicate start is harmless');
    check(runWorker('stop') === 0, 'stop accepted');
    waitStopped();
    $requests = file_get_contents($root . '/requests.jsonl');
    check(substr_count($requests, '"State":1') === 1, 'only one overlay activation');
    check(str_contains($requests, '"State":0'), 'stop disables overlay');
    check(str_contains($requests, '"RGB":[0,0,0]'), 'black frame sent');
    check(runWorker('stop') === 0, 'repeat stop harmless');
    file_put_contents($root . '/requests.jsonl', '');
    touch($root . '/fail-text');
    runWorker('start', true); waitStopped();
    check(DailyCountdown\status()['state']['phase'] === 'error', 'render error recorded');
    check(str_contains(file_get_contents($root . '/requests.jsonl'), '"State":0'), 'render error cleanup');
    unlink($root . '/fail-text');
    file_put_contents($root . '/requests.jsonl', '');
    touch($root . '/active');
    runWorker('start', true); waitStopped();
    check(!str_contains(file_get_contents($root . '/requests.jsonl'), '"State":'), 'active overlay is not changed');
    unlink($root . '/active');
    $end = new DateTimeImmutable('+2 seconds');
    DailyCountdown\saveConfig(array_replace($config, ['mode' => 'event', 'eventDate' => $end->format('Y-m-d'), 'targetTime' => $end->format('H:i:s'), 'holdSeconds' => 0]));
    runWorker('start'); waitStopped();
    check(DailyCountdown\status()['state']['phase'] === 'complete', 'event finishes');
    DailyCountdown\saveConfig($config);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/router.php'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $root . '/http.log', 'a'], 2 => ['file', $root . '/http.log', 'a']], $pipes);
    $url = 'http://' . $address . '/api/plugin/FPP-Plugin-DailyShowCountdown/countdown/';
    for ($i = 0; $i < 40; $i++) {
        if (@file_get_contents($url . 'config') !== false) break;
        usleep(50000);
    }
    $get = json_decode(file_get_contents($url . 'config'), true);
    $stateBefore = file_get_contents(DailyCountdown\dataDir() . '/status.json');
    foreach (['GET', 'POST'] as $method) {
        $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true]]);
        $blocked = file_get_contents('http://' . $address . '/test/runtime-write', false, $context);
        check(str_contains($http_response_header[0], '403') && str_contains($blocked, 'restricted to the CLI'), 'web runtime write rejected: ' . $method);
    }
    check(file_get_contents(DailyCountdown\dataDir() . '/status.json') === $stateBefore, 'web runtime write leaves status unchanged');
    check($get['ok'] && $get['data']['config']['model'] === 'Small Matrix', 'HTTP loads saved settings');
    $post = function ($endpoint, $body) use ($url) {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => json_encode($body), 'ignore_errors' => true]]);
        return json_decode(file_get_contents($url . $endpoint, false, $ctx), true);
    };
    $bad = array_replace($config, ['showHours' => false, 'showMinutes' => false, 'showSeconds' => false]);
    check(!$post('config', $bad)['ok'], 'HTTP rejects no units');
    check(DailyCountdown\loadConfig() === $config, 'failed save preserves previous settings');
    $missingFont = array_replace($config, ['enabled' => false, 'font' => 'Definitely-Not-An-FPP-Font']);
    check(!$post('config', $missingFont)['ok'], 'HTTP rejects unavailable font while disabled');
    check(DailyCountdown\loadConfig() === $config, 'unavailable font does not replace settings');
    $minutes = array_replace($config, ['showHours' => false]);
    check($post('config', $minutes)['ok'], 'HTTP saves minutes checkbox');
    check(!DailyCountdown\loadConfig()['showHours'], 'checkbox persisted');
    $before = file_get_contents($root . '/requests.jsonl');
    $p = $post('preview', $minutes);
    check($p['ok'] && str_contains($p['data']['text'], "\n"), 'HTTP preview preserves two lines');
    check(file_get_contents($root . '/requests.jsonl') === $before, 'preview does not touch display API');
    $page = file_get_contents('http://' . $address . '/');
    check(str_contains($page, 'name="showMinutes"') && str_contains($page, 'name="showSeconds"'), 'UI renders both requested checkboxes');
    check(!str_contains($page, '<?php'), 'UI PHP rendered');
    $dom = new DOMDocument();
    $previousErrors = libxml_use_internal_errors(true);
    $dom->loadHTML($page);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($dom);
    check($xpath->query('//section[h3="Controls"]//button[@id="dc-stop"]')->length === 1, 'Stop is inside Controls');
    check($xpath->query('//button[@id="dc-stop"]')->length === 1, 'only one Stop button');
    check($xpath->query('//button[@id="dc-stop"]/ancestor::fieldset[@disabled]')->length === 0, 'Stop remains available while settings load');
    $commandResponse = file_get_contents('http://' . $address . '/api/command');
    check(str_starts_with($commandResponse, 'Daily Show Countdown'), 'FPP command fixture returns plain text');
    proc_terminate($server); proc_close($server); $server = null;
    DailyCountdown\saveConfig($config);
    $fg = proc_open([PHP_BINARY, $worker, 'run', '--force'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    usleep(400000);
    check(DailyCountdown\status()['running'], 'foreground worker started');
    proc_terminate($fg, SIGTERM); proc_close($fg); waitStopped();
    check(DailyCountdown\status()['state']['phase'] === 'stopped', 'SIGTERM handled cleanly');
    runWorker('start', true);
    $callback = proc_open(['/usr/bin/bash', __DIR__ . '/../callbacks.sh', '--type', 'lifecycle', 'shutdown'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    check(proc_close($callback) === 0, 'FPP lifecycle shutdown callback'); waitStopped();
    $colors = array_replace($config, ['headingColor' => '#FF0000', 'timerColor' => '#00FF00']);
    $frame = DailyCountdown\colorFrame("Show begins in:\n01:30:00", $colors, ['width' => 160, 'height' => 80]);
    check(strlen($frame) === 160 * 80 * 3, 'RGB frame follows model dimensions');
    check(str_contains(substr($frame, 0, 160 * 40 * 3), "\xff\x00\x00"), 'heading has selected red pixels');
    check(str_contains(substr($frame, 160 * 40 * 3), "\x00\xff\x00"), 'timer has selected green pixels');
    DailyCountdown\saveConfig($colors);
    check(DailyCountdown\loadConfig()['timerColor'] === '#00FF00', 'timer color persists');
    file_put_contents($root . '/requests.jsonl', '');
    runWorker('start', true); waitRendered(); runWorker('stop'); waitStopped();
    $requests = file_get_contents($root . '/requests.jsonl');
    check(str_contains($requests, '/data'), 'two-color worker uploads RGB frame');
    check(str_contains($requests, '"bytes":36864'), 'two-color upload has correct byte count');
    check(str_contains($requests, '"State":0'), 'two-color cleanup disables overlay');
    DailyCountdown\saveConfig(array_replace($config, ['headingColor' => '#123456', 'timerColor' => '#123456']));
    file_put_contents($root . '/requests.jsonl', '');
    runWorker('start', true); waitRendered(); runWorker('stop'); waitStopped();
    check(str_contains(file_get_contents($root . '/requests.jsonl'), '"Color":"#123456"'), 'matching colors use native text API');
    runWorker('start', true);
    check(runWorker('uninstall') === 0, 'uninstall stops worker and removes data');
    check(!is_dir(DailyCountdown\dataDir()), 'uninstall data removed');
    check(!is_file($root . '/plugin.FPP-Plugin-DailyShowCountdown'), 'uninstall settings removed');
    check(runWorker('uninstall') === 0, 'repeat uninstall harmless');
    $missingDataRejected = false;
    try { DailyCountdown\writeState(['phase' => 'stopped']); }
    catch (RuntimeException $e) { $missingDataRejected = true; }
    check($missingDataRejected, 'status write rejects missing plugin data directory');
    echo "PASS: $checks checks (formatting, timing, settings HTTP API, process lifecycle, uninstall and mocked overlay API).\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    DailyCountdown\requestStop();
    waitStopped();
    // Remove only this test's freshly allocated temporary tree.
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
