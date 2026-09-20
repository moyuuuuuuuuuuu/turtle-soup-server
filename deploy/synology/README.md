# Synology Container Manager deployment

This directory is the working directory for one Container Manager project. Clone the three repositories into
`sources/server`, `sources/ui`, and `sources/admin`, copy `production.env.example` to the untracked
`production.env`, then validate it before starting the project.

```bash
docker compose --env-file production.env config --quiet
docker compose --env-file production.env build
docker compose --env-file production.env run --rm --no-deps server php bin/check-production-config.php --environment
```

The LAN validation endpoints are `http://NAS_IP:18080` for the user H5 application and
`http://NAS_IP:18081` for SaiAdmin. MySQL, Redis, Webman HTTP, and WebSocket are only reachable on the
private Compose network.

`config --quiet` validates Compose without printing expanded passwords. The PHP check reads the
container environment without starting Webman or connecting to MySQL. It prints variable names and
validation results only. Do not use the example file as a production configuration.

Before starting services, create `data/server-runtime` and `data/uploads` and make them writable by
the NAS account specified by `SERVER_UID` / `SERVER_GID` (defaults: `1026:100`). Verify with:

```bash
docker compose --env-file production.env run --rm --no-deps server sh -c 'test -w /app/runtime && test -w /app/public/uploads'
```

The H5 image defaults to `/api/v1` and a same-origin WebSocket connection through the gateway.
Mini-program builds require their own public HTTPS/WSS URLs and configured platform domains.
Fill the mini-program credentials for the providers you enable. Open-platform OAuth remains a
placeholder and must not be presented as a working login method for this release.

After database initialization/migrations have been separately authorized and completed, start with
`docker compose --env-file production.env up -d`. Verify user login, anonymous single-player games,
AI judgement and completion, WebSocket reconnect, and management login/publish before public traffic.
Keep a database backup and the previous image versions for rollback; do not use `down -v` to roll back.

Run database backup and migration commands only after explicit approval. Cloudflare Tunnel is added after LAN
smoke testing so a broken origin is never published accidentally.
