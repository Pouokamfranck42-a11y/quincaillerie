<?php

namespace App\Providers;

use App\Contracts\PaymentProviderContract;
use App\Services\Payment\AggregatorPaymentProvider;
use App\Services\Payment\SimulatedPaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProviderContract::class, function () {
            return config('services.payment.mode') === 'aggregator'
                ? new AggregatorPaymentProvider()
                : new SimulatedPaymentProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
