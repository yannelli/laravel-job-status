<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

use Closure;
use Illuminate\Contracts\Container\Container;

class Dispatcher extends \Illuminate\Bus\Dispatcher
{
    public function __construct(
        Container $container,
        Closure $queueResolver,
        private readonly JobStatusUpdater $updater
    ) {
        parent::__construct($container, $queueResolver);
    }

    public function dispatch(mixed $command): mixed
    {
        $result = parent::dispatch($command);

        $this->updater->update($command, [
            'job_id' => $result,
        ]);

        return $result;
    }
}
