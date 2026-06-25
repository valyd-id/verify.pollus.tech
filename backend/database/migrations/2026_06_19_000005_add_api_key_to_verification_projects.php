<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the raw API key so the console can show the full key anytime (not just once
 * at creation). Stored encrypted at rest (Laravel `encrypted` cast); the sha256
 * `api_key_hash` is still what auth looks up. Keys created before this migration
 * have no stored raw key until the next rotation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_projects', function (Blueprint $table) {
            $table->text('api_key')->nullable()->after('api_key_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('verification_projects', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
