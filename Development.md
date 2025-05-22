$clientKvmOne->index([
                            'index' => 'error_logs',
                            'body' => [
                                'server_name' => 'KVM4.4',
                                'error_type' => 'Internal Server Error',
                                'command_name' => 'process:delete-expired-data',
                                'error' => 'Bulk delete errors: ' . json_encode($bulkResponse),
                                'created_at' => now()->toIso8601String(),
                                'updated_at' => now()->toIso8601String(),
                            ],
                        ]);
