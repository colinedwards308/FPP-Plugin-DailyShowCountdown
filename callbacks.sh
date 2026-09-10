#!/usr/bin/bash
case "${1:-}" in
    --list) printf '%s\n' 'lifecycle' ;;
    --type)
        if [[ "${2:-}" == lifecycle && "${3:-}" == shutdown ]]; then
            exec php "$(dirname -- "$0")/scripts/countdown.php" stop
        fi
        ;;
esac
exit 0
