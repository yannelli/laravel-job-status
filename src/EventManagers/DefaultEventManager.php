<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus\EventManagers;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Yannelli\TrackJobStatus\Enums\JobStatusEnum;

class DefaultEventManager extends EventManager
{
    public function before(JobProcessing $event): void
    {
        $this->getUpdater()->update($event, [
            'status' => JobStatusEnum::EXECUTING->value,
            'job_id' => $event->job->getJobId(),
            'queue' => $event->job->getQueue(),
            'started_at' => now(),
        ]);
    }

    public function after(JobProcessed $event): void
    {
        if (!$event->job->hasFailed()) {
            $this->getUpdater()->update($event, [
                'status' => JobStatusEnum::FINISHED->value,
                'finished_at' => now(),
            ]);
        }
    }

    public function failing(JobFailed $event): void
    {
        $status = $event->job->attempts() >= $event->job->maxTries()
            ? JobStatusEnum::FAILED->value
            : JobStatusEnum::RETRYING->value;

        $this->getUpdater()->update($event, [
            'status' => $status,
            'finished_at' => now(),
        ]);
    }

    public function exceptionOccurred(JobExceptionOccurred $event): void
    {
        $status = $event->job->attempts() >= $event->job->maxTries()
            ? JobStatusEnum::FAILED->value
            : JobStatusEnum::RETRYING->value;

        $this->getUpdater()->update($event, [
            'status' => $status,
            'finished_at' => now(),
        ]);
    }
}
