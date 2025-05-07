<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ResetKvmOne extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:kvm-one';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset KVM One by wiping MongoDB, deleting Elasticsearch indices, and recreating them.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Starting reset process for KVM One...');

        try {
            // Step 1: Run Artisan commands
            $this->info('Running php artisan db:wipe --database=mongodb...');
            Artisan::call('db:wipe --database=mongodb');

            $this->info('Running php artisan migrate --database=mongodb...');
            Artisan::call('migrate --database=mongodb --path=database/migrations/mongodb');

            $this->info('Removing laravel.log...');
            File::delete(storage_path('logs/laravel.log'));

            // Step 2: Elasticsearch client using the app binding
            $client = app('ElasticsearchKvmOne'); // Ensure this resolves to the correct Elasticsearch client

            // Step 3: Delete Elasticsearch indices
            $this->info('Deleting existing Elasticsearch indices...');
            $indicesToDelete = [
                'vehicle_api_data',
                'vehicle_process_cached_api_data',
                'vehicle_archived_api_data',
                'cron_run_histories'
            ];

            foreach ($indicesToDelete as $index) {
                // Check if the index exists before deleting
                if ($this->indexExists($client, $index)) {
                    $this->info("Deleting index: $index...");
                    $client->indices()->delete(['index' => $index]);
                } else {
                    $this->info("Index $index does not exist. Skipping deletion.");
                }
            }

            // Step 4: Create Elasticsearch indices
            $this->info('Creating Elasticsearch indices with the specified mappings...');
            $this->createIndex($client, 'vehicle_api_data', [
                'mappings' => [
                    'properties' => [
                        'cache_value' => ['type' => 'text'],
                        'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis'],
                        'expires_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis']
                    ]
                ]
            ]);

            $this->createIndex($client, 'vehicle_process_cached_api_data', [
                'mappings' => [
                    'properties' => [
                        'cache_value' => ['type' => 'text'],
                        'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                        'updated_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                        'expires_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                        'status' => ['type' => 'keyword']
                    ]
                ]
            ]);

            $this->createIndex($client, 'vehicle_archived_api_data', [
                'mappings' => [
                    'properties' => [
                        'cache_value' => ['type' => 'text'],
                        'created_at' => ['type' => 'keyword'],
                        'updated_at' => ['type' => 'date'],
                        'expires_at' => ['type' => 'date'],
                        'status' => ['type' => 'keyword']
                    ]
                ]
            ]);

            $this->createIndex($client, 'cron_run_histories', [
                'mappings' => [
                    'properties' => [
                        'cron_name' => ['type' => 'keyword'],
                        'start_time' => ['type' => 'date'],
                        'end_time' => ['type' => 'date'],
                        'status' => ['type' => 'keyword'],
                        'error_message' => ['type' => 'text']
                    ]
                ],
                'settings' => [
                    'index' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0
                    ]
                ]
            ]);

            $this->info('KVM One reset completed successfully!');
        } catch (\Exception $e) {
            Log::error('Error during KVM One reset: ' . $e->getMessage());
            $this->error('Error during KVM One reset: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to check if an Elasticsearch index exists.
     *
     * @param Client $client
     * @param string $index
     * @return bool
     */
    private function indexExists($client, string $index)
    {
        try {
            $response = $client->indices()->exists(['index' => $index]);
            return $response;
        } catch (\Exception $e) {
            Log::error("Error checking if index $index exists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper method to create an Elasticsearch index.
     *
     * @param Client $client
     * @param string $index
     * @param array $body
     * @return void
     */
    private function createIndex($client, string $index, array $body)
    {
        try {
            $client->indices()->create([
                'index' => $index,
                'body' => $body
            ]);
            $this->info("Index $index created successfully.");
        } catch (\Exception $e) {
            $this->error("Failed to create index $index: " . $e->getMessage());
        }
    }
}
