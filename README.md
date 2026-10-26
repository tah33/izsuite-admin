# izSuite Admin & API

Laravel 12 backend for izSuite. This project contains the admin panel and the
REST API consumed by the Nuxt frontend.

The project runs in Docker. You do not need to install PHP, Composer, MySQL,
Redis, or Node on the server or your local machine.

| Component | Version |
|---|---|
| Runtime | FrankenPHP 1.x / PHP 8.4.27 |
| Framework | Laravel 12 |
| Database | MySQL 8.4 |
| Cache / sessions / queue | Redis 7 |
| Assets | Vite 7 + Tailwind 4 |

## Prerequisites

Install Docker Desktop or Docker Engine with Compose v2.

Check Compose:

```bash
docker compose version
```

## Quick Start

Clone the backend:

```bash
git clone git@github.com:shuvo1610/izsuite-admin.git
cd izsuite-admin
```

Create the shared Docker network. This network is also used by the frontend
project, so create it once before starting either stack:

```bash
docker network create izsuite-public
```

Create the environment file:

```bash
cp .env.example .env
```

Generate an app key:

```bash
docker compose run --rm --no-deps --entrypoint php app -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Paste the generated `base64:...` value into `APP_KEY=` in `.env`.

Start the backend:

```bash
docker compose up -d
```

Watch startup logs:

```bash
docker compose logs -f app
```

The API/admin backend will be available at:

```text
http://127.0.0.1:${APP_PORT}
```

By default, `APP_PORT=8000` in `.env.example`.

Health check:

```bash
curl http://127.0.0.1:8000/up
```

## Frontend Network

The frontend project should join the same external network:

```text
izsuite-public
```

The backend app joins that network with the alias:

```text
api
```

So server-side frontend code can reach the backend at:

```text
http://api:8000
```

Browser-facing frontend code should use a public URL or same-origin proxy, not
`http://api:8000`, because that hostname only exists inside Docker.

## Services

| Container | Role | Host exposure |
|---|---|---|
| `izsuite-api` | Laravel app | `127.0.0.1:${APP_PORT}->8000` |
| `izsuite-mysql` | MySQL database | internal only |
| `izsuite-redis` | Redis cache/session/queue | internal only |
| `izsuite-queue` | Laravel queue worker | none |
| `izsuite-scheduler` | Laravel scheduler | none |

MySQL and Redis are not published to the host. They are reachable only by
containers on the internal Docker network.

## Environment

Important `.env` values:

| Variable | Example | Notes |
|---|---|---|
| `APP_KEY` | `base64:...` | Required before the app can start |
| `APP_PORT` | `8000` | Host port bound to `127.0.0.1` |
| `APP_URL` | `https://admin.example.com` | Public backend/admin URL |
| `FRONTEND_URL` | `https://example.com` | Public frontend URL |
| `DB_DATABASE` | `izsuite` | MySQL database name |
| `DB_USERNAME` | `izsuite` | MySQL app user |
| `DB_PASSWORD` | strong password | MySQL app user password |
| `DB_ROOT_PASSWORD` | strong password | MySQL root password |
| `CACHE_STORE` | `redis` | Redis is used by default |
| `SESSION_DRIVER` | `redis` | Redis-backed sessions |
| `QUEUE_CONNECTION` | `redis` | Redis-backed queues |
| `SESSION_DOMAIN` | `.example.com` | Use for shared auth across subdomains |
| `SANCTUM_STATEFUL_DOMAINS` | `example.com,admin.example.com` | Required for Sanctum SPA cookies |

Database credentials are used only when MySQL initializes an empty data volume.
If MySQL has already started once, changing `DB_PASSWORD` in `.env` will not
change the existing database user's password. For a fresh local reset:

```bash
docker compose down -v
docker compose up -d
```

This deletes local database data.

## Common Commands

Show containers:

```bash
docker compose ps
```

Run migrations:

```bash
docker compose exec app php artisan migrate
```

Seed local demo data:

```bash
docker compose exec app php artisan db:seed
```

Open a shell in the app container:

```bash
docker compose exec app bash
```

Open Tinker:

```bash
docker compose exec app php artisan tinker
```

Run tests:

```bash
docker compose exec app php artisan test
```

View logs:

```bash
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f scheduler
```

Restart workers after code changes:

```bash
docker compose restart queue scheduler
```

Rebuild after changing Dockerfile, Composer dependencies, or Node dependencies:

```bash
docker compose up -d --build
```

Stop containers:

```bash
docker compose down
```

Stop containers and delete local database/cache volumes:

```bash
docker compose down -v
```

## Production

Production uses `docker-compose.prod.yml` on top of the base compose file:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Production differences:

- Builds the `production` Docker target.
- Installs Composer dependencies without `require-dev`.
- Builds Vite assets into the image.
- Removes source bind mounts.
- Persists `storage/` in a named Docker volume.
- Runs Laravel Octane (FrankenPHP) instead of `php artisan serve`. The framework stays booted across requests for higher throughput; dev still uses `artisan serve` for instant reloads. `OCTANE_SERVER=frankenphp` must be set (see `.env.example`).
- Keeps `APP_DEBUG=false` through `PRODUCTION_APP_DEBUG=false`.

Recommended production `.env` values:

```env
APP_ENV=production
APP_DEBUG=false
PRODUCTION_APP_DEBUG=false
APP_URL=https://admin.example.com
FRONTEND_URL=https://example.com

DB_DATABASE=izsuite
DB_USERNAME=izsuite
DB_PASSWORD=change-this
DB_ROOT_PASSWORD=change-this-too

SESSION_DOMAIN=.example.com
SANCTUM_STATEFUL_DOMAINS=example.com,admin.example.com
```

Set database passwords before the first production `up`. If MySQL already
initialized its volume, changing `.env` later will not update those users.

## Deployment

The GitHub Actions deploy workflow is in:

```text
.github/workflows/deploy.yml
```

Required repository secrets:

```text
HOST
USERNAME
PORT
SSH_KEY
```

The workflow:

- Pulls the latest code on the server.
- Ensures `izsuite-public` exists.
- Builds the production app, queue, and scheduler images.
- Starts MySQL and Redis.
- Recreates the app container.
- Restarts queue and scheduler so they use the new release.
- Checks the `/up` endpoint.

## Troubleshooting

`network izsuite-public declared as external, but could not be found`

Create the shared network:

```bash
docker network create izsuite-public
```

`APP_KEY is not set`

Generate an app key and put it in `.env`:

```bash
docker compose run --rm --no-deps --entrypoint php app -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

`port is already allocated`

Change `APP_PORT` in `.env`, then restart:

```bash
docker compose up -d
```

`Class "...ServiceProvider" not found` or `vite: not found`

Old anonymous `vendor` or `node_modules` volumes may be stale. For local
development, reset them:

```bash
docker compose down -v
docker compose up -d --build
```

`getaddrinfo for mysql failed`

Start the database container first, or run commands against the already-running
app container:

```bash
docker compose up -d mysql redis
docker compose exec app php artisan migrate
```

Container is unhealthy

Check app logs:

```bash
docker compose logs --tail=200 app
```
