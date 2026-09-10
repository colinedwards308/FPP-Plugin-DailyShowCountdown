#!/usr/bin/bash

# Daily countdown for the FPP "Small Matrix" Pixel Overlay Model.
# This script may be started at any time between 4:00 PM and 5:30 PM.

# FPP may launch scripts as root. Use one account for shared logs and locks.
if (( EUID == 0 )); then
    exec sudo -u fpp /usr/bin/bash "$0" "$@"
fi

MODEL_URL="http://127.0.0.1/api/overlays/model/Small%20Matrix"
START_TIME="16:00:00"
END_TIME="17:30:00"
LOG_FILE=/home/fpp/media/logs/daily-countdown.log
LOCK_FILE=/tmp/fpp-daily-countdown.lock
MODE=scheduled
COLORS=no
SHOW_STATUS=no
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)

for arg in "$@"; do
    case "$arg" in
        --force) MODE=manual ;;
        --colors=yes) COLORS=yes ;;
        --colors=no) COLORS=no ;;
        --status) SHOW_STATUS=yes ;;
        *) echo "Usage: $0 [--force] [--colors=yes|no] [--status]" >&2; exit 2 ;;
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

log() {
    local entry
    printf -v entry '%s pid=%s mode=%s colors=%s %s' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "$$" "$MODE" "$COLORS" "$*"
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
    if [[ "$COLORS" == yes ]]; then
        local result
        if ! result=$( (
            set -o pipefail
            python3 "$SCRIPT_DIR/countdown-colors.py" "$1" |
                curl --fail --silent --show-error --max-time 3 \
                    --header 'Content-Type: application/octet-stream' \
                    --request PUT --data-binary @- \
                    "${MODEL_URL}/data?w=128&h=96&fmt=rgb"
        ) 2>&1); then
            log "ERROR colored countdown rendering failed: $result"
            return 1
        fi
    else
        put_json "${MODEL_URL}/text" \
            "{\"Message\":\"Show begins in:\\n${1}\",\"Color\":\"#FFFFFF\",\"Font\":\"NimbusSans-Regular\",\"FontSize\":16,\"AntiAlias\":true,\"Position\":\"Center\",\"PixelsPerSecond\":0}"
    fi
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

log "START model=Small_Matrix target=$(date -d "@$end_epoch" '+%Y-%m-%d %H:%M:%S %Z') font=NimbusSans-Regular"
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