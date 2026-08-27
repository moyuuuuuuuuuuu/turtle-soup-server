# Synology Container Manager deployment

This directory is the working directory for one Container Manager project. Clone the three repositories into
`sources/server`, `sources/ui`, and `sources/admin`, copy `production.env.example` to the untracked
`production.env`, then validate it before starting the project.

```bash
docker compose --env-file production.env config
docker compose --env-file production.env build
docker compose --env-file production.env up -d
```

The LAN validation endpoints are `http://NAS_IP:18080` for the user H5 application and
`http://NAS_IP:18081` for SaiAdmin. MySQL, Redis, Webman HTTP, and WebSocket are only reachable on the
private Compose network.

Run database backup and migration commands only after explicit approval. Cloudflare Tunnel is added after LAN
smoke testing so a broken origin is never published accidentally.
