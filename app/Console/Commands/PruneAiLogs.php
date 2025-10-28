<?php

namespace App\Console\Commands;

use App\Models\AiLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneAiLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:prune-ai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune the ai_logs table, keeping only the last 100 records.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Pruning AI logs...');

        try {
            $totalLogs = AiLog::count();
            $logsToKeep = 200;

            if ($totalLogs > $logsToKeep) {
                $logsToDelete = $totalLogs - $logsToKeep;

                $this->info("Total logs: {$totalLogs}. Deleting {$logsToDelete} oldest logs.");

                // Get the IDs of the oldest logs to be deleted
                $oldestLogIds = AiLog::orderBy('id', 'asc')
                                     ->limit($logsToDelete)
                                     ->pluck('id');

                // Delete the logs
                AiLog::whereIn('id', $oldestLogIds)->delete();

                $this->info("Successfully deleted {$logsToDelete} old AI logs.");
                Log::info("Successfully pruned {$logsToDelete} old AI logs.");
            } else {
                $this->info('No logs to prune.');
            }
        } catch (\Exception $e) {
            $this->error('An error occurred while pruning AI logs.');
            $this->error($e->getMessage());
            Log::error('Error pruning AI logs: ' . $e->getMessage());
        }
    }
}
