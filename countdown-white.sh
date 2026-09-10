#!/usr/bin/bash

# Standalone white-text countdown for an FPP pixel overlay model.
# Requires Bash, curl, flock, GNU date/stat, and PHP CLI (no Python/Pillow).
#
# ==================== USER SETTINGS ====================
# Copy the model's exact name from FPP. Spaces are handled automatically.
MODEL_NAME="Small Matrix"
START_TIME="16:00:00"
END_TIME="17:30:00"
HEADING="Show begins in:"
FONT="NimbusSans-Regular"  # Must appear in FPP's overlay font list.
FONT_SIZE=16               # Adjust to fit your own matrix dimensions.
FPP_URL="http://127.0.0.1"
LOG_FILE=/home/fpp/media/logs/daily-countdown-white.log
# Shared with the advanced version to prevent competing countdowns.
LOCK_FILE=/tmp/fpp-daily-countdown.lock
# ================== END USER SETTINGS ==================

# FPP may launch scripts as root. Use one account for shared logs and locks.
if (( EUID == 0 )); then
    exec sudo -u fpp /usr/bin/bash "$0" "$@"
fi

MODE=scheduled
SHOW_STATUS=no
CHECK_ONLY=no

usage() {
    printf '%s\n' \
        "Usage: $0 [--force] [--model=NAME] [--status | --check]" \
        'Edit USER SETTINGS for the model, times, heading, font and size.' \
        '--force       Count down to the next configured end time.' \
        '--model=NAME  Override MODEL_NAME; quote names containing spaces.' \
        '--status      Show the shared countdown lock and recent white-mode logs.' \
        '--check       Validate settings and inspect FPP; do not change the display.'
}

for arg in "$@"; do
    case "$arg" in
        --force) MODE=manual ;;
        --model=*) MODEL_NAME=${arg#--model=} ;;
        --status) SHOW_STATUS=yes ;;
        --check) CHECK_ONLY=yes ;;
        --help|-h) usage; exit 0 ;;
        *) usage >&2; exit 2 ;;
    esac
done

if [[ "$SHOW_STATUS" == yes ]]; then
    if [[ -r "$LOCK_FILE" ]]; then
        exec 8<"$LOCK_FILE"
        if flock -n 8; then
            echo "Countdown: stopped"
        else
            echo "Countdown: running (lock held)"
        fi
    else
        echo "Countdown: no readable lock file"
    fi
    echo "Recent log entries:"
    tail -n 15 "$LOG_FILE" 2>/dev/null || echo "No log entries yet."
    exit 0
fi

for dependency in curl flock php date stat; do
    command -v "$dependency" >/dev/null || { echo "Missing dependency: $dependency" >&2; exit 1; }
done
if [[ -z "$MODEL_NAME" || ! "$FONT_SIZE" =~ ^[0-9]+$ ]] || (( FONT_SIZE < 4 || FONT_SIZE > 200 )); then
    echo 'Set a nonempty MODEL_NAME and FONT_SIZE between 4 and 200.' >&2
    exit 2
fi
for clock_time in "$START_TIME" "$END_TIME"; do
    if [[ ! "$clock_time" =~ ^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$ ]]; then
        echo 'START_TIME and END_TIME must use 24-hour HH:MM:SS.' >&2
        exit 2
    fi
done
if [[ "$START_TIME" > "$END_TIME" || "$START_TIME" == "$END_TIME" ]]; then
    echo 'START_TIME must be earlier than END_TIME on the same day.' >&2
    exit 2
fi
MODEL_ENCODED=$(php -r 'echo rawurlencode($argv[1]);' -- "$MODEL_NAME") || exit 1
MODEL_URL="${FPP_URL%/}/api/overlays/model/$MODEL_ENCODED"

check_configuration() {
    local model_json fonts_json
    model_json=$(curl --fail --silent --show-error --max-time 3 "$MODEL_URL") || return 1
    printf '%s' "$model_json" | php -r '
        $m = json_decode(stream_get_contents(STDIN), true);
        if (!is_array($m) || ($m["Name"] ?? "") !== $argv[1] ||
            ($m["width"] ?? 0) <= 0 || ($m["height"] ?? 0) <= 0) {
            fwrite(STDERR, "FPP model not found or invalid: " . $argv[1] . "\n"); exit(1);
        }
        echo "Model: " . $m["Name"] . " (" . $m["width"] . "x" . $m["height"] . ")\n";
    ' -- "$MODEL_NAME" || return 1
    fonts_json=$(curl --fail --silent --show-error --max-time 3 "${FPP_URL%/}/api/overlays/fonts") || return 1
    printf '%s' "$fonts_json" | php -r '
        $fonts = json_decode(stream_get_contents(STDIN), true);
        if (!is_array($fonts) || !in_array($argv[1], $fonts, true)) {
            fwrite(STDERR, "Font not listed by FPP: " . $argv[1] . "\n"); exit(1);
        }
        echo "Font: " . $argv[1] . "\n";
    ' -- "$FONT" || return 1
    printf 'Schedule: %s to %s; font size: %s; time: %s\n' "$START_TIME" "$END_TIME" "$FONT_SIZE" "$(date)"
}
if [[ "$CHECK_ONLY" == yes ]]; then
    check_configuration
    exit $?
