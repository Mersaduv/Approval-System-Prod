<?php

namespace App\Console\Commands;

use App\Services\QueueWorkerService;
use Illuminate\Console\Command;

class StartQueueWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:start-worker {--daemon : Run in daemon mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the queue worker service';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting queue worker...');

        if ($this->option('daemon')) {
            QueueWorkerService::startWorker();
            $this->info('Queue worker started in daemon mode');
        } else {
            $this->info('Starting queue worker in foreground...');
            $this->call('queue:work', [
                '--tries' => 3,
                '--timeout' => 60,
                '--memory' => 128
            ]);
        }
    }
}
