#!/usr/bin/env bash
set -euo pipefail

SLUG="${1:-mcp-bridge-for-divi-woocommerce}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT_DIR}/build"
STAGE_DIR="${BUILD_DIR}/${SLUG}"
ZIP_PATH="${BUILD_DIR}/${SLUG}.zip"

command -v rsync >/dev/null 2>&1 || {
  echo "rsync is required to build the distributable." >&2
  exit 1
}

command -v zip >/dev/null 2>&1 || {
  echo "zip is required to build the distributable." >&2
  exit 1
}

"${ROOT_DIR}/scripts/fetch-chromium.sh"

rm -rf "${BUILD_DIR}"
mkdir -p "${STAGE_DIR}"

rsync -a \
  --exclude-from="${ROOT_DIR}/.distignore" \
  "${ROOT_DIR}/" \
  "${STAGE_DIR}/"

(
  cd "${BUILD_DIR}"
  zip -qr "${SLUG}.zip" "${SLUG}"
)

printf 'Built %s\n' "${ZIP_PATH}"
