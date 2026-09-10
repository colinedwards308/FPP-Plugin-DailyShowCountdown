# FPP Countdown Advanced

A daily show countdown for a Falcon Player (FPP) LED matrix. Displays **“Show begins in:”** above a live `HH:MM:SS` countdown, with optional green, red, and blue words, manual operation outside scheduled hours, and diagnostic logging.

Developed and tested on FPP 10.0 running on a Raspberry Pi 5 with a 128×96 ColorLight matrix. Other hardware may work when its FPP pixel overlay model is configured correctly; it has not been tested here.

## Choose a version

| Version | Best for | Requirements |
| --- | --- | --- |
| **`countdown-white.sh`** | A standalone, all-white countdown for your own FPP model. Easy-to-edit settings; no fixed pixel dimensions. | FPP, Bash, curl, flock, GNU date/stat, PHP CLI. No Python, Pillow, or helper file. |
| **`countdownscript.sh`** | The advanced version with optional green/red/blue words. | Add `countdown-colors.py` and Pillow for colors. Color layout is 128×96. |

Both versions support `--force`, `--status`, logging, and Ctrl+C cleanup. They intentionally share a lock, so only one countdown version runs at a time. The standalone version does not accept color flags.

## Standalone white version: quick start

Download **only `countdown-white.sh`** and edit the **USER SETTINGS** section at the top:

```bash
MODEL_NAME="Your Matrix Name"
START_TIME="16:00:00"
END_TIME="17:30:00"
HEADING="Show begins in:"
FONT="NimbusSans-Regular"
FONT_SIZE=16
```

Copy the exact pixel overlay model name from FPP, including spaces and capitalization. The script URL-encodes the name automatically. Choose a font listed by your own FPP installation and adjust `FONT_SIZE` for your panel size. FPP handles centering; text is not automatically shrunk to fit, so confirm the layout on your display.

Copy it to your device (replace `FPP_HOST`):

```bash
scp countdown-white.sh fpp@FPP_HOST:/home/fpp/media/scripts/
ssh fpp@FPP_HOST
cd /home/fpp/media/scripts
chmod 755 countdown-white.sh

# Validate settings, model and font without displaying anything.
./countdown-white.sh --check

# Run immediately, counting down to the next configured end time.
./countdown-white.sh --force

# Or override the model for this invocation without editing the file.
./countdown-white.sh --model="Your Matrix Name" --force
```

Press **Ctrl+C** to stop and clear. For daily scheduling, select `countdown-white.sh` in a blocking FPP playlist Script entry and leave arguments empty (or use `--model="Your Matrix Name"`). Schedule the playlist to match `START_TIME` and `END_TIME`. As with the advanced version, starting normally outside the configured window exits; it does not wait for the next start time.

To inspect the standalone version:

```bash
./countdown-white.sh --status
tail -f /home/fpp/media/logs/daily-countdown-white.log
```

Its log is separate from the advanced version's log. Because the lock is shared, `--status` can report a running countdown from either version; the displayed recent log entries belong to the standalone version only. `--check` performs read-only API checks even if another countdown is running. Before display startup, it verifies that the model exists and the requested font appears in FPP's font list. This does not prove that every glyph fits or that the physical panels are working.

PHP CLI is used for safe URL/JSON encoding and API response validation. It is available on the tested FPP device. If `php` is missing on another installation, install its platform's PHP CLI package. No matrix-specific channel numbers or raw pixel sizes are embedded in this version. The URL and FPP account/log paths default to a standard local FPP installation.

The remaining detailed installation examples use the **advanced version**; substitute the standalone filename and its log path where appropriate, and omit color-specific dependencies and arguments.

## Features

- Default countdown window: **4:00 PM–5:30 PM**, using the FPP device's local time.
- `--force` starts a countdown at any time, targeting the next 5:30 PM.
- `--colors=yes` renders **Show** in green, **begins** in red, **in:** in blue, and the countdown in white.
- Default white text, or explicitly select `--colors=no`.
- `--status` reports whether the countdown lock is held and shows recent logs.
- Start, progress, completion, cleanup, skipped-run, and error logging.
- A shared lock prevents multiple copies of this countdown from running together.
- Ctrl+C sends a black frame, allows time for transmission, and disables the overlay.

## Project files

