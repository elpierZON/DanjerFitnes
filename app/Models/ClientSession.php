<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Reserva de un cliente en una sesión (tabla pivote client_sessions).
 */
class ClientSession extends Pivot
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'client_sessions';

    public $incrementing = true;

    const STATUS_RESERVED  = 'reserved';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    /** Estados que ocupan un cupo de la sesión. */
    const ACTIVE_STATUSES = [self::STATUS_RESERVED, self::STATUS_CONFIRMED];

    const STATUS_LABELS = [
        self::STATUS_RESERVED  => 'Reservada',
        self::STATUS_CONFIRMED => 'Confirmada',
        self::STATUS_CANCELLED => 'Cancelada',
        self::STATUS_COMPLETED => 'Completada',
    ];

    protected $fillable = [
        'client_id',
        'session_id',
        'status',
        'attendance',
        'reserved_at',
        'cancelled_at',
    ];

    protected $casts = [
        'attendance'   => 'boolean',
        'reserved_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client() : BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function session() : BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function scopeActive(Builder $query) : Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeForSession(Builder $query, $sessionId) : Builder
    {
        return $query->where('session_id', $sessionId);
    }

    public function isActive() : bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function getStatusLabelAttribute() : string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
