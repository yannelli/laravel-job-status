<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Yannelli\TrackJobStatus\Enums\JobStatusEnum;

/**
 * @property int $id
 * @property string|null $job_id
 * @property string $type
 * @property string|null $queue
 * @property int $attempts
 * @property int $progress_now
 * @property int $progress_max
 * @property JobStatusEnum $status
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
 */
class JobStatus extends Model
{
    protected string $table = 'job_statuses';

    protected array $fillable = [
        'job_id',
        'type',
        'queue',
        'attempts',
        'progress_now',
        'progress_max',
        'status',
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

    public static function getAllowedStatuses(): array
    {
        return JobStatusEnum::values();
    }
}
