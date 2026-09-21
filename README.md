# Daily Show Countdown — FPP plugin

Display a countdown on your matrix to a daily show time or a one-time event.
Configure and control it from the FPP UI. Available in FPP's Plugin Manager
under **Messaging**.

## Features

- Daily or one-time countdowns using FPP's timezone.
- Choose which time units to display: hours, minutes, and seconds.
- Custom heading, font size, and separate heading and timer colors.
- Model and font selectors using what's available in FPP.
- Text preview, start/stop controls, and scheduler commands.
- Optional daily time window and completion message.

## Requirements

- FPP 10.x on a platform supported by the plugin's `pluginInfo.json`.
- A configured pixel-overlay model for your matrix or other suitable display.
- Internet access during installation to download the plugin and dependencies.

FPP installs the required `python3-pil` package automatically. No separate
matrix-tools plugin is required.

## Installation

Daily Show Countdown is included in the
[FPP plugin list](https://github.com/FalconChristmas/fpp-data/blob/master/pluginList.json).

1. Open your player's FPP web interface and go to **Content Setup → Plugins**.
2. On the **Available** tab, search for **Daily Show Countdown**, or browse the
   **Messaging** category.
3. Select **Install**, review the privacy disclosure when shown, and confirm.
4. When installation finishes, open the **Installed** tab and select **Open**
   on the Daily Show Countdown card.

Developer UI mode, a manual plugin URL, and a GitHub release download are not
needed for normal installation. If the plugin does not appear, clear the search
filter, reload the Plugins page, and check that your player can access GitHub
and meets the requirements above.

Already installed from the manual URL? It is the same plugin; you do not need
to uninstall and reinstall it. Use Plugin Manager's update controls when an
update is available.

## Configuration and first countdown

1. Open **Daily Show Countdown** from the plugin's **Open** button or
   **Input/Output Setup → Daily Show Countdown**.
2. Choose your display model and the time or event date to count down to.
3. Set the heading, font, colors, and time units. Enable the countdown and save.
4. Check the preview, then select **Start now** to test it on your matrix.
5. Use **Stop countdown** to end the test.

The preview shows the text and colors, not the exact pixel layout. Check that
the text fits your matrix. Saving settings does not start the countdown.

## Updates

Use FPP's Plugin Manager to check for and install updates. This plugin tracks
the repository's `main` branch, so updates are not limited to tagged releases.
Stop an active countdown before updating, then check your settings before
starting it again.

See [GitHub releases](https://github.com/colinedwards308/FPP-Plugin-DailyShowCountdown/releases)
for tagged milestones and release notes.

## Schedule automatic starts

1. Enable the countdown and save your settings.
2. In FPP's scheduler, add a command entry using **Daily Show Countdown Start**.
3. Choose the days and time to begin displaying the countdown. If you enabled
   a daily time window, choose a start time inside it.
4. To stop early, schedule **Daily Show Countdown Stop** or use the Stop button.

For a one-time event, schedule the Start command before the event.
The time window limits when a scheduled Start is allowed; it does not start
the countdown automatically. **Daily Show Countdown Start Now** bypasses the
window and uses the next daily target time.

## Important notes

- Stop other effects using the same display model before starting the countdown.
- Stopping a playlist does not stop the countdown. Use its Stop command or button.
- Settings changes apply to the next start. Stop and restart to apply them.
- The countdown does not start a playlist when it finishes.
- Hiding hours displays total minutes, for example `90:00` instead of `01:30:00`.
- For troubleshooting, check `plugin-FPP-Plugin-DailyShowCountdown.log` in FPP's logs.

## Privacy and device changes

The countdown uses local FPP APIs and FPP's existing web server. It sends no
runtime data to external services and uses no sensors, separate network
listeners, tunnels, or remote-access software. Its source and the source of
its dependencies are public.

FPP installs the `python3-pil` package. The installer checks for `fc-list`,
provided by FPP's existing `fontconfig` installation. It is not declared as a
plugin dependency, to avoid FPP 10.x removing it and dependent media packages
when uninstalling this plugin. Starting the countdown
launches a background worker that exits at completion or when stopped; no boot
service is installed. The selected overlay is activated, drawn on, and cleared.

Operator settings are saved in `config/plugin.FPP-Plugin-DailyShowCountdown`.
Runtime status, lock and stop files are in
`plugindata/FPP-Plugin-DailyShowCountdown/`. Diagnostic logs record run times,
target dates, model names, remaining time and errors in
`logs/plugin-FPP-Plugin-DailyShowCountdown.log` under FPP's media directory.
Status is overwritten each run. The plugin provides no log age limit or delete
button; logs remain until removed manually or managed by FPP's logging tools.
Uninstall removes the settings and runtime data after stopping the worker,
but leaves the log. Package removal is managed separately by FPP.

The eight-key disclosure in `pluginInfo.json` is used by FPP's install dialog.

## Support and validation

Report problems through
[GitHub Issues](https://github.com/colinedwards308/FPP-Plugin-DailyShowCountdown/issues).
Include your FPP version, hardware, model dimensions, steps to reproduce, and
relevant log messages. Remove private information before sharing logs.

Development test harnesses and fixtures are not included in the plugin tree.
The production start/stop commands remain available for FPP scheduling and use.
Release/nightly installation and
physical-display acceptance testing remain outstanding. See
[validation notes](docs/VALIDATION.md) for details.

## License

Licensed under the [MIT License](LICENSE).
