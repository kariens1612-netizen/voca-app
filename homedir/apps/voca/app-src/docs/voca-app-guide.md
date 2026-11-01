# VOCA app: owner and platform guide

Based on the checked-out Laravel/Vue application, reviewed 27 October 2026. This describes local code, not a certification of the live service. Companion inventory: [website and mobile feature map](voca-mobile-feature-map.md).

## What VOCA is

VOCA combines a public faith and community platform with private organization and ministry workspaces. Visitors can discover broadcasts, events, inspirational material and ministries. Members sign in to the tools their organization and ministry roles permit. Organizations share the application while keeping their records, administration and branding scoped to their own workspace.

The backend uses Laravel; the interface uses Vue. Capacitor packages the web interface for iOS and Android. Native distribution, production configuration and provider connections remain separate release tasks.

An **organization** is the top-level tenant: a church, ministry organization, association or other community. A **ministry** inside it is a department/team, such as worship, youth, hospitality or finance. Website registration profile types are a separate concept from app permissions.

## How people register and sign in

### Public VOCA membership

1. Open the VOCA account entry point and choose Register (`/register`).
2. Enter name, email, phone, password and password confirmation. The current registration endpoint requires a password of at least eight characters.
3. The app backend calls the configured VOCA website member service. The service must be deployed and connected for registration to work.
4. Successful registration creates/links an app account using the website member ID and gives it ordinary user membership in the VOCA organization. It starts a signed-in session.
5. Subsequent member sign-in uses the website-backed login. Forgotten-password and reset screens call the same member service.

Public registration currently submits the **individual** profile type. The website inventory also identifies pastor, church, ministry, business and affiliate profiles, but these are not six working app registration options and do not automatically confer organization administration rights.

An existing team account with the same email is not silently merged with a website identity. The app returns a conflict requiring account-linking support.

### Organization and ministry team accounts

1. The platform administrator provisions the organization and assigns/invites an owner.
2. An owner or administrator adds people by name, email and organization role through the organization dashboard. Only owners/platform administrators can manage owner access.
3. The invitation sends a password-setup link through the configured mail service. New users are created with a random password; no shared default password is distributed.
4. Invitees set their password and use team sign-in on the correct organization's portal. Existing accounts retain their existing credentials.
5. Ministry administrators assign ministry memberships and roles separately. Organization access alone does not assign a person to every ministry team.

Ordinary public registration currently does **not** provision an organization or make its registrant an owner. The new owner guide starts after provisioning and owner assignment. Fully self-service organization creation would require a separate provisioning journey.

## Roles and access

| Scope | Role | Purpose and access |
| --- | --- | --- |
| Platform | Superadministrator (`is_superadmin`) | Creates/archives organizations, manages plans, domains and tenant accounts, reads platform audits and can impersonate authorized tenant users. |
| Organization | Owner | Organization branding, modules, ministries, media and user administration, including owner access. The last active owner is protected from removal/demotion/suspension. |
| Organization | Admin | Organization configuration and ordinary user/admin management. Cannot manage owner access. |
| Organization | User | Active access to that organization and assigned ministry functions; no organization management rights. |
| Ministry | Admin | Ministry roster/member management and task-board administration, plus lower ministry-role functions. |
| Ministry | Leader | Announcement management and schedule audits, plus lower ministry-role functions. |
| Ministry | Member | Schedule creation/editing subject to controller-level rules, plus reader/comment access. |
| Ministry | User | Ministry reader access, including schedules, announcements and task-board comments. |
| Finance | Permission derived from membership | Organization owners/admins and platform administrators qualify; other users may qualify through configured finance ministry memberships. This is not a general organization role named “finance.” |

Organization owners/admins and platform superadministrators can pass ministry role gates. Controllers may add more restrictions. A role never substitutes for active tenant access, tenant matching or an enabled module.

## Setting up an organization

### Platform administrator preparation

1. Sign in through `/platform/login` and open `/platform`.
2. Create the organization with its name, unique slug, package and active status.
3. Assign an existing owner or add/invite an owner account through the platform organization controls.
4. Give the owner the correct portal address. A Standard organization uses `/{slug}` on the shared application, without DNS setup. Other packages may use subdomains; Enterprise can attach custom domains after operator verification and hosting setup.
5. Confirm email delivery and access to that portal before handing it over.

### Guided owner setup — implemented in this change

The executable setup is an integrated Vue screen, `resources/js/pages/OwnerSetup.vue`, backed by `POST /api/admin/setup`. It runs inside the app; it is not a standalone desktop installer. There is no new database migration.

On owner sign-in without an explicit return destination, the app opens `/admin/setup` within the organization's portal. For a Standard organization called `example`, that is `/example/admin/setup`. The dashboard also has a Guided owner setup link. Owners with completed setup go straight to the dashboard; administrators cannot complete it for an owner. Platform superadministrators can complete it.

1. **Organization:** enter its name, tagline, welcome/about text and timezone.
2. **Branding:** upload an optional PNG, JPG or WebP logo and welcome image, up to 8 MB each. The browser samples common logo colors and offers selectable swatches. Choose primary and secondary colors yourself or use a suggestion. Existing branding is prefilled.
3. **Contact:** enter the required public email and optional telephone, address, website and social URLs.
4. **Features and first team:** choose the available feature switches. Optionally create the first ministry with its name and unique address slug.
5. **Review:** inspect the branding preview, enabled tools and optional first ministry, then finish.
6. **Dashboard:** the saved branding configures the existing landing page and shared interface theme; the owner can then invite people and add content through the existing management tools.

Configuration and optional ministry creation are committed in one database transaction, with existing audit logging. Completion is recorded on the organization. Repeated completion is rejected to avoid duplicate ministries. Later edits use the ordinary dashboard.

Uploads are stored as soon as they succeed, before Finish. They remain in the media library if setup is abandoned. Other unsaved form entries live in the current screen only; refreshing before completion loses them. This version does not persist partially completed setup drafts.

The public organization homepage displays the saved logo, name, tagline, description, welcome image and public contact/social details. Its existing feature cards include live broadcasts, events and inspiration when enabled. Branding and module settings configure existing pages; they do not generate a separate custom website layout for every organization.

Color suggestions are simple image sampling, not an AI brand assessment. They do not generate a logo, infer a brand strategy, check contrast automatically or extract text from uploaded brochures. Owners review colors and written content themselves.

### Completing day-to-day setup

- Add departments under Ministries in the organization dashboard, or use the optional first-ministry step.
- Add/invite organization users, then assign each person's ministry role through ministry member management.
- Add broadcasts, event records, schedules, announcements and inspirational content in their respective management screens.
- Configure any external streaming, commerce, Bible or mail providers before relying on those integrations.
- Use a second ordinary account to check the public page and member experience, then test the owner's actual daily workflows.

## What the platform can do

| Area | Implemented capabilities and practical limits |
| --- | --- |
| Organization identity | Tenant branding, colors, logos, media library, module settings, organization homepages and package-specific portal addresses. |
| Ministry operations | Public ministry details, private team portals, roster/member management, member import/export, invitations and ministry roles. |
| Team planning | Ministry schedules, schedule audits, task boards, task comments and announcements. Individual operations remain role-controlled. |
| Events | Public calendar, registration, ticket retrieval and a protected registration/check-in portal. Website/app event consolidation remains a separate integration task. |
| Delegate movements | Admin-managed delegate rosters, spreadsheet/CSV import, QR badges, journeys, checkpoints, boarding/exits, arrivals/departures, history and manifests. Prepared devices can capture movements offline and synchronize with conflict handling. Initial preparation requires connectivity. |
| Live media | Live-stream screens and stream settings. Actual playback depends on supported provider URLs and configuration. |
| Inspiration | Public/member content, media and protected editorial management, including publishing, unpublishing, archiving and duplication. |
| Bible study | Bible screens backed by configured Bible API endpoints for books, chapters and passages. |
| Directory | Searchable website-backed business directory with country/city/category filters, details and contact links. Provider connectivity is required. Public ministry directory is also available. |
| Commerce | Marketplace browsing and organization commerce settings. These do not by themselves establish a unified production checkout, stock, settlement or refunds system. |
| Finance | Slip submissions and restricted finance administration. Do not equate this with a complete accounting/settlement system. |
| Personalization and accountability | Locale/timezone and user preferences, enabled modules, access controls and organization/platform audits. |
| Mobile | Shared web features packaged for Android/iOS. Capacitor dependencies do not guarantee every native capability, such as push delivery, has been configured or released. |

Bookings is an available feature flag, but enabling it alone does not establish a complete booking workflow. Website training/enrolment, Radio/TV/FaithChannel, business-profile editing and affiliate approval are identified in the broader website inventory; mobile parity and shared APIs should be assessed separately.

## What the guide does not provision automatically

It does not purchase domains, set DNS, configure email sender credentials, create payment-provider accounts, connect bank accounts, seed events/products, establish streaming subscriptions, publish app-store builds or deploy the app. It does not make organization self-registration available. Those services and decisions need their own configuration.

## Implementation and verification

Core files: `resources/js/pages/OwnerSetup.vue`, `app/Http/Controllers/TenantAdminController.php`, `routes/web.php`, Vue router/account access, organization homepage and dashboard entry point. Behavioral coverage is in `tests/Feature/OwnerSetupTest.php`.

Run the existing Laravel app and Vite development server, or build/deploy assets using the project's normal release process. The guide requires the authenticated Laravel backend and the organization's portal context; opening the Vue file directly will not execute it.


## Shared account links and layouts

Registration (`/register`), member login (`/member-login`), team login (`/login`) and password recovery/reset use the same routes and authentication logic across this frontend. App navigation keeps these links inside the current organization and app.

Account presentation is selected independently of the URL: native Capacitor builds use a compact mobile form; browser builds use the web app split layout, which also adapts to small screens. Both use the same form component and existing website-backed member endpoints.

The public website remains unchanged. Its member service supplies shared account information to the web app and mobile app; these layouts apply only to this application's frontend.


The web and mobile app public interface labels VOCA members as **Members**, including navigation, account access and business directory copy. Existing `/member-login` and password URLs, website member IDs and backend roles remain unchanged; this wording does not grant affiliate approval or alter permissions. The public website is unchanged.


## Affiliates inside the apps

The public Affiliates menu and homepage cards open `/affiliates` inside the web and mobile apps. Featured biographies use `/affiliates/:slug`; category pathways use `/affiliates/pathways/:group`. `/members` is an app alias. Mobile home also includes an Affiliates shortcut.

The app backend reads the existing public website affiliate pages using `VOCA_MEMBER_API_URL` (default `https://voca.africa`) and caches extracted public content for five minutes. Profiles, approved directory cards, counts and pathway copy come from the website, rendered as text, images and links in app components. No website scripts, menus or styles are embedded. The public website remains unchanged. Because the website has no public affiliate JSON endpoint, this adapter depends on its page markup; unavailable or unrecognized pages show a retry state rather than an empty directory.

## Public live broadcasts and signed-in home

The shared web/mobile app keeps its original homepage at `/`, including the mobile dashboard and organization-specific layout. The public **Live now** feed is at `/live` only. Visitors can select a ministry/organization broadcast and watch its embedded player without signing in, or open its public provider watch page. The feed includes active organizations with livestreaming enabled, a configured public player/watch URL and an explicit live status. Offline, scheduled and ended streams are excluded. With no live broadcasts, the page shows an empty state. Failed refreshes show a retry state rather than presenting stale broadcasts as live.

The original homepage remains public at `/`; `/inside` retains a signed-in entry to the same home experience. The existing information, affiliates, directory, Bible study, marketplace, calendar, inspiration, fellowship and ministry browsing screens require sign-in through the app router. Contact, account recovery and public ticket retrieval remain available on their existing routes. Existing backend permissions continue to control organization/team tools; this UI change does not turn formerly public APIs into private APIs. Ordinary affiliate login/registration opens Inside; role-specific team/admin destinations and explicit return links are preserved.

Organization administrators can now edit stream settings from the organization dashboard as well as the existing authorized media-team portal. Save the public embed/watch URL and set status to **Live** when broadcasting, then **Offline** when the broadcast ends. Recognized live statuses are Live, Live now, Currently live, On air and Broadcasting (case insensitive). The public page checks the saved status every 30 seconds while visible and when returning to the app. It does not detect provider status automatically: provider API/webhook integrations are still required for unattended start/end detection. Each organization currently has one stream setting; department ministries inside it share that broadcast.

Validation: production frontend build and 35 targeted Laravel tests passed (146 assertions). In-app browser unavailable in this session; real device playback and visual checks remain outstanding. Building/copying assets locally does not deploy the backend or publish store releases.

The main navigation stays available before and after sign-in on both app interfaces: a top navigation bar on desktop and the header Menu button on mobile. Home opens the original homepage; Live opens public live broadcasts; protected information pages prompt for sign-in and retain the requested destination. Administrative links remain permission-controlled.

### Native navigation during connection failures

A bundled native app without `VITE_API_BASE_URL` now uses the documented `https://app.voca.co.za` backend by default. An explicit build-time API address takes precedence; local native live-reload servers retain same-origin requests. The backend's availability, mobile CORS/session configuration and deployment still require device validation.

When the default native VOCA app cannot load bootstrap settings, public VOCA navigation remains visible, alongside the connection error and retry control. No organization, membership or admin permission is fabricated. Explicit organization contexts do not use this fallback; successfully loaded organization module switches remain authoritative. Updated bundled assets require rebuilding and running the native application to reach an installed device.


## Public About Us access

`/about-us` (and `/about`) is public on web and mobile; visitors do not need to sign in. It includes the website’s full Four Pillars, Four Corners significance, vision, mission and bundled branding image. The page uses the shared tenant identity, including the native public VOCA fallback when bootstrap cannot connect. Other organisation pages keep their own branding. Android/iOS projects need to be rebuilt and run after copying updated web assets.


## Google Translate language selection

Web, Android and iOS share a **Languages** button below the header. It uses the same Google Translate Website Translator element as https://uicafrica.com, with the same 32 language options. No API key or Google Cloud billing setup is required for this widget.

Open Languages, then choose a language in Google's dropdown. Google manages translation and its language cookie. The widget remains mounted while navigating between app routes. It loads only after opening Languages and requires an internet connection. Load failures show a retry option. Translation is performed by Google's external script, not VOCA's backend. Images, video and editable fields are not translated. Existing `translate="no"` sections, including mobile team identity cards and organisation dashboards, remain excluded.

Google's official notice says the Website Translator widget is unsupported from 1 October 2026: https://developers.google.com/search/blog/2020/05/google-translates-website-translator. It is still embedded by the reference website, but ongoing availability and native WebView compatibility are not guaranteed. Verify selecting a language, navigating pages, returning to English and failure/retry in the target browser and Android/iOS app before release. Native apps must be rebuilt/run after updated web assets are copied. The previously prepared Cloud Translation API integration has been replaced by this widget.


Member terminology: navigation, home shortcuts, sign-in, member profiles, imported public content and business directory labels use Members/Member across web and mobile. Existing `/affiliates` URLs, `/members` alias, API endpoints and role permissions are retained for compatibility.
