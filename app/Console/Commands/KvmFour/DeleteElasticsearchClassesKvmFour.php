<?php

namespace App\Console\Commands\KvmFour;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class DeleteElasticsearchClassesKvmFour extends Command
{
    protected $signature = 'process:delete-elasticsearch-classes-kvm-four';
    protected $description = 'Delete Elasticsearch indexes if they exist';

    public function handle()
    {
        $client = app('ElasticsearchKvmFour');

        $indices = [
            'vehicle_records',
            'vehicle_record_archiveds',
            'sale_auction_histories'
        ];

        foreach ($indices as $indexName) {
            $exists = $client->indices()->exists(['index' => $indexName]);
            $this->info("Checking index '{$indexName}': " . ($exists ? 'Exists' : 'Does not exist'));

            if ($exists) {
                try {
                    $client->indices()->delete(['index' => $indexName]);
                    $this->info("Index '{$indexName}' deleted successfully.");
                } catch (ClientResponseException $e) {
                    if (str_contains($e->getMessage(), 'index_not_found_exception')) {
                        $this->info("Index '{$indexName}' not found during delete, skipping.");
                    } else {
                        throw $e;
                    }
                }
            } else {
                $this->info("Index '{$indexName}' does not exist. Skipping delete.");
            }
        }
    }
}
