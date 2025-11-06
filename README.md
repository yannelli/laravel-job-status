# Laravel Job Status

[![Latest Stable Version](https://poser.pugx.org/imTigger/laravel-job-status/v/stable)](https://packagist.org/packages/imTigger/laravel-job-status)
[![Total Downloads](https://poser.pugx.org/imTigger/laravel-job-status/downloads)](https://packagist.org/packages/imTigger/laravel-job-status)
[![Build Status](https://travis-ci.org/imTigger/laravel-job-status.svg?branch=master)](https://travis-ci.org/imTigger/laravel-job-status)
[![License](https://poser.pugx.org/imTigger/laravel-job-status/license)](https://packagist.org/packages/imTigger/laravel-job-status)


Laravel package to add ability to track `Job` progress, status and result dispatched to `Queue`.

- Queue name, attempts, status and created/updated/started/finished timestamp.
- Progress update, with arbitrary current/max value and percentage auto calculated
- Handles failed job with exception message
- Custom input/output
- Native Eloquent model `JobStatus`
- Support all drivers included in Laravel (null/sync/database/beanstalkd/redis/sqs)

- This package intentionally do not provide any UI for displaying Job progress.

  If you have such need, please refer to [laravel-job-status-progress-view](https://github.com/imTigger/laravel-job-status-progress-view)  
  
  or make your own implementation using `JobStatus` model

## Requirements

- PHP ^8.3
- Laravel ^12.0

## Installation

[Installation for Laravel](INSTALL.md)

### Usage

In your `Job`, use `Trackable` trait and call `$this->prepareStatus()` in constructor.

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Imtigger\LaravelJobStatus\Trackable;

class TrackableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    public function __construct(private readonly array $params)
    {
        $this->prepareStatus();
        $this->setInput($this->params); // Optional
    }

    public function handle(): void
    {
        $max = mt_rand(5, 30);
        $this->setProgressMax($max);

        for ($i = 0; $i <= $max; $i++) {
            sleep(1); // Some Long Operations
            $this->setProgressNow($i);
        }

        $this->setOutput(['total' => $max, 'other' => 'parameter']);
    }
}
```

In your Job dispatcher, call `$job->getJobStatusId()` to get `$jobStatusId`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\TrackableJob;
use Illuminate\Http\JsonResponse;

class YourController extends Controller
{
    public function dispatchJob(): JsonResponse
    {
        $job = new TrackableJob([]);
        dispatch($job);

        $jobStatusId = $job->getJobStatusId();

        return response()->json(['job_status_id' => $jobStatusId]);
    }
}
```

`$jobStatusId` can be used elsewhere to retrieve job status, progress and output.

```php
<?php

declare(strict_types=1);

use Imtigger\LaravelJobStatus\JobStatus;

$jobStatus = JobStatus::find($jobStatusId);
```
### Troubleshooting

#### Call to undefined method ...->getJobStatusId()

Laravel provide many ways to dispatch Jobs. Not all methods return your Job object, for example:

```php
<?php
YourJob::dispatch(); // Returns PendingDispatch instead of YourJob object, leaving no way to retrive `$job->getJobStatusId();`
```

If you really need to dispatch job in this way, workarounds needed: Create your own key

1. Create migration adding extra key to job_statuses table.

2. In your job, generate your own unique key and pass into `prepareStatus();`, `$this->prepareStatus(['key' => $params['key']]);`

3. Find JobStatus another way: `$jobStatus = JobStatus::whereKey($key)->firstOrFail();`

#### Status not updating until transaction commited

On version >= 1.1, dedicated database connection support is added.

Therefore JobStatus updates can be saved instantly even within your application transaction.

Read setup step 6 for instructions.


## API Reference

### Trackable Trait Methods (Protected)

Call these methods from within your Job class:

```php
$this->prepareStatus(array $data = []): void
// Must be called in constructor before any other methods
// Optional $data array can include custom fields

$this->setProgressMax(int $value): void
// Set the maximum progress value

$this->setProgressNow(int $value, int $every = 1): void
// Update current progress
// $every: write to database only when $value % $every === 0

$this->incrementProgress(int $offset = 1, int $every = 1): void
// Increase current progress by $offset
// $every: write to database only when value % $every === 0

$this->setInput(array $value): void
// Store input data into database

$this->setOutput(array $value): void
// Store output/result data into database
```

### Public Methods

```php
$job->getJobStatusId(): int|string|null
// Returns the JobStatus primary key (use to retrieve status later)
```

### JobStatus Model Properties

```php
$jobStatus->id                    // int: Primary key
$jobStatus->job_id                // ?string: Queue job ID (varies by driver, see note below)
$jobStatus->type                  // string: Job class name
$jobStatus->queue                 // ?string: Queue name
$jobStatus->status                // JobStatusEnum: Current status enum
$jobStatus->attempts              // int: Number of attempts
$jobStatus->progress_now          // int: Current progress value
$jobStatus->progress_max          // int: Maximum progress value
$jobStatus->input                 // ?array: Input data
$jobStatus->output                // ?array: Output data (includes exception message if failed)
$jobStatus->created_at            // ?\Illuminate\Support\Carbon: Creation timestamp
$jobStatus->updated_at            // ?\Illuminate\Support\Carbon: Last update timestamp
$jobStatus->started_at            // ?\Illuminate\Support\Carbon: Start timestamp
$jobStatus->finished_at           // ?\Illuminate\Support\Carbon: Completion timestamp

// Computed Properties
$jobStatus->progress_percentage   // float: Progress percentage [0-100]
$jobStatus->is_ended              // bool: True if finished or failed
$jobStatus->is_executing          // bool: True if currently executing
$jobStatus->is_failed             // bool: True if failed
$jobStatus->is_finished           // bool: True if finished successfully
$jobStatus->is_queued             // bool: True if queued
$jobStatus->is_retrying           // bool: True if retrying after failure
```

### JobStatusEnum Values

```php
use Imtigger\LaravelJobStatus\Enums\JobStatusEnum;

JobStatusEnum::QUEUED      // 'queued'
JobStatusEnum::EXECUTING   // 'executing'
JobStatusEnum::FINISHED    // 'finished'
JobStatusEnum::FAILED      // 'failed'
JobStatusEnum::RETRYING    // 'retrying'
```

# Note 

`$jobStatus->job_id` result varys with driver

| Driver     | job_id
| ---------- | --------
| null       | NULL (Job not run at all!)
| sync       | empty string
| database   | integer
| beanstalkd | integer 
| redis      | string(32)
| sqs        | GUID 
