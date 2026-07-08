#!/usr/bin/env bash
set -euo pipefail

URL="${HEALTH_CHECK_URL:-http://localhost/up}"
TOKEN="${HEALTH_CHECK_TOKEN:-}"
TIMEOUT="${HEALTH_CHECK_TIMEOUT:-10}"

HEADERS=()
if [[ -n "$TOKEN" ]]; then
    HEADERS+=("-H" "Authorization: Bearer $TOKEN")
fi

RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$TIMEOUT" \
    "${HEADERS[@]+"${HEADERS[@]}"}" "$URL" || true)

if [[ "$RESPONSE" != "200" ]]; then
    echo "Health check FAILED: HTTP ${RESPONSE:-(no response)}"
    exit 1
fi

echo "Health check OK (HTTP 200)"
exit 0
