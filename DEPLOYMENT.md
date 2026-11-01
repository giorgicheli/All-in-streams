# Production deployment

The application is deployed at https://streams.chelidze.ge on aaPanel with Nginx, PHP 8.3 FPM and MySQL.

- Checkout: `/www/wwwroot/streams.chelidze.ge/app`
- Laravel directory: `/www/wwwroot/streams.chelidze.ge/app/platform`
- Document root: `/www/wwwroot/streams.chelidze.ge/app/platform/public`
- PHP CLI: `/www/server/php/83/bin/php`
- Application database: `ais_streams_prod`
- Deployment account: `ais_streams_deploy`

Database credentials and the application key are stored only in the private production `.env`. Keep that key when updating the application. The initial administrator credentials were delivered separately in a private local file.

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

## Sample workspace and reporting

At the owner's request, production includes a labelled fictional workspace: 4 companies, 2 agencies, 10 creators and 12 campaigns across the review, matching, proposal, contracts, live and completed stages, including requested changes and a declined brief. Sample data never publishes itself to the public campaign gallery.

```bash
umask 0007
/www/server/php/83/bin/php -d disable_functions= artisan showcase:seed --force \
  --credentials-file=/home/ais_streams_deploy/deploy-tools/showcase-accounts.json
```

The command adds only its own marked sample records, preserves existing administrator accounts, passwords and edited workflow states, and saves newly generated sample login details in a private file outside the web root. Repeating it does not reset passwords or duplicate campaigns. The regular `DatabaseSeeder` remains blocked on production.

Company and agency accounts use business permissions and see only their own campaigns. Creator signup requires a valid Twitch or Kick channel URL; both can be saved. Channel identities remain hidden from businesses until staff authorizes an introduction.

Staff can enter or correct daily reports under `/admin/campaign-metrics`. Dashboards aggregate recorded impressions, clicks, conversions and delivered streams over an inclusive 30-day window. CTR is total clicks divided by total impressions. Currency budgets remain separate; they are planned brief budgets, not collected revenue. Sample figures are simulated, and no streaming-platform analytics API is connected.

## Website content

Administrators manage the public website at `/admin/website-editor`. Home-page sections can be edited, reordered and hidden. Site settings cover branding, navigation, footer and sign-in/registration copy. Images use the public disk's `site-content` directory; `php artisan storage:link` must expose only `storage/app/public`, keeping campaign documents on the private disk.

`Save draft & preview` updates the private preview. `Publish changes` updates the public content. Published versions can be restored to a draft and reviewed before publishing again. Preview URLs require an administrator session and are never cacheable public pages. The renderer uses typed blocks and escaped text rather than user-supplied HTML or scripts.

Country flags use locally served Flaticon PNGs with visible author attribution. The source/checksum manifest and [asset credits](platform/docs/ASSET-CREDITS.md) are committed; the licensed PNGs are deployed separately and excluded from Git. Preserve the visible attribution when changing the site footer.
