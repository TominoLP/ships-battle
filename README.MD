# Ships App – Development & Deployment Guide

## Requirements

Before you begin, make sure you have the following installed:

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)
- [Node.js (v18+)](https://nodejs.org/)
- [Yarn](https://yarnpkg.com/getting-started/install)

---

## Setup

### Environment Configuration

Before running the app, create your `.env` file.

You can copy the example provided in the repository:

```bash
cp .env.example .env
```

Then open `.env` and update the values as needed:

- Generate an application key:
  ```bash
  php artisan key:generate
  ```
- Adjust URLs (`APP_URL`, `ASSET_URL`) to match your local or production setup.
- Update any WebSocket/Reverb hostnames if you’re deploying remotely.

For Production deployments:
- create a `.env.production` file similarly.
- Ensure all settings match your production environment.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Update database credentials accordingly.
- add `VITE_REVERB_TLS=true`

### 1. Clone the repository

```bash
git clone https://github.com/your-org/ships-app.git
cd ships-app
```

### 2. Install dependencies

```bash
yarn install
```

or

```bash
npm install
```

### 3. Start Docker environment

```bash
docker compose up -d
```

### 4. Create and migrate database

```bash
php artisan migrate
```

If it’s the first time running the project, you may also want to seed the database:

```bash
php artisan db:seed
```

---

## First-time Build (Manual Development Mode)

If you’re setting up the project for the first time:

```bash
# Start backend + database
docker compose up -d

# Run Vite hot reload locally
yarn dev
```

### Development URLs

- Laravel:  http://localhost:8000
- Nginx:    http://localhost:8080
- Vite:     http://localhost:5173
- Reverb:   ws://localhost:8081

---

## Useful Docker Commands

```bash
# Stop environment
docker compose down

# Restart all containers
docker compose restart

# Show logs
docker compose logs -f

# Access Laravel container shell
docker compose exec laravel sh
```

---

## Build & Deploy

### Build Docker Image

The `./build` script creates a clean Docker image of the app using `DockerfileProd`.

```bash
./build [tag]
```

**Examples:**

```bash
./build
./build v1.2.3
```

This will:

- Remove old build artifacts
- Build a new Docker image without cache
- Tag it with `latest` or a custom tag

---

### Deploy Container

Use the `./deploy` script to run the built image in either `dev` or `prod` mode.

```bash
./deploy dev
```

or

```bash
./deploy prod
```

This will:

- Stop and remove existing containers
- Start a new container using the selected `.env` file
- Mount the database storage
- Expose the correct ports (8080 for dev, 6969 for prod)

**After deployment:**

```bash
# Check container status
docker ps

# View logs
docker logs -f ships-app

# Enter container
docker exec -it ships-app sh
```

---

## Maintenance

```bash
# Rebuild all containers from scratch
docker compose build --no-cache

# Clear Laravel caches
docker compose exec laravel php artisan optimize:clear

# Re-run migrations
docker compose exec laravel php artisan migrate:fresh --seed
```

---

## Tips

- Keep your `.env` and `.env.production` files consistent.
- Use `yarn dev` for local development with hot reload.
- Always run `./build` before deploying new versions.
- Logs and container management can be done via Docker CLI.

---

**Author:** Your Company / Team  
**License:** MIT
