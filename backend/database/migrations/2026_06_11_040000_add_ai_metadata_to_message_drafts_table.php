<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_drafts', function (Blueprint $table): void {
            $table->string('prompt_version')->default('message-draft-v1')->index();
            $table->boolean('fallback_used')->default(false)->index();
            $table->json('generation_metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('message_drafts', function (Blueprint $table): void {
            $table->dropColumn(['prompt_version', 'fallback_used', 'generation_metadata']);
        });
    }
};
