<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiple webhook destinations per project. Each endpoint has its own signing
 * secret and optional event filter; verification events fan out to every active
 * endpoint. (The legacy single project.webhook_url still works for back-compat.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('verification_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('signing_secret', 64)->nullable();
            $table->json('events')->nullable(); // null = all events
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
