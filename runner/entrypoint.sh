#!/bin/sh
set -eu

CRON_SCHEDULE="${CRON_SCHEDULE:-0 12 * * *}"

mkdir -p /data

# Cron job output goes to the container's stdout/stderr so it shows up in `docker compose logs`.
echo "${CRON_SCHEDULE} /usr/local/bin/run-speedtest.sh > /proc/1/fd/1 2> /proc/1/fd/2" > /etc/crontabs/root
echo "Speedtest scheduled: '${CRON_SCHEDULE}' (TZ=${TZ:-UTC})"

if [ "${RUN_ON_START:-false}" = "true" ]; then
  /usr/local/bin/run-speedtest.sh || true
fi

exec crond -f -l 8
