<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class QueueWorkerService
{
    /**
     * Start the queue worker in background
     */
    public static function startWorker(): void
    {
        try {
            // Check if queue worker is already running
            if (self::isWorkerRunning()) {
                Log::info('Queue worker is already running');
                return;
            }

            // Start queue worker in background
            $command = 'php artisan queue:work --daemon --tries=3 --timeout=60';

            if (PHP_OS_FAMILY === 'Windows') {
                // Windows command
                $process = Process::start($command);
                Log::info('Queue worker started on Windows with PID: ' . $process->id());
            } else {
                // Unix/Linux command
                $process = Process::start($command);
                Log::info('Queue worker started on Unix/Linux with PID: ' . $process->id());
            }

        } catch (\Exception $e) {
            Log::error('Failed to start queue worker: ' . $e->getMessage());
        }
    }

    /**
     * Stop the queue worker
     */
    public static function stopWorker(): void
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows command
                Process::run('taskkill /F /IM php.exe /FI "WINDOWTITLE eq *queue:work*"');
            } else {
                // Unix/Linux command
                Process::run('pkill -f "queue:work"');
            }

            Log::info('Queue worker stopped');
        } catch (\Exception $e) {
            Log::error('Failed to stop queue worker: ' . $e->getMessage());
        }
    }

    /**
     * Check if queue worker is running
     */
    public static function isWorkerRunning(): bool
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows command
                $result = Process::run('tasklist /FI "IMAGENAME eq php.exe" /FI "WINDOWTITLE eq *queue:work*"');
                return strpos($result->output(), 'php.exe') !== false;
            } else {
                // Unix/Linux command
                $result = Process::run('pgrep -f "queue:work"');
                return !empty(trim($result->output()));
            }
        } catch (\Exception $e) {
            Log::error('Failed to check queue worker status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restart the queue worker
     */
    public static function restartWorker(): void
    {
        self::stopWorker();
        sleep(2); // Wait a bit before restarting
        self::startWorker();
    }

    /**
     * Process queue jobs manually
     */
    public static function processJobs(int $maxJobs = 10): void
    {
        try {
            $command = "php artisan queue:work --once --max-jobs={$maxJobs}";
            $result = Process::run($command);

            if ($result->successful()) {
                Log::info("Processed up to {$maxJobs} queue jobs");
            } else {
                Log::error("Failed to process queue jobs: " . $result->errorOutput());
            }
        } catch (\Exception $e) {
            Log::error('Failed to process queue jobs: ' . $e->getMessage());
        }
    }
}
