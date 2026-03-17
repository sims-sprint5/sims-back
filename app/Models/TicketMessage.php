<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $table = 'ticket_messages';

    protected $primaryKey = 'ticket_message_id';

    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'sender_id',
        'message',
        'is_admin',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_admin' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    /** Alias used by the controller for eager-loading */
    public function user()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }
}
