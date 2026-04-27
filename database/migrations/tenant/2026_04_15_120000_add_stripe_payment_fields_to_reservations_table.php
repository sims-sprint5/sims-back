<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('status');
            }

            if (! Schema::hasColumn('reservations', 'stripe_session_id')) {
                $table->string('stripe_session_id', 255)->nullable()->unique()->after('price');
            }

            if (! Schema::hasColumn('reservations', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('stripe_session_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('reservations', 'stripe_session_id')) {
                $table->dropUnique('reservations_stripe_session_id_unique');
                $table->dropColumn('stripe_session_id');
            }

            if (Schema::hasColumn('reservations', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};
