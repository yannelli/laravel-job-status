<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

trait Trackable
{
    protected int|string|null $statusId = null;

    protected int $progressNow = 0;

    protected int $progressMax = 0;

    protected bool $shouldTrack = true;

    protected function setProgressMax(int $value): void
    {
        $this->update(['progress_max' => $value]);
        $this->progressMax = $value;
    }

    protected function setProgressNow(int $value, int $every = 1): void
    {
        if ($value % $every === 0 || $value === $this->progressMax) {
            $this->update(['progress_now' => $value]);
        }
        $this->progressNow = $value;
    }

    protected function incrementProgress(int $offset = 1, int $every = 1): void
    {
        $value = $this->progressNow + $offset;
        $this->setProgressNow($value, $every);
    }

    protected function setInput(array $value): void
    {
        $this->update(['input' => $value]);
    }

    protected function setOutput(array $value): void
    {
        $this->update(['output' => $value]);
    }

    protected function update(array $data): void
    {
        $updater = app(JobStatusUpdater::class);
        $updater->update($this, $data);
    }

    protected function prepareStatus(array $data = []): void
    {
        if (!$this->shouldTrack) {
            return;
        }

        /** @var class-string<JobStatus> $entityClass */
        $entityClass = app(config('job-status.model'));

        $data = array_merge(['type' => $this->getDisplayName()], $data);

        /** @var JobStatus $status */
        $status = $entityClass::query()->create($data);

        $this->statusId = $status->getKey();
    }

    protected function getDisplayName(): string
    {
        return method_exists($this, 'displayName') ? $this->displayName() : static::class;
    }

    public function getJobStatusId(): int|string|null
    {
        return $this->statusId;
    }
}
