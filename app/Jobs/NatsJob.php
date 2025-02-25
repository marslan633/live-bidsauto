<?php

namespace App\Jobs;

use Goodway\LaravelNats\NatsMessageJob;
use Illuminate\Support\Str;

class NatsJob extends NatsMessageJob
{
    public $queue = 'process-api-queue';

    // Property to store dynamic content.
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param mixed $data
     */
    public function __construct($data = null)
    {
        $this->data = $data;
    }

    /**
     * The body method returns the data that will be serialized
     * and sent as the message.
     *
     * @return string
     */
    public function body(): string
    {
        return json_encode([
            'data'         => $this->data ?? Str::random(32),
            'group_random' => random_int(1, 3),
        ]);
    }
}
