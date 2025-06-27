Change data_source = 1 to comment on 
if (isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['lot_id']]; // Add ID for update
                        $record['processed_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        $record['data_source'] = 1;
                        $updatedRecords[] = $record;
                    } 

this point if conditions added
