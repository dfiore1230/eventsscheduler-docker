# EventSchedule Docker Environment

This repository provides a Docker-based runtime for the [EventSchedule](https://github.com/eventschedule/eventschedule) Laravel application. It packages PHP-FPM, Nginx, MariaDB, and a scheduler worker so that the app can be bootstrapped quickly for local development, testing, or small-scale deployments.

## Features

- **Multi-service stack**: PHP-FPM application container, Nginx web server, MariaDB database, and a dedicated scheduler runner.
- **Automated bootstrap**: Composer dependencies, npm assets, database migrations, and the application key are provisioned automatically when the containers start.
- **Persistent volumes**: Shared Docker volumes retain database data, uploaded files, Composer vendors, and Node modules between restarts.
- **Configurable upstream branch**: Build arguments allow pinning to a specific EventSchedule git reference.

## Prerequisites

- Docker Engine 24.0 or newer
- Docker Compose v2 plugin
- Internet access on the build machine to fetch Composer, npm, and git dependencies

## Getting Started

1. Copy the upstream environment template and adjust credentials:
   ```bash
   cp .env.example .env
   # Update DB_PASSWORD and any additional overrides
   ```
2. Start the stack:
   ```bash
   docker compose up --build -d
   ```
3. Visit [http://localhost:8080](http://localhost:8080) to access the application.

The first startup can take several minutes while dependencies are installed and assets are compiled.

## Single-Container Stack (App Bundle + Embedded MariaDB)

For lightweight environments you can run the web server, PHP-FPM worker, scheduler, **and MariaDB** inside a single container.
Application uploads and database files are persisted on bind-mounted directories so that data survives rebuilds without using
named Docker volumes for the app container.

1. Prepare the bind-mount directories (they can live anywhere on your host):
   ```bash
   mkdir -p bind/storage bind/mysql
   ```
2. Start the single-container stack:
   ```bash
   docker compose -f docker-compose.single.yml up --build -d
   ```
3. Access the application at [http://localhost:8080](http://localhost:8080).

## Using the Prebuilt Docker Hub Image

If you would prefer to start from the published image rather than building the
Dockerfile in this repository, pull
[`dfiore/eventsschedule:latest`](https://hub.docker.com/r/dfiore/eventsschedule)
from Docker Hub. You can create a thin wrapper Dockerfile that layers
environment defaults, assets, or other customizations on top of the prebuilt
runtime:

```Dockerfile
FROM dfiore/eventsschedule:latest

# Example override: copy a production-ready .env into the container
# COPY .env.production /var/www/html/.env
```

See [`examples/Dockerfile.from-prebuilt`](examples/Dockerfile.from-prebuilt) for a
fully annotated template that demonstrates common customization points while
reusing the prebuilt container. When you are ready to run the full stack without
rebuilding images, use the companion Compose file at
[`examples/docker-compose.prebuilt.yml`](examples/docker-compose.prebuilt.yml):

```bash
cp .env.example .env
docker compose -f examples/docker-compose.prebuilt.yml up -d
```

The Compose definition wires the published image into the standard `app`,
`web`, and `scheduler` services while leaving the MariaDB dependency unchanged.

Prefer the single-container topology? A matching prebuilt Compose file is available at
[`examples/docker-compose.single-prebuilt.yml`](examples/docker-compose.single-prebuilt.yml):

```bash
cp .env.example .env
mkdir -p bind/storage bind/mysql
docker compose -f examples/docker-compose.single-prebuilt.yml up -d
```

This version layers the published image with bind mounts for Laravel storage and MariaDB data so you can mirror the single-container
topology without rebuilding images.

## Service Overview

| Service    | Description                                                                 |
|------------|-----------------------------------------------------------------------------|
| `app`      | PHP-FPM container running the Laravel application code.                     |
| `web`      | Nginx container serving HTTP traffic and proxying PHP requests to `app`.    |
| `db`       | MariaDB 11 database with credentials controlled by `.env`.                  |
| `scheduler`| Long-running worker that executes `php artisan schedule:run` every minute.  |

## Environment Configuration

Key settings are defined in `.env` and forwarded into the containers. At a minimum you should set `DB_PASSWORD`. Additional variables supported by Laravel (e.g., `APP_URL`, `MAIL_` settings) can be added to tailor the runtime.

The Dockerfile clones the upstream EventSchedule repository. You can change the source branch or tag by editing `APP_REF` in `docker-compose.yml` or passing `--build-arg APP_REF=...` to `docker compose build`.

## Operational Tips

- **Logs**: View service logs with `docker compose logs -f <service>`.
- **Migrations**: The entrypoint runs `php artisan migrate --force` on startup. Run additional artisan commands via `docker compose exec app php artisan ...`.
- **Database access**: Connect to MariaDB on `localhost:3306` (when exposed) using credentials defined in `.env`.
- **Updating dependencies**: Rebuild the `app` image (`docker compose build app`) after modifying Composer or npm dependencies.

## Publishing Images with GitHub Actions

This repository ships with a GitHub Actions workflow named **Build and Publish
Docker image**. The workflow builds the `single` stage of the Dockerfile on
every pull request, push to `main`, and manual run from the **Actions** tab. On
pull requests it performs a build-only dry run, while pushes to `main` publish
the resulting image to Docker Hub when credentials are available.

To enable publishing, create the following secrets in your GitHub repository:

| Secret                 | Description                                           |
|------------------------|-------------------------------------------------------|
| `DOCKERHUB_USERNAME`   | Docker Hub username that owns the target repository. |
| `DOCKERHUB_TOKEN`      | Access token or password for that account.           |
| `DOCKERHUB_REPOSITORY` | Fully-qualified repository name (e.g. `user/image`). |

You can set these secrets through the GitHub web UI or with the
[GitHub CLI](https://cli.github.com/) by running:

```bash
gh secret set DOCKERHUB_USERNAME --body "your-username"
gh secret set DOCKERHUB_TOKEN --body "<access token>"
gh secret set DOCKERHUB_REPOSITORY --body "your-username/eventsschedule"
```

Once configured, pushing to `main` will build, tag, and publish images using the
branch, tag, and `latest` conventions emitted by the workflow. Manual runs from
the Actions tab behave the same way. If the secrets are missing (for example,
when the workflow is triggered from a fork), the workflow still builds the
image to ensure the Dockerfile remains healthy but skips publishing.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a history of notable updates.

## License

This repository packages the upstream EventSchedule application, which is subject to its own license. Review the upstream project for licensing details and ensure compliance when deploying.