| File | Purpose |
| --- | --- |
| `countdownscript.sh` | Main script: arguments, timing, FPP requests, locking, logging, and cleanup. |
| `countdown-white.sh` | Independent all-white version with editable model/time/font settings, `--model`, and read-only `--check`. |
| `countdown-colors.py` | Pillow renderer used only for `--colors=yes`; produces one 128×96 RGB frame. |
| `test_countdown_colors.py` | Pixel-based regression check for stable timer alignment; run on FPP with Pillow and the configured font. |

Keep `countdownscript.sh` and `countdown-colors.py` in the **same directory** when using colors. The advanced shell script finds the renderer relative to its own location, so it can be launched from another working directory. `countdown-white.sh` is standalone and does not use that helper.

## Requirements

- An FPP device with working matrix output and an enabled/configured pixel overlay model.
- FPP's local HTTP API at `http://127.0.0.1/api/overlays/model/...`.
- Linux, Bash, GNU `date` and `stat`, `curl`, and `flock` (normally supplied with FPP).
- The standard `fpp` account with permission to write `/home/fpp/media/logs`.
- For white text: an FPP-recognized font named `NimbusSans-Regular`.
- For colors: Python 3, Pillow, and `/usr/share/fonts/opentype/urw-base35/NimbusSans-Regular.otf`.
- For colors: support for FPP's bulk RGB pixel API, `PUT /api/overlays/model/{model}/data`. This was tested on the installed FPP 10.0 build; compatibility with older builds is not established.

The script uses FPP directly. The Matrix Tools plugin is **not required**. This is intended to run on the FPP device, not directly on macOS or Windows.

## Installation

### 1. Copy the project to FPP

On your computer, clone the repository and upload both scripts. Replace `FPP_HOST` with your device's IP address or hostname:

```bash
git clone https://github.com/colinedwards308/FPP-Countdown-Advanced.git
cd FPP-Countdown-Advanced
scp countdownscript.sh countdown-colors.py fpp@FPP_HOST:/home/fpp/media/scripts/
ssh fpp@FPP_HOST
```

If you already downloaded the files, run the `scp` command from their directory instead of cloning again.

On the FPP device:

```bash
chmod 755 /home/fpp/media/scripts/countdownscript.sh
cd /home/fpp/media/scripts
bash -n countdownscript.sh
```

### 2. Install color-rendering requirements

For the color option, on Debian-based FPP:

```bash
sudo apt-get update
sudo apt-get install -y python3-pil fonts-urw-base35
```

Check the renderer's requirements:

```bash
python3 -c 'from PIL import ImageFont; ImageFont.truetype("/usr/share/fonts/opentype/urw-base35/NimbusSans-Regular.otf", 16); print("Color rendering ready")'
```

Pillow is not needed when running in all-white mode. If you install fonts while FPP is running, restart FPPD so its overlay font list is reloaded.

### 3. Check the overlay model and time

The default model is named **Small Matrix**, with a space. In `countdownscript.sh`, its URL is:

```bash
MODEL_URL="http://127.0.0.1/api/overlays/model/Small%20Matrix"
```

Change this URL if your model has a different name; URL-encode spaces and other special characters. Model names must match FPP exactly.

Confirm the device's time and inspect the configured model:

```bash
date
curl --fail http://127.0.0.1/api/overlays/model/Small%20Matrix
```

The script does not configure your panels, channel mapping, receiver, or network output. Confirm those work in FPP before testing the countdown. The tested setup uses FPP **Player** mode.

## Running the countdown

From `/home/fpp/media/scripts`:

```bash
# Scheduled behavior: runs only between 4:00 PM and 5:30 PM.
./countdownscript.sh

# Run now, outside or inside the normal window, with white text.
./countdownscript.sh --force

# Run now with colored words and a white timer.
./countdownscript.sh --force --colors=yes

# Colored words, but still respect the normal daily window.
./countdownscript.sh --colors=yes

# Explicitly select all-white text.
./countdownscript.sh --force --colors=no

# Inspect the lock and recent log entries without starting the display.
./countdownscript.sh --status
```

Arguments can appear in either order: `--colors=yes --force` and `--force --colors=yes` are equivalent. Unsupported arguments or color values produce a usage message and exit code 2.

### Timing behavior

