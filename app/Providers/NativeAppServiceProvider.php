<?php

namespace App\Providers;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Artisan;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

/**
 * Standalone desktop build (NativePHP): Laravel, PHP and SQLite run on the clinic's PC and the window
 * shows the same PWA at /app/. config('app.standalone') is on here, see Clinic::subscriptionState().
 */
class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        // Migrations run automatically on first launch of each version; registration needs the plans too.
        if (! Plan::query()->exists()) {
            Artisan::call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
        }

        Window::open()
            ->url(url('/app/'))
            ->title('عيادتي')
            ->width(1366)
            ->height(860)
            ->minWidth(900)
            ->minHeight(600)
            ->backgroundColor('#EEF3F7')
            ->hideMenu()
            ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'upload_max_filesize' => '50M',
            'post_max_size' => '55M',
        ];
    }
}
