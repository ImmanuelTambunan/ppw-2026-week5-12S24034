<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

public function boot(): void
{
    DB::listen(function ($query) {
        Log::info(sprintf("[SQL AUDIT] (%.2f ms) %s", $query->time, $query->sql));
    });
}
}