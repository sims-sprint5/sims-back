<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id('ticket_message_id');

            $table->foreignId('ticket_id')
                ->constrained('tickets', 'ticket_id')
                ->onDelete('cascade');

            $table->foreignId('sender_id')
                ->constrained('users', 'user_id')
                ->onDelete('cascade');

            $table->text('message');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
