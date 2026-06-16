<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Valyd SSO may not return an email (e.g. anonymous/phone-only accounts).
    // We still let those developers in and ask for an email later (optional),
    // so the column must allow NULLs. The unique index stays — Postgres treats
    // NULLs as distinct, so multiple email-less users are fine.
    public function up(): void
    {
        Schema::table('console_users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('console_users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
