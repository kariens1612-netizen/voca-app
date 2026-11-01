# Voca

Voca is a Laravel and Vue application with Capacitor support for iOS and Android.

The application is in `homedir/apps/voca`. Laravel source is in `app-src`; the accounting application is in `back-office/accounting`.

## Local setup

```sh
cd homedir/apps/voca/app-src
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

Configure your database and service credentials in `.env` before running migrations. Point your web server at `homedir/apps/voca`, which contains the public `index.php`.

Production runs on Hostinger Agency Cloud hosting, where npm builds cannot run. Run `npm run build` locally from `homedir/apps/voca/app-src` before deploying frontend changes. Commit the generated `homedir/apps/voca/build/` directory, `manifest.webmanifest`, `sw.js`, and `workbox-*.js` with the source changes so Hostinger receives ready-to-serve assets. Include deletions of obsolete generated assets in release commits. Never deploy a local Vite `hot` file.

Server backups, credentials, databases, uploads, dependencies and native build caches are excluded from version control.

For public uploads, create a `storage` symlink in the web root pointing to `app-src/storage/app/public`.

## Account access

- `/login` signs in organisation users; `/platform/login` is for platform administrators.
- Administrators add users in `/admin` or the platform organisation's Admins tab. New users receive an expiring password setup email. Existing accounts keep their password and role when invited again; change roles using the access controls.
- Use **Send invitation** to resend a setup link. Requests for another link are throttled for 60 seconds. Links expire after 60 minutes and can only be used once.
- `/forgot-password` sends a recovery link without revealing whether an account exists. New passwords require at least 12 characters, letters, and numbers. Passwords are shared across a user's organisations; a reset invalidates existing database sessions and remember-me tokens.
- Owners and platform administrators can manage owners. The last active owner cannot be removed, demoted, or suspended. Organisation administrators can manage other administrators and users.
- Suspending an organisation membership blocks its ministry access on subsequent requests. Removing organisation access also removes that user's ministry memberships in that organisation. Legacy users who only have ministry memberships retain their assigned ministry access.

Before deployment, configure `APP_URL`, `TENANCY_BASE_DOMAIN`, verified custom domains, and a real `MAIL_MAILER` with delivery credentials and sender address. The example mailer logs email locally rather than delivering it. Use HTTPS with `SESSION_SECURE_COOKIE=true` and leave `SESSION_DOMAIN=null` to isolate organisation cookies. Invitation links use a verified organisation domain or its configured subdomain; recovery links use the resolved request host. Test delivery on the target host before inviting real users.

Run checks from `homedir/apps/voca/app-src` with `php artisan test` and `npm run build`.

## Automatic member sync

The website is the main record for approved member information. The app stores a local database copy and syncs every five minutes through an authenticated JSON endpoint. Deploy both projects and configure Hostinger cron as described in [member sync deployment](homedir/apps/voca/app-src/docs/member-sync.md).
