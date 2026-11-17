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

`APP_ENV=production`, `APP_DEBUG=false`, encrypted secure session cookies and database-backed sessions/cache are configured. Password recovery uses the administrator-configured SMTP mailer described below; other unspecified application mail still uses the environment's log mailer. Payments and document signature readiness are managed by administrators; payment and electronic-signature providers are not connected. Email-verification flows are not implemented; partner-account approval remains an administrator action.

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

Country flags use locally served public-domain Flagpedia/Flagcdn PNGs. The source/checksum manifest and [asset provenance](platform/docs/ASSET-CREDITS.md) are committed; the seventeen supported flag images are deployed separately. Public-domain replacements removed the former Flaticon attribution requirement. Preserve their native national ratios within the shared flag slots.

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

The footer links to `/privacy-policy` and `/terms-and-conditions`. Both pages start with empty content, as requested by the owner. The obsolete footer slogan and attribution row are removed. The replaced public-domain flags are hosted locally and use a new cache version; deploy all supported `public/images/flags/*.png` files alongside their committed source manifest.

Administrators edit each page under Website editor > Privacy policy or Terms & conditions. The visual text editor supports headings, emphasis, lists and links. Save draft & preview keeps changes private; Publish changes updates the public page. Published versions can be restored to draft. Formatting is stored as a bounded document tree and rendered through the sanitized rich-content renderer. Embedded files, images, scripts and arbitrary HTML are not accepted in policy content. Existing home/settings drafts, published content and revisions are preserved. New legal pages require no migration or initial database write.

## Public campaign results and network figures

Migration `2026_11_04_000005` adds a separate public aggregate dataset. Staff manage it under Website > Published results (`/admin/public-campaign-results`): country, region, platform, completion date, delivered streams/posts, views, clicks and conversions. Publishing requires staff review, a completed date and at least one delivered item. Internal references, counterparties, contacts and private budgets never reach these public cards. Unpublish or delete a card to remove it from the gallery; GEO tabs filter the published results.

The owner's four requested sample cases are explicitly labelled simulated and can be created with `php artisan showcase:public-results --force`. The command only adds its own missing demo records and preserves edited or unpublished cases. It does not complete private campaigns, approve payments, sign documents or change campaign analytics. Run the usual permissions helper after deployment CLI operations.

Website editor > Site settings > Network statistics controls the four public counts and their labels. The initial illustrative figures are 120 influencers & streamers, 18 agencies, 24 countries and 60 companies; the owner requested that the separate network sample-data note and gallery privacy caption be omitted from both homepage versions. Per-result demo badges remain visible. These editable marketing figures are independent of private account counts. Values count up once on entry and respect reduced motion. The figures appear as connected interactive cards beside the hero copy on desktop and beneath it on phones, above the audience flags.

The owner does not want decorative zero-padded ordinal labels. Public statistics pair their actual count on the left with an icon on the right; audience cards omit ordinals and the process uses icons.

The public website uses white and cool off-white surfaces, near-black Inter headings and restrained blue accents, including navigation, footer, policy pages, sign-in and registration. `/white` remains a legacy noindex presentation alias of the published homepage and omits Livewire assets; it shares the current public theme. Partner workspaces and administration retain their existing appearance. Both homepage routes read the same published content and results.

The homepage FAQ contains the owner's five market, terms, creator selection, reporting and signup answers, with an editable Talk to us link to /talk-to-us. Saved home revisions missing the newer FAQ link fields receive defaults without replacing their authored answers. The audience carousel lists Germany, Portugal, Canada, Poland, Switzerland, Brazil, Argentina, New Zealand, Hungary, United States, Austria, Sweden, Norway, Finland and Kyrgyzstan in that order. It loops on overflowing desktop and mobile layouts, pauses during interaction and respects reduced motion. Home content publication updates only the FAQ copy and carousel countries, retaining unrelated published content and unpublished edits; older published versions remain in revision history.

## Partner enquiry reference forms

Migration 2026_11_16_000001 creates the private partner enquiry inbox at /admin/partner-enquiries. GET /talk-to-us accepts type=brand, agency or creator; creator_type=streamer, influencer or both selects the creator form. Public CTA links open these forms. Submitting any category now creates a real account with a unique email and confirmed password. Creator registration requires the existing private profile/audience proof video; administrator-controlled media limits and compression apply.

