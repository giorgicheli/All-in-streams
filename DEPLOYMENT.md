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

Companies, agencies and creators have separate roles and directories. Agencies can request creators for their clients, supply an independently reviewed talent roster, or do both. Client briefs and provider invitations use separate screens and permissions. Creator signup separates streamers (Twitch or Kick required) and influencers (Facebook, Instagram, YouTube or TikTok required). Both require a private short verification video and at least one audience country. All registrations require Telegram. Identities, profile links and contacts remain private to administrators at every campaign stage.

## Provider offers and delivery

See [the workflow guide](platform/docs/WORKFLOW.md). Staff can send a reviewed brief to several approved independent creators or agencies, with a neutral scope and a private fixed fee plus an optional CPA unit rate. Provider screens receive a restricted data projection, with no client identity, contacts, private brief, total budget or other providers' terms. The provider accepts, declines or proposes a price/scope change with a note. Staff can accept an exact counterproposal or revise the offer for fresh acceptance. Each change retains negotiation history and checks the accepted offer version.

Exactly one provider must be selected before contracts. Separate client and selected-provider agreements, verified signatures and payment readiness are required for launch. Selected live providers submit private delivery evidence; staff approval is required before completion. Payment milestones are a manual ledger with pending, approved and paid states; recording a payment does not transfer funds. Provider-reported metrics do not change staff-verified campaign reports.

Creator deletion archives the profile and retains campaign history. Selected providers in contracts or live delivery cannot be deleted or have their committed identity changed. Restored creators need staff review again. Migration `000005` preserves one deterministic lead for existing contracts/live/completed campaigns; new campaigns require the explicit staff selection action. Migration `000006` converts former business/agency accounts to the dedicated agency role without changing their login credentials.

Staff can enter or correct daily reports under `/admin/campaign-metrics`. Dashboards aggregate recorded impressions, clicks, conversions and delivered streams over an inclusive 30-day window. CTR is total clicks divided by total impressions. Currency budgets remain separate; they are planned brief budgets, not collected revenue. Sample figures are simulated, and no streaming-platform analytics API is connected.

## Website content

Administrators manage the public website at `/admin/website-editor`. Home-page sections can be edited, reordered and hidden. Site settings cover branding, navigation, footer and sign-in/registration copy. Images use the public disk's `site-content` directory; `php artisan storage:link` must expose only `storage/app/public`, keeping campaign documents on the private disk.

`Save draft & preview` updates the private preview. `Publish changes` updates the public content. Published versions can be restored to a draft and reviewed before publishing again. Preview URLs require an administrator session and are never cacheable public pages. The renderer uses typed blocks and escaped text rather than user-supplied HTML or scripts.

Country flags use locally served public-domain Flagpedia/Flagcdn PNGs. The source/checksum manifest and [asset provenance](platform/docs/ASSET-CREDITS.md) are committed; the six images are deployed separately. Public-domain replacements removed the former Flaticon attribution requirement. Preserve their native national ratios within the shared flag slots.

## Creator types, private media and staff files

Migrations `2026_11_04_000001` through `000004` add creator types, private verification media, Telegram contacts, media settings, staff-only per-user files, and streamer/influencer agency roster entries. Existing supported social-platform profiles are classified as influencers; other existing creator profiles remain streamers. Existing accounts and campaign history are preserved.

Staff use `/admin/creator-profiles` for streamers, `/admin/influencer-profiles` for influencers, `/admin/user-accounts` for all roles and `/admin/user-files` for multiple private PDF/DOC/DOCX attachments. These internal files are inaccessible even to the account holder. Partner contracts remain separate, party-specific campaign documents. New and edited contacts can be saved at `/account/settings`.

`/admin/creator-video-settings` controls the upload size, short-video duration and optional compression quality/width. A valid playable MP4/MOV/WebM with a video stream is required; renamed files and overlong recordings are rejected. Staff must still manually review that the recording actually shows the applicant's profile and audience countries. Automatic platform-account ownership or analytics verification is not implemented.

