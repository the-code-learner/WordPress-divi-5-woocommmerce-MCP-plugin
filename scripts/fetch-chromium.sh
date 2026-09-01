#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT_DIR}/config/chromium-bundle.env"

TARGET_ROOT="${ROOT_DIR}/bin/linux-x86_64/chrome-headless-shell"
TARGET_BIN="${TARGET_ROOT}/chrome-headless-shell"
VERSION_FILE="${TARGET_ROOT}/.bundle-version"

if [[ -x "${TARGET_BIN}" && -f "${VERSION_FILE}" ]] && [[ "$(cat "${VERSION_FILE}")" == "${CHROMIUM_VERSION}" ]]; then
  printf 'Bundled Chromium %s already present.\n' "${CHROMIUM_VERSION}"
  exit 0
fi

for command in curl unzip sha256sum; do
  command -v "${command}" >/dev/null 2>&1 || {
    printf '%s is required to prepare the bundled Chromium renderer.\n' "${command}" >&2
    exit 1
  }
done

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

ARCHIVE="${TMP_DIR}/chrome-headless-shell-linux64.zip"
EXTRACT_DIR="${TMP_DIR}/extract"

curl \
  --fail \
  --location \
  --proto '=https' \
  --tlsv1.2 \
  --retry 3 \
  --output "${ARCHIVE}" \
  "${CHROMIUM_ARCHIVE_URL}"

printf '%s  %s\n' "${CHROMIUM_ARCHIVE_SHA256}" "${ARCHIVE}" | sha256sum --check --strict

mkdir -p "${EXTRACT_DIR}"
unzip -q "${ARCHIVE}" -d "${EXTRACT_DIR}"

SOURCE_DIR="${EXTRACT_DIR}/chrome-headless-shell-linux64"
test -f "${SOURCE_DIR}/chrome-headless-shell"
test -f "${SOURCE_DIR}/LICENSE.headless_shell"
test -f "${SOURCE_DIR}/ABOUT"

rm -rf "${TARGET_ROOT}"
mkdir -p "$(dirname "${TARGET_ROOT}")"
mv "${SOURCE_DIR}" "${TARGET_ROOT}"
chmod 0755 "${TARGET_BIN}"
printf '%s\n' "${CHROMIUM_VERSION}" > "${VERSION_FILE}"

printf 'Prepared Chrome Headless Shell %s at %s\n' "${CHROMIUM_VERSION}" "${TARGET_ROOT}"
