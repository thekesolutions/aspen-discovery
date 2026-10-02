# Aspen Discovery - Docker

## Quick Start

1. Download the compose file and the environment template into an empty directory:

   ```bash
   mkdir aspen-docker && cd aspen-docker
   ASPEN_VERSION=26.10.00
   curl -O https://raw.githubusercontent.com/Aspen-Discovery/aspen-discovery/$ASPEN_VERSION/docker/docker-compose.yml
   curl -o .env https://raw.githubusercontent.com/Aspen-Discovery/aspen-discovery/$ASPEN_VERSION/docker/files/env/default.env
   ```

2. Optionally edit `.env`. Every variable is commented out and has a default, so you can skip this
   step to try Aspen locally. Before going live, see [Going Live](#going-live).

3. Start Aspen and wait for initialization to finish:

   ```bash
   docker compose up -d
   docker compose logs -f backend
   # Look for: "Starting PHP-FPM in foreground mode..."
   ```

4. Open http://localhost:85 and log in as `aspen_admin` with password `aspenPass123` (or the
   `ASPEN_ADMIN_PASSWORD` you set).

The `.env` file must exist, even if it is empty.

## How Configuration Works

All settings come from environment variables in `.env`. A variable that is not set, or set to an
empty value, uses its default. The backend turns them into Aspen's configuration files:

- **First start:** the site configuration is generated and the database is initialized.
- **Every start:** variables marked "Every start" below are copied into the existing configuration,
  so changing them in `.env` and recreating the containers updates Aspen.

Variables marked "First start" are only read when the site or database is created. Changing them
later has no effect; see the variable's description for how to change it afterwards.

"Every start" variables overwrite the matching configuration values each time, so change them in
`.env`, not in the configuration files. A variable left out of `.env`, or set to an empty value, is
reset to its default.

## Environment Variables

### Site

| Variable | Default | Applies | Description |
|----------|---------|---------|-------------|
| `URL` | `http://localhost:85` | Every start | Public URL users reach Aspen at |
| `TITLE` | `Aspen Discovery` | Every start | Site title |
| `LIBRARY` | `Test Library` | Every start | Library name |
| `TZ` | `UTC` | Every start | Container and Aspen timezone, e.g. `America/Chicago` |
| `ASPEN_ADMIN_PASSWORD` | `aspenPass123` | First start | Password for `aspen_admin`. Change it later from Aspen's admin interface |
| `SUPPORTING_COMPANY` | `ByWater Solutions` | First start | Support company shown in Aspen. Change it later in Aspen's system variables |
| `SITE_NAME` | `aspen` | First start | Site identifier. Changing it later creates a new, empty site |
| `ASPEN_HOME` | `./aspen` | Every start | Host directory for the instance's conf, data, logs and database |
| `LOCAL_USER_ID` | | Every start | UID that should own the files in `ASPEN_HOME` (e.g. the output of `id -u`). Unset keeps the container's `www-data` |
| `DEBUG` | `false` | Every start | Aspen debug mode |
| `FILE_GROUP_OWNER` | `www-data` | Every start | Group assigned to directories Aspen creates for uploaded files |

### Database

| Variable | Default | Applies | Description |
|----------|---------|---------|-------------|
| `DATABASE_HOST` | `db` | Every start | MariaDB hostname |
| `DATABASE_PORT` | `3306` | Every start | MariaDB port |
| `DATABASE_NAME` | `aspen` | Every start | Database name |
| `DATABASE_USER` | `aspenusr` | Every start | Database user |
| `DATABASE_PASSWORD` | `aspenpasswd` | Every start | Database password |
| `DATABASE_ROOT_PASSWORD` | `password` | First start | MariaDB root password |
| `DATABASE_SSL_MODE` | `DISABLED` | Every start | When `DISABLED`, the mariadb CLI client connects with `--skip-ssl` (needed since mariadb-client 11.x defaults to requiring TLS). Set to `REQUIRED` only when the db service has TLS configured, e.g. via a compose overlay adding certs |

The bundled MariaDB creates the database and user only on its first start. To change
`DATABASE_NAME`, `DATABASE_USER` or `DATABASE_PASSWORD` afterwards, make the change in MariaDB
first, then in `.env`.

### Services

| Variable | Default | Applies | Description |
|----------|---------|---------|-------------|
| `SOLR_HOST` | `solr` | Every start | Solr hostname |
| `SOLR_PORT` | `8983` | Every start | Solr port, also published on the host |
| `PHP_FPM_HOST` | `backend` | First start | PHP-FPM hostname |
| `PHP_FPM_PORT` | `9000` | First start | PHP-FPM port |

### Docker Images

| Variable | Default | Description |
|----------|---------|-------------|
| `BACKEND_IMAGE_TAG` | `aspendiscovery/aspen` | Backend image |
| `SOLR_IMAGE_TAG` | `aspendiscovery/solr` | Solr image |
| `MARIADB_IMAGE` | `mariadb:10.5` | MariaDB image |

## Going Live

The defaults are meant for trying Aspen locally. Before going live, set at least these in `.env`:

- `URL`, `TITLE` and `LIBRARY` for your site
- `TZ` for your location
- `ASPEN_ADMIN_PASSWORD`, `DATABASE_PASSWORD` and `DATABASE_ROOT_PASSWORD`. The defaults are publicly
  known, and the backend logs a warning while they are in use. Set them before the first start, since
  the admin password and the database users are created then.

## Changing Configuration

1. Edit your `.env` file
2. Recreate the containers:
   ```bash
   docker compose up -d --force-recreate
   ```

Only "Every start" variables are applied this way, see [How Configuration Works](#how-configuration-works).

## Scheduled Jobs

The cron container rebuilds `conf/crontab` (inside `ASPEN_HOME`) from the image on every start, so
don't edit it directly. To add site-specific jobs, put them in `conf/crontab_local`; it is appended to
the generated crontab. If a start replaces a crontab that differs from the image's version, the
previous file is kept as `conf/crontab.bak`.

## Services & Ports

| Service | Port | Description |
|---------|------|-------------|
| apache | 85 | Web interface |
| solr | 8983 | Search engine admin |
| backend | - | PHP-FPM (internal) |
| cron | - | Background tasks (internal) |
| db | - | MariaDB (internal) |

## Common Commands

```bash
# View logs
docker compose logs -f backend

# Shell access
docker compose exec backend bash

# Stop services
docker compose down

# Apply configuration changes
docker compose up -d --force-recreate

# Full reset (deletes all data, including the database)
docker compose down -v
rm -rf aspen/   # or your ASPEN_HOME
```
