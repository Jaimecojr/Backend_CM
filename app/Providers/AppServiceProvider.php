<?php

namespace App\Providers;

use App\Auth\Md5UserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
    }
}
