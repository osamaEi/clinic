<?php

namespace App\Providers;

use App\Http\Controllers\Admin\AuthController;
use App\Support\Subscriptions\ChangeSubscriptionPlan;
use App\Support\Subscriptions\ExtendSubscription;
use App\Support\Subscriptions\ResumeSubscription;
use App\Support\Subscriptions\SubscriptionActions;
use App\Support\Subscriptions\SuspendSubscription;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(AuthController::class)
            ->needs(StatefulGuard::class)
            ->give(fn (Application $app): StatefulGuard => $app->make(AuthFactory::class)->guard('web'));

        $this->app->tag([
            ExtendSubscription::class,
            SuspendSubscription::class,
            ResumeSubscription::class,
            ChangeSubscriptionPlan::class,
        ], 'clinic.subscription.actions');

        $this->app->when(SubscriptionActions::class)
            ->needs('$actions')
            ->giveTagged('clinic.subscription.actions');
    }

    public function boot(): void {}
}
