<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Yannelli\TrackJobStatus\Enums\JobStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('job_id')->index()->nullable();
            $table->string('unique_id')->index()->nullable();
            $table->string('batch_id')->nullable();
            $table->string('chain_id')->nullable();
            $table->string('type')->index();
            $table->string('queue')->index()->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('progress_now')->default(0);
            $table->unsignedInteger('progress_max')->default(0);
            $table->unsignedInteger('total_jobs')->nullable();
            $table->unsignedInteger('current_step')->nullable();
            $table->string('status', 16)->default(JobStatusEnum::QUEUED->value)->index();
            $table->text('status_message')->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['batch_id', 'current_step'], 'idx_batch_step');
            $table->index(['chain_id', 'current_step'], 'idx_chain_step');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index(['unique_id', 'created_at'], 'idx_unique_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_statuses');
    }
};
