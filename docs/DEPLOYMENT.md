# Deployment

## Any PHP host

1. PHP 8.2+ with `pdo_mysql`, `mbstring`, `gd`, `fileinfo`; MySQL 8; Composer 2.
2. `composer install --no-dev --optimize-autoloader`
3. Web server document root: `public/` (see the Apache and Nginx notes in the README).
4. `.env` from `.env.example`:

   | Variable | Production value |
   |---|---|
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | the public HTTPS address, used in password-reset links |
   | `APP_KEY` | output of `php bin/console key:generate` |
   | `DB_*` | the database credentials (`DB_SOCKET` when MySQL is reached through a socket) |
   | `AUTO_MIGRATE` | `true`, or `false` and run `php bin/console migrate` on each deployment |
   | `ADMIN_EMAIL` | the administrator's e-mail |
   | `ADMIN_INITIAL_PASSWORD` | **a strong one-time password**; it must be changed at the first sign-in |
   | `TRUSTED_PROXIES` | the address(es) of the reverse proxy in front of PHP, so client IPs and HTTPS are detected |
   | `MAIL_*` | SMTP settings, to enable password-reset e-mails |

5. `storage/` must be writable by PHP and persist between releases (logs, caches, uploaded
   photos). It is never served by the web server.
6. Health check: `GET /health` returns `{"status":"ok"}` with HTTP 200 when the database answers.

Releases are safe to switch atomically: migrations run once under a database lock, and seeders are
idempotent (templates are refreshed without overwriting administrator edits).

## CloudOps (abdocloud.com)

The application matches CloudOps' **Plain PHP** website type. It runs on PHP-FPM in its own pool,
with Composer, a shared `.env` and a shared `storage/`.

| Wizard field | Value |
|---|---|
| Type | PHP |
| Source | Git, `https://github.com/AbdoHerO/generationResumeMultiCountry_Models.git`, branch `main` |
| Domain | `resume.abdocloud.com` |
| Document root | `public` |
| Database | MySQL (CloudOps creates the database and user) |
| Health path | `/health` |

CloudOps keeps the website's settings in `<app>/shared/.env` (its Environment page). It does not
link that file into plain PHP releases, so the application reads it from there when the release
has no `.env` of its own: see `app/bootstrap.php`.

Environment, in addition to the `DB_*` values CloudOps provides: `APP_ENV=production`,
`APP_DEBUG=false`, `APP_URL=https://resume.abdocloud.com`, a generated `APP_KEY`,
`AUTO_MIGRATE=true` (CloudOps runs no migration command for plain PHP), `ADMIN_EMAIL`, a strong
`ADMIN_INITIAL_PASSWORD`, and `TRUSTED_PROXIES` set to the address PHP sees for Nginx.

After the first deployment:

1. Open `https://resume.abdocloud.com/health`. It should return `{"status":"ok"}`.
2. Sign in as the administrator, then choose a new password when asked.
3. In Administration → Settings, set the application name and support e-mail.
4. Create a test CV and download its PDF. The first PDF builds mPDF's font cache in `storage/`.
