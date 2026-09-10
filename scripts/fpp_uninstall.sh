#!/usr/bin/bash
set -euo pipefail
# Cooperative stop is safe repeatedly and never targets FPP or another plugin.
php "$(dirname -- "$0")/countdown.php" uninstall
