<?php

namespace App\Providers;

use App\Services\Orchestrator\AttentionSummary;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.app', function ($view): void {
            $view->with('layoutAttentionSummary', app(AttentionSummary::class)->forDashboard());
        });
    }
}
