<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account prepaid balance + an append-only transaction ledger. Each API call
 * deducts the feature cost from the owning console user's balance; our-side
 * failures refund it. Top-ups credit it (Stripe later — for now a direct credit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('console_users', function (Blueprint $table) {
            $table->decimal('balance', 19, 4)->default(0)->after('profile');
        });

        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('console_users')->cascadeOnDelete();
            $table->string('type');                 // credit | debit | refund
            $table->decimal('amount', 19, 4);        // always positive; sign implied by type
            $table->decimal('balance_after', 19, 4); // running balance snapshot (for history)
            $table->string('reason');                // top_up | check:<feature> | refund:<feature>
            $table->string('reference')->nullable(); // session id / external ref
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
        Schema::table('console_users', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
