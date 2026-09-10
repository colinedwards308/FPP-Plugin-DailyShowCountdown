#!/usr/bin/bash
set -eo pipefail
: "${FPPDIR:=/opt/fpp}"
. "${FPPDIR}/scripts/common"
set -u
PLUGIN_DIR=$(cd -- "$(dirname -- "$0")/.." && pwd)
php -r 'foreach (["curl_init", "pcntl_fork", "posix_setsid"] as $f) { if (!function_exists($f)) { fwrite(STDERR, "Missing PHP capability: $f\n"); exit(1); } }'
python3 -c 'from PIL import Image, ImageFont'
command -v fc-list >/dev/null
command -v timeout >/dev/null
install -d -o fpp -g fpp -m 2775 "${MEDIADIR}/plugindata/FPP-Countdown-Advanced"
touch "${LOGDIR}/plugin-FPP-Countdown-Advanced.log"
chown fpp:fpp "${LOGDIR}/plugin-FPP-Countdown-Advanced.log"
chmod 664 "${LOGDIR}/plugin-FPP-Countdown-Advanced.log"
chmod 755 "$PLUGIN_DIR/callbacks.sh" "$PLUGIN_DIR"/commands/*.sh "$PLUGIN_DIR"/scripts/*.sh
# FPP live-registers commands through callbacks.sh; no FPPD restart is requested.
