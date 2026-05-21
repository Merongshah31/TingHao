# Supabase Setup For Ting Hao

This project uses Laravel for both frontend and backend. Supabase will be used as the PostgreSQL database.

Current local status:

- Laravel app is already configured with a dedicated `supabase` database connection.
- PHP PostgreSQL drivers are available: `pdo_pgsql` and `pgsql`.
- The real `.env` file still uses SQLite until Supabase credentials are added.
- `.env.example` contains safe Supabase placeholders.

## 1. Architecture

```text
Browser
  -> Laravel Blade UI
  -> Laravel routes, controllers, validation, auth
  -> Supabase PostgreSQL database
```

Laravel will handle:

- Login and logout
- Admin and staff pages
- Inventory logic
- Form validation
- Database migrations
- Reports and queries

Supabase will store:

- Users
- Products
- Suppliers
- Stock movements
- Sales and report data
- Sessions, cache, and queued jobs if those Laravel drivers stay database-backed

## 2. Get Supabase Database Credentials

In Supabase:

1. Open your Supabase project.
2. Go to `Project Settings`.
3. Open `Database`.
4. Find the connection information.
5. Use the direct PostgreSQL connection values for Laravel.

You need:

- Host
- Port
- Database name
- Username
- Password
- SSL mode

Typical values look like this:

```env
SUPABASE_DB_HOST=db.your-project-ref.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_DATABASE=postgres
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=your-database-password
SUPABASE_DB_SCHEMA=public
SUPABASE_DB_SSLMODE=require
```

## 3. Update `.env`

Keep your real credentials only in `.env`. Do not commit real passwords.

Change:

```env
DB_CONNECTION=sqlite
```

To:

```env
DB_CONNECTION=supabase
```

Then add:

```env
SUPABASE_DB_HOST=db.your-project-ref.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_DATABASE=postgres
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=your-database-password
SUPABASE_DB_SCHEMA=public
SUPABASE_DB_SSLMODE=require
```

Optional values for future Supabase API features:

```env
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
```

Important:

- `SUPABASE_SERVICE_ROLE_KEY` is powerful. Use it only on the Laravel backend.
- Never expose `SUPABASE_SERVICE_ROLE_KEY` in frontend JavaScript.
- For this project, Laravel authentication can use the `users` table in Supabase. Supabase Auth is not required unless you choose to use it later.

## 4. Clear Laravel Config Cache

After editing `.env`, run:

```bash
php artisan config:clear
```

If config was cached before, also run:

```bash
php artisan cache:clear
```

## 5. Test The Connection

Run:

```bash
php artisan migrate:status
```

If the connection works, Laravel will show migration status from Supabase.

If this is a new Supabase database, run:

```bash
php artisan migrate
php artisan db:seed
```

This creates the current Laravel tables and the admin user.

Current admin seed:

```text
Email: admin@tinghao.com
Password: password
```

## 6. Expected Tables After Migration

The current project migrations create:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

Inventory tables are not created yet. They should be added next with Laravel migrations.

Recommended next tables:

- `products`
- `suppliers`
- `stock_movements`
- `sales`
- `roles` or a simple `role` column on `users`

## 7. Files Added Or Updated

- `config/database.php`: added `supabase` database connection.
- `config/services.php`: added optional Supabase API config.
- `.env.example`: added Supabase placeholders.
- `docs/supabase-setup.md`: this setup guide.

## 8. Common Problems

### `could not find driver`

The PHP PostgreSQL extension is missing. This machine already has `pdo_pgsql`, but another machine may need it enabled in `php.ini`.

### `password authentication failed`

Check `SUPABASE_DB_PASSWORD`.

### `connection timed out`

Check the host, network, and whether the Supabase project is paused.

### `no pg_hba.conf entry ... no encryption`

Set:

```env
SUPABASE_DB_SSLMODE=require
```

### Laravel still uses SQLite

Run:

```bash
php artisan config:clear
```

Then confirm `.env` has:

```env
DB_CONNECTION=supabase
```
