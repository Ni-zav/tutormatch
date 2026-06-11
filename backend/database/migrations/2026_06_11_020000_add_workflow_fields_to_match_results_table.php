<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_results', function (Blueprint $table): void {
            $table->string('status')->default('recommended')->index();
            $table->string('outreach_status')->default('not_contacted')->index();
            $table->text('coordinator_notes')->nullable();
            $table->timestamp('status_updated_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('match_results', function (Blueprint $table): void {
            $table->dropColumn(['status', 'outreach_status', 'coordinator_notes', 'status_updated_at']);
        });
    }
};
