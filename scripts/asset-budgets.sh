#!/usr/bin/env bash
# Public bundle budgets, gzipped (docs/TESTING.md §6): CSS < 25 KB, JS < 30 KB. Run after `npm run build`.
set -euo pipefail
cd "$(dirname "$0")/.."
status=0
check() {
    local pattern=$1 limit=$2 total=0
    for f in public/build/assets/$pattern; do total=$((total + $(gzip -9c "$f" | wc -c))); done
    printf '%-8s %6d bytes gzip (limit %d)\n' "$pattern" "$total" "$limit"
    if [ "$total" -ge "$limit" ]; then status=1; fi
}
check 'app-*.css' 25600
check 'app-*.js' 30720
exit $status
