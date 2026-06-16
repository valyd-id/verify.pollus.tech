<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Stored as a SHA-256 hash of the raw key; the raw key is shown once at creation.
            $table->string('api_key_hash', 64)->unique();
            $table->string('api_key_prefix', 16)->index(); // first chars, for display ("vk_live_ab…")
            $table->string('webhook_url')->nullable();
            $table->string('webhook_signing_secret')->nullable();
            $table->json('allowed_features')->nullable(); // null = all features allowed
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_projects');
    }
};