This host disables process execution in PHP-FPM. The private `creator-media:worker --port=18743` process runs as the existing deployment user, with fixed operations and a private token. It listens only on `127.0.0.1`; its token is stored only in the site's private `.env`. FFmpeg and FFprobe binaries are checksum-verified and installed outside the web root at `/home/ais_streams_deploy/deploy-tools/media-tools`. Keep their execute permissions when repairing application storage permissions.

The deployment user's cron starts the worker under `flock` each minute, so only one instance runs and a stopped process is restarted. After source/config changes, restart the verified worker process to load the new configuration. Keep the deployment account valid for its cron to continue running. The existing `www` and deployment-user default ACLs must apply to `storage/app/private/creator-proof`; the service activates those ACLs on uploaded originals for worker access.

Site-local PHP upload/request limits are 100/110 MB. Nginx currently caps requests at 50 MB, so production sets `CREATOR_MEDIA_REQUEST_MAX_MB=49`; the admin UI shows that ceiling and the effective limit. Compression validates the original size before processing and retains a verified output only if smaller. It does not guarantee an exact output size. Livewire temporary files stay on the private disk.

To create the owner's clearly fictional five-role demo workspace without resetting existing passwords or edited workflows:

```bash
umask 0007
/www/server/php/83/bin/php -d disable_functions= artisan demo:roles --force \
  --fixtures-dir=/home/ais_streams_deploy/deploy-tools/role-fixtures \
  --credentials-file=/home/ais_streams_deploy/deploy-tools/demo-role-accounts.json
```

The private fixture directory contains `sample-agreement.pdf`, `internal-notes.docx` and `verification-demo.mp4`, each clearly labelled DEMO. The command creates one administrator, business, streamer, influencer and agency, three selected-provider contract examples, one agency client brief with pending/counter offers, six unsigned campaign PDFs and ten administrator-only user attachments. It does not mark signatures or payments complete. Run the established application permissions helper after CLI fixture creation so PHP-FPM can read the private files. Credentials stay in a mode-0600 file outside the web root and must never be committed or served publicly.

## Footer and policy pages

The footer links to `/privacy-policy` and `/terms-and-conditions`. Both pages start with empty content, as requested by the owner. The obsolete footer slogan and attribution row are removed. The replaced public-domain flags are hosted locally and use a new cache version; deploy all six `public/images/flags/*.png` files alongside their committed source manifest.

Administrators edit each page under Website editor > Privacy policy or Terms & conditions. The visual text editor supports headings, emphasis, lists and links. Save draft & preview keeps changes private; Publish changes updates the public page. Published versions can be restored to draft. Formatting is stored as a bounded document tree and rendered through the sanitized rich-content renderer. Embedded files, images, scripts and arbitrary HTML are not accepted in policy content. Existing home/settings drafts, published content and revisions are preserved. New legal pages require no migration or initial database write.

## Public campaign results and network figures

Migration `2026_11_04_000005` adds a separate public aggregate dataset. Staff manage it under Website > Published results (`/admin/public-campaign-results`): country, region, platform, completion date, delivered streams/posts, views, clicks and conversions. Publishing requires staff review, a completed date and at least one delivered item. Internal references, counterparties, contacts and private budgets never reach these public cards. Unpublish or delete a card to remove it from the gallery; GEO tabs filter the published results.

The owner's four requested sample cases are explicitly labelled simulated and can be created with `php artisan showcase:public-results --force`. The command only adds its own missing demo records and preserves edited or unpublished cases. It does not complete private campaigns, approve payments, sign documents or change campaign analytics. Run the usual permissions helper after deployment CLI operations.

Website editor > Site settings > Network statistics controls the four public count cards and their labels. The initial illustrative figures are 120 creators, 18 agencies, 24 countries and 60 companies; their public sample-data note must remain enabled while these are hypothetical. These editable marketing figures are independent of private account counts. Cards count up once on entry and respect reduced motion. The mobile introduction uses a stable small-viewport height so the audience flags complete the initial screen and statistics follow below.
