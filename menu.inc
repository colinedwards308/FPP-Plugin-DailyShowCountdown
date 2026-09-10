<?php
$menuEntries = [
    ['text' => 'Daily Show Countdown', 'type' => 'output', 'page' => 'plugin_setup.php', 'wrap' => 1],
];
foreach ($menuEntries as $entry) {
    if ($entry['type'] !== $menu) continue;
    printf('<li><a href="plugin.php?plugin=%s&amp;page=%s">%s</a></li>',
        htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($entry['page'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($entry['text'], ENT_QUOTES, 'UTF-8'));
}
