<?php

namespace App\Providers;

use App\Contracts\FinancialSystemInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Financial\SimulatedFinancialSystem;
use App\Payments\FakePaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        $this->app->bind(FinancialSystemInterface::class, SimulatedFinancialSystem::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
