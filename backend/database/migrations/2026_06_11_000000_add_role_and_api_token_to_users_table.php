<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('coordinator')->index();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->timestamp('api_token_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'api_token_hash', 'api_token_issued_at']);
        });
    }
};
