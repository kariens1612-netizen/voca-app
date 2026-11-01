# Website-to-app member sync

The website (`voca.africa`) is the main record for approved public member information. The app (`app.voca.africa`) has its own database, routes and Vue displays. Its member pages read `synced_member_pages` in the app database, without a website request during page loading.

`voca:sync-members` fetches a versioned JSON export over HTTPS, authenticated using the existing shared `VOCA_MEMBER_API_KEY`. The website export reuses the website's public controllers and view data so approvals, founders, member pathways and published biographies stay consistent. It exports only public display fields, never passwords or private registration fields. Images remain URLs hosted by the website; this sync does not copy image binaries or the business directory. Member login/registration and the separate business directory integration retain their existing behavior.

The scheduler syncs every five minutes. A successful complete snapshot replaces the local copy atomically, including removing unpublished biographies. Network failures, incomplete snapshots and invalid responses leave the last successful copy untouched. Until the first successful sync, the app reports that member information has not synced yet. If a source is offline, already synced public information can be older until the next successful run. Publication withdrawals therefore depend on a successful sync.

## Deploy the website first

The companion website files are included under `integrations/website/` and are also installed in the separate local website project when filesystem permissions allow. Deploy the controller and service to the website repository and add this to the website's `routes/api.php`:

```php
Route::get('/mobile-members/content', \App\Http\Controllers\MemberContentExportController::class)->middleware('throttle:30,1');
```

Set the website's `VOCA_MEMBER_API_KEY` to the same secret used by the app. Preserve existing keys that already connect member accounts. Refresh the website configuration cache. No database migration is required on the website for the export.

## Deploy the app

Commit and deploy the app code and locally generated production build assets. From the app server's `app-src` directory:

```bash
php artisan migrate --force
php artisan config:cache
php artisan voca:sync-members
```

The app server `.env` needs `VOCA_MEMBER_API_URL=https://voca.africa` and the shared `VOCA_MEMBER_API_KEY`. These are integration settings; `APP_URL` and `TENANCY_BASE_DOMAIN` remain the app's own domain, `app.voca.africa`. Never commit secrets.

Add a Hostinger cron job once per minute using the appropriate PHP CLI executable:

```bash
cd /home/u200259796/domains/app.voca.africa/public_html/homedir/apps/voca/app-src && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Do not run npm on Hostinger. A sync failure produces a failed command exit and is logged by the scheduler. `php artisan voca:sync-members` can be used for immediate refresh or diagnosis. `php artisan schedule:list` confirms the five-minute schedule. Prevent overlapping sync jobs through the scheduler's configured cache store.

This is website-to-app synchronization, not independent editing of replicated profiles. Make member updates and approval changes on the website; they appear in the app after the next successful sync. App administration roles and tenant records are not overwritten by public member content.
