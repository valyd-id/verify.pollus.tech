<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_sessions', function (Blueprint $table) {
            $table->string('redirect_url')->nullable()->after('callback_url');
        });
    }

    public function down(): void
    {
        Schema::table('verification_sessions', function (Blueprint $table) {
            $table->dropColumn('redirect_url');
        });
    }
};
