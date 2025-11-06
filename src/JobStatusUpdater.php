<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Yannelli\TrackJobStatus\Enums\JobStatusEnum;

class JobStatusUpdater
{
    public function update(mixed $job, array $data): void
    {
        if ($this->isEvent($job)) {
            $this->updateEvent($job, $data);
        }

        $this->updateJob($job, $data);
    }

    protected function updateEvent(
        JobProcessing|JobProcessed|JobFailed|JobExceptionOccurred $event,
        array $data
    ): void {
        $job = $this->parseJob($event);
        $jobStatus = $this->getJobStatus($job);

        if (!$jobStatus) {
            return;
        }

        try {
            $data['attempts'] = $event->job->attempts();
        } catch (\Throwable $e) {
            try {
                $data['attempts'] = $job?->attempts();
            } catch (\Throwable $e) {
                Log::error($e->getMessage());
            }
        }

        // Prevent overwriting failed status with finished status
        if ($jobStatus->is_failed
            && isset($data['status'])
            && $data['status'] === JobStatusEnum::FINISHED->value
        ) {
            unset($data['status']);
        }

        $jobStatus->update($data);
    }

    protected function updateJob(mixed $job, array $data): void
    {
        if ($jobStatus = $this->getJobStatus($job)) {
            $jobStatus->update($data);
        }
    }

    protected function parseJob(
        JobProcessing|JobProcessed|JobFailed|JobExceptionOccurred $event
    ): mixed {
        try {
            $payload = $event->job->payload();

            return unserialize($payload['data']['command']);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());

            return null;
        }
    }

    protected function getJobStatusId(mixed $job): int|string|null
    {
        try {
            if ($job instanceof TrackableJob || method_exists($job, 'getJobStatusId')) {
                return $job->getJobStatusId();
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage());

            return null;
        }

        return null;
    }

    protected function getJobStatus(mixed $job): ?JobStatus
    {
        if ($id = $this->getJobStatusId($job)) {
            /** @var class-string<JobStatus> $entityClass */
            $entityClass = app(config('job-status.model'));

            return $entityClass::on(config('job-status.database_connection'))->whereKey($id)->first();
        }

        return null;
    }

    protected function isEvent(mixed $job): bool
    {
        return $job instanceof JobProcessing
            || $job instanceof JobProcessed
            || $job instanceof JobFailed
            || $job instanceof JobExceptionOccurred;
    }
}
