$minutes = 25;

if (!empty($hits)) {
    $lastCron = $hits[0]['_source'];
    if (!empty($lastCron['end_time'])) {
        $endTime = Carbon::parse($lastCron['end_time']);
        $timeDifference = max(0, $endTime->diffInMinutes(now()));
        Log::info('Time Difference Active '. $timeDifference);
        if ($timeDifference > 25) {
            $minutes = $timeDifference + 10;
        } elseif ($timeDifference === 25) {
            $minutes = $timeDifference + 5;
        }
    }
}

// Get the current hour
$currentHour = now()->hour;

// Check if the current hour is one of the specific hours (0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22)
if (in_array($currentHour, range(0, 24, 2))) {
    Log::info('Current time is an even hour: ' . $currentHour);
    // Print something for even hours like 0, 2, 4, 6, etc.
} else {
    $currentMinute = now()->minute;
    // Check if it's 0:30, 1:00, 1:30, etc.
    if (($currentMinute == 30) || ($currentHour == 0 && $currentMinute == 0)) {
        Log::info('Current time is 0:30, 1:00, 1:30, etc.');
        // Print something else for these times
    }
}


->whereRaw(
                "DATE_FORMAT(DATE_ADD(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), INTERVAL 28 HOUR), '%Y-%m-%d %H:%i') <= ?",
                [now()->format('Y-m-d H:i')]
            )

zhwuf5zfxmla16208
mvin388616ind

Process Cahed Data To Database Job With Elasticsearch code


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
