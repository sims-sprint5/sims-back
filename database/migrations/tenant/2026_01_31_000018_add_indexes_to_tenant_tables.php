<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->index('license_plate');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('vehicle_id');
        });

        Schema::table('maintenance', function (Blueprint $table) {
            $table->index('vehicle_id');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->index('key');
        });

        Schema::table('vehicle_locations', function (Blueprint $table) {
            $table->index(['recorded_at'], 'idx_vehicle_locations_recorded_at');
        });

        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['license_plate']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['vehicle_id']);
        });

        Schema::table('maintenance', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id']);
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex(['key']);
        });

        Schema::table('vehicle_locations', function (Blueprint $table) {
            $table->dropIndex('idx_vehicle_locations_recorded_at');
        });

        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
