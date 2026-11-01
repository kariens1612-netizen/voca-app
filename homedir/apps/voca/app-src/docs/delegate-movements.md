# Delegate Movements

Open `/delegates` in the website/web app or bundled mobile app. The navigation shows **Delegate Movements** to organisation owners/admins and platform administrators. API access is checked on every request and scoped to the resolved organisation. Ordinary members and delegates cannot access the roster. Delegates need only their printed badge or a saved badge image, not an app account.

## Prepare before travelling

1. Sign in as an organisation administrator on each marshal's phone while connected.
2. Select **VOCA Delegates**, or create a roster and upload XLSX/CSV. The supplied workbook was imported into the local VOCA database with 70 named delegates. Contact fields stay on the server; offline rosters contain names, groups and random badge references. Imports are transactional; existing emails in the same roster are skipped without changing badge references. Files without email addresses cannot be deduplicated automatically.
3. Open **Manage delegates, journeys and badges**, prepare the QR badges, then print/save them using the website browser. Keep badges available on lanyards or delegate phones.
4. Create a bus journey for each bus/leg, with a meaningful name and an expected passenger list. A separate checkpoint records hotel/venue arrivals and departures. Existing journeys retain their expected list when new delegates are imported; create a new journey to include them.
5. On every marshal's phone, open the roster and press **Sync now** after setup. For web offline reloads, allow the service worker to finish installing while connected. Native apps bundle the interface.
6. Select the journey, action and current stop before scanning. Use search and the action button if a badge is missing or the camera is unavailable. Check **Still expected** before departure. Verify other marshals have synced before treating the combined headcount as current.

## Offline operation and reconciliation

- Scans are saved to IndexedDB before the success message. Failure to save is shown as an error. Pending scans survive a reload, provided browser/app storage is retained. Keep the prepared portal tab open during travel; the offline access grant lasts 12 hours and is stored in that tab's session. Reconnect before expiry or after closing the tab/app. Offline access cannot know about server-side revocation until the device reconnects.
- The queue is separated by API origin, organisation and signed-in user. Signing out revokes offline access; saved scans are retained for the same account to recover after signing back in. Do not clear app/browser data with unsynced scans. Use trusted marshal devices.
- Online phones refresh and attempt sync every 30 seconds, on reconnect, after scanning, and on **Sync now**. No background upload is promised when the app is closed. Each screen and CSV shows its last refresh. Pending scans on other phones cannot be included until uploaded.
- Every movement has a UUID and the ID of the delegate's previous movement. Server transactions serialize a delegate's changes. Replaying a request is idempotent; stale updates, repeated boarding, boarding another bus before exiting, incorrect exits and impossible checkpoint transitions become conflicts. Device timestamps are retained alongside server receipt times; future or out-of-order times are rejected. Server acceptance order is canonical.
- Conflicts are excluded from headcounts and retained on the phone for review. Read the history, dismiss the saved conflicting record, then record the correct movement if needed. Dismissal does not alter accepted server history. Accepted movements are append-only in this version; mistaken accepted scans require an appropriate subsequent movement, rather than a silent edit.
- This records checkpoints and last known movements, not GPS tracking or an authoritative safety guarantee when phones have not synced.

## Local implementation and deployment

The migration adds `movement_rosters`, `movement_delegates`, `movement_journeys`, and `movement_scans`. Existing event tickets/check-in are separate and unchanged. This portal uses the app's shared backend; the separate public voca.africa website must link to the deployed portal or host the same frontend. It does not receive an independent copy of delegate records.

```sh
php artisan migrate --force
php artisan delegates:import /path/to/VOCA_Delegates_Google_Contacts.xlsx --organisation=voca
npm run mobile:sync
```

Use HTTPS for website camera access (localhost is allowed for development). Native camera permissions are declared in iOS/Android and requested at scan time. The existing native API base URL/session configuration must point to the shared deployed backend. Deploy the backend and web build, and rebuild/distribute the native apps before expecting this module on installed production apps. `mobile:sync` copies web assets; it does not publish or sign native releases.

Checks: `php artisan test`, `node --test tests/movement-offline.test.mjs`, and `npm run mobile:sync`. Browser visual verification was unavailable in this session. Before rollout, check actual camera permission/scanning, printable badges, offline reload/airplane-mode boarding and exiting, reconnect retries, and two-phone conflict handling on the intended iOS/Android devices.

## Android emulator development

If the bundled app shows “Organisation portal” and only Bible study, check the server connection. A native app's bundled origin does not serve the Laravel API. Startup and sign-in now reject HTML fallback responses and show a connection error instead of accepting them as API data.

For local Android testing, start Laravel on port 8012, start the emulator, then run `npm run mobile:android:local` from `app-src` and run the app in Android Studio. This command builds assets, syncs Android with `CAPACITOR_SERVER_URL=http://localhost:8012`, and forwards the Laravel and active Vite ports through ADB. The emulator uses same-origin Laravel sessions and CSRF cookies. Keep the servers running and rerun the setup command after restarting the emulator. This development mode loads the interface from your computer; use the bundled build with a deployed backend for travel/offline testing.

Sign in through **Menu → Team Portal** with the local admin account. Administrators land at `/admin`; **Menu → Delegate Movements** opens the roster. Signed-in users also have a team section on the mobile home screen. The local account remains separate from public website member sign-in.

HTTP access is allowed only by the Android debug manifest. Run `npm run mobile:sync` to remove the temporary server URL before preparing a bundled release, configure the deployed native backend/session integration, then build the release. Do not ship a localhost server URL. Regression coverage: `node --test tests/mobile-connection.test.mjs`.
