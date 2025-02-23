<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goodway\LaravelNats\Facades\Nats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Carbon\Carbon;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer
};

class ProcessNatsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:cached-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume vehicle data from NATS JetStream and process it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $this->info("🚀 NATS Consumer started at: " . $startDateTime);
        \Log::info("🚀 NATS Consumer started at: " . $startDateTime);

        try {
            // Start the NATS subscriber
            Nats::subscribe('auction.data', function ($message) {
                try {
                    $data = json_decode($message, true);

                    if (!$data) {
                        \Log::warning("⚠ Received empty data from NATS.");
                        return;
                    }

                    foreach ($data as $car) {
                        $this->processCarData($car);
                    }

                    \Log::info("✅ Successfully processed vehicle data from NATS.");
                } catch (\Exception $e) {
                    \Log::error("❌ Error processing vehicle data: " . $e->getMessage());

                    // Send email notification
                    $adminEmails = explode(',', env('ADMIN_EMAIL'));
                    Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), 'nats_consumer'));
                }
            });

            while (true) {
                Nats::wait(1); // Keep the consumer running
            }
        } catch (\Exception $e) {
            \Log::error("❌ Error in NATS Consumer: " . $e->getMessage());
        }
    }

    /**
     * Process each vehicle data
     */
    private function processCarData(array $car)
    {
        // No changes in data processing logic, keeping it the same
        $model = null;
        $generation = null;

        $unknownApiId = 0;
        $unknownName = 'unknown';

        // Process Manufacturer
        $manufacturer = $car['manufacturer'] ? Manufacturer::firstOrCreate(
            ['manufacturer_api_id' => $car['manufacturer']['id']],
            ['name' => $car['manufacturer']['name']]
        ) : Manufacturer::firstOrCreate(
            ['manufacturer_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Model
        if ($manufacturer) {
            $model = $car['model'] ? VehicleModel::firstOrCreate(
                ['vehicle_model_api_id' => $car['model']['id']],
                [
                    'name' => $car['model']['name'],
                    'manufacturer_id' => $manufacturer->id
                ]
            ) : VehicleModel::firstOrCreate(
                ['vehicle_model_api_id' => $unknownApiId],
                [
                    'name' => $unknownName,
                    'manufacturer_id' => $manufacturer->id
                ]
            );
        }

        // Process Generation
        if ($manufacturer && $model) {
            $generation = $car['generation'] ? Generation::firstOrCreate(
                ['generation_api_id' => $car['generation']['id']],
                [
                    'name' => $car['generation']['name'],
                    'manufacturer_id' => $manufacturer->id,
                    'model_id' => $model->id
                ]
            ) : Generation::firstOrCreate(
                ['generation_api_id' => $unknownApiId],
                [
                    'name' => $unknownName,
                    'manufacturer_id' => $manufacturer->id,
                    'model_id' => $model->id
                ]
            );
        }

        // Process Year
        $year = null;
        if ($car['year']) {
            $year = Year::firstOrCreate(
                ['name' => $car['year']]
            );
        }

        // Process Vehicle Record
        $vehicleRecord = VehicleRecord::updateOrCreate(
            ['api_id' => $car['id']],
            [
                'year' => $car['year'],
                'year_id' => $year?->id,
                'title' => $car['title'],
                'vin' => $car['vin'],
                'manufacturer_id' => $manufacturer?->id,
                'vehicle_model_id' => $model?->id,
                'generation_id' => $generation?->id,
                'cylinders' => $car['cylinders'],
            ]
        );

        if ($vehicleRecord->wasRecentlyCreated) {
            $vehicleRecord->update([
                'processed_at' => Carbon::now(),
                'is_new' => true,
            ]);
        } elseif ($vehicleRecord->wasChanged()) {
            if ($vehicleRecord->is_new) {
                $vehicleRecord->update([
                    'processed_at' => Carbon::now(),
                ]);
            }
        }

        \Log::info("✅ Processed vehicle ID: " . $car['id']);
    }
}
