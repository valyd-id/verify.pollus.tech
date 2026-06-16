<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('console_users', function (Blueprint $table) {
            $table->id();
            $table->string('valyd_user_id')->nullable()->unique(); // pollus_id / sub from Valyd
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::table('verification_projects', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained('console_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('verification_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::dropIfExists('console_users');
    }
};
