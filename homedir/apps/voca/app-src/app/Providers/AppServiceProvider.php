<?php

namespace App\Providers;

use App\Console\Commands\SyncAimeosCommerceSites;
use App\Models\Ministry;
use App\Tenancy\CurrentOrganisation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Tenancy\CurrentOrganisation::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, $token) {
            return request()->getSchemeAndHttpHost().'/reset-password/'.$token.'?'.http_build_query(['email' => $user->email]);
        });
        \Illuminate\Support\Facades\RateLimiter::for('login', function ($request) {
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip()),
            ];
        });
        Gate::define('admin', function ($user, $class, $roles) {
            if ($user?->is_superadmin) {
                return true;
            }

            $site = Route::input('site', request('site', config('shop.mshop.locale.site', 'voca')));
            $organisation = app(CurrentOrganisation::class)->get();

            if (! $organisation) {
                return false;
            }

            if ($site === $organisation->slug || $site === config('shop.mshop.locale.site', 'voca')) {
                return $user->organisationMemberships()
                    ->where('organisation_id', $organisation->id)
                    ->whereIn('role', ['owner', 'admin'])
                    ->where('status', 'active')
                    ->exists();
            }

            $ministry = Ministry::query()
                ->where('organisation_id', $organisation->id)
                ->get()
                ->first(fn (Ministry $ministry) => $site === SyncAimeosCommerceSites::siteCode($ministry));

            if (! $ministry) {
                return false;
            }

            return $user->memberships()
                ->where('ministry_id', $ministry->id)
                ->whereIn('role', ['admin', 'leader'])
                ->exists();
        });
    }

}
