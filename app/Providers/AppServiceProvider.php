<?php

namespace App\Providers;

use App\Contracts\AiServiceInterface;
use App\Contracts\DocumentParserInterface;
use App\Contracts\StorageServiceInterface;
use App\Services\AiFormService;
use App\Services\CloudinaryStorageService;
use App\Services\DocumentParserService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiServiceInterface::class, AiFormService::class);
        $this->app->singleton(StorageServiceInterface::class, CloudinaryStorageService::class);
        $this->app->singleton(DocumentParserInterface::class, DocumentParserService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
