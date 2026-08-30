# Obin Academy — PHP / MySQL

Plain PHP 8 + MySQL + vanilla HTML/CSS/JS rewrite of Obin Academy, built for
Hostinger shared hosting (no Node.js required).

## ⚠️ Not yet tested — read this first

This was written without a local PHP or MySQL install available in the build
environment, so **none of it has been run yet**. Before trusting it with real
payments, install a local PHP stack and go through it page by page:

1. Install **[XAMPP](https://www.apachefriends.org/)** (bundles Apache + PHP + MySQL + phpMyAdmin — the easiest single-installer option on Windows).
2. Copy this whole `obin-academy-php` folder into `C:\xampp\htdocs\`.
3. Start Apache and MySQL from the XAMPP control panel.
4. In phpMyAdmin (`http://localhost/phpmyadmin`), create a database (e.g. `obin_academy`), then import `schema.sql`.
5. Edit `config/config.php`: set `DB_NAME` to what you created, `DB_USER` to `root`, `DB_PASS` to `''` (XAMPP's MySQL has no root password by default), and `APP_URL` to `http://localhost/obin-academy-php/public`.
6. Run the data migration to bring over your real courses/users/payments: open a terminal in this folder and run `php migration/import-mysql.php`.
7. Visit `http://localhost/obin-academy-php/public/index.php` and click through every page: signup, login, browse courses, enroll, the creator dashboard, uploading a lesson, the admin dashboard.
8. Run `php -l` on every `.php` file (or just open each page — a syntax error shows immediately) to catch any typos before they reach production.

Tell me once XAMPP is installed and I'll go through this list with you and fix
whatever turns up.

## What changed from the Next.js version

- **Auth**: PHP native sessions + `password_hash()`/`password_verify()`, CSRF tokens on every form and JSON API call.
- **File storage**: back to local disk (shared hosting has a persistent filesystem, so the R2/Postgres detour from the Vercel plan isn't needed). Lesson videos/PDFs live in `private-uploads/` — **outside** `public/`, so they're never directly web-accessible — and are only served through `stream.php`, which checks login + enrollment + expiry + premium status before streaming a byte, exactly like the old protected route. Thumbnails/avatars stay in `public/uploads/` since they're meant to be public.
- **Payments**: same iotec Pay REST API (OAuth2 client-credentials + collection + status polling), called via PHP `curl` instead of `fetch`. Same 10% platform commission math.
- **Email**: same Resend API, called via `curl`.
- **Database**: MySQL, with `INT AUTO_INCREMENT` primary keys instead of Prisma's `cuid()` strings — the natural fit for plain PHP/MySQL.

## Local development

```
php -S localhost:8000 -t public
```

Then visit `http://localhost:8000`. (Update `APP_URL` in `config/config.php` to match.)

## Deploying to Hostinger

1. **Create the database**: hPanel → Databases → MySQL Databases → create a database + user, note the host/name/user/password.
2. **Upload files**: via hPanel's File Manager or FTP, upload the contents of `public/` into `public_html/`, and upload `includes/`, `config/`, `private-uploads/`, `migration/`, and `schema.sql` as **siblings of** `public_html` (one level up) — not inside it. This keeps `includes/`, `config/`, and `private-uploads/` off the public web even if the `.htaccess` deny rules aren't honored for some reason.
3. **Import the schema**: hPanel → phpMyAdmin → your database → Import → `schema.sql`.
4. **Configure**: edit `config/config.php` with the real Hostinger DB credentials, set `APP_URL` to `https://obinacademy.site`, generate a real `APP_SECRET` (`php -r "echo bin2hex(random_bytes(32));"`), and switch `IOTEC_WALLET_ID` to the live wallet only once you're ready for real learner payments.
5. **Raise PHP upload limits**: hPanel → Advanced → PHP Configuration → raise `upload_max_filesize`, `post_max_size` (both to at least a few hundred MB for course videos), `max_execution_time`, and `memory_limit`. The `.htaccess` in `public/` tries to set these too, but only takes effect if Hostinger runs PHP as an Apache module rather than PHP-FPM — the hPanel setting always works.
6. **Migrate your real data** (once, before real users touch the new site): SSH in if your plan allows it and run `php migration/import-mysql.php`, or run it locally against a database you then export/import into Hostinger's MySQL via phpMyAdmin.
7. **Point the domain**: if `public_html` is the doc root for `obinacademy.site`, you're done — Hostinger DNS already points there. Otherwise add the domain in hPanel and point it at the folder you uploaded `public/`'s contents into.

## Known limitations vs. the Next.js version

- No TypeScript — nothing catches a typo before it reaches the browser. Test thoroughly.
- No build step / bundler — every page loads plain CSS/JS files directly; fine for this app's size.
- No automatic HTTPS — Hostinger issues a free SSL certificate via hPanel (Let's Encrypt); make sure it's enabled and `config.php`'s `APP_URL` uses `https://`.
