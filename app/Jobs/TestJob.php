<?php

namespace App\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cacheKeyId;
    protected $cacheKey;
    /**
     * Create a new job instance.
     */
    public function __construct($cacheKeyId, $cacheKey)
    {
        $this->queue = 'test_job';
        $this->cacheKeyId = $cacheKeyId;
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Hello World ' . $this->cacheKeyId . ' ' . $this->cacheKey);
    }
}
