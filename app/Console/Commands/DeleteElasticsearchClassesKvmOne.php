<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class DeleteElasticsearchClassesKvmOne extends Command
{
    protected $signature = 'process:delete-elasticsearch-classes-kvm-one';
    protected $description = 'Delete Elasticsearch indexes if they exist';

    public function handle()
    {
        $client = app('ElasticsearchKvmOne');

        $indices = [
            'vehicle_api_data',
            'vehicle_process_cached_api_data',
            'vehicle_archived_api_data',
            'cron_run_histories',
            'error_logs',
        ];

        foreach ($indices as $indexName) {
            $exists = $client->indices()->exists(['index' => $indexName]);
            $this->info("Exists response for index {$indexName}: " . json_encode($exists));

            // If response is empty object {}, index does NOT exist
            if (is_object($exists) && count(get_object_vars($exists)) === 0) {
                $this->info("Index '{$indexName}' does NOT exist. Skipping delete.");
                continue;
            }

            if ((bool) $exists) {
                try {
                    $client->indices()->delete(['index' => $indexName]);
                    $this->info("Index '{$indexName}' deleted successfully.");
                } catch (ClientResponseException $e) {
                    if (str_contains($e->getMessage(), 'index_not_found_exception')) {
                        $this->info("Index '{$indexName}' not found (caught in delete). Skipping.");
                    } else {
                        throw $e;
                    }
                }
            } else {
                $this->info("Index '{$indexName}' does NOT exist. Skipping delete.");
            }
        }
    }
}
