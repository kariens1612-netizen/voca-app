# VOCA website, web app and mobile app feature map

## Direction

Use **voca.africa as the public brand and account entry point**, with a shared VOCA API and identity service behind voca.africa, the web app and the iOS/Android app. Keep separate interfaces where mobile benefits from native navigation, push notifications, camera access and compact forms. Keep organisation and ministry administration scoped to the permissions already enforced by the app API.

This is a code-based inventory of the checked-out projects, not a guarantee about every live production page. Confirm it with VOCA owners before feature migration.

## Current inventory

| Feature | voca.africa website | Existing web/mobile app | Recommended integration |
| --- | --- | --- | --- |
| Accounts and registration | Website users identity; six registration profiles (individual, pastor, church, ministry, business, affiliate); website roles and affiliate approval | App users, organisation/ministry memberships and team roles. A bridge now authenticates app members against website accounts, creates local VOCA access, and supports individual registration and password recovery. | Finish phase 1. Website owns credentials/profile identity; issue revocable API tokens for mobile sessions. Add verified account linking for existing duplicate accounts. Extend registration/profile editing to approved website profile types. |
| Profiles and memberships | Registrations JSON plus companies, ministries, affiliate approval and directory profile data | Organisation memberships and ministry memberships are separate app records; app links central identity by website_member_id. | Make website profile and approval status authoritative. Map member-to-organisation/ministry access explicitly; define approval, suspension and removal propagation. |
| Public pages and identity | Home, About, affiliate/member pages, FaithChannel, Apostolic Fellowship | VOCA-branded mobile dashboard and separate full web landing page; app also has International/Fellowship. | Keep public website pages canonical. In app, use native screens for member journeys and open editorial/legal pages in an in-app browser until native versions are justified. |
| Business directory | Public directory, company detail/logo; member business profile and admin review | Marketplace/product browsing exists; no direct website company-directory API/client. | Phase 2: expose read-only company search/detail APIs, then member-owned profile updates and review workflow. Distinguish directory listings from products. |
| Events | Public/admin event pages; website event model | Public calendar, registration/tickets/check-in portal, app event data. | Phase 2: choose one event record and registration system. Add APIs/adapters and map records before replacing either UI. Preserve ticket IDs, attendee status and check-in audit history. |
| Training and courses | Course, lesson, enrolment and training dashboards; affiliate dashboard/profile tools. | Bible Study and Inspiration content; no equivalent website course/enrolment flow. | Phase 3: inventory course, lesson and progress data, then add mobile course catalogue, lesson reader and progress sync. Start read-only; add downloads/offline only after licensing review. |
| Radio, TV and FaithChannel | Dedicated Radio, TV and FaithChannel pages. | Live stream and Inspiration pages; media cards currently point to website pages. | Phase 3: reuse verified stream/feed/embed URLs through a shared media API and native-friendly player. Retain external providers where playback, rights or DRM require them. |
| Commerce | Website products/orders and business listings; payment capability needs confirmation. | Marketplace module and organisation commerce settings; payment/checkout behavior may differ. | Phase 4: map catalog, prices, stock, orders and payment provider first. Keep one server-side checkout/order authority; integrate receipts and order history across clients. |
| Ministry and organisation operations | Website training/admin views for affiliate approval, company status and content. | App tenant admin, ministry hubs/portals, rosters, schedules, announcements, event operations and finance. | Keep app-only operational features where the website has no counterpart. Use central identity and explicit roles. Add push notifications after preferences and delivery rules are agreed. |
| Payments and financial records | Website checkout and settlement status need confirmation. | App has finance slips and access-controlled finance administration. | Do not merge ledgers by assumption. Identify providers, settlement records, currencies, refunds and reconciliation owners before cross-client payment views. |

## Recommended delivery sequence

### 1. Identity foundation

- Complete and deploy the website API endpoints used for app member login, registration and recovery.
- Decide the canonical account ID and issue short-lived, revocable API tokens for native clients; never embed a permanent shared API secret in an installed app.
- Add verified account linking for users present in both databases. Never link by matching email alone.
- Define canonical profile fields and roles for all six registration types.
- Check registration on both clients, cross-client sign-in, password reset, suspension, role changes and duplicate-account linking.

### 2. Public member services

- Publish shared read APIs for directory listings and events.
- Build mobile directory search/detail and event discovery/registration screens.
- Set migration rules for existing company, event, registration, ticket and check-in records; retain stable IDs and audit history.

### 3. Media and learning

- Expose channel metadata, current streams, recordings and training catalogue through supported APIs.
- Add mobile playback and course/lesson/progress screens, with app links and opt-in push notifications.
- Confirm rights, embed restrictions, accessibility and offline/download policy before release.

### 4. Commerce and operational parity

- Confirm checkout provider, currencies, inventory and refund flows; centralize order/payment state.
- Add account/profile, business management and support workflows in the client best suited to each task.
- Preserve app-specific ministry/finance permissions and keep tenant records out of public VOCA endpoints.

### 5. Release and retire duplication

- Release APIs behind versioned endpoints and feature flags.
- Pilot with existing accounts, monitor authentication and data reconciliation, resolve mismatches.
- Publish web, Play Store and App Store releases; retire duplicate flows after usage and support checks.

## Implementation status and next slice

### Completed locally: delegate movements

- `/delegates` provides an organisation-admin portal shared by the web frontend and bundled mobile app. Includes XLSX/CSV import, printable QR badges, bus journeys and checkpoint manifests, boarding/exits and arrivals/departures, search, delegate histories and CSV manifests.
- IndexedDB stores offline rosters and pending movements; idempotent batch syncing validates movement order and flags conflicts from other devices. Devices show pending counts and last refresh times. Offline access requires preparing the phone while connected.
- The local VOCA roster contains the 70 delegates from the supplied workbook. Backend/web deployment and native release distribution remain outstanding. See [operating and deployment notes](delegate-movements.md).

### Completed locally: member sign-in foundation and business directory read path

- Website-backed member login, individual registration and password recovery are connected to local app accounts. The app uses a server-to-server API call; this is not yet a production token exchange or verified account-linking journey.
- The website exposes public API endpoints for approved business listings and detail pages. The app proxies those endpoints through its backend, so the website API key stays on servers.
- The app has a searchable mobile/desktop directory with country, city and category filters, business detail pages, and public website/contact links.
- The app migration is applied locally and iOS/Android assets have been synced.
- Production deployment and configuration, existing duplicate-account linking, and real device/API validation remain outstanding.

### Next: shared events

Inventory the website event fields and app event/ticket/registration fields. Choose one system of record, then add read APIs and a migration map before app registration/check-in writes are connected. Preserve stable event IDs, ticket tokens, attendee statuses, and check-in history.

## Decisions before later phases

- Which registration profiles can log into the app immediately, and which require approval?
- Should company/ministry profiles have one owner login or multiple invited members?
- Which system owns events, registrations and historic tickets?
- Which Radio/TV streams and course materials can be embedded or downloaded in mobile apps?
- Which payment provider and settlement ledger are authoritative?
- Which public-site pages stay web-only, and which need native screens for store launch?

## Standard organisation package

- Public address: `/{slug}` on the shared VOCA application; no DNS setup required.
- Default homepage uses the organisation name, logo, tagline and description, with enabled feature links and a dashboard link for administrators.
- Vue Router retains the organisation path; Axios sends `X-Voca-Organisation` so backend tenant scopes and permissions apply to that organisation. Shared-host requests resolve the explicit organisation, rejecting unknown or suspended organisations.
- Existing organisation dashboard supports branding, modules, ministries, user access and media. Invitation and recovery links retain the Standard page address; public media URLs retain organisation context.
- Platform portal lists and opens the package-specific address. Standard uses page URLs; custom domain attachment requires Enterprise.
- Validation: tenancy, account access, ministry member management and media tests (33 tests, 143 assertions) and production frontend build passed. Emulator visual verification remains outstanding.

## Owner onboarding and app guide

See [VOCA app guide](voca-app-guide.md) for registration, roles, organization/ministry setup, capabilities and integration limits. The integrated owner guide at `/admin/setup` now collects identity, uploaded logo/welcome image, suggested logo colors, public contact/social details, modules and an optional first ministry. Configuration saves atomically; uploads are immediate. Organization provisioning remains a platform-admin task.

### Completed locally: public live feed and signed-in information

Both app interfaces retain the original homepage/dashboard at `/`. The public network-wide Live now feed is on `/live`, with playback available without an account. `/inside` provides a signed-in entry to the home experience; information screens and navigation now sit behind sign-in. Active organizations with livestreaming enabled and an explicitly live, playable broadcast appear; the feed polls every 30 seconds. Operators manage saved live/offline status in organization stream settings. Automatic provider detection and separate concurrent broadcasts for department ministries remain future integration work. See the app guide for validation and release limits.


Google Translate: shared Languages button uses the same online Google Website Translator widget as uicafrica.com, without an API key. Google has ended support for this legacy widget; browser and native WebView compatibility require release testing. See the app guide.
