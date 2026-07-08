#!/bin/bash

echo "=== Cwt Academy Runtime Integrity Check ==="
echo "Date: $(date)"
echo ""

BASELINE_FILE=".file-integrity-baseline"
APP_DIR="app"

if [ ! -f "$BASELINE_FILE" ]; then
    echo "Generating new baseline..."
    find "$APP_DIR" -type f -name "*.php" -exec sha256sum {} \; > "$BASELINE_FILE"
    echo "Baseline saved to $BASELINE_FILE"
    exit 0
fi

echo "Verifying file integrity..."
TEMP_HASHES=$(mktemp)
find "$APP_DIR" -type f -name "*.php" -exec sha256sum {} \; > "$TEMP_HASHES"

CHANGES=$(diff "$BASELINE_FILE" "$TEMP_HASHES")

if [ -n "$CHANGES" ]; then
    echo "⚠️  WARNING: File changes detected!"
    echo "$CHANGES"
    rm "$TEMP_HASHES"
    exit 1
else
    echo "✅ All files verified: No changes detected."
    rm "$TEMP_HASHES"
    exit 0
fi
