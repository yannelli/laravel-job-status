<?php

declare(strict_types=1);

namespace Imtigger\LaravelJobStatus\Tests\Data;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\Constraints\HasInDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Imtigger\LaravelJobStatus\Tests\Feature\TestCase;
use Imtigger\LaravelJobStatus\Trackable;
use Imtigger\LaravelJobStatus\TrackableJob;

class TestJobWithDatabase implements ShouldQueue, TrackableJob
{
    use Dispatchable;
    use InteractsWithDatabase;
    use InteractsWithQueue;
    use Queueable;
    use Trackable;

    public function __construct(protected array $data)
    {
        $this->prepareStatus();
    }

    public function handle(): void
    {
        TestCase::assertThat(
            'job_statuses',
            new HasInDatabase($this->getConnection(), [
                'id' => $this->getJobStatusId(),
            ] + $this->data)
        );
    }

    protected function getConnection(): Connection
    {
        $database = app('db');

        return $database->connection($database->getDefaultConnection());
    }
}
