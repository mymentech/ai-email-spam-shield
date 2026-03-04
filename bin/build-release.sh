#!/usr/bin/env bash
# build-release.sh — builds ai-email-spam-shield.zip for GitHub release upload.
# Run from the plugin root directory.
set -euo pipefail

PLUGIN_SLUG="ai-email-spam-shield"
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
OUTPUT_ZIP="${PLUGIN_DIR}/../${PLUGIN_SLUG}.zip"

echo "Building release zip for ${PLUGIN_SLUG}..."

# Remove previous build if present.
rm -f "${OUTPUT_ZIP}"

# Zip the plugin directory, excluding files listed in .distignore.
cd "${PLUGIN_DIR}/.."

zip -r "${OUTPUT_ZIP}" "${PLUGIN_SLUG}/" \
    --exclude "${PLUGIN_SLUG}/.git/*" \
    --exclude "${PLUGIN_SLUG}/.claude/*" \
    --exclude "${PLUGIN_SLUG}/.env" \
    --exclude "${PLUGIN_SLUG}/.env.*" \
    --exclude "${PLUGIN_SLUG}/.phpunit.result.cache" \
    --exclude "${PLUGIN_SLUG}/composer.json" \
    --exclude "${PLUGIN_SLUG}/composer.lock" \
    --exclude "${PLUGIN_SLUG}/docker-compose.yml" \
    --exclude "${PLUGIN_SLUG}/docker-compose-sample.yml" \
    --exclude "${PLUGIN_SLUG}/phpunit.xml" \
    --exclude "${PLUGIN_SLUG}/CLAUDE.md" \
    --exclude "${PLUGIN_SLUG}/.distignore" \
    --exclude "${PLUGIN_SLUG}/.gitignore" \
    --exclude "${PLUGIN_SLUG}/docs/*" \
    --exclude "${PLUGIN_SLUG}/spam-api/*" \
    --exclude "${PLUGIN_SLUG}/bin/*" \
    --exclude "${PLUGIN_SLUG}/tests/*" \
    --exclude "${PLUGIN_SLUG}/vendor/*"

echo "Done: ${OUTPUT_ZIP}"
echo "Upload this file as a release asset named '${PLUGIN_SLUG}.zip' on GitHub."