Brand forms request contact details, a company website, audience countries and an optional campaign budget. Agencies also provide stream count, required budget and total-campaign/per-stream basis. Creators provide email, password, Telegram, a private proof video, one or more audience countries (up to thirty) and conditional channel links/rates: Twitch or Kick plus stream rate for streamers; Instagram plus story/Reel rates for influencers; all for Both. The creator country picker supports searching and multiple selections; other roles accept comma-separated supported country names. Currency is recorded explicitly and 18+ confirmation is required. Amounts and requirements are initial proposals, not accepted contracts or payments.

POST /talk-to-us is CSRF protected and rate limited. Server validation excludes unrelated role fields, canonicalizes platform links/Telegram and requires conditional data. A unique submission key prevents duplicate clicks creating duplicate records. Only staff can view submissions or update status/private notes; contact details never appear in another partner's portal or the public gallery. GET responses are private and uncacheable. Account creation, the pending role profile and linked private enquiry are one transaction; failed transactions remove uploaded proof files. Replayed submission keys never authenticate an existing account. No email or Telegram message is sent automatically.

The attached hero recording is a design reference, not an embedded video asset. The resulting HTML/CSS hero uses editable title fields plus two alternate headlines, with stable dimensions, a gentle six-second rotation, reduced-motion support and paused animation in background tabs. Publishing the reference update preserves unrelated authored content, unpublished drafts, network counts and content revision history.

Migrations 2026_11_16_000002 and 000003 add partner-account verification and link new inquiries to accounts. Newly registered partners start pending and can sign in to /account/pending, update contact information or sign out. Workspace routes, private campaign documents, provider operations and persistent Livewire requests require administrator verification. Administrators approve, reject or return accounts to review from /admin/user-accounts. Verification is separate from email verification and from campaign/provider-profile approval. Existing accounts are backfilled as verified to preserve access. Trusted CLI demo creation explicitly verifies new fixtures and preserves edited statuses on reruns.

Combined Streamer & influencer profiles retain both channel types, appear in both staff directories and can be matched to either creator category after approval. Profile edits and media validation preserve this combined identity. No pending provider can be invited or accept campaign terms. Passwords are hashed and excluded from private enquiry payloads, old input, public responses and admin displays.

## Public section and creator-network refinement

The owner prefers minimal borders. Public sections, header, GEO strip and footer have no separator rules; cards and content panels use contrasting surfaces and spacing instead of outlines. Tabs and secondary controls use soft filled backgrounds. Preserve visible form fields and keyboard focus indicators.

Guest navigation pairs Sign in (`/login`) with Register (`/talk-to-us`, followed by the role chooser). Mobile places Register in the header and Sign in inside the full-screen menu. The footer also uses the concise Sign in label. These labels remain editable in site settings. Authenticated visitors retain their Your workspace shortcut.

## Email password recovery

The public and administrator login pages link to `/forgot-password`. All account roles, including pending partners, can request a reset by email. Recovery uses Laravel's existing password broker and `password_reset_tokens` table: tokens are hashed, expire after sixty minutes and are single-use. Requests are limited by normalized email and IP; redemption is also limited by IP. Resetting a password rotates the remember token, deletes the account's database sessions and returns to sign-in. It preserves roles and administrator verification states and does not automatically sign in or verify an account.

Migration `2026_11_16_000006` adds private SMTP settings. Administrators configure Administration > Email settings (`/admin/mail-settings`): SMTP host, port, STARTTLS or SSL/TLS, username, password, sender email and sender name. Save settings, enable SMTP and send a test to a chosen inbox using the saved configuration. Test mail is limited to five attempts per administrator per minute. Passwords are encrypted using the existing application key, excluded from serialization and never prefilled in the form. A blank password keeps the existing secret; the explicit removal switch clears it. Saved configuration is read for each send and the named transport is refreshed without a server restart.

