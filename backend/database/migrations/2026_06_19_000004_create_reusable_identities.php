<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Verify once, reuse" — verify.pollus.tech is the system of record for a verified
 * identity, keyed by the Valyd pollus_id, isolated PER APP. Login-with-Valyd only
 * supplies the pollus_id; everything verified is captured by our own KYC and stored
 * here so a returning user can re-verify with a selfie alone.
 *
 * Storage note: text columns (not json) so the array/PII fields can switch to
 * Laravel `encrypted` casts later without a schema change. Plaintext for testing now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reusable_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('verification_projects')->cascadeOnDelete();
            $table->string('pollus_id')->index();
            $table->string('vendor_data')->nullable()->index();

            $table->text('full_name')->nullable();        // → encrypted cast later
            $table->text('dob')->nullable();              // → encrypted cast later
            $table->longText('age_bands')->nullable();    // JSON (array cast)
            $table->longText('face_embedding')->nullable(); // 2056-int FaceOnLive feature (array cast)
            $table->longText('licenses')->nullable();     // JSON (array cast)

            $table->uuid('source_session_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();  // reuse window (unused — until-revoked)
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // Per-app isolation: one reusable record per (app, Valyd identity).
            $table->unique(['project_id', 'pollus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reusable_identities');
    }
};
