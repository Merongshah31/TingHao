# Render Deployment Guide

This project is prepared for Render using Docker, Nginx, PHP 8.4 FPM, Laravel, Vite-built frontend assets, and Supabase PostgreSQL.

References:

- Render Laravel Docker guide: https://render.com/docs/deploy-php-laravel-docker
- Render Blueprint reference: https://render.com/docs/blueprint-spec

## 1. Files Added For Render

- `Dockerfile`
- `.dockerignore`
- `render.yaml`
- `docker/nginx.conf.template`
- `docker/php.ini`
- `docker/supervisord.conf`
- `scripts/render-start.sh`

The Docker image:

- Installs Composer production dependencies.
- Builds Vite frontend assets.
- Uses PHP 8.4, which is required by the current Laravel/Symfony dependency lockfile.
- Installs PHP PostgreSQL extensions.
- Runs Nginx and PHP-FPM together.
- Runs `php artisan migrate --force` on startup.
- Caches Laravel config, routes, and views.
- Listens on Render's `$PORT`.

## 2. Before Deploying

Generate a Laravel app key locally:

```bash
php artisan key:generate --show
```

Copy the full output, including `base64:`.

Example:

```text
base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
```

## 3. Deploy Using `render.yaml`

1. Push this repository to GitHub.
2. Open Render Dashboard.
3. Choose `New` -> `Blueprint`.
4. Select this GitHub repository.
5. Render will detect `render.yaml`.
6. Fill the prompted `sync: false` values.

Required prompted values:

```env
APP_KEY=base64:your-generated-key
APP_URL=https://your-render-service-name.onrender.com
SUPABASE_DB_HOST=db.your-project-ref.supabase.co
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=your-supabase-database-password
```

If you use the Supabase pooler, your values may look like this:

```env
SUPABASE_DB_HOST=aws-1-ap-northeast-1.pooler.supabase.com
SUPABASE_DB_PORT=6543
SUPABASE_DB_USERNAME=postgres.your-project-ref
```

If you use direct Supabase Postgres, your values usually look like this:

```env
SUPABASE_DB_HOST=db.your-project-ref.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_USERNAME=postgres
```

## 4. Manual Render Web Service Setup

If you do not use Blueprint:

1. Create `New Web Service`.
2. Connect the GitHub repo.
3. Runtime: `Docker`.
4. Branch: `main`.
5. Dockerfile path: `./Dockerfile`.
6. Docker context: `.`.
7. Health check path: `/`.
8. Add the environment variables below.

Required environment variables:

```env
APP_NAME=TingHao
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-generated-key
APP_URL=https://your-render-service-name.onrender.com
LOG_CHANNEL=stderr
LOG_LEVEL=debug

DB_CONNECTION=supabase
SUPABASE_DB_HOST=db.your-project-ref.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_DATABASE=postgres
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=your-supabase-database-password
SUPABASE_DB_SCHEMA=public
SUPABASE_DB_SSLMODE=require

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

## 5. Deployment Behavior

On each deploy, `scripts/render-start.sh` runs:

```bash
php artisan package:discover --ansi
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

This means your Supabase database migrations run automatically when Render starts the service.

## 6. Demo Data

The deployment does not automatically run `db:seed`, because seeding demo data on every production boot can overwrite presentation data.

To add demo data on Render:

1. Open the Render service dashboard.
2. Open `Shell`.
3. Run:

```bash
php artisan db:seed
```

Seeded accounts:

```text
Admin: admin@tinghao.com / password
Staff: staff@tinghao.com / password
```

Change these passwords before using the app beyond a demo.

## 7. Important Notes

- Do not commit `.env`.
- Do not put Supabase passwords directly in `render.yaml`.
- `APP_DEBUG` must be `false` in production.
- `APP_URL` should match your Render URL.
- Render provides HTTPS, and the app forces HTTPS URLs in production.
- Uploaded local files are not persistent on Render free web services unless you add persistent storage. This app currently uses database records and public assets from the repo, so it is fine for the current scope.

## 8. Troubleshooting

### 502 or service failed to start

Check Render logs. Most failures will be missing environment variables or database connection errors.

### Database connection failed

Check:

- `SUPABASE_DB_HOST`
- `SUPABASE_DB_PORT`
- `SUPABASE_DB_USERNAME`
- `SUPABASE_DB_PASSWORD`
- `SUPABASE_DB_SSLMODE=require`

### Assets not loading over HTTPS

Confirm:

```env
APP_ENV=production
APP_URL=https://your-render-service-name.onrender.com
```

### Migrations failed

Open Render Shell and run:

```bash
php artisan migrate:status
```

If needed:

```bash
php artisan migrate --force
```
