<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update counts for various models in the database';

    // Define the models and their respective tables and relationships
    protected $models = [
        'Manufacturer' => ['table' => 'manufacturers', 'relation' => 'manufacturer_id'],
        'VehicleModel' => ['table' => 'vehicle_models', 'relation' => 'vehicle_model_id'],
        'VehicleType' => ['table' => 'vehicle_types', 'relation' => 'vehicle_type_id'],
        'Damage' => ['table' => 'damages', 'relation' => 'damage_id'],
        'Condition' => ['table' => 'conditions', 'relation' => 'condition_id'],
        'Fuel' => ['table' => 'fuels', 'relation' => 'fuel_id'],
        'SellerType' => ['table' => 'seller_types', 'relation' => 'seller_type_id'],
        'DriveWheel' => ['table' => 'drive_wheels', 'relation' => 'drive_wheel_id'],
        'Transmission' => ['table' => 'transmissions', 'relation' => 'transmission_id'],
        'DetailedTitle' => ['table' => 'detailed_titles', 'relation' => 'detailed_title_id'],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('Starting count update for all models...');

        foreach ($this->models as $model => $details) {
            $this->updateCount($model, $details['table'], $details['relation']);
        }

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        $this->info("Count update completed in {$executionTime} seconds.");
    }

    // protected function updateCount($model, $table, $relation)
    // {
    //     $this->info("Updating counts for {$model}...");

    //     // Perform the count aggregation
    //     $counts = DB::table('vehicle_records')
    //         ->select($relation, DB::raw('COUNT(*) as total'))
    //         ->groupBy($relation)
    //         ->get();

    //     foreach ($counts as $count) {
    //         DB::table($table)
    //             ->where('id', $count->$relation)
    //             ->update(['count' => $count->total]);
    //     }

    //     $this->info("Counts updated for {$model}.");
    // }
    protected function updateCount($model, $table, $relation)
    {
        $this->info("Updating counts for {$model}...");

        $counts = DB::table('vehicle_records')
            ->select($relation, DB::raw('COUNT(*) as total'))
            ->groupBy($relation)
            ->get();

        $updateQuery = '';

        foreach ($counts as $count) {
            if (!empty($count->$relation)) { // Check if the ID is valid
                $updateQuery .= "UPDATE {$table} SET count = {$count->total} WHERE id = {$count->$relation}; ";
            }
        }

        if (!empty($updateQuery)) {
            DB::unprepared($updateQuery);
        } else {
            $this->info("No valid records found for {$model}.");
        }

        $this->info("Counts updated for {$model}.");
    }
}