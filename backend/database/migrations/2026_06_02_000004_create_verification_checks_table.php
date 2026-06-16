<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            // id_verification | liveness | face_match | age | credential
            $table->string('type')->index();
            // pending | running | passed | failed | review
            $table->string('status')->default('pending');
            $table->float('score')->nullable();   // similarity / liveness / confidence
            $table->json('data')->nullable();      // raw engine result (sanitised)
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('verification_sessions')->cascadeOnDelete();
            $table->unique(['session_id', 'type']); // one row per feature per session
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_checks');
    }
};
