# EventSchedule Docker Environment

This repository provides a Docker-based runtime for the [EventSchedule](https://github.com/eventschedule/eventschedule) Laravel application. It packages PHP-FPM, Nginx, MariaDB, and a scheduler worker so that the app can be bootstrapped quickly for local development, testing, or small-scale deployments.

## Features

- **Multi-service stack**: PHP-FPM application container, Nginx web server, MariaDB database, and a dedicated scheduler runner.
- **Automated bootstrap**: Composer dependencies, npm assets, database migrations, and the application key are provisioned automatically when the containers start.
- **Persistent bind mounts**: Shared host directories retain database data, uploaded files, Composer vendors, and Node modules between restarts.
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
2. (Optional) Create host directories for bind-mounted data so permissions can be adjusted ahead of time:
   ```bash
   mkdir -p data/{db,storage,vendor,node_modules}
   ```

3. Start the stack. Choose the workflow that matches your situation:

   - **Build locally:**
     ```bash
     docker compose up --build -d
     ```
     This rebuilds the `eventschedule/app:local`, `eventschedule/web:local`, and
     `eventschedule/scheduler:local` images on your machine and then boots the
     stack.

   - **Use images published by GitHub Actions:**
     ```bash
     export APP_IMAGE="<dockerhub-repo>:app"
     export WEB_IMAGE="<dockerhub-repo>:web"
     export SCHEDULER_IMAGE="<dockerhub-repo>:scheduler"
     docker compose pull
     docker compose up -d --no-build
     ```
     Replace `<dockerhub-repo>` with the repository you configured in
     `.github/.dockerhub-credentials` (for example `acme/eventschedule`). The
     workflow pushes tags for each service (`:app`, `:web`, and `:scheduler`),
     allowing `docker compose` to pull the artifacts instead of rebuilding
     locally.

   > **Tip:** Running `docker build` alone only produces the PHP-FPM image from
   > the `Dockerfile`. Use `docker compose up` so the `web`, `db`, and
   > `scheduler` services start alongside the `app` container.
4. Visit [http://localhost:8080](http://localhost:8080) to access the application.

The first startup can take several minutes while dependencies are installed and assets are compiled.

### Running alongside other Docker projects

If you already have other Compose stacks running on your machine, use the
provided [`docker-compose.extras.example.yml`](docker-compose.extras.example.yml)
as an override file so EventSchedule can share the host without port
collisions. The override adjusts the published ports and optionally connects the
services to a shared user-defined bridge network so they can be discovered by an
existing reverse proxy or other containers.

1. Create the shared network if you want the stack to communicate with other
   Compose projects:

   ```bash
   docker network create shared-services
   ```

2. Start the stack with the override to publish alternate ports (change the
   `WEB_PORT` and `DB_HOST_PORT` values if you prefer different mappings):

   ```bash
   WEB_PORT=18080 DB_HOST_PORT=13306 \
   docker compose -f docker-compose.yml -f docker-compose.extras.example.yml up -d
   ```

   To use a different external network name, set `SHARED_NETWORK=<network>` when
   running the command or edit the override file to match your environment.

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

This repository ships with a reusable GitHub Actions workflow that can build and
publish the Docker images straight to Docker Hub. To authenticate with your own
registry account:

1. Copy the bundled credentials template and update it with your Docker Hub
   account details:
   ```bash
   cp .github/.dockerhub-credentials.example .github/.dockerhub-credentials
   # edit the file to set your username, access token, and repository name
   ```
   The real credentials file is ignored by git so it stays local to your
   machine.
2. Upload the credentials to your GitHub repository secrets by running the
   helper script (requires the [GitHub CLI](https://cli.github.com/) to be
   installed and authenticated for the repository):
   ```bash
   ./scripts/publish-dockerhub-secrets.sh
   ```

   The script reads `.github/.dockerhub-credentials` and configures the
   `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`, and `DOCKERHUB_REPOSITORY` secrets in
   your GitHub repository.

Once those secrets are configured, you can trigger the workflow in either of two
ways:

- Push or merge changes into the `main` branch. The workflow will build the
  images and push the tagged artifacts to Docker Hub automatically.
- Run the workflow manually from the **Actions** tab by selecting “Build and
  Publish Docker image” and clicking **Run workflow**.

The workflow publishes three tags—`:app`, `:web`, and `:scheduler`—along with
per-commit variants (e.g., `:web-<sha>`). Pull requests continue to run the
workflow in build-only mode so you can confirm the Dockerfile still builds
without publishing artifacts.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a history of notable updates.

## License

This repository packages the upstream EventSchedule application, which is subject to its own license. Review the upstream project for licensing details and ensure compliance when deploying.
