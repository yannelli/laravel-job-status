<?php

declare(strict_types=1);

namespace Yannelli\TrackJobStatus\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yannelli\TrackJobStatus\LaravelJobStatusServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(realpath(__DIR__.'/../../database/migrations'));
        $this->loadMigrationsFrom(realpath(__DIR__.'/../database/migrations'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelJobStatusServiceProvider::class,
        ];
    }
}
