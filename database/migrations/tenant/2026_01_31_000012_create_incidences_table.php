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
        Schema::create('incidences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reported_by');
            $table->enum('type', ['Technical', 'Maintenance', 'UserComplaint', 'Accident', 'other']);
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->enum('status', ['reported', 'investigating', 'resolved', 'closed'])->default('reported');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('incident_number')->unique();
            $table->timestamps();
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidences');
    }
};
