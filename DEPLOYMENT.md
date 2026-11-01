# Production deployment

The application is deployed at https://streams.chelidze.ge on aaPanel with Nginx, PHP 8.3 FPM and MySQL.

- Checkout: `/www/wwwroot/streams.chelidze.ge/app`
- Laravel directory: `/www/wwwroot/streams.chelidze.ge/app/platform`
- Document root: `/www/wwwroot/streams.chelidze.ge/app/platform/public`
- PHP CLI: `/www/server/php/83/bin/php`
- Application database: `ais_streams_prod`
- Deployment account: `ais_streams_deploy`

Database credentials and the application key are stored only in the private production `.env`. Keep that key when updating the application. The production database contains no seeded demo accounts. The initial administrator credentials were delivered separately in a private local file.

## Updates

Build assets locally with `npm ci` and `npm run build` in `platform`, then upload the resulting `public/build` directory. The production server has no Node.js installation. Install PHP packages with Composer using the PHP 8.3 binary and `--no-dev --optimize-autoloader`.

Run application commands as the deployment account with `umask 0007`. The application directories use ACLs to grant PHP-FPM's `www` user read access and shared runtime access. A restrictive `umask 0077` during Blade cache compilation makes Laravel's atomically replaced views unreadable to PHP-FPM.

```bash
cd /www/wwwroot/streams.chelidze.ge/app/platform
umask 0007
/www/server/php/83/bin/php -d disable_functions= artisan down --retry=60
/www/server/php/83/bin/php -d disable_functions= artisan migrate --force
/www/server/php/83/bin/php -d disable_functions= artisan filament:assets
/www/server/php/83/bin/php -d disable_functions= artisan config:cache
/www/server/php/83/bin/php -d disable_functions= artisan view:cache
/www/server/php/83/bin/php -d disable_functions= artisan up
```

Do not run the demo seeder or database-reset commands on production. Take a database backup before migrations. Nginx serves only `platform/public`, including a dedicated Livewire route rule. The application redirects production HTTP requests to its configured canonical HTTPS origin before sessions are started.

## Verification and integration status

Deployment verification covered public HTTPS pages and assets, business and creator sign-in, both Livewire wizard updates, administrator resource pages, role restrictions and private-file protection. Temporary verification users were removed.

`APP_ENV=production`, `APP_DEBUG=false`, encrypted secure session cookies and database-backed sessions/cache are configured. The mailer currently writes to the application log; external email delivery needs an SMTP provider. Payments and document signature readiness are managed by administrators; payment and electronic-signature providers are not connected. Password-reset and email-verification flows are not implemented.

The temporary sudo grant allowed only a frozen, root-owned setup script for this domain and a grant-revocation command. It should remain revoked after setup. The SSH deployment account is time limited and may need renewal for later updates.
