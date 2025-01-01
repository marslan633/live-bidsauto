<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdatePivotCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:pivot-counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update counts in pivot tables dynamically for large-scale data';

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     $this->info('Starting pivot table counts update...');
        
    //     // Configuration map for relationships
    //     $config = [
    //         'manufacturers' => [
    //             'relations' => [
    //                 'vehicle_models' => ['pivot' => 'manufacturer_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
    //                 'vehicle_types' => ['pivot' => 'manufacturer_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
    //                 'conditions' => ['pivot' => 'manufacturer_condition', 'foreign_key' => 'condition_id'],
    //                 'fuels' => ['pivot' => 'manufacturer_fuel', 'foreign_key' => 'fuel_id'],
    //                 'seller_types' => ['pivot' => 'manufacturer_seller_type', 'foreign_key' => 'seller_type_id'],
    //                 'drive_weels' => ['pivot' => 'manufacturer_drive_weel', 'foreign_key' => 'drive_weel_id'],
    //                 'transmissions' => ['pivot' => 'manufacturer_transmission', 'foreign_key' => 'transmission_id'],
    //                 'detailed_titles' => ['pivot' => 'manufacturer_detailed_title', 'foreign_key' => 'detailed_title_id'],
    //                 'damages' => ['pivot' => 'manufacturer_damage', 'foreign_key' => 'damage_id'],
    //             ],
    //         ],
    //     ];

    //     foreach ($config as $base => $data) {
    //         $this->info("Processing base table: {$base}");
    //         foreach ($data['relations'] as $related => $relationConfig) {
    //             $this->info("Processing relation: {$base} -> {$related}");
    //             $this->processRelation($base, $related, $relationConfig);
    //         }
    //     }

    //     $this->info('Pivot table counts updated successfully!');
    // }

    // protected function processRelation($base, $related, $relationConfig)
    // {
    //     $pivot = $relationConfig['pivot'];
    //     $foreignKey = $relationConfig['foreign_key'];
        
    //     $this->info("Join condition: {$related}.id = {$base}.{$foreignKey}");

    //     $counts = DB::table($base)
    //         ->join($related, "{$related}.id", '=', "{$base}.{$foreignKey}")
    //         ->select("{$base}.id as base_id", "{$related}.id as related_id", DB::raw('COUNT(*) as count'))
    //         ->groupBy("{$base}.id", "{$related}.id")
    //         ->get();

    //     $insertData = $counts->map(function ($count) {
    //         return [
    //             'base_id' => $count->base_id,
    //             'related_id' => $count->related_id,
    //             'count' => $count->count,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ];
    //     })->toArray();

    //     if (!empty($insertData)) {
    //         DB::table($pivot)->upsert($insertData, ['base_id', 'related_id'], ['count', 'updated_at']);
    //         $this->info("Updated pivot table: {$pivot}");
    //     }
    // }

    public function handle()
    {
        $startTime = now();
        $this->info('Starting pivot table counts update...');
        $this->info("Start time: {$startTime}");
        \Log::info("Process started at: " . $startTime);

        // Configuration for relationships
        $config = [
            // 'manufacturers' => [
            //     'relations' => [
            //         'vehicle_models' => ['pivot' => 'manufacturer_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'manufacturer_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'manufacturer_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'manufacturer_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'manufacturer_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_weels' => ['pivot' => 'manufacturer_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'manufacturer_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'manufacturer_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'manufacturer_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'manufacturer_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'manufacturer_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'vehicle_models' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'vehicle_model_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_types' => ['pivot' => 'vehicle_model_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'vehicle_model_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'vehicle_model_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'vehicle_model_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_weels' => ['pivot' => 'vehicle_model_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'vehicle_model_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'vehicle_model_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'vehicle_model_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'vehicle_model_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'vehicle_model_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'vehicle_types' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'vehicle_type_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'vehicle_type_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'conditions' => ['pivot' => 'vehicle_type_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'vehicle_type_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'vehicle_type_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_wheels' => ['pivot' => 'vehicle_type_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'vehicle_type_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'vehicle_type_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'vehicle_type_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'vehicle_type_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'vehicle_type_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'conditions' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'condition_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'condition_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'condition_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'fuels' => ['pivot' => 'condition_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'condition_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_wheels' => ['pivot' => 'condition_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'condition_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'condition_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'condition_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'condition_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'condition_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'fuels' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'fuel_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'fuel_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'fuel_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'fuel_condition', 'foreign_key' => 'condition_id'],
            //         'seller_types' => ['pivot' => 'fuel_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_wheels' => ['pivot' => 'fuel_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'fuel_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'fuel_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'fuel_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'fuel_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'fuel_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'seller_types' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'seller_type_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'seller_type_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'seller_type_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'seller_type_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'seller_type_fuel', 'foreign_key' => 'fuel_id'],
            //         'drive_wheels' => ['pivot' => 'seller_type_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'seller_type_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'seller_type_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'seller_type_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'seller_type_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'seller_type_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'drive_wheels' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'drive_wheel_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'drive_wheel_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'drive_wheel_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'drive_wheel_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'drive_wheel_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'drive_wheel_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'transmissions' => ['pivot' => 'drive_wheel_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'drive_wheel_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'drive_wheel_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'drive_wheel_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'drive_wheel_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'transmissions' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'transmission_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'transmission_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'transmission_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'transmission_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'transmission_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'transmission_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_wheels' => ['pivot' => 'transmission_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'detailed_titles' => ['pivot' => 'transmission_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'transmission_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'transmission_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'transmission_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'detailed_titles' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'detailed_title_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'detailed_title_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'detailed_title_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'detailed_title_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'detailed_title_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'detailed_title_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_wheels' => ['pivot' => 'detailed_title_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'detailed_title_transmission', 'foreign_key' => 'transmission_id'],
            //         'damages' => ['pivot' => 'detailed_title_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'domains' => ['pivot' => 'detailed_title_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'detailed_title_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'damages' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'damage_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'damage_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'damage_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'damage_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'damage_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'damage_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_wheels' => ['pivot' => 'damage_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'damage_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'damage_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'domains' => ['pivot' => 'damage_domain', 'foreign_key' => 'domain_id'],
            //         'years' => ['pivot' => 'damage_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            // 'domains' => [
            //     'relations' => [
            //         'manufacturers' => ['pivot' => 'domain_manufacturer', 'foreign_key' => 'manufacturer_id'],
            //         'vehicle_models' => ['pivot' => 'domain_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
            //         'vehicle_types' => ['pivot' => 'domain_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
            //         'conditions' => ['pivot' => 'domain_condition', 'foreign_key' => 'condition_id'],
            //         'fuels' => ['pivot' => 'domain_fuel', 'foreign_key' => 'fuel_id'],
            //         'seller_types' => ['pivot' => 'domain_seller_type', 'foreign_key' => 'seller_type_id'],
            //         'drive_wheels' => ['pivot' => 'domain_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
            //         'transmissions' => ['pivot' => 'domain_transmission', 'foreign_key' => 'transmission_id'],
            //         'detailed_titles' => ['pivot' => 'domain_detailed_title', 'foreign_key' => 'detailed_title_id'],
            //         'damages' => ['pivot' => 'domain_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
            //         'years' => ['pivot' => 'domain_year', 'foreign_key' => 'year_id'],
            //     ],
            // ],
            'years' => [
                'relations' => [
                    'manufacturers' => ['pivot' => 'year_manufacturer', 'foreign_key' => 'manufacturer_id'],
                    'vehicle_models' => ['pivot' => 'year_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
                    'vehicle_types' => ['pivot' => 'year_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
                    'conditions' => ['pivot' => 'year_condition', 'foreign_key' => 'condition_id'],
                    'fuels' => ['pivot' => 'year_fuel', 'foreign_key' => 'fuel_id'],
                    'seller_types' => ['pivot' => 'year_seller_type', 'foreign_key' => 'seller_type_id'],
                    'drive_wheels' => ['pivot' => 'year_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
                    'transmissions' => ['pivot' => 'year_transmission', 'foreign_key' => 'transmission_id'],
                    'detailed_titles' => ['pivot' => 'year_detailed_title', 'foreign_key' => 'detailed_title_id'],
                    'damages' => ['pivot' => 'year_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
                    'domains' => ['pivot' => 'year_domain', 'foreign_key' => 'domain_id'],
                ],
            ],
        ];

        foreach ($config as $base => $data) {
            $this->info("Processing base table: {$base}");
            \Log::info("Processing base table: {$base}");

            $baseRecords = DB::table($base)->select('id')->get();

            foreach ($baseRecords as $baseRecord) {
                $baseId = $baseRecord->id;
                $this->info("Processing {$base} ID: {$baseId}");
                \Log::info("Processing {$base} ID: {$baseId}");
                

                $singularBase = Str::singular($base);

                $relatedRecords = DB::table('vehicle_records')
                    ->where("{$singularBase}_id", $baseId)
                    ->get();

                foreach ($data['relations'] as $relation => $relationData) {
                    $pivotTable = $relationData['pivot'];
                    $foreignKey = $relationData['foreign_key'];

                    // Check if a custom field is specified
                    $fieldToGroupBy = $relationData['custom_field'] ?? $foreignKey;

                    $this->info("Processing relation: {$relation} (Pivot: {$pivotTable}, Group By: {$fieldToGroupBy})");
                    \Log::info("Processing relation: {$relation} (Pivot: {$pivotTable}, Group By: {$fieldToGroupBy})");

                    $counts = $relatedRecords
                        ->filter(function ($record) use ($fieldToGroupBy) {
                            return isset($record->{$fieldToGroupBy}) && is_numeric($record->{$fieldToGroupBy});
                        })
                        ->groupBy($fieldToGroupBy)
                        ->map(function ($group) {
                            return $group->count();
                        });

                    foreach ($counts as $relatedId => $count) {
                        if ($relatedId === null || !is_numeric($relatedId)) {
                            continue;
                        }

                        DB::table($pivotTable)->updateOrInsert(
                            [
                                "{$singularBase}_id" => $baseId,
                                $foreignKey => $relatedId,
                            ],
                            [
                                'count' => $count,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }

                    $this->info("Updated pivot table: {$pivotTable} for {$base} ID: {$baseId}");
                }
            }
        }

        $endTime = now();
        $executionTime = $startTime->diffInSeconds($endTime);

        $this->info('Pivot table counts updated successfully!');
        $this->info("End time: {$endTime}");
        $this->info("Total execution time: {$executionTime} seconds");

        \Log::info("End time: {$endTime}");
        \Log::info("Total execution time: {$executionTime} seconds");
    }
}