<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Voca Live Streaming with OBS

The live page at `resources/js/pages/LiveStream.vue` is now set up for an OBS workflow.

Fastest way to update the live link:

- Open `resources/js/config/liveStream.js`
- Paste the current Rumble video embed URL into `embedUrl`
- The Voca Ministries International Rumble channel is configured in `watchUrl`
- Save and rebuild or restart Vite

How it works:

- OBS streams to a public platform such as YouTube, Facebook Live, Vimeo, Rumble, or another RTMP service.
- This app embeds that public player for viewers on `/live`.
- Private stream keys must stay inside OBS or server-only env vars and must never be exposed with `VITE_*`.

Public frontend env vars:

- `VITE_LIVE_EMBED_URL` - the public embed URL shown on the `/live` page
- `VITE_LIVE_WATCH_URL` - the public watch page for opening the stream directly
- `VITE_LIVE_PLATFORM` - label such as `YouTube Live` or `Facebook Live`
- `VITE_LIVE_STATUS` - label such as `Live now` or `Stand by`
- `VITE_LIVE_SCHEDULE` - label such as `Sunday 09H30`

Example:

```env
VITE_LIVE_EMBED_URL="https://www.youtube.com/embed/live_stream?channel=YOUR_CHANNEL_ID"
VITE_LIVE_WATCH_URL="https://www.youtube.com/@yourchannel/live"
VITE_LIVE_PLATFORM="YouTube Live"
VITE_LIVE_STATUS="Live on Sundays"
VITE_LIVE_SCHEDULE="Sunday 09H30"
```

Do not put an OBS stream key in `VITE_*`.

You can also skip env vars entirely and use `resources/js/config/liveStream.js` as the edit point for Rumble links.

## Native mobile app

The Vue frontend is packaged for iOS and Android with Capacitor.

```bash
npm run mobile:sync
npm run mobile:open:ios
npm run mobile:open:android
```

For a native build, copy `.env.mobile.example` to the build environment and set `VITE_API_BASE_URL` to the public Laravel HTTPS URL. Native deep links, camera access, and push notification registration are exposed through `resources/js/composables/useNative.js`.

Push tokens are currently stored locally and emitted as `voca:push-token`; the next backend step is to persist those tokens against authenticated users and send notifications through APNs/FCM.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
