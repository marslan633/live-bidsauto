<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\{VehicleRecord, VehicleRecordArchived, Status};

class ProcessArchivedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:archived-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move archived data from VehicleRecord to VehicleRecordArchived based on status_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = 1000; // Time frame in minutes
        $perPage = 100; // Records per page
        $baseUrl = 'http://carstat.dev/api/archived-lots';
        $totalPages = 0;

        try {
            // Fetch total records from the API
            $response = Http::withHeaders([
                'x-api-key' => env('CAR_API_KEY'),
            ])
            ->timeout(120)
            ->retry(3, 1000)
            ->get("{$baseUrl}?minutes={$minutes}&page=1&per_page={$perPage}");

            if ($response->successful()) {
                $totalRecords = $response->json()['meta']['total'] ?? 0;
                $this->info("Fetching total number of records: {$totalRecords}");
                \Log::info("Fetching total number of records: {$totalRecords}");

                if ($totalRecords > 0) {
                    $totalPages = ceil($totalRecords / $perPage); // Calculate total API calls required
                    $this->info("Total Pages: $totalPages");
                    \Log::info("Total Pages: $totalPages");

                    for ($page = 1; $page <= $totalPages; $page++) {
                        $apiUrl = "{$baseUrl}?minutes={$minutes}&page={$page}&per_page={$perPage}";

                        $this->info("Fetching page {$page} of {$totalPages}");
                        \Log::info("Fetching page {$page} of {$totalPages}, URL: {$apiUrl}");

                        $pageResponse = Http::withHeaders([
                            'x-api-key' => env('CAR_API_KEY'),
                        ])
                        ->timeout(120)
                        ->retry(3, 1000)
                        ->get($apiUrl);

                        if ($pageResponse->successful()) {
                            $data = $pageResponse->json()['data'] ?? [];

                            foreach ($data as $car) {
                                $lot = (int) $car['lot']; 
                                $this->processCarData($lot);
                            }

                            $this->info("Page {$page} processed successfully.");
                            \Log::info("Page {$page} processed successfully.");
                        } else {
                            $this->error("Failed to fetch data for page {$page}.");
                            \Log::error("Failed to fetch data for page {$page}.");
                            break;
                        }
                    }
                } else {
                    $this->info("No records to process.");
                    \Log::info("No records to process.");
                }
            } else {
                $this->error("Failed to fetch total records.");
                \Log::error("Failed to fetch total records.");
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            \Log::error("Error: " . $e->getMessage());
        }
    }

    private function processCarData($lotId)
    {
        // Check if the record exists in VehicleRecord
        $record = VehicleRecord::where('lot_id', $lotId)->first();

        if ($record) {
            // Move the record to VehicleRecordArchived
            VehicleRecordArchived::create($record->toArray());

            // Delete the record from VehicleRecord
            $record->delete();

            $this->info("Archived and deleted record with lot_id: {$lotId}");
            \Log::info("Archived and deleted record with lot_id: {$lotId}");
        } else {
            $this->info("No record found with lot_id: {$lotId}");
            \Log::info("No record found with lot_id: {$lotId}");
        }
    }
}