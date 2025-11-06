<?php

declare(strict_types=1);

namespace Imtigger\LaravelJobStatus\Tests\Data;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Imtigger\LaravelJobStatus\Trackable;
use Imtigger\LaravelJobStatus\TrackableJob;

class TestJobWithException implements ShouldQueue, TrackableJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use Trackable;

    public function __construct()
    {
        $this->prepareStatus();
    }

    public function handle(): void
    {
        throw new Exception('test-exception');
    }
}
