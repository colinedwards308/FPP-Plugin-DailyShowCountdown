<?php
declare(strict_types=1);

namespace DailyCountdown;

const PLUGIN = 'FPP-Plugin-DailyShowCountdown';

function defaults(): array
{
    return [
        'enabled' => false, 'mode' => 'daily', 'targetTime' => '17:30:00',
        'eventDate' => '', 'useWindow' => true, 'startTime' => '16:00:00',
        'model' => '', 'heading' => 'Show begins in:', 'font' => 'NimbusSans-Regular',
        'fontSize' => 16, 'showHours' => true, 'showMinutes' => true,
        'showSeconds' => true, 'completionMessage' => '', 'holdSeconds' => 3,
        'headingColor' => '#FFFFFF', 'timerColor' => '#FFFFFF',
    ];
}

function validate(array $input): array
{
    $c = defaults();
    foreach ($c as $key => $default) {
        if (!array_key_exists($key, $input)) continue;
        $v = $input[$key];
        if (is_bool($default)) {
            if (!is_bool($v)) throw new \InvalidArgumentException("Invalid checkbox: $key");
        } elseif (is_int($default)) {
            if (!is_int($v)) throw new \InvalidArgumentException("Invalid number: $key");
        } else {
            if (!is_string($v) || strlen($v) > 256 || preg_match('/[\x00-\x1f\x7f]/', $v)) {
                throw new \InvalidArgumentException("Invalid text: $key");
            }
        }
        $c[$key] = $v;
    }
    if (!in_array($c['mode'], ['daily', 'event'], true)) throw new \InvalidArgumentException('Choose daily time or event date.');
    foreach (['headingColor', 'timerColor'] as $key) {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $c[$key])) throw new \InvalidArgumentException('Choose a valid six-digit text color.');
        $c[$key] = strtoupper($c[$key]);
    }
    foreach (['targetTime', 'startTime'] as $key) {
        if (preg_match('/^\d{2}:\d{2}$/', $c[$key])) $c[$key] .= ':00';
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $c[$key])) {
            throw new \InvalidArgumentException('Times must use HH:MM or HH:MM:SS.');
        }
    }
    if ($c['mode'] === 'event') {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $c['eventDate']);
        if (!$d || $d->format('Y-m-d') !== $c['eventDate']) throw new \InvalidArgumentException('Choose a valid event date.');
    } elseif ($c['useWindow'] && $c['startTime'] === $c['targetTime']) {
        throw new \InvalidArgumentException('Window start and target time must differ.');
    }
    if (!$c['showHours'] && !$c['showMinutes'] && !$c['showSeconds']) throw new \InvalidArgumentException('Select at least one time unit.');
    if ($c['fontSize'] < 4 || $c['fontSize'] > 200) throw new \InvalidArgumentException('Font size must be between 4 and 200.');
    if ($c['holdSeconds'] < 0 || $c['holdSeconds'] > 60) throw new \InvalidArgumentException('Completion display must be between 0 and 60 seconds.');
    if (trim($c['model']) === '' && $c['enabled']) throw new \InvalidArgumentException('Select a pixel overlay model.');
    if (trim($c['font']) === '') throw new \InvalidArgumentException('Select a font.');
    return $c;
}

/** Highest selected unit is a total, so 90 minutes never becomes 30 minutes. */
function formatRemaining(int $remaining, array $c): string
{
    $remaining = max(0, $remaining);
    $precision = $c['showSeconds'] ? 1 : ($c['showMinutes'] ? 60 : 3600);
    $remaining = (int)(ceil($remaining / $precision) * $precision);
    $selected = [];
    foreach (['showHours' => [3600, 'h'], 'showMinutes' => [60, 'm'], 'showSeconds' => [1, 's']] as $key => [$size, $suffix]) {
        if ($c[$key]) {
            $selected[] = [intdiv($remaining, $size), $suffix];
            $remaining %= $size;
        }
    }
    // Non-adjacent units need explicit labels to avoid an ambiguous HH:SS.
    $labels = count($selected) === 1 || ($c['showHours'] && !$c['showMinutes'] && $c['showSeconds']);
    return implode($labels ? ' ' : ':', array_map(
        fn($part) => str_pad((string)$part[0], 2, '0', STR_PAD_LEFT) . ($labels ? $part[1] : ''), $selected
    ));
}

/** Device-local calendar arithmetic handles midnight and DST without adding 86400. */
function target(array $c, \DateTimeImmutable $now, bool $force = false): ?\DateTimeImmutable
{
    if ($c['mode'] === 'event') {
        $end = new \DateTimeImmutable($c['eventDate'] . ' ' . $c['targetTime'], $now->getTimezone());
        if ($end <= $now) throw new \InvalidArgumentException('The event date/time has already passed.');
        return $end;
    }
    $end = new \DateTimeImmutable($now->format('Y-m-d') . ' ' . $c['targetTime'], $now->getTimezone());
    if ($force || !$c['useWindow']) return $end > $now ? $end : $end->modify('+1 day');
    $start = new \DateTimeImmutable($now->format('Y-m-d') . ' ' . $c['startTime'], $now->getTimezone());
    if ($c['startTime'] > $c['targetTime']) {
        if ($now >= $start) $end = $end->modify('+1 day');
        else $start = $start->modify('-1 day');
    }
    return $now >= $start && $now < $end ? $end : null;
}

function message(int $remaining, array $c): string
{
    return ($c['heading'] === '' ? '' : $c['heading'] . "\n") . formatRemaining($remaining, $c);
}
