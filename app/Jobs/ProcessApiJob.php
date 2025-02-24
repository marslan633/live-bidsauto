<?php

namespace App\Jobs;

use Goodway\LaravelNats\NatsMessageJob;
use Illuminate\Support\Str;

class ProcessApiJob extends NatsMessageJob
{
    public $queue = 'process-api-queue';
    // Optionally, set a custom subject for your message.

    // You can use a property to store dynamic content.
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param mixed $data
     */
    public function __construct($data = null)
    {
        // Store any dynamic data passed to the job.
        $this->data = $data;
    }

    /**
     * The body method returns the data that will be serialized
     * and sent as the message.
     *
     * @return mixed
     */
    public function body(): mixed
    {
        return [
            'data'         => $this->data ?? Str::random(32),
            'group_random' => random_int(1, 3),
        ];
    }
}
