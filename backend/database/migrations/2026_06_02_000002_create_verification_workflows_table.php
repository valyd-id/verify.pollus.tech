<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained('verification_projects')->cascadeOnDelete();
            $table->string('name');
            // Ordered list of feature keys to run, e.g. ["id_verification","liveness","face_match"].
            $table->json('features');
            // Per-workflow tuning: required vs optional checks, thresholds, auto-approve.
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['project_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_workflows');
    }
};
