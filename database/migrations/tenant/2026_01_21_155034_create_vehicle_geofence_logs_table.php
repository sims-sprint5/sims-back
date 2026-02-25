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
        Schema::create('vehicle_geofence_logs', function (Blueprint $table) {
            $table->id('log_id');
            
            $table->foreignId('vehicle_id')
                  ->constrained('vehicles', 'vehicle_id')
                  ->onDelete('cascade');

            $table->foreignId('geofence_id')
                  ->constrained('geofences', 'geofence_id')
                  ->onDelete('cascade');

            $table->string('event_type', 50)->comment('entry, exit, violation');
            $table->timestamp('event_timestamp');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_geofence_logs');
    }
};
