# Laravel Job Status

[![Latest Stable Version](https://poser.pugx.org/imTigger/laravel-job-status/v/stable)](https://packagist.org/packages/imTigger/laravel-job-status)
[![Total Downloads](https://poser.pugx.org/imTigger/laravel-job-status/downloads)](https://packagist.org/packages/imTigger/laravel-job-status)
[![Build Status](https://travis-ci.org/imTigger/laravel-job-status.svg?branch=master)](https://travis-ci.org/imTigger/laravel-job-status)
[![License](https://poser.pugx.org/imTigger/laravel-job-status/license)](https://packagist.org/packages/imTigger/laravel-job-status)


Laravel package to add ability to track `Job` progress, status and result dispatched to `Queue`.

## Features

- Queue name, attempts, status and created/updated/started/finished timestamp
- Progress update, with arbitrary current/max value and percentage auto calculated
- Handles failed job with exception message
- Custom input/output with optional disable tracking
- Optional status message string for custom status descriptions
- Unique job ID support (integrates with Laravel's `uniqueId()` method)
- **Full batch support** - automatically tracks Laravel job batches with total jobs and current step
- **Chain support** - manually track job chains with relationships between jobs
- Complete history log of job status changes
- Native Eloquent model `JobStatus`
- Support all drivers included in Laravel (null/sync/database/beanstalkd/redis/sqs)

This package intentionally does not provide any UI for displaying job progress.

If you have such need, please refer to [laravel-job-status-progress-view](https://github.com/imTigger/laravel-job-status-progress-view)

or make your own implementation using `JobStatus` model

## Requirements

- PHP ^8.3
- Laravel ^12.0

## Tracked Queue Events

The package automatically tracks all Laravel queue events to provide comprehensive job monitoring:

| Event | When it fires | What we track |
|-------|--------------|---------------|
| `JobQueueing` | Before job is pushed to queue | Initial tracking setup |
| `JobQueued` | After job is queued | Job queued confirmation |
| `JobProcessing` | Job starts executing | Status → EXECUTING, started_at timestamp |
| `JobProcessed` | Job completes successfully | Status → FINISHED, finished_at timestamp |
| `JobFailed` | Job fails permanently | Status → FAILED, finished_at timestamp |
| `JobExceptionOccurred` | Exception occurs during job | Status → FAILED or RETRYING |
| `JobRetryRequested` | Retry is requested | Status → RETRYING |
| `JobReleasedAfterException` | Job released back after exception | Status → RETRYING |
| `JobTimedOut` | Job execution times out | Status → FAILED, finished_at timestamp |

All events are handled automatically - you don't need to configure anything!

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
### Advanced Features

#### Status Messages

Set custom status messages to provide more context about your job's current state:

```php
public function handle(): void
{
    $this->setStatusMessage('Starting data import...');

    // Import data
    $this->setProgressMax(100);

    for ($i = 0; $i < 100; $i++) {
        $this->setStatusMessage("Processing batch {$i} of 100");
        $this->setProgressNow($i);
        // Process batch
    }

    $this->setStatusMessage('Import completed successfully');
}
```

#### Unique Job IDs

If your job implements Laravel's `uniqueId()` method, the unique ID will automatically be captured and stored. This allows you to track multiple executions of the same logical operation:

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessUserReport implements ShouldQueue, ShouldBeUnique
{
    use Trackable;

    public function uniqueId(): string
    {
        return 'process-user-' . $this->userId;
    }

    // Rest of your job...
}
```

**Important**: The `unique_id` is **NOT** unique in the database - it allows multiple executions to be tracked historically. This means you can:
- Retry failed jobs for the same entity
- Reprocess data after corrections
- Run scheduled recurring jobs for the same entity
- Track the complete history of all executions

**Finding Jobs by Unique ID:**

```php
// Get the most recent execution
$latestJob = JobStatus::findLatestByUniqueId('process-user-123');

// Get all historical executions (ordered by most recent first)
$allExecutions = JobStatus::findAllByUniqueId('process-user-123');

// Check if currently running
if (JobStatus::isRunning('process-user-123')) {
    echo "Job is currently processing";
}

// Old way still works (gets first match, order not guaranteed)
$jobStatus = JobStatus::where('unique_id', 'process-user-123')->first();
```

**Example: Viewing Execution History**

```php
$executions = JobStatus::findAllByUniqueId('import-user-456');

foreach ($executions as $execution) {
    echo "{$execution->created_at}: {$execution->status->value}";
    if ($execution->is_failed) {
        echo " - Failed: {$execution->output['exception'] ?? 'Unknown error'}";
    }
}

// Output:
// 2025-11-06 10:30:00: finished
// 2025-11-05 14:20:00: failed - Failed: Database connection timeout
// 2025-11-05 09:15:00: finished
```

#### Job Status History

Every status change is automatically logged to a history table, allowing you to track the complete lifecycle of your job:

```php
$jobStatus = JobStatus::find($jobStatusId);

// Get all history records (most recent first)
$history = $jobStatus->histories;

foreach ($history as $record) {
    echo $record->status->value; // Status at that time
    echo $record->status_message; // Message at that time
    echo $record->created_at; // When this change occurred
}
```

History tracking can be disabled in the config file:

```php
'track_history' => false,
```

#### Disabling Input/Output Tracking

If you want to disable tracking of input/output data (for privacy or performance reasons), you can configure it in `config/job-status.php`:

```php
return [
    // Disable input tracking - setInput() calls will be ignored
    'track_input' => false,

    // Disable output tracking - setOutput() calls will be ignored
    'track_output' => false,

    // Other config options...
];
```

#### Job Batches

Laravel's native batch feature is **automatically supported**! When you dispatch jobs as part of a batch, the package will automatically capture:
- Batch ID
- Total number of jobs in the batch
- Current step/position in the batch

```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new ProcessPodcast(1),
    new ProcessPodcast(2),
    new ProcessPodcast(3),
])->then(function (Batch $batch) {
    // All jobs completed successfully
})->catch(function (Batch $batch, Throwable $e) {
    // First batch job failure detected
})->dispatch();

// Later, query all jobs in the batch
$batchJobs = JobStatus::where('batch_id', $batch->id)->get();

foreach ($batchJobs as $job) {
    echo "Step {$job->current_step} of {$job->total_jobs}: {$job->status->value}";
}

// Or use the relationship
$firstJob = JobStatus::where('batch_id', $batch->id)->first();
$allJobsInBatch = $firstJob->batchJobs;
```

Your job doesn't need any special configuration - the batch information is captured automatically:

```php
use Illuminate\Bus\Batchable;

class ProcessPodcast implements ShouldQueue
{
    use Batchable, Trackable;

    public function __construct(private readonly int $podcastId)
    {
        $this->prepareStatus(); // Automatically captures batch info
    }

    public function handle(): void
    {
        // Your job logic
        $this->setProgressMax(100);
        // ...
    }
}
```

#### Job Chains

For job chains, you need to manually set a chain identifier since Laravel doesn't provide built-in chain IDs:

```php
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

$chainId = Str::uuid()->toString();

Bus::chain([
    new ProcessPodcast($chainId, 1, 3),
    new OptimizePodcast($chainId, 2, 3),
    new ReleasePodcast($chainId, 3, 3),
])->catch(function (Throwable $e) {
    // Handle chain failure
})->dispatch();
```

In your jobs, pass and set the chain information:

```php
class ProcessPodcast implements ShouldQueue
{
    use Trackable;

    public function __construct(
        private readonly string $chainId,
        private readonly int $step,
        private readonly int $totalSteps
    ) {
        $this->prepareStatus([
            'chain_id' => $this->chainId,
            'current_step' => $this->step,
            'total_jobs' => $this->totalSteps,
        ]);
    }

    public function handle(): void
    {
        $this->setStatusMessage("Processing step {$this->step} of {$this->totalSteps}");
        // Your job logic
    }
}
```

Query all jobs in a chain:

```php
$chainJobs = JobStatus::where('chain_id', $chainId)
    ->orderBy('current_step')
    ->get();

foreach ($chainJobs as $job) {
    echo "Step {$job->current_step}: {$job->type} - {$job->status->value}\n";
}

// Or use the relationship
$firstJob = JobStatus::where('chain_id', $chainId)->first();
$allJobsInChain = $firstJob->chainJobs;
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
// Automatically captures uniqueId() if the method exists on your job

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
// Respects 'track_input' config setting

$this->setOutput(array $value): void
// Store output/result data into database
// Respects 'track_output' config setting

$this->setStatusMessage(string $message): void
// Set a custom status message describing the current job state
// Example: "Processing batch 3 of 10" or "Waiting for external API"

$this->setChain(string $chainId, int $currentStep, int $totalJobs): void
// Manually set chain tracking information
// Use this if you didn't pass chain info to prepareStatus()
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
$jobStatus->unique_id             // ?string: Logical identifier (NOT unique in DB - allows reprocessing)
$jobStatus->batch_id              // ?string: Batch ID if part of a batch
$jobStatus->chain_id              // ?string: Chain ID if part of a chain
$jobStatus->type                  // string: Job class name
$jobStatus->queue                 // ?string: Queue name
$jobStatus->status                // JobStatusEnum: Current status enum
$jobStatus->status_message        // ?string: Custom status message
$jobStatus->attempts              // int: Number of attempts
$jobStatus->progress_now          // int: Current progress value
$jobStatus->progress_max          // int: Maximum progress value
$jobStatus->total_jobs            // ?int: Total jobs in batch/chain
$jobStatus->current_step          // ?int: Current step in batch/chain (1-based)
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
$jobStatus->is_batch              // bool: True if part of a batch
$jobStatus->is_chain              // bool: True if part of a chain

// Relationships
$jobStatus->histories()           // HasMany: Job status history records
$jobStatus->batchJobs()           // HasMany: All jobs in the same batch
$jobStatus->chainJobs()           // HasMany: All jobs in the same chain
```

### JobStatusEnum Values

```php
use Yannelli\TrackJobStatus\Enums\JobStatusEnum;

JobStatusEnum::QUEUED      // 'queued'
JobStatusEnum::EXECUTING   // 'executing'
JobStatusEnum::FINISHED    // 'finished'
JobStatusEnum::FAILED      // 'failed'
JobStatusEnum::RETRYING    // 'retrying'
```

### JobStatusHistory Model Properties

```php
$history->id                      // int: Primary key
$history->job_status_id           // int: Foreign key to job_statuses table
$history->status                  // JobStatusEnum: Status at time of logging
$history->status_message          // ?string: Status message at time of logging
$history->progress_now            // int: Progress value at time of logging
$history->progress_max            // int: Max progress at time of logging
$history->metadata                // ?array: Additional metadata about the change
$history->created_at              // \Illuminate\Support\Carbon: When this record was created

// Relationships
$history->jobStatus()             // BelongsTo: Parent JobStatus record
```

### Configuration Options

```php
// config/job-status.php

return [
    'model' => \Yannelli\TrackJobStatus\JobStatus::class,
    'event_manager' => \Yannelli\TrackJobStatus\EventManagers\DefaultEventManager::class,
    'database_connection' => null,  // Dedicated DB connection (null = default)
    'track_input' => true,          // Enable/disable input tracking
    'track_output' => true,         // Enable/disable output tracking
    'track_history' => true,        // Enable/disable history logging
];
```

### Custom Event Handling

You can create a custom event manager to customize how queue events are handled:

```php
namespace App\JobStatus;

use Yannelli\TrackJobStatus\EventManagers\EventManager;
use Illuminate\Queue\Events\JobProcessing;
use Yannelli\TrackJobStatus\Enums\JobStatusEnum;

class CustomEventManager extends EventManager
{
    public function before(JobProcessing $event): void
    {
        // Custom logic before job starts
        $this->getUpdater()->update($event, [
            'status' => JobStatusEnum::EXECUTING->value,
            'job_id' => $event->job->getJobId(),
            'queue' => $event->job->getQueue(),
            'started_at' => now(),
            'status_message' => 'Custom: Job is now running',
        ]);
    }

    // Implement other abstract methods...
}
```

Then register it in your config:

```php
// config/job-status.php
return [
    'event_manager' => App\JobStatus\CustomEventManager::class,
    // ...
];
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
