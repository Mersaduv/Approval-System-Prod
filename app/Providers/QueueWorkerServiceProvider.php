<?php

namespace App\Providers;

use App\Services\QueueWorkerService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class QueueWorkerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Start queue worker when application boots
        if (config('queue.auto_start_worker', true)) {
            try {
                QueueWorkerService::startWorker();
                Log::info('Queue worker auto-started');
            } catch (\Exception $e) {
                Log::error('Failed to auto-start queue worker: ' . $e->getMessage());
            }
        }
    }
}
