<?php

namespace Yannelli\TrackJobStatus\Tests\Feature;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Yannelli\TrackJobStatus\JobStatus;
use Yannelli\TrackJobStatus\LaravelJobStatusBusServiceProvider;
use Yannelli\TrackJobStatus\Tests\Data\TestJob;
use Yannelli\TrackJobStatus\Tests\Data\TestJobWithDatabase;
use Yannelli\TrackJobStatus\Tests\Data\TestJobWithException;
use Yannelli\TrackJobStatus\Tests\Data\TestJobWithFail;
use Yannelli\TrackJobStatus\Tests\Data\TestJobWithoutTracking;

class TrackableTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            LaravelJobStatusBusServiceProvider::class,
        ]);
    }

    public function testFinished()
    {
        /** @var TestJobWithDatabase $job */
        $job = new TestJobWithDatabase([
            'status' => 'executing',
        ]);

        $this->assertDatabaseHas('job_statuses', [
            'id' => $job->getJobStatusId(),
            'status' => 'queued',
        ]);

        app(Dispatcher::class)->dispatch($job);

        Artisan::call('queue:work', [
            '--once' => 1,
        ]);

        $this->assertDatabaseHas('job_statuses', [
            'id' => $job->getJobStatusId(),
            'status' => 'finished',
        ]);
    }

    public function testStatusFailedWithException()
    {
        $this->expectException(\Exception::class);

        /** @var TestJob $job */
        $job = new TestJobWithException();

        app(Dispatcher::class)->dispatch($job);

        Artisan::call('queue:work', [
            '--once' => 1,
        ]);

        $this->assertDatabaseHas('job_statuses', [
            'id' => $job->getJobStatusId(),
            'status' => 'failed',
        ]);
    }

    public function testStatusFailedWithFail()
    {
        /** @var TestJob $job */
        $job = new TestJobWithFail();

        app(Dispatcher::class)->dispatch($job);

        Artisan::call('queue:work', [
            '--once' => 1,
        ]);

        $this->assertDatabaseHas('job_statuses', [
            'id' => $job->getJobStatusId(),
            'status' => 'failed',
        ]);
    }

    public function testTrackingDisabled()
    {
        $job = new TestJobWithoutTracking();

        $this->assertNull($job->getJobStatusId());

        $this->assertEquals(0, JobStatus::query()->count());

        app(Dispatcher::class)->dispatch($job);

        $this->assertEquals(0, JobStatus::query()->count());
    }
}
