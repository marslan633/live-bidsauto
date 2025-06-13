// $existingSaleRecords = DB::table('sale_auction_histories')
            //     ->whereIn('vin', $vins)
            //     ->orderBy('created_at', 'desc')
            //     ->pluck('id', 'lot_id');
            // Log::info('Vehicle Sale Records', ['existingSaleRecords' => json_encode($existingSaleRecords)]); 
 
  if (isset($existingSaleRecords[$record['lot_id']]) && isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        // Check If Record Exists or not
                        $checkExistingSaleAuctionHistoryRecord = DB::connection('mysql')->table('sale_auction_histories')->where([
                            'vin' => $record['vin'],
                            'lot_id' => $record['lot_id'],
                            'sale_date' => $existingRecords[$record['lot_id']]->sale_date
                        ])->first();

                        if (!is_null($checkExistingSaleAuctionHistoryRecord)) {
                            $saleRecord = $record;
                            // vin, bid, lot_id, status_id, final_bid_updated_at
                            $saleRecord['id'] = $existingRecords[$record['lot_id']]->id;
                            $saleRecord['sale_date'] = $existingRecords[$record['lot_id']]->sale_date;
                            $saleRecord['odometer_mi'] = $existingRecords[$record['lot_id']]->odometer_mi;
                            $saleRecord['seller_id'] = $existingRecords[$record['lot_id']]->seller_id;
                            $saleRecord['updated_at'] = now();
                            $updatedSaleRecords[] = $saleRecord;
                        } else {
                            $newSaleRecord = $record;
                            // vin, bid, lot_id, status_id, final_bid_updated_at
                            $newSaleRecord['sale_date'] = $existingRecords[$record['lot_id']]->sale_date;
                            $newSaleRecord['odometer_mi'] = $existingRecords[$record['lot_id']]->odometer_mi;
                            $newSaleRecord['domain_id'] = $existingRecords[$record['lot_id']]->domain_id;
                            $newSaleRecord['seller_id'] = $existingRecords[$record['lot_id']]->seller_id;
                            $newSaleRecord['created_at'] = now();
                            $newSaleRecord['updated_at'] = now();
                            unset($newSaleRecord['id']); // ✅ Prevent duplicate primary key
                            $newSaleRecords[] = $newSaleRecord;
                        }
                    } elseif (!isset($existingSaleRecords[$record['lot_id']]) && isset($existingRecords[$record['lot_id']])) {
                        $newSaleRecord = $record;
                        // vin, bid, lot_id, status_id, final_bid_updated_at
                        $newSaleRecord['sale_date'] = $existingRecords[$record['lot_id']]->sale_date;
                        $newSaleRecord['odometer_mi'] = $existingRecords[$record['lot_id']]->odometer_mi;
                        $newSaleRecord['domain_id'] = $existingRecords[$record['lot_id']]->domain_id;
                        $newSaleRecord['seller_id'] = $existingRecords[$record['lot_id']]->seller_id;
                        $newSaleRecord['created_at'] = now();
                        $newSaleRecord['updated_at'] = now();
                        unset($newSaleRecord['id']); // ✅ Prevent duplicate primary key
                        $newSaleRecords[] = $newSaleRecord;
                    }

    if (!empty($updatedSaleRecords)) {
                foreach ($updatedSaleRecords as $item_two) {
                    DB::table('sale_auction_histories')->where('id', $item_two['id'])->update($item_two);
                    Log::info('Sale Record Updated ' . $item_two['id'], ['data' => json_encode($item_two)]);
                }
            }

            if (!empty($newSaleRecords)) {
                Log::info('New Sale Record', ['newSaleRecords' => json_encode($newSaleRecords)]);
                DB::table('sale_auction_histories')->insert($newSaleRecords);
            }
