<?php

namespace App\Modules\Incidences\Models;

use App\Modules\Incidences\Factories\IncidenceFactory;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Incidence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reported_by',
        'incident_number',
        'type',
        'severity',
        'description',
        'status',
        'resolution_notes',
        'metadata',
        'occurred_at',
        'resolved_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function newFactory()
    {
        return IncidenceFactory::new();
    }

    protected static function booted()
    {
        static::creating(function ($incidence) {
            if (empty($incidence->incident_number)) {
                $incidence->incident_number = 'INC-'.strtoupper(Str::random(10));
            }
        });

        // Global scope: Users without view.all permission only see their own incidences
        static::addGlobalScope('user_incidences', function ($builder) {
            $user = auth()->user();
            if ($user && ! $user->hasPermissionTo('incidences.view.all')) {
                $builder->where('reported_by', $user->id);
            }
        });
    }

    /* --- Relationships --- */

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
