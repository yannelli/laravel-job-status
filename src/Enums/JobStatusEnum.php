<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus\Enums;

enum JobStatusEnum: string
{
    case QUEUED = 'queued';
    case EXECUTING = 'executing';
    case FINISHED = 'finished';
    case FAILED = 'failed';
    case RETRYING = 'retrying';

    public function hasEnded(): bool
    {
        return in_array($this, [self::FAILED, self::FINISHED], true);
    }

    public function isFinished(): bool
    {
        return $this === self::FINISHED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isExecuting(): bool
    {
        return $this === self::EXECUTING;
    }

    public function isQueued(): bool
    {
        return $this === self::QUEUED;
    }

    public function isRetrying(): bool
    {
        return $this === self::RETRYING;
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
