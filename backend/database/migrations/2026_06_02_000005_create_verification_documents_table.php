<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id')->nullable();
            // id_front | id_back | selfie
            $table->string('type');
            $table->string('storage_path');
            $table->string('mime')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('verification_sessions')->cascadeOnDelete();
            $table->index(['session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_documents');
    }
};
