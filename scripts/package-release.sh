#!/usr/bin/env bash
# Packs a release with an include list: only what runs on the server ships (ADR-0003).
#   scripts/package-release.sh <out.tgz>   pack the release (after composer --no-dev and npm run build)
#   scripts/package-release.sh --check     fail if a tracked top-level or resources/ entry is in neither list
# A new top-level file or folder must be added to SHIP or DEV_ONLY below; the check runs in CI (Pest).
set -euo pipefail
cd "$(dirname "$0")/.."

# Shipped to the server. RELEASE is written by the deploy build.
SHIP=(RELEASE app artisan bootstrap composer.json composer.lock config database deploy public routes vendor
    resources/fonts resources/og resources/views)

# Never shipped: tooling, docs, tests and build sources (@vite reads public/build/manifest.json in production).
DEV_ONLY=(.claude .editorconfig .env.example .gitattributes .github .gitignore .npmrc CLAUDE.md LICENSE README.md
    SECURITY.md docs lighthouserc.json node_modules package-lock.json package.json phpstan.neon phpunit.xml
    pint.json scripts storage tests vite.config.js resources/css resources/js)

known() {
    local entry=$1 item
    for item in "${SHIP[@]}" "${DEV_ONLY[@]}"; do [ "$item" = "$entry" ] && return 0; done
    return 1
}

if [ "${1:-}" = "--check" ]; then
    unknown=()
    while IFS= read -r entry; do
        known "$entry" || unknown+=("$entry")
    done < <({ git ls-files | cut -d/ -f1; git ls-files resources | cut -d/ -f1-2; } | grep -vx resources | sort -u)
    if [ ${#unknown[@]} -gt 0 ]; then
        echo "Not in SHIP or DEV_ONLY (scripts/package-release.sh): ${unknown[*]}" >&2
        exit 1
    fi
    echo "Release package lists cover every tracked entry."
    exit 0
fi

out=${1:?usage: package-release.sh <out.tgz> | --check}
for item in "${SHIP[@]}"; do
    [ -e "$item" ] || { echo "Missing release entry: $item" >&2; exit 1; }
done
tar -czf "$out" --exclude='database/*.sqlite' --exclude='public/hot' "${SHIP[@]}"
echo "Packed ${#SHIP[@]} entries into $out"
