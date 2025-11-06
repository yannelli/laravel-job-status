<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Imtigger\LaravelJobStatus\Enums\JobStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('job_id')->index()->nullable();
            $table->string('type')->index();
            $table->string('queue')->index()->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('progress_now')->default(0);
            $table->unsignedInteger('progress_max')->default(0);
            $table->string('status', 16)->default(JobStatusEnum::QUEUED->value)->index();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_statuses');
    }
};
