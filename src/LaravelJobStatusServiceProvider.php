<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Yannelli\TrackJobStatus\EventManagers\EventManager;

class LaravelJobStatusServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->mergeConfigFrom(__DIR__.'/../config/job-status.php', 'job-status');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../config/' => config_path(),
        ], 'config');

        $this->bootListeners();
    }

    private function bootListeners(): void
    {
        /** @var EventManager $eventManager */
        $eventManager = app(config('job-status.event_manager'));

        $queueManager = app(QueueManager::class);

        $queueManager->before(function (JobProcessing $event) use ($eventManager): void {
            $eventManager->before($event);
        });

        $queueManager->after(function (JobProcessed $event) use ($eventManager): void {
            $eventManager->after($event);
        });

        $queueManager->failing(function (JobFailed $event) use ($eventManager): void {
            $eventManager->failing($event);
        });

        $queueManager->exceptionOccurred(function (JobExceptionOccurred $event) use ($eventManager): void {
            $eventManager->exceptionOccurred($event);
        });
    }
}
