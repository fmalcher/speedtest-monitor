#!/bin/sh
# Run one Ookla speedtest and store the raw JSON result in /data/<UTC timestamp>.json
# Server: random pick from SERVERS_FILE; on failure, try other servers, then auto-select.
set -eu

DATA_DIR="${DATA_DIR:-/data}"
SERVERS_FILE="${SERVERS_FILE:-/etc/speedtest/servers.txt}"
MAX_ATTEMPTS="${MAX_ATTEMPTS:-3}"
NAME="$(date -u +%Y-%m-%dT%H-%M-%SZ).json"
TMP="${DATA_DIR}/.${NAME}.tmp"

# Random order of server IDs (comments and blank lines ignored)
CANDIDATES=""
if [ -f "$SERVERS_FILE" ]; then
  CANDIDATES="$(sed -e 's/#.*//' -e 's/[[:space:]]//g' "$SERVERS_FILE" | grep -E '^[0-9]+$' | shuf | head -n "$MAX_ATTEMPTS" || true)"
fi

run_test() {
  # $1: server ID, or empty for auto-select
  if [ -n "$1" ]; then
    echo "[$(date)] Running speedtest against server $1..."
    speedtest --accept-license --accept-gdpr --format=json --server-id="$1" > "$TMP"
  else
    echo "[$(date)] Running speedtest (auto-selected server)..."
    speedtest --accept-license --accept-gdpr --format=json > "$TMP"
  fi
}

for id in $CANDIDATES ""; do
  if run_test "$id"; then
    chmod 644 "$TMP"
    mv "$TMP" "${DATA_DIR}/${NAME}"
    echo "[$(date)] Saved ${NAME}"
    exit 0
  fi
  echo "[$(date)] Speedtest failed${id:+ for server $id}" >&2
done

rm -f "$TMP"
echo "[$(date)] All attempts failed" >&2
exit 1
