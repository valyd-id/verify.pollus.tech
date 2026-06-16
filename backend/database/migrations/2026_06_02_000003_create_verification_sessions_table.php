<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained('verification_projects')->cascadeOnDelete();
            $table->uuid('workflow_id')->nullable(); // null for ad-hoc standalone audit rows
            // NOT_STARTED | IN_PROGRESS | IN_REVIEW | APPROVED | DECLINED | ABANDONED | EXPIRED
            $table->string('status')->default('NOT_STARTED')->index();
            // Mode: 'hosted' (has a hosted UI) or 'standalone' (synchronous API audit row).
            $table->string('mode')->default('hosted');
            $table->string('vendor_data')->nullable()->index(); // client's own user/reference id
            $table->string('callback_url')->nullable();         // per-session webhook override
            $table->string('session_token', 80)->nullable()->unique(); // scopes the hosted page
            $table->json('features');           // snapshot of workflow features at create time
            $table->json('settings')->nullable(); // snapshot of workflow settings at create time
            $table->json('metadata')->nullable();
            $table->json('decision')->nullable(); // aggregated summary written on terminal status
            $table->timestamp('expires_at')->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_sessions');
    }
};
