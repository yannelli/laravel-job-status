<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

interface TrackableJob
{
    public function getJobStatusId(): int|string|null;
}
