<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id('ticket_id');

            $table->foreignId('user_id')
                  ->constrained('users', 'user_id')
                  ->onDelete('cascade');

            $table->foreignId('vehicle_id')
                  ->nullable()
                  ->constrained('vehicles', 'vehicle_id')
                  ->onDelete('set null');

            $table->foreignId('reservation_id')
                  ->nullable()
                  ->constrained('reservations', 'reservation_id')
                  ->onDelete('set null');

            $table->string('type', 50)->comment('technical, billing, complaint, inquiry');
            $table->string('subject', 255);
            $table->text('description');
            $table->string('priority', 20)->comment('low, medium, high, urgent');
            $table->string('status', 20)->default('open')->comment('open, in_progress, resolved, closed');
            
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('users', 'user_id')
                  ->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
