<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('tutors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tutor_type')->index();
            $table->string('teaching_mode')->index();
            $table->string('location')->index();
            $table->unsignedInteger('hourly_rate_min')->index();
            $table->unsignedInteger('hourly_rate_max')->index();
            $table->unsignedTinyInteger('years_experience')->default(0);
            $table->decimal('rating', 2, 1)->nullable();
            $table->decimal('acceptance_rate', 4, 2)->default(0);
            $table->decimal('success_score', 4, 2)->default(0);
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('proficiency')->default(3);
            $table->timestamps();
            $table->unique(['tutor_id', 'subject_id', 'level_id']);
            $table->index(['subject_id', 'level_id', 'tutor_id']);
        });

        Schema::create('tutor_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week');
            $table->string('time_block');
            $table->timestamps();
            $table->unique(['tutor_id', 'day_of_week', 'time_block']);
            $table->index(['day_of_week', 'time_block']);
        });

        Schema::create('student_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('student_name');
            $table->string('parent_name')->nullable();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('level_id')->constrained();
            $table->string('location')->index();
            $table->string('teaching_mode')->index();
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->index();
            $table->string('preferred_tutor_type')->nullable()->index();
            $table->string('requested_day_of_week')->nullable();
            $table->string('requested_time_block')->nullable();
            $table->string('urgency')->default('normal')->index();
            $table->string('status')->default('new')->index();
            $table->string('schedule_notes');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['subject_id', 'level_id']);
            $table->index('created_at');
        });

        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_request_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('open')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('applied')->index();
            $table->text('message')->nullable();
            $table->timestamp('applied_at')->useCurrent()->index();
            $table->timestamps();
            $table->unique(['assignment_id', 'tutor_id']);
        });

        Schema::create('match_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('total_score');
            $table->json('score_breakdown');
            $table->text('deterministic_explanation');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            $table->unique(['student_request_id', 'tutor_id']);
            $table->index(['student_request_id', 'total_score']);
        });

        Schema::create('message_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('match_result_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience')->index();
            $table->string('channel')->default('whatsapp')->index();
            $table->text('body');
            $table->string('generated_by')->default('mock_ai');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_drafts');
        Schema::dropIfExists('match_results');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('student_requests');
        Schema::dropIfExists('tutor_availabilities');
        Schema::dropIfExists('tutor_subjects');
        Schema::dropIfExists('tutors');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('subjects');
    }
};
