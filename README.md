# Daily Show Countdown — FPP plugin

An FPP 10+ plugin configured through the FPP UI. The plugin uses its own
model-size-aware renderer for separate heading and timer colors.
Standalone scripts are no longer included. The files under `commands/` and
`scripts/` are required internal plugin components, not manual scripts.

## Plugin features

- Configure everything from FPP's **Daily Show Countdown** settings page.
- Count down to a recurring daily time or one event date/time, in FPP's timezone.
- Optional daily start window, including windows crossing midnight.
- Hours, minutes, and seconds checkboxes. At least one must be selected.
- Model/font selectors populated from FPP, heading, font size, and a text-only preview.
- Independent heading and timer color pickers, with a colored text preview.
- Completion message and 0–60 second hold, followed by clearing the overlay.
- Start now, scheduled Start, Stop, status, and FPP-managed logging.
- Duplicate-run prevention and cleanup after stop, signals, or display errors.

Text is centered. Heading and timer colors default to white, preserving existing
settings. Completion messages use the timer color. Blank headings show only the timer.
Only fonts reported by the local FPP installation can be selected or saved.
The preview is not a pixel-accurate simulation: verify font fit on your matrix.
The highest selected unit is a total, not a component: hiding hours turns
`01:30:00` into `90:00`; seconds alone gives `5400s`. Hours plus seconds uses
labels (`01h 1800s`) rather than the ambiguous `01:00`. When seconds or minutes
are hidden, the smallest displayed unit rounds up so zero is not shown early.
Event countdowns can show more than 24 total hours. There is no days checkbox.

## Plugin installation and operation

This is a development version, not yet published to Plugin Manager's registry.
Publish this repository's plugin files before trying an install from GitHub.
The metadata deliberately keeps the existing repository name
`FPP-Countdown-Advanced`; the visible plugin name is **Daily Show Countdown**.

After installing through FPP's plugin installation workflow:

1. If FPP requests an FPPD restart, perform it at a suitable time.
2. Open the plugin's `plugin_setup.php` configuration page (also linked under
   **Input/Output Setup → Daily Show Countdown**).
3. Select a model, target, time units, and font. Enable countdown commands and save.
4. Preview the text, then use **Start now** for a manual display test and **Stop
   countdown** to finish. An already-active overlay is rejected, not erased.
5. In FPP's scheduler, schedule **Daily Show Countdown Start** at the desired
   start time, with appropriate days/dates. The optional window is a gate, not
   an automatic scheduler: outside it, scheduled Start exits immediately.

Commands return immediately while the countdown runs in the background:

| Command | Behavior |
| --- | --- |
| Daily Show Countdown Start | Honor the daily window; target today's time or the overnight window's endpoint. |
| Daily Show Countdown Start Now | Bypass the window; target the next daily occurrence. Event dates never roll forward. |
| Daily Show Countdown Stop | Request stop and clear only this plugin's active display. |

Without a daily window, Start also targets the next daily occurrence. Stopping
a playlist does **not** automatically stop a countdown launched by a command;
schedule the Stop command when required. The plugin also requests stop during
FPPD shutdown. Disabling commands or saving settings affects the next start;
use Stop to stop an existing run. Stop cleanup can take several seconds if FPP
is not responding. It does not start a playlist when the timer completes.

The plugin has its own lock; do not run another overlay writer on the same model.

Settings are stored using FPP's `WriteSettingToFile`/`ReadSettingFromFile` helpers,
as one encoded CONFIG value in `config/plugin.FPP-Countdown-Advanced`.
Runtime state lives in `plugindata/FPP-Countdown-Advanced`; one runtime log lives
in FPP's logs directory as `plugin-FPP-Countdown-Advanced.log`, rotated by FPP.
Uninstall waits for cleanup, then removes the plugin's saved settings and runtime
files. The FPP-managed log remains available for diagnostics. No services, cron jobs, global packages, or other plugin files
are installed beyond the declared `python3-pil` and `fontconfig` packages used
for separate colors. PHP CLI with curl, pcntl, and posix is required and checked
by the installer; these are available on the development FPP device.

When colors match, the plugin uses FPP's native text endpoint. For different
heading/timer colors, it renders an RGB frame with Pillow and uploads it through
FPP's pixel data API. The exact installed font must resolve through fontconfig;
there is no silent font substitution. Split-color output supports models up to
1,048,576 pixels and may have slightly different font spacing from native text.
The development device already has these dependencies; Plugin Manager installs
the declared packages on fresh installations.

## Development validation

Run `php tests/run.php` on Linux with the PHP extensions above. The suite uses
an isolated temporary directory and a mocked FPP overlay API; it never drives
the physical display. Check PHP files with `php -l` and shell files with `bash -n`.
Before registry submission, actual installation, UI, display, upgrade, and
uninstall must be verified on release and nightly FPP. This development build
does not claim that release/nightly acceptance testing is complete.

Plugin conventions: [official template](https://github.com/FalconChristmas/fpp-plugin-Template),
[submission guidelines](https://github.com/FalconChristmas/fpp-data/blob/master/PLUGINS.md).
