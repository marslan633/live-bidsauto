<?php

namespace App\Console\Commands\KvmFour;

use Illuminate\Console\Command;
use App\Models\LandingPageRule;
use Carbon\Carbon;
use Log;

class IndexLandingPageRules extends Command
{
    protected $signature = 'index:landing-page-rules';
    protected $description = 'Index landing page rules to Elasticsearch';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $this->info("Index Landing Page Rules started at: " . $startDateTime);
        Log::info("Index Landing Page Rules started at: " . $startDateTime);

        $clientKvmOne  = app('ElasticsearchKvmOne');
        $clientKvmFour = app('ElasticsearchKvmFour');

        $cronRun = null;

        /**
         * ------------------------------------------------------------
         * Create cron run history (status = running)
         * ------------------------------------------------------------
         */
        try {
            $response = $clientKvmOne->index([
                'index' => 'cron_run_histories',
                'body' => [
                    'cron_name'   => 'process_landing_page_rules_to_elasticsearch',
                    'start_time'  => $startDateTime->toIso8601String(),
                    'status'      => 'running',
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ],
            ]);

            $cronRun = $response['_id'];
        } catch (\Exception $e) {
            $clientKvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.4',
                    'error_type'  => 'Internal Server Error',
                    'command_name'=> 'index:landing-page-rules',
                    'error'       => 'CRON HISTORY ERROR: ' . $e->getMessage(),
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ],
            ]);
            return;
        }

        /**
         * ------------------------------------------------------------
         * Fetch & Index Landing Page Rules
         * ------------------------------------------------------------
         */
        try {
            $rules = LandingPageRule::where('is_active', true)->get();

            if ($rules->isEmpty()) {
                $this->warn('No active landing page rules found.');
            }

            foreach ($rules as $rule) {
                $docId = $rule->section_key;

                $body = [
                    'section_key'   => $rule->section_key,
                    'section_title' => $rule->section_title,
                    'request_body'  => $rule->request_body,
                    'limit'         => $rule->limit,
                    'is_active'     => (bool) $rule->is_active,
                ];

                $clientKvmFour->index([
                    'index' => 'landing_page_rules',
                    'id'    => $docId,
                    'body'  => [
                        'section_key'   => $rule->section_key,
                        'section_title' => $rule->section_title,
                        'request_body'  => $rule->request_body, // DB is source of truth
                        'limit'         => $rule->limit,
                        'is_active'     => (bool) $rule->is_active,
                    ],
                ]);

                $this->info("Indexed rule: {$rule->section_key}");
            }

        } catch (\Exception $e) {
            /**
             * Log indexing failure
             */
            $clientKvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.4',
                    'error_type'  => 'Indexing Failed',
                    'command_name'=> 'index:landing-page-rules',
                    'error'       => 'ERROR: LANDING PAGE RULES INDEXING FAILED: ' . $e->getMessage(),
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ],
            ]);

            if ($cronRun) {
                $clientKvmOne->update([
                    'index' => 'cron_run_histories',
                    'id'    => $cronRun,
                    'body'  => [
                        'doc' => [
                            'end_time'   => now()->toIso8601String(),
                            'status'     => 'failed',
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ],
                ]);
            }

            return;
        }

        /**
         * ------------------------------------------------------------
         * Mark cron as SUCCESS
         * ------------------------------------------------------------
         */
        if ($cronRun) {
            $clientKvmOne->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun,
                'body'  => [
                    'doc' => [
                        'end_time'   => now()->toIso8601String(),
                        'status'     => 'success',
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
            ]);

            Log::info('STORE LANDING PAGE RULES TO ELASTICSEARCH SUCCESS');
        }
    }
}