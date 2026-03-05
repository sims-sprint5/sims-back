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
        Schema::create('geofences', function (Blueprint $table) {
            $table->id('geofence_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('type', 50)->comment('allowed, restricted, parking, service_area');
            $table->decimal('center_latitude', 10, 8);
            $table->decimal('center_longitude', 11, 8);
            $table->integer('radius')->comment('radius in meters');
            $table->json('polygon_coordinates')->nullable()->comment('for complex polygonal areas');
            $table->string('status', 20)->default('active')->comment('active, inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
