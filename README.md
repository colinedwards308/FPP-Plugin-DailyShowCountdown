# Daily Show Countdown — FPP plugin

Display a countdown on your matrix to a daily show time or a one-time event.
Configure and control it from the FPP UI. Requires FPP 10 or newer and a
configured pixel-overlay model.

## Features

- Daily or one-time countdowns using FPP's timezone.
- Choose which time units to display: hours, minutes, and seconds.
- Custom heading, font size, and separate heading and timer colors.
- Model and font selectors using what's available in FPP.
- Text preview, start/stop controls, and scheduler commands.
- Optional daily time window and completion message.

## Getting started

The plugin is published on GitHub but is not yet listed in FPP's Plugin Manager.
Repository: [FPP-Plugin-DailyShowCountdown](https://github.com/colinedwards308/FPP-Plugin-DailyShowCountdown), branch `main`.

After installing the plugin in FPP:

1. Open **Daily Show Countdown** from the plugin's **Open** button or
   **Input/Output Setup → Daily Show Countdown**.
2. Choose your display model and the time or event date to count down to.
3. Set the heading, font, colors, and time units. Enable the countdown and save.
4. Check the preview, then select **Start now** to test it on your matrix.
5. Use **Stop countdown** to end the test.

The preview shows the text and colors, not the exact pixel layout. Check that
the text fits your matrix. Saving settings does not start the countdown.

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

## Testing and license

The isolated test suite passes 80 checks. Release/nightly installation and
physical-display acceptance testing remain outstanding. See
[validation notes](docs/VALIDATION.md) for details.

Licensed under the [MIT License](LICENSE).
