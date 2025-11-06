<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus\EventManagers;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Yannelli\TrackJobStatus\JobStatus;
use Yannelli\TrackJobStatus\JobStatusUpdater;

abstract class EventManager
{
    abstract public function before(JobProcessing $event): void;

    abstract public function after(JobProcessed $event): void;

    abstract public function failing(JobFailed $event): void;

    abstract public function exceptionOccurred(JobExceptionOccurred $event): void;

    /** @var class-string<JobStatus> */
    private string $entity;

    public function __construct(private readonly JobStatusUpdater $updater)
    {
        $this->entity = app(config('job-status.model'));
    }

    protected function getUpdater(): JobStatusUpdater
    {
        return $this->updater;
    }

    /**
     * @return class-string<JobStatus>
     */
    protected function getEntity(): string
    {
        return $this->entity;
    }
}
