<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\{VehicleRecord, VehicleRecordArchived, Status};
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Illuminate\Support\Facades\Log;

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
        $startTime = microtime(true);
        $startDateTime = Carbon::now();
        $this->info("Process Archived Data started at: " . $startDateTime);
        Log::info("Process Archived Data started at: " . $startDateTime);

        $minutes = 250; // Time frame in minutes

        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name' => 'process_archived_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'minutes' => $minutes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perPage = 100;
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

                DB::table('cron_run_history')
                    ->where('id', $cronRun)
                    ->update([
                        'total_records' => $totalRecords,
                        'updated_at' => now(),
                    ]);

                $this->info("Fetching total number of records: {$totalRecords}");
                Log::info("Fetching total number of records: {$totalRecords}");

                if ($totalRecords > 0) {
                    $totalPages = ceil($totalRecords / $perPage);
                    $this->info("Total Pages: $totalPages");
                    Log::info("Total Pages: $totalPages");

                    for ($page = 1; $page <= $totalPages; $page++) {
                        $apiUrl = "{$baseUrl}?minutes={$minutes}&page={$page}&per_page={$perPage}";

                        $this->info("Fetching page {$page} of {$totalPages}");
                        Log::info("Fetching page {$page} of {$totalPages}, URL: {$apiUrl}");

                        $pageResponse = Http::withHeaders([
                            'x-api-key' => env('CAR_API_KEY'),
                        ])
                        ->timeout(120)
                        ->retry(3, 1000)
                        ->get($apiUrl);

                        if ($pageResponse->successful()) {
                            $data = $pageResponse->json()['data'] ?? [];

                            foreach ($data as $car) {
                                $this->updateArchivedRecord($car);
                            }

                            $this->info("Page {$page} processed successfully.");
                            Log::info("Page {$page} processed successfully.");
                        } else {
                            $this->error("Failed to fetch data for page {$page}.");
                            Log::error("Failed to fetch data for page {$page}.");
                            break;
                        }
                    }
                } else {
                    $this->info("No records to process.");
                    Log::info("No records to process.");
                }
            } else {
                $this->error("Failed to fetch total records.");
                Log::error("Failed to fetch total records.");
            }

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Error: " . $e->getMessage());

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $adminEmail = env('ADMIN_EMAIL');
            Mail::to($adminEmail)->send(new CronJobFailedMail($e->getMessage()));
        }
    }

    /**
     * Update VehicleRecordArchived based on lot_id from third-party API response
     */
    private function updateArchivedRecord($car)
    {
        try {
            $lotId = $car['lot'];
            $status = $car['status']['name'];
            $bid = $car['bid'];
            $finalBidUpdatedAt = $car['final_bid_updated_at'];

            $archivedRecord = VehicleRecordArchived::where('lot_id', $lotId)->first();

            if ($archivedRecord) {
                $archivedRecord->update([
                    'status' => $status,
                    'bid' => $bid,
                    'final_bid_updated_at' => $finalBidUpdatedAt,
                ]);

                Log::info("Updated archived record for lot_id: {$lotId}");
            } else {
                Log::warning("Archived record not found for lot_id: {$lotId}");
            }
        } catch (Exception $e) {
            Log::error("Error updating archived record for lot_id: {$lotId} - " . $e->getMessage());
        }
    }

    // private function processCarData($lotId)
    // {
    //     // Check if the record exists in VehicleRecord
    //     $record = VehicleRecord::where('lot_id', $lotId)->first();

    //     if ($record) {
    //         // Move the record to VehicleRecordArchived
    //         VehicleRecordArchived::create($record->toArray());

    //         // Delete the record from VehicleRecord
    //         $record->delete();

    //         $this->info("Archived and deleted record with lot_id: {$lotId}");
    //         \Log::info("Archived and deleted record with lot_id: {$lotId}");
    //     } else {
    //         $this->info("No record found with lot_id: {$lotId}");
    //         \Log::info("No record found with lot_id: {$lotId}");
    //     }
    // }
}