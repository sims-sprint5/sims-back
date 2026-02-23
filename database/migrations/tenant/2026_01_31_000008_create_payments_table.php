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
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->enum('gateway', ['stripe', 'paypal', 'square'])->nullable();
            $table->string('gateway_txn_id', 255)->nullable();
            $table->string('gateway_secondary_id', 255)->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 50)->default('pending');
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
