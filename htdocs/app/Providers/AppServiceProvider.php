<?php

namespace App\Providers;

use App\Models\Haul;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // ledger_entries.source_type is varchar(40); keep class names out of it.
        Relation::morphMap([
            'haul' => Haul::class,
        ]);
    }
}
