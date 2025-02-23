<?php

return [
    'url'     => env('NATS_URL', 'nats://kvm4.2-ip:4222'),
    'cluster' => env('NATS_CLUSTER', 'vehicle_cluster'),
    'stream'  => [
        'name'     => env('NATS_STREAM', 'vehicle_stream'),
        'subjects' => ['vehicle.data'],
        'storage'  => env('NATS_STORAGE', 'file'),
        'retention' => 'limits',
        'limits'    => [
            'max_msgs'   => env('NATS_MAX_MESSAGES', 1000000),
            'max_age'    => env('NATS_MAX_AGE', '48h'),
            'max_memory' => env('NATS_MAX_MEMORY', '2GB'),
            'max_file'   => env('NATS_MAX_FILE', '5GB'),
        ],
    ],
    'consumer' => [
        'name'            => env('NATS_CONSUMER', 'vehicle_worker'),
        'queue'           => 'vehicle-queue',
        'deliver_policy'  => 'all',
        'ack_policy'      => 'explicit',
        'max_ack_pending' => 500,
    ],
];