The dedicated `site_smtp` mailer enforces TLS and certificate validation, with a ten-second connection timeout. Password recovery never falls back to the log mailer. SMTP starts disabled and empty; real delivery begins after the owner enters valid settings. Disabled or incomplete configuration creates no reset token. Delivery failures return neutral public messages and discard transport exceptions that could contain credentials; a failed send removes its token. The test-email action reports a sanitized failure. Ordinary requests use the same acknowledgement for known and unknown email addresses.

Reset URLs use the configured canonical HTTPS origin. Recovery pages, redirects and errors are private/no-store and no-referrer. Their no-transform directive prevents edge analytics injection, and a first-party-only script/connect content security policy blocks external script execution or beacon requests. The new forms use ordinary CSRF-protected POSTs with blank password fields. No password or reset token is flashed as old input. SMTP settings are isolated from public website content and can be read or updated only by administrators. Preserve the application key when restoring the encrypted configuration. No new queue worker is required for these synchronous emails.

The audience flag strip supports captured mouse dragging with a short momentum glide, native touch swiping and keyboard scrolling. Native touch preserves vertical page scrolling and pinch zoom. The fixed MATCH BY AUDIENCE GEO caption sits outside the scrolling viewport; the former right caption is omitted. Flags are 28px wide. Autoplay pauses during hover, dragging and native momentum; it resumes after scrolling settles and the pointer leaves. Reduced motion disables autoplay and the extra mouse glide while retaining manual navigation.

## Public visual redesign

The public layout uses the scoped `site-redesign` root class. The homepage, account entry forms and policy pages share a light editorial identity with bundled Inter headings; private workspaces and Filament retain their existing styles. Published CMS content, block ordering and visibility remain authoritative. Four network statistics form the right-hand hero composition, replacing the sculpture and separate compact statistics row. Their labels and values remain editable. The hero, audience and industry introductory eyebrows are omitted.

The opening hero pairs its copy with the supplied creator-connections composition. The audience flags remain at the bottom of the first-screen stage. Four editable network figures follow the flags in a compact section. When the creator visual is disabled, the hero falls back to the connected statistics cards (or the authored image if statistics are disabled). The statistics fallback uses semantic HTML and colored curved SVG connections with a small local spring simulation, supporting mouse, touch and keyboard interaction. Reference websites informed composition and motion; their images, models and code are not shipped.

The standard first screen fills the viewport, including the normal-flow header, centered hero and audience flags aligned to its bottom. Stable small viewport units prevent mobile browser chrome from causing jumps. The mobile creator scene stacks beneath the copy; short screens retain natural vertical flow instead of clipping text or cards.

Fine-pointer devices support the original near-black Canvas2D ink trail: tapered droplets joined by elastic curved bridges, bounded to a compact 60px range. A short 50ms input delay precedes the fixed 120Hz simulation and interpolated drawing; smoothstep drying eases the shrink to rest. The overlay appears above page copy and media without intercepting clicks. In the desktop header it becomes a black circle, with a matching clipped overlay that turns only covered navigation-label pixels white. Canvas coordinates use its actual CSS bounds, including scrollbar and pixel-ratio adjustments; a small inset keeps white antialiasing within the black edge. The canvas and text clip render in the same compositing cycle. Real links remain the sole focusable and accessible controls. Coarse pointers, reduced motion and data-saving preferences retain the normal pointer; editable focus, keyboard navigation and open mobile menus retain their existing quiet behavior. Canvas resources, clipped labels and listeners are disposed on navigation. The body trail sleeps within 1.2 seconds after movement ends; a stationary header circle stays visible without a continuous animation loop. It makes no network requests.

Rebuild Vite assets when changing the public design and deploy the matching manifest, CSS and application bundle together. The active application no longer imports or downloads the Three.js sculpture module.

The current public palette is off-white `#F5F6FA`, white panels, near-black `#191C1F` copy and blue `#0A5CFF` accents. Inter headings and editorial spacing follow the owner's Antefluence reference while preserving All In Streams branding and content. The rounded floating header retains its structure and shape. The black `#08090D` footer contains the editable About eyebrow, heading and description, followed by branding, account and legal links in contrasting light colors. The About navigation anchor points to that footer. The former standalone About band is removed and the Ready to go all in call-to-action block is disabled. Reduced-motion preferences keep the headline rotation and interactive effects static. Navigation follows Platform, How it works, Creator network and About us. The Platform dropdown and audience cards link directly to their role forms. Generic Let's talk links open a three-role chooser at `/talk-to-us` before registration.

