<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;

class ClearWorkflowCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all workflow-related caches for performance optimization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing workflow caches...');

        CacheService::clearWorkflowCaches();

        $this->info('Workflow caches cleared successfully!');

        return 0;
    }
}
