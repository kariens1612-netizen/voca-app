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

Server backups, credentials, databases, uploads, dependencies and generated builds are excluded from version control.

For public uploads, create a `storage` symlink in the web root pointing to `app-src/storage/app/public`.
