<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProcessApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'process-api-queue';

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
     * Handle the job.
     */
    public function handle()
    {
        // Process the job here
        \Log::info("Processing API Job with data: ", [
            'data'         => $this->data ?? Str::random(32),
            'group_random' => random_int(1, 3),
        ]);
    }
}
