<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        if (str_starts_with((string) config('app.url'), 'https://') && $this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->autoSetup();

        View::composer(['layouts.*', 'pages.*', 'member.*', 'auth.*', 'admin.*'], function ($view) {
            static $gym = null;
            $gym ??= Setting::all_values() + array_fill_keys(array_keys(Setting::FIELDS), '');
            $view->with('gym', $gym);
        });
    }

    /**
     * Local convenience (AUTO_SETUP=true): on the first web request, create the
     * MySQL database if it is missing and run migrations + seeders, so the site
     * works in XAMPP without typing artisan commands.
     */
    /** Bump this when the seed data changes so existing local databases refresh themselves. */
    private const SETUP_VERSION = '2026-11-12-real-gym-data-3';

    /**
     * Local convenience (AUTO_SETUP=true): on the first web request, create the
     * MySQL database if it is missing and run migrations + seeders, so the site
     * works in XAMPP without typing artisan commands. When SETUP_VERSION changes,
     * it runs new migrations and reloads the gym data (coaches, classes, timetable).
     */
    private function autoSetup(): void
    {
        if ($this->app->runningInConsole() || ! env('AUTO_SETUP', false)) {
            return;
        }
        $flag = storage_path('app/athena-installed');
        if (is_file($flag) && trim((string) file_get_contents($flag)) === self::SETUP_VERSION) {
            return;
        }

        try {
            try {
                DB::connection()->getPdo();
            } catch (\Throwable $e) {
                if (config('database.default') !== 'mysql' || ! str_contains($e->getMessage(), 'Unknown database')) {
                    throw $e;
                }
                $c = config('database.connections.mysql');
                $pdo = new \PDO("mysql:host={$c['host']};port={$c['port']}", $c['username'], $c['password']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                DB::purge();
            }

            $fresh = ! Schema::hasTable('plans');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true] + ($fresh ? [] : ['--class' => \Database\Seeders\GymDataSeeder::class]));
            @file_put_contents($flag, self::SETUP_VERSION);
        } catch (\Throwable $e) {
            report($e);
            throw new \RuntimeException('Athena AI setup could not prepare the database. Check that MySQL is started in the XAMPP Control Panel. Details: ' . $e->getMessage(), 0, $e);
        }
    }
}
