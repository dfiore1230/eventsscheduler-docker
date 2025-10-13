<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewView;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $forceHttps = config('app.force_https');

        if ($forceHttps === null) {
            $forceHttps = (bool) env('FORCE_HTTPS', false);
        }

        if ($forceHttps) {
            URL::forceScheme('https');
        }

        if ($this->app->runningInConsole()) {
            return;
        }

        foreach (['schedules', 'venues', 'curators'] as $key) {
            if (!View::shared($key)) {
                View::share($key, collect());
            }
        }

        View::composer('*', function (ViewView $view): void {
            $data = $view->getData();

            if (!array_key_exists('schedules', $data)) {
                $view->with('schedules', collect());
            }

            if (!array_key_exists('venues', $data)) {
                $view->with('venues', collect());
            }

            if (!array_key_exists('curators', $data)) {
                $view->with('curators', collect());
            }
        });

        if (!class_exists(\App\Models\Setting::class)) {
            return;
        }

        try {
            if (Schema::hasTable('settings')) {
                $settings = \App\Models\Setting::query()
                    ->get(['name', 'value'])
                    ->pluck('value', 'name')
                    ->toArray();

                foreach ($settings as $key => $value) {
                    config(["settings.$key" => $value]);
                }

                View::share('globalSettings', $settings);
            }
        } catch (\Throwable $e) {
            // During image build we do not have a database, so swallow errors.
            return;
        }
    }
}