fi

log() {
    local entry
    printf -v entry '%s pid=%s mode=%s model=%s %s' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "$$" "$MODE" "$MODEL_NAME" "$*"
    printf '%s\n' "$entry"
    # Retain the current log and one backup, capped at roughly 1 MB each.
    if [[ -f "$LOG_FILE" ]] && (( $(stat -c %s "$LOG_FILE") > 1048576 )); then
        mv -f "$LOG_FILE" "${LOG_FILE}.1"
    fi
    printf '%s\n' "$entry" >> "$LOG_FILE"
}

# Prevent two copies from writing to the matrix at the same time.
# Linux flock supports a read-only descriptor, so root and fpp can share it.
# Create it without overwriting an existing lock or changing its inode.
if [[ ! -e "$LOCK_FILE" ]]; then
    (umask 022; set -o noclobber; : > "$LOCK_FILE") 2>/dev/null || true
fi
if ! exec 9<"$LOCK_FILE"; then
    log "ERROR cannot open countdown lock: $LOCK_FILE"
    exit 1
fi
if ! flock -n 9; then
    log "SKIP another countdown is already running; stop it before starting another."
    exit 0
fi

last_error_epoch=0
request_failed=0
put_json() {
    local result rc request_epoch
    result=$(curl --fail --silent --show-error --max-time 3 \
        --header "Content-Type: application/json" \
        --request PUT \
        --data "$2" \
        "$1" 2>&1)
    rc=$?
    if (( rc != 0 )); then
        request_epoch=$(date +%s)
        if (( request_failed == 0 || request_epoch - last_error_epoch >= 60 )); then
            log "ERROR display request $1 failed (curl=$rc): $result"
            last_error_epoch=$request_epoch
        fi
        request_failed=1
        return "$rc"
    fi
    if (( request_failed )); then
        log "RECOVERED display requests responding again"
    fi
    request_failed=0
}

overlay_started=0
render_countdown() {
    local payload
    # JSON encoding handles quotes, backslashes and Unicode in user settings.
    payload=$(php -r '
        echo json_encode([
            "Message" => $argv[1] . "\n" . $argv[2],
            "Color" => "#FFFFFF", "Font" => $argv[3], "FontSize" => (int)$argv[4],
            "AntiAlias" => true, "Position" => "Center", "PixelsPerSecond" => 0
        ], JSON_THROW_ON_ERROR);
    ' -- "$HEADING" "$1" "$FONT" "$FONT_SIZE") || return 1
    put_json "${MODEL_URL}/text" "$payload"
}

clear_countdown() {
    local exit_code=$?
    # Let cleanup finish even if Ctrl+C is pressed again during its requests.
    trap '' INT TERM HUP
    if (( overlay_started )); then
        local cleared=no
        if put_json "${MODEL_URL}/fill" '{"RGB":[0,0,0]}'; then
            # Keep the overlay active long enough to transmit the black frame.
            sleep 0.3
            cleared=yes
        fi
        if put_json "${MODEL_URL}/state" '{"State":0}'; then
            log "CLEAR black_frame_sent=$cleared overlay=disabled"
        else
            log "ERROR could not disable overlay during cleanup"
        fi
    fi
    log "STOP exit=$exit_code"
}

trap clear_countdown EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP

now_epoch=$(date +%s)
start_epoch=$(date --date="today ${START_TIME}" +%s)
end_epoch=$(date --date="today ${END_TIME}" +%s)

# A manual --force run ignores the start-time gate and targets the next 5:30 PM.
if [[ "$MODE" == manual ]]; then
    if (( now_epoch >= end_epoch )); then
        end_epoch=$(date --date="tomorrow ${END_TIME}" +%s)
    fi
else
    # Scheduled runs remain silent outside the daily countdown window.
    if (( now_epoch < start_epoch || now_epoch >= end_epoch )); then
        log "SKIP outside scheduled window $START_TIME to $END_TIME"
        exit 0
    fi
fi

if ! check_configuration; then
    log 'ERROR configuration check failed; display not changed'
    exit 1
fi
log "START target=$(date -d "@$end_epoch" '+%Y-%m-%d %H:%M:%S %Z') font=$FONT size=$FONT_SIZE"
overlay_started=1
curl --fail --silent --max-time 3 "${MODEL_URL}/clear" >/dev/null 2>&1 || true
put_json "${MODEL_URL}/state" '{"State":1}' || exit 1
next_progress_epoch=0

while (( now_epoch < end_epoch )); do
    remaining=$((end_epoch - now_epoch))
    hours=$((remaining / 3600))
    minutes=$(((remaining % 3600) / 60))
    seconds=$((remaining % 60))
    printf -v countdown_text '%02d:%02d:%02d' "$hours" "$minutes" "$seconds"

    render_countdown "$countdown_text" || exit 1
    if (( now_epoch >= next_progress_epoch )); then
        log "PROGRESS remaining=$countdown_text display_request_failed=$request_failed"
        next_progress_epoch=$((now_epoch + 60))
    fi

    sleep 1
    now_epoch=$(date +%s)
done

# Briefly show zero before clearing the overlay.
log "COMPLETE target reached"
render_countdown '00:00:00' || exit 1
sleep 3
