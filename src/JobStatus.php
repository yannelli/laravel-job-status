<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Yannelli\TrackJobStatus\Enums\JobStatusEnum;

/**
 * @property int $id
 * @property string|null $job_id
 * @property string|null $unique_id
 * @property string|null $batch_id
 * @property string|null $chain_id
 * @property string $type
 * @property string|null $queue
 * @property int $attempts
 * @property int $progress_now
 * @property int $progress_max
 * @property int|null $total_jobs
 * @property int|null $current_step
 * @property JobStatusEnum $status
 * @property string|null $status_message
 * @property array|null $input
 * @property array|null $output
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property float $progress_percentage
 * @property bool $is_ended
 * @property bool $is_executing
 * @property bool $is_failed
 * @property bool $is_finished
 * @property bool $is_queued
 * @property bool $is_retrying
 * @property bool $is_batch
 * @property bool $is_chain
 */
class JobStatus extends Model
{
    protected string $table = 'job_statuses';

    protected array $fillable = [
        'job_id',
        'unique_id',
        'batch_id',
        'chain_id',
        'type',
        'queue',
        'attempts',
        'progress_now',
        'progress_max',
        'total_jobs',
        'current_step',
        'status',
        'status_message',
        'input',
        'output',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'status' => JobStatusEnum::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'attempts' => 'integer',
            'progress_now' => 'integer',
            'progress_max' => 'integer',
            'total_jobs' => 'integer',
            'current_step' => 'integer',
        ];
    }

    protected function progressPercentage(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->progress_max !== 0
                ? round(100 * $this->progress_now / $this->progress_max, 2)
                : 0,
        );
    }

    protected function hasEnded(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->hasEnded(),
        );
    }

    protected function isFinished(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->isFinished(),
        );
    }

    protected function isFailed(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->isFailed(),
        );
    }

    protected function isExecuting(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->isExecuting(),
        );
    }

    protected function isQueued(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->isQueued(),
        );
    }

    protected function isRetrying(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->isRetrying(),
        );
    }

    protected function isBatch(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->batch_id !== null,
        );
    }

    protected function isChain(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->chain_id !== null,
        );
    }

    public static function getAllowedStatuses(): array
    {
        return JobStatusEnum::values();
    }

    /**
     * Find the most recent job status by unique ID.
     *
     * @param string $uniqueId The unique identifier to search for
     * @return static|null The most recent job status, or null if not found
     */
    public static function findLatestByUniqueId(string $uniqueId): ?static
    {
        return static::on(config('job-status.database_connection'))
            ->where('unique_id', $uniqueId)
            ->latest('created_at')
            ->first();
    }

    /**
     * Find all job statuses for a unique ID (all historical executions).
     *
     * @param string $uniqueId The unique identifier to search for
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function findAllByUniqueId(string $uniqueId): \Illuminate\Database\Eloquent\Collection
    {
        return static::on(config('job-status.database_connection'))
            ->where('unique_id', $uniqueId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if a job with this unique ID is currently running.
     *
     * @param string $uniqueId The unique identifier to check
     * @return bool True if a job is currently executing
     */
    public static function isRunning(string $uniqueId): bool
    {
        return static::on(config('job-status.database_connection'))
            ->where('unique_id', $uniqueId)
            ->where('status', JobStatusEnum::EXECUTING)
            ->exists();
    }

    /**
     * Get the history records for this job status.
     *
     * @return HasMany<JobStatusHistory>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(JobStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all jobs in the same batch as this job.
     *
     * @return HasMany<JobStatus>
     */
    public function batchJobs(): HasMany
    {
        return $this->hasMany(static::class, 'batch_id', 'batch_id')
            ->orderBy('current_step');
    }

    /**
     * Get all jobs in the same chain as this job.
     *
     * @return HasMany<JobStatus>
     */
    public function chainJobs(): HasMany
    {
        return $this->hasMany(static::class, 'chain_id', 'chain_id')
            ->orderBy('current_step');
    }

    /**
     * Log the current status to the history table.
     * Respects the 'track_history' configuration setting.
     *
     * @param array<string, mixed> $metadata Additional metadata to store with the history record
     * @return void
     */
    public function logHistory(array $metadata = []): void
    {
        if (!config('job-status.track_history', true)) {
            return;
        }

        // Use the configured database connection for history records
        JobStatusHistory::on(config('job-status.database_connection'))
            ->create([
                'job_status_id' => $this->getKey(),
                'status' => $this->status->value,
                'status_message' => $this->status_message,
                'progress_now' => $this->progress_now,
                'progress_max' => $this->progress_max,
                'metadata' => $metadata,
            ]);
    }

    /**
     * Boot the model and register event listeners.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updated(function (JobStatus $jobStatus): void {
            // Only log history if status or message actually changed
            // This prevents infinite loops and unnecessary history records
            if ($jobStatus->wasChanged('status') || $jobStatus->wasChanged('status_message')) {
                // Defer history logging to after the transaction commits
                // to avoid race conditions and ensure data consistency
                $jobStatus->logHistory([
                    'changed_fields' => array_keys($jobStatus->getChanges()),
                ]);
            }
        });
    }
}
