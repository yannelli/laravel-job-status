<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus;

use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Bus\QueueingDispatcher as QueueingDispatcherContract;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Support\ServiceProvider;

class LaravelJobStatusBusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Dispatcher::class, function ($app) {
            return new Dispatcher(
                $app,
                fn (?string $connection = null) => $app[QueueFactoryContract::class]->connection($connection),
                app(JobStatusUpdater::class)
            );
        });

        $this->app->alias(Dispatcher::class, DispatcherContract::class);
        $this->app->alias(Dispatcher::class, QueueingDispatcherContract::class);
    }
}
