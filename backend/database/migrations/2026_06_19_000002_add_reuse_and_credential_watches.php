<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Verify once, reuse" + license re-check engine.
 *
 * - verification_sessions gets a `pollus_id` (the Valyd identity the session is
 *   linked to after Login-with-Valyd) + a `reused` flag.
 * - credential_watches tracks credential checks that must be re-verified on a
 *   cadence (scheduled) or near expiry, so a lapsed license is caught.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_sessions', function (Blueprint $table) {
            $table->string('pollus_id')->nullable()->index()->after('vendor_data');
            $table->boolean('reused')->default(false)->after('pollus_id');
        });

        Schema::create('credential_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('verification_projects')->cascadeOnDelete();
            $table->string('pollus_id')->nullable()->index();
            $table->uuid('session_id')->nullable()->index();

            // Inputs needed to re-run CredentialRunner against the registry.
            $table->json('credential');          // { provider_code, license_state, license_number, npi, full_name, license_type }
            $table->string('license_type')->nullable();
            $table->string('license_state')->nullable();
            $table->string('license_number')->nullable();

            $table->string('policy');             // scheduled | expiry  (per_action is never persisted)
            $table->string('interval')->nullable(); // daily | weekly (scheduled only)
            $table->string('last_status')->nullable(); // passed | failed | review
            $table->timestamp('expire_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_recheck_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_watches');
        Schema::table('verification_sessions', function (Blueprint $table) {
            $table->dropColumn(['pollus_id', 'reused']);
        });
    }
};
