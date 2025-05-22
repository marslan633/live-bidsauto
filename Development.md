$client->index([
                            'index' => 'error_logs',
                            'body' => [
                                'server_name' => 'KVM4.3',
                                'error_type' => 'Internal Server Error',
                                'command_name' => 'process:expired-auction-archive-with-elasticsearch',
                                'error' => 'Bulk delete errors: ' . json_encode($bulkResponse),
                                'created_at' => now()->toIso8601String(),
                                'updated_at' => now()->toIso8601String(),
                            ],
                        ]);
