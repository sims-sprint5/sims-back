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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('license_plate', 20)->unique();
            $table->string('vin', 17)->nullable();
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->enum('type', ['scooter', 'bike', 'car', 'van']);
            $table->enum('status', ['available', 'in_use', 'maintenance', 'retired'])->default('available');
            $table->integer('year')->nullable();
            $table->string('color', 50)->nullable();
            $table->integer('battery_level')->nullable()->check('battery_level >= 0 AND battery_level <= 100');
            $table->integer('range_km')->nullable();
            $table->decimal('price_per_minute', 6, 2)->nullable();
            $table->decimal('price_per_hour', 8, 2)->nullable();
            $table->geography('location', 'POINT', 4326)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
