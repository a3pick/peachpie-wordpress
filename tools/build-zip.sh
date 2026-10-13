#!/usr/bin/env bash
# Build an installable zip of the Media Sweeper plugin (tests and dev files excluded).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/media-sweeper.zip}"
TMP="$(mktemp -d)"
cp -r "$ROOT/MyContent/plugins/media-sweeper" "$TMP/media-sweeper"
find "$TMP" -name '.DS_Store' -delete
( cd "$TMP" && zip -qr "$OUT" media-sweeper )
rm -rf "$TMP"
echo "Built: $OUT"
