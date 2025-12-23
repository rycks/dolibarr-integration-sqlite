#!/usr/bin/bash
# Build a lightweight release zip of Dolibarr with SQLite support
# Excludes .git and all languages except fr_FR and en_US

set -e

# Get version from current branch or tag
VERSION="18.0.8"
OUTPUT_FILE="dolibarr-integration-sqlite-${VERSION}.zip"

echo "Building release: ${OUTPUT_FILE}"

# Build exclusion list for languages
LANG_EXCLUDES=""
if [ -d "htdocs/langs" ]; then
    for lang_dir in htdocs/langs/*/; do
        lang_name=$(basename "$lang_dir")
        if [ "$lang_name" != "fr_FR" ] && [ "$lang_name" != "en_US" ]; then
            LANG_EXCLUDES="${LANG_EXCLUDES} --exclude=htdocs/langs/${lang_name}/*"
        fi
    done
fi

# Create temporary directory with release name
RELEASE_DIR="dolibarr-integration-sqlite-${VERSION}"
rm -rf "$RELEASE_DIR"
mkdir "$RELEASE_DIR"

# Build rsync exclusion list
RSYNC_EXCLUDES="--exclude=.git --exclude=*.zip --exclude=build-release.sh --exclude=${RELEASE_DIR}"
if [ -d "htdocs/langs" ]; then
    for lang_dir in htdocs/langs/*/; do
        lang_name=$(basename "$lang_dir")
        if [ "$lang_name" != "fr_FR" ] && [ "$lang_name" != "en_US" ]; then
            RSYNC_EXCLUDES="${RSYNC_EXCLUDES} --exclude=htdocs/langs/${lang_name}"
        fi
    done
fi

# Copy files to release directory
rsync -a $RSYNC_EXCLUDES ./ "$RELEASE_DIR/"

# Create zip from release directory
zip -r "$OUTPUT_FILE" "$RELEASE_DIR"

# Cleanup
rm -rf "$RELEASE_DIR"

# Show result
ZIP_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
echo ""
echo "Release built successfully:"
echo "  File: ${OUTPUT_FILE}"
echo "  Size: ${ZIP_SIZE}"
