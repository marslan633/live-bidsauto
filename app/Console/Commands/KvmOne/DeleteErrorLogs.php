<?php

namespace App\Console\Commands\KvmOne;

use Illuminate\Console\Command;
use Elasticsearch\Client; // or your Elasticsearch client namespace

class DeleteErrorLogs extends Command
{
    protected $signature = 'process:delete-error-logs';
    protected $description = 'Delete error logs older than 3 days from Elasticsearch';

    public function handle()
    {
        $client = app('ElasticsearchKvmOne'); // Adjust this if your client binding is different

        // Calculate date 3 days ago in the correct format
        $dateThreshold = now()->subDays(3)->format('yyyy-MM-dd HH:mm:ss');

        // Elasticsearch Delete By Query parameters
        $params = [
            'index' => 'error_logs',
            'body'  => [
                'query' => [
                    'range' => [
                        'created_at' => [
                            'lt' => $dateThreshold,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $client->deleteByQuery($params);
            $this->info('Deleted documents older than 3 days. Deleted count: ' . $response['deleted']);
        } catch (\Exception $e) {
            $this->error('Error deleting old error logs: ' . $e->getMessage());
        }
    }
}
