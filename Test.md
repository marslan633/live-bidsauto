  $clientkvmOne = app('ElasticsearchKvmOne');
        try {
            if (empty($batchData)) {
                return;
            }


            // Extract API IDs from batchData
            $lotIds = array_column($batchData, 'lot_id');

            // Fetch existing records by API ID
            $existingRecords = DB::table('vehicle_records')->whereIn('lot_id', $lotIds)->pluck('id', 'lot_id');

            // Lists for new and updated records
            $newRecords = [];
            $updatedRecords = [];
            $failedRecords = []; // ❌ Store records that failed

            foreach ($batchData as $record) {
                try {
                    if (isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['lot_id']]; // Add ID for update
                        $record['processed_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        // $record['data_source'] = 1;
                        $updatedRecords[] = $record;
                    } else {
                        // New record - insert
                        $record['is_new'] = true;
                        $record['processed_at'] = Carbon::now();
                        $record['created_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        $record['data_source'] = 1;
                        $newRecords[] = $record;
                    }
                } catch (\Exception $e) {
                    $failedRecords[] = $record;
                    $clientkvmOne->index([
                        'index' => 'error_logs',
                        'body' => [
                            'server_name' => 'KVM4.3',
                            'error_type' => 'Internal Server Error',
                            'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                            'error' => "Skipping record due to error: " . json_encode($e->getMessage()),
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }

            // ✅ Bulk Insert New Records
            if (!empty($newRecords)) {
                DB::table('vehicle_records')->insert($newRecords);
            }

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                foreach ($updatedRecords as $item) {
                    // DB::table('vehicle_records')->where('id', $item['id'])->update($item);
                    $getVehicleRecord = DB::table('vehicle_records')->where('id', $item['id'])->first();

                    if(!is_null($getVehicleRecord)){
                        $checkRecordSaleDate = Carbon::parse($getVehicleRecord->sale_date);
                        $currentSaleDate = $item['sale_date'];


                        // Condition 1: Sale Date Matched AND data_source = 2 update the record | update or create auction history
                        // Condition 2: Sale Date Not Macthed And Status != 3 And data_source = 2 update or create auction history
                        // Condition 3: Sale Date Not Macthed And Status == 3(sale) Active and Update

                        if($checkRecordSaleDate == $currentSaleDate && $getVehicleRecord->data_source == 2){
                            Log::info('Condition 1', ['record' => json_encode($item)]);

                            // DB::table('vehicle_records')->where('id', $getVehicleRecord->id)->update($item);
                        }elseif($checkRecordSaleDate != $currentSaleDate && $getVehicleRecord->status_id != 3 && $getVehicleRecord->data_source == 2){
                            Log::info('Condition 2', ['record' => json_encode($item)]);
                            // DB::table('vehicle_records')->where('id', $getVehicleRecord->id)->update($item);
                        }elseif($checkRecordSaleDate != $currentSaleDate && $getVehicleRecord->status_id == 3){
                            $item['data_source'] = 1;
                            Log::info('Condition 3', ['record' => json_encode($item)]);
                            // DB::table('vehicle_records')->where('id', $getVehicleRecord->id)->update($item);
                        }


                        if(($checkRecordSaleDate == $currentSaleDate && $getVehicleRecord->data_source == 2)
                        || ($checkRecordSaleDate != $currentSaleDate && $getVehicleRecord->status_id != 3 && $getVehicleRecord->data_source == 2)){
                                $saleAuctionRecord = DB::table('sale_auction_histories')
                                ->where([
                                    'vin' => $item['vin'],
                                    'lot_id' => $item['lot_id'],
                                    'sale_date' => $item['sale_date'],
                                ])->first();
                                $saleData = [
                                    'vin' => strtolower($item['vin']),
                                    'domain_id' => $item['domain_id'],
                                    'sale_date' => $item['sale_date'],
                                    'lot_id' => $item['lot_id'],
                                    'bid' => $item['bid'],
                                    'odometer_mi' => $item['odometer_mi'],
                                    'status_id' => $item['status_id'],
                                    'seller_id' => $item['seller_id'],
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                                if($saleAuctionRecord){
                                    unset($saleData['created_at']);
                                    // DB::connection('mysql')
                                    //     ->table('sale_auction_histories')
                                    //     ->where('id', $saleAuctionRecord->id)
                                    //     ->update($saleData);
                                    Log::info('Sale Auctio History Record Updadted Active', ['record' => json_encode($saleAuctionRecord)]);
                                }else{
                                    // DB::table('sale_auction_histories')->insert($saleData);
                                    Log::info('Sale Auctio History Record Created Active', ['record' => json_encode($saleData)]);
                                }
                        }


                    }

                }
                // DB::table('vehicle_records')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
            }
        } catch (\Throwable $e) {
            $clientkvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                    'error' => "Batch insert failed: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            return;
        }

        try {
            $client = app('ElasticsearchKvmOne');

            $response = $client->exists([
                'index' => 'vehicle_process_cached_api_data',
                'id' => $cacheKey,
            ]);

            if ($response) {
                try {
                    $client->update([
                        'index' => 'vehicle_process_cached_api_data',
                        'id' => $cacheKey,
                        'body' => [
                            'doc' => [
                                'status' => 'completed'
                            ]
                        ]
                    ]);
                    Log::info("✅ Elasticsearch Processed document status updated for _id: $cacheKey");
                } catch (\Throwable $e) {
                    // $clientkvmOne->index([
                    //     'index' => 'error_logs',
                    //     'body' => [
                    //         'server_name' => 'KVM4.3',
                    //         'error_type' => 'Internal Server Error',
                    //         'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                    //         'error' => "⚠️ Failed to update document: " . json_encode($e->getMessage()),
                    //         'created_at' => now()->toIso8601String(),
                    //         'updated_at' => now()->toIso8601String(),
                    //     ],
                    // ]);
                }
            } else {
                $clientkvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'General',
                        'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                        'error' => "⚠️ Document not found for update with _id: $cacheKey",
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            $clientkvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                    'error' => "❌ Elasticsearch exists check failed: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
