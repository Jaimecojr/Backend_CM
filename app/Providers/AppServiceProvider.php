<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Md5UserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(150);

        Auth::provider('md5-eloquent', function ($app, array $config) {
            return new Md5UserProvider($app['hash'], $config['model']);
        });

        // Generated URLs (carnet PDF links sent to Meta, pagination links) must be https in
        // production even when TLS terminates at a proxy in front of PHP.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Generous enough for the admin panel (several parallel requests per screen), low enough
        // to stop scripted scraping of the API with a stolen session.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });

        // Keyed by username + IP so one attacker can't lock out a user from another network, plus a
        // per-IP ceiling against spraying many usernames from one machine.
        RateLimiter::for('login', function (Request $request) {
            $user = Str::lower((string) $request->input('user'));

            return [
                Limit::perMinute(5)->by($user . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Public website forms (contact, affiliate request): a human sends a handful per minute.
        RateLimiter::for('public-forms', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
