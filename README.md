# Speedtest Monitor

Runs the [Ookla Speedtest CLI](https://www.speedtest.net/apps/cli) on a schedule in Docker and shows the results on a web page.

- **`speedtest`**: cron runs the CLI against a random server from `config/servers.txt` and saves each result as `data/<timestamp>.json`.
- **`web`**: PHP page with a summary and a table of all runs at <http://localhost:16000>.

## Usage

```sh
docker compose up -d --build                     # start
docker compose exec speedtest run-speedtest.sh   # run a test now
docker compose logs -f speedtest                 # logs
```

## Configuration

- **Schedule and options:** set them in `docker-compose.yml`: `CRON_SCHEDULE`, `TZ`, `RUN_ON_START` (also test when the container starts), and `MAX_ATTEMPTS` (servers to try before Ookla picks one automatically).
- **Servers:** `config/servers.txt` holds one ID per line, and `#` starts a comment. Edits apply from the next run. To list nearby servers, run `docker compose exec speedtest speedtest -L`.
- **Warning threshold and display time zone:** set them at the top of `web/index.php`.
