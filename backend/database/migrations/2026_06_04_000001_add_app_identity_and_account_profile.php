<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_projects', function (Blueprint $table) {
            // Public-facing App ID, a UUID (e.g. daf8f6f2-6bd2-497a-9762-1b860b72e67e).
            $table->uuid('app_id')->nullable()->after('name');
            $table->text('description')->nullable()->after('app_id');
            // App logo stored inline as a base64 data URL (kept small, served via JSON).
            $table->longText('logo')->nullable()->after('description');
            // Exactly one app per user is the default; the default cannot be deleted.
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        // Backfill app_id for existing rows and mark the earliest app per user default.
        foreach (DB::table('verification_projects')->orderBy('id')->get() as $row) {
            DB::table('verification_projects')->where('id', $row->id)->update([
                'app_id' => (string) Str::uuid(),
            ]);
        }

        $seenUsers = [];
        foreach (DB::table('verification_projects')->orderBy('id')->get() as $row) {
            $key = $row->user_id ?? 'null';
            if (!isset($seenUsers[$key])) {
                $seenUsers[$key] = true;
                DB::table('verification_projects')->where('id', $row->id)->update(['is_default' => true]);
            }
        }

        Schema::table('verification_projects', function (Blueprint $table) {
            $table->unique('app_id');
        });

        Schema::table('console_users', function (Blueprint $table) {
            // Organization / account profile (org name, address, billing contact, prefs…).
            $table->json('profile')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('verification_projects', function (Blueprint $table) {
            $table->dropUnique(['app_id']);
            $table->dropColumn(['app_id', 'description', 'logo', 'is_default']);
        });
        Schema::table('console_users', function (Blueprint $table) {
            $table->dropColumn('profile');
        });
    }
};
