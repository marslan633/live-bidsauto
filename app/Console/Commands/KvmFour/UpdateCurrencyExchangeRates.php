<?php

namespace App\Console\Commands\KvmFour;

use Illuminate\Console\Command;
use App\Models\CurrencyExchange;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
class UpdateCurrencyExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'index:update-exchange-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch latest currency exchange rates and update database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching currency exchange data...');

        // Elasticsearch clientKvmFour
        $clientKvmFour = app('ElasticsearchKvmFour');

        // List of API providers
        $apis = [
            [
                'name' => 'ExchangeRateHost',
                'url' => 'https://api.exchangerate.host/live?access_key=' . config('app.exchange_rate_access_key'),
                'parse' => function ($data) {
                    if (!isset($data['success']) || !$data['success']) {
                        return null;
                    }
                    return [
                        'base_code' => $data['source'] ?? 'USD',
                        'last_update_at' => now(),
                        'bgn' => $data['quotes']['USDBGN'] ?? null,
                        'eur' => $data['quotes']['USDEUR'] ?? null,
                        'usd' => 1.0,
                        'source' => 'ExchangeRateHost',
                    ];
                },
            ],
            [
                'name' => 'OpenERAPI',
                'url' => 'https://open.er-api.com/v6/latest',
                'parse' => function ($data) {
                    if (!isset($data['result']) || $data['result'] !== 'success') {
                        return null;
                    }
                    return [
                        'base_code' => $data['base_code'] ?? 'USD',
                        'last_update_at' => now(),
                        'bgn' => $data['rates']['BGN'] ?? null,
                        'eur' => $data['rates']['EUR'] ?? null,
                        'usd' => 1.0,
                        'source' => 'OpenERAPI',
                    ];
                },
            ],
        ];

        $fetched = false;

        foreach ($apis as $api) {
            try {
                $this->info("Trying API: {$api['name']}");

                $response = Http::timeout(60)->get($api['url']);
                if ($response->failed()) {
                    $this->warn("❌ Failed to fetch data from {$api['name']}");
                    continue;
                }

                $data = $api['parse']($response->json());
                if (!$data || empty($data['base_code'])) {
                    $this->warn("⚠️ Invalid response from {$api['name']}");
                    continue;
                }

                // Elasticsearch index name
                $index = 'currency_exchanges';

                // Check if document exists (we assume only one document with ID=1)
                $docId = 1;
                $exists = $clientKvmFour->exists([
                    'index' => $index,
                    'id' => $docId,
                ])->asBool();

                if ($exists) {
                    // Update existing document
                    $clientKvmFour->update([
                        'index' => $index,
                        'id' => $docId,
                        'body' => [
                            'doc' => $data,
                        ],
                    ]);
                    $this->info("✅ Updated currency data in Elasticsearch ({$api['name']})");
                } else {
                    // Create new document
                    $clientKvmFour->index([
                        'index' => $index,
                        'id' => $docId,
                        'body' => $data,
                    ]);
                    $this->info("✅ Created new currency data in Elasticsearch ({$api['name']})");
                }

                $this->info("Base: {$data['base_code']} | BGN: {$data['bgn']} | EUR: {$data['eur']} | Updated: {$data['last_update_at']}");
                $fetched = true;
                break; // Stop after first successful API

            } catch (\Exception $e) {
                $this->warn("⚠️ Error with {$api['name']}: " . $e->getMessage());
            }
        }

        if (!$fetched) {
            $this->error('❌ All currency APIs failed. No data updated.');
        }
    }
}