| Invocation time | Normal mode | `--force` mode |
| --- | --- | --- |
| Before 4:00 PM | Logs a skipped run and exits. | Counts down to today's 5:30 PM. |
| 4:00 PM through before 5:30 PM | Counts down to today's 5:30 PM. | Counts down to today's 5:30 PM. |
| At or after 5:30 PM | Logs a skipped run and exits. | Counts down to tomorrow's 5:30 PM. |

`--force` does **not** start a fixed 90-minute timer. It selects the next occurrence of the configured end time. Normal mode does not wait for 4:00 PM or repeat itself the next day; use the FPP scheduler for daily operation.

The timer is recalculated from the device's clock approximately once per second. At completion, it displays zero for about three seconds and then clears. An FPP scheduler hard stop at exactly 5:30 PM may end it before that zero display completes.

## Daily FPP scheduling

1. Open FPP's **Playlists** page and create a playlist, for example `Daily-Countdown`.
2. Add a **Script** entry selecting `countdownscript.sh`.
3. Leave arguments empty for white text, or enter `--colors=yes` for colored words.
4. Enable **Blocking** so the playlist stays on this entry while the countdown runs.
5. Save the playlist.
6. In the FPP scheduler, add an enabled **Everyday** entry for that playlist, starting at **16:00:00** and ending at **17:30:00**, with appropriate active dates and a hard stop if desired.

For a manual playlist, create `Countdown-Override` with a blocking Script entry and arguments:

```text
--force --colors=yes
```

Select that playlist on FPP's Status page and press Play. Use Stop Now to stop it. Do not start a second countdown from SSH while the playlist is already running; the shared lock intentionally rejects duplicates. Stop a manual countdown before the scheduled run if you want the daily playlist to take over.

### Upgrading an earlier installation

Earlier versions in this project used the filename `Daily-Countdown-4pm-to-530pm.sh`. This repository uses `countdownscript.sh`. Update existing FPP playlist Script entries to select the new name after copying the files, or intentionally retain the old shell filename on your device. The helper must still be called `countdown-colors.py` and sit next to it. Stop existing instances before replacing their files.

## Stopping and clearing the screen

Press **Ctrl+C** in the terminal running the script. Cleanup sends black pixels, waits 0.3 seconds for output, and disables the model. The log records `CLEAR` and `STOP exit=130`.

**Ctrl+Z pauses the process; it does not exit or run cleanup.** If you paused it in your current shell, use `jobs` to identify the job, bring it to the foreground with `fg`, then press Ctrl+C.

Cleanup also runs for normal completion, SIGTERM, and SIGHUP. It cannot run after SIGKILL, a power failure, or an unreachable FPP API. Other active FPP content may become visible when this overlay is disabled; this script does not stop unrelated playlists or effects.

## Logging and status

The current log is:

```text
/home/fpp/media/logs/daily-countdown.log
```

Useful commands on FPP:

```bash
# Quick status and the latest 15 log entries.
./countdownscript.sh --status

# Follow new log entries as they appear.
tail -f /home/fpp/media/logs/daily-countdown.log

# Show more recent history.
tail -n 100 /home/fpp/media/logs/daily-countdown.log
```

Each entry includes the device's local timestamp/time zone, process ID, mode, and color option. Event types include:

| Event | Meaning |
| --- | --- |
| `START` | Model, target date/time, and font for the new run. |
| `PROGRESS` | Remaining time and request failure state, approximately once per minute. |
| `SKIP` | Outside scheduled hours or another countdown holds the lock. |
| `ERROR` | A request or rendering failure. |
| `RECOVERED` | A subsequent request succeeded after a failure; this does not imply automatic restart. |
| `COMPLETE` | The target time was reached. |
| `CLEAR` | Black-frame request result and overlay disable result. |
| `STOP` | Script exit code. |

The log rotates to `daily-countdown.log.1` after roughly 1 MiB; only one backup is retained. Routine progress is logged once per minute, not every second.

`--status` checks the lock, not the physical panels. A paused script still holds its lock and reports as running. Successful HTTP requests and `black_frame_sent=yes` mean FPP accepted the requests, not that the physical LEDs were independently observed.

Common exit codes: `0` normal completion or skipped run, `1` runtime failure, `2` invalid arguments, `129` SIGHUP, `130` Ctrl+C/SIGINT, and `143` SIGTERM. Rendering/request failures in the main loop end the countdown and trigger cleanup rather than retrying forever.

## Customization

Edit these settings near the top of `countdownscript.sh`:

| Setting | Default | Purpose |
| --- | --- | --- |
| `MODEL_URL` | Local API for `Small Matrix` | Target FPP model. |
| `START_TIME` | `16:00:00` | Earliest normal-mode start. |
| `END_TIME` | `17:30:00` | Countdown target time. |
| `LOG_FILE` | `/home/fpp/media/logs/daily-countdown.log` | Log location. |
| `LOCK_FILE` | `/tmp/fpp-daily-countdown.lock` | Shared single-instance lock. |

If changing the schedule, update both the script's times and the FPP scheduler. For example, a **4:30 PM** start requires `START_TIME="16:30:00"` and a 16:30 scheduler start. The implementation assumes a same-day normal window; an overnight window needs additional timing logic.

White mode uses FPP's `NimbusSans-Regular` font at size 16. Color mode uses the Nimbus Sans font file through Pillow, size 16 for the heading and size 24 for the timer. Edit the renderer's `words` list to change individual word colors. To change the heading text consistently, edit both the white-mode message in the shell script and the renderer's words.

The color renderer uses a fixed timer baseline derived from the full digit set, so changing numbers does not shift the whole countdown up or down. Horizontal centering uses font advance width rather than the visible bounds of the current digits.

**Color mode is fixed to 128×96 pixels.** Changing matrix size requires updating the Python frame dimensions and layout, the shell's `w=128&h=96` upload parameters, and the fit checks together. Changing only the FPP model URL is not sufficient. Countdown input to the color renderer is two-digit `HH:MM:SS`.

## Troubleshooting

### Nothing appears

- Check `--status` and the log for `SKIP`, `ERROR`, and the target time.
- Before 4:00 PM or after 5:30 PM, use `--force` for an immediate test.
- Verify the overlay model name, channel mapping, panel output, and device time.
- Check FPP warnings and `/home/fpp/media/logs/fppd.log`.
- An HTTP success does not guarantee text rendered: FPP can report a missing font separately. Inspect `curl --fail http://127.0.0.1/api/overlays/fonts` and use the exact font name. `Nimbus Sans` and `NimbusSans-Regular` are not interchangeable in this setup.

### Colors fail but white text works

- Ensure `countdown-colors.py` is beside the shell script.
- Install `python3-pil` and verify the font file using the installation check above.
- Confirm your FPP build supports the raw RGB `/data` endpoint.
- Verify the target model is 128×96.

### Permission denied for the lock or log

The script normally runs as `fpp`; if FPP launches it as root, it re-executes itself as `fpp`. Existing locks are opened read-only so a readable root-owned lock can still be shared safely.

Inspect permissions first:

```bash
ls -l /tmp/fpp-daily-countdown.lock /home/fpp/media/logs/daily-countdown.log
```

Ensure `fpp` can read the lock and write the log directory/current log. Do not delete or replace a lock while a countdown is running: that can allow competing copies to run.

### A countdown is already running

Stop the existing FPP playlist or foreground terminal run before starting another. A paused terminal job retains the lock. Do not use `--force` to try to bypass it: that flag bypasses the time window only.

### The screen stays lit after stopping

Check for `CLEAR black_frame_sent=yes overlay=disabled` in the log. Ensure the process actually exited rather than being paused with Ctrl+Z. Also check whether another FPP sequence, effect, or input is supplying content.

## Validation

Run the alignment regression check on an FPP device with the configured font and Pillow, from a copy of this repository containing the test file:

```bash
python3 -m unittest -v test_countdown_colors.py
```

It checks that the colon pixels remain in exactly the same position across 65 timer values, including transitions involving 1 and 4. This test reproduces the vertical movement in the original renderer and passes with the fixed baseline.

On the development FPP device, white and colored countdowns, out-of-window handling, duplicate prevention, and progress logging were exercised. A terminal Ctrl+C test exited with code 130, disabled the model, and left all 36,864 bytes of its 128×96 RGB buffer at zero. The color renderer was checked for green, red, blue, and white pixels within the frame boundaries. These checks do not establish compatibility with every FPP build or matrix configuration.