Header navigation, account links and registration labels use a one-pixel larger type size. The Local audiences / Shared ambition results block is disabled in the default, published and draft homepage content; published result records remain managed in the admin. This uses the existing section visibility control and preserves unrelated authored content and draft changes.

Website editor > Site settings > Creator video showcase manages visibility, copy and the ordered clip list. With nine clips, positions 1–3 form the top row, 4–6 the stationary middle row and 7–9 the bottom row; other list lengths are divided into consecutive thirds. Each authored clip belongs to one row. The outer rows loop in opposite directions over 94 and 104 seconds; seamless visual copies stay within their row and do not duplicate keyboard stops. Every visible copy remains clickable when a destination is provided. Playback is muted, inline, limited to visible clips and paused offscreen, in background tabs or behind navigation. A public pause control is available. Reduced-motion and data-saving visitors see posters until explicitly choosing playback. Uploaded showcase media is separate from private account proof videos. The nine initial stock previews and source/license checksums are under `public/videos/creator-reels/sources.json`; they illustrate formats and do not represent registered partners or completed campaigns.

Each clip supports an optional public HTTPS MP4/WebM source URL, a managed public upload or a sample preset, in that precedence order. Source URLs are validated without server-side fetching; private/local destinations and verification-media paths are rejected. A separate optional destination link accepts watch-page URLs such as YouTube, Twitch or Kick, or an internal website page. The numbered editor repeater preserves each source/destination pair when moved. Existing authored lists gain only missing fields; publication history and unrelated drafts are preserved. Bundled sample media remains first-party; an explicitly configured external source loads directly in the visitor's browser.

Website editor > Site settings > Brand spotlight controls the screenshot-style introduction above the audience cards: two headline lines, body, CTAs, public image and campaign-card copy. Disabling it restores the original audience heading. The fallback photo is a fictional streamer illustration generated with the built-in image tool; its prompt and provenance are in `public/images/brand-spotlight/sources.json`. The overlaid card illustrates the matching workflow and is not a real campaign result.

Website editor > Site settings > Creator connections visual controls the uploaded-HTML-style right block in the first hero: three floating video cards, a campaign card, central logo, orbits and blue connecting routes. Its labels are editable, and its public video pool comes from Creator video showcase. Clips change randomly with decoded-frame crossfades; playback and visual motion pause offscreen, behind menus and in background tabs. Reduced-motion and data-saving visitors retain readable poster cards with an explicit play control. Brand spotlight below retains its authored/fallback streamer photo. The supplied document's additional cursor and embedded media payloads are not duplicated; the existing global ink cursor and managed public clip library remain authoritative.

Migration `2026_11_16_000004` adds a separate reviewed public-creator dataset, managed under Website > Public creator profiles (`/admin/creator-showcases`). The gallery shows at most three published, reviewed cards per region across Europe, CIS, MENA, Africa, North America, LATAM, Asia and Oceania. The public projection contains a random public UUID, region, country, creator category, platforms, language, studio image and approved aggregate figures. It contains no real name, contact, private user ID, channel link or commercial rate. Blur covers generic placeholders only; private identities are never delivered to the browser.

`php artisan showcase:creators --force` adds twenty-four explicitly labelled demo cards and preserves edited or unpublished records on reruns. Three locally hosted studio photographs and their source/license/checksum manifest live under `public/images/creator-showcase`. These public cards are independent of registered partner profiles and private campaign analytics.

The browser shortlist stores only known public UUIDs. Sending it opens the Brand form with the selected region and safe public country/category labels. Migration `2026_11_16_000005` records the submitted selection in the private partner enquiry for staff review; unavailable or unknown UUIDs are rejected. It does not reveal identities, introduce parties or approve a campaign.

Administrator-only user-file uploads allow selecting multiple PDF/DOC/DOCX files together and appending further selections before saving. Existing attachments remain available. Only the newest attachment per user receives the `ბოლო მიბმული` badge, with an ID tie-break when timestamps match. These internal records remain separate from campaign contracts visible to the applicable partner.
