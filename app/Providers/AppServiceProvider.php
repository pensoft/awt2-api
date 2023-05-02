<?php

namespace App\Providers;

use App\Services\ProxyService;
use App\Socialite\PassportProvider;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if(env('FORCE_HTTPS')) {
            $this->app['request']->server->set('HTTPS', true);
        }

        $this->app->bind('ProxyService', function($app) {
            return new ProxyService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(UrlGenerator $url)
    {
        if(env('FORCE_HTTPS')) {
            $url->forceScheme('https');
        }

        Socialite::extend('passport', function ($app) {
        $config = $app['config']['services.passport'];

        return new PassportProvider(
            $app['request'],
            $config['client_id'],
            $config['client_secret'],
            URL::to($config['redirect'])
        );
    });
    }
}
