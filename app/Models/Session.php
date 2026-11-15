<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use HasFactory;

    protected $table = 'sessions';

    protected $fillable = [
        'trainer_id',
        'room_id',
        'class_type_id',
        'name',
        'description',
        'capacity',
        'date',
        'start_datetime',
        'duration_minutes',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'start_datetime' => 'datetime',
    ];

    const STATUS_SCHEDULED   = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    const STATUS_LABELS = [
        self::STATUS_SCHEDULED   => 'Programada',
        self::STATUS_IN_PROGRESS => 'En progreso',
        self::STATUS_COMPLETED   => 'Completada',
        self::STATUS_CANCELLED   => 'Cancelada',
    ];

    /**
     * Momento exacto en que arranca la clase (fecha + hora de inicio).
     */
    public function getStartsAtAttribute() : Carbon
    {
        $date = $this->date instanceof Carbon ? $this->date : Carbon::parse($this->date);

        return $date->copy()->setTimeFrom($this->start_datetime);
    }

    public function getEndsAtAttribute() : Carbon
    {
        return $this->starts_at->addMinutes((int) $this->duration_minutes);
    }

    /**
     * El estado que corresponde a una clase según su franja horaria real.
     * "En progreso" significa que se está dictando ahora mismo, no que sea hoy:
     * una clase de hoy a las 21:30 sigue siendo reservable a las 20:00.
     */
    public static function statusFor(Carbon $startsAt, int $durationMinutes) : string
    {
        $now = Carbon::now();

        if ($now->lt($startsAt)) {
            return self::STATUS_SCHEDULED;
        }

        return $now->lt($startsAt->copy()->addMinutes($durationMinutes))
            ? self::STATUS_IN_PROGRESS
            : self::STATUS_COMPLETED;
    }

    /**
     * Estado que debe tener esta clase. Una cancelación la decide el
     * administrador, así que no se recalcula.
     */
    public function resolveStatus() : string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        return self::statusFor($this->starts_at, (int) $this->duration_minutes);
    }

    /**
     * Clases que todavía se pueden reservar: no canceladas y que aún no empezaron.
     * Se filtra por la franja real y no por la columna `status`, que puede estar
     * desactualizada si nadie ha abierto el panel de administración.
     */
    public function scopeBookable(Builder $query) : Builder
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED)
            ->whereRaw('TIMESTAMP(`date`, `start_datetime`) > ?', [Carbon::now()->format('Y-m-d H:i:s')]);
    }

    /**
     * Pone al día las clases cuyo estado quedó viejo al pasar el tiempo.
     * Nunca toca las canceladas.
     *
     * Se pasa la hora desde PHP a propósito: la conexión MySQL va en UTC
     * mientras que la aplicación trabaja en America/Lima.
     */
    public static function refreshStatuses() : void
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $startsAt = 'TIMESTAMP(`date`, `start_datetime`)';
        $endsAt = "DATE_ADD({$startsAt}, INTERVAL `duration_minutes` MINUTE)";

        static::where('status', '!=', self::STATUS_CANCELLED)
            ->where('status', '!=', self::STATUS_COMPLETED)
            ->whereRaw("{$endsAt} <= ?", [$now])
            ->update(['status' => self::STATUS_COMPLETED]);

        static::where('status', '!=', self::STATUS_CANCELLED)
            ->where('status', '!=', self::STATUS_IN_PROGRESS)
            ->whereRaw("{$startsAt} <= ?", [$now])
            ->whereRaw("{$endsAt} > ?", [$now])
            ->update(['status' => self::STATUS_IN_PROGRESS]);

        static::where('status', '!=', self::STATUS_CANCELLED)
            ->where('status', '!=', self::STATUS_SCHEDULED)
            ->whereRaw("{$startsAt} > ?", [$now])
            ->update(['status' => self::STATUS_SCHEDULED]);
    }

    public function getStatusLabelAttribute() : string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function trainer() : BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }
    public function clients() : BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_sessions', 'session_id', 'client_id')
            ->using(ClientSession::class)
            ->withPivot('id', 'status', 'attendance', 'reserved_at', 'cancelled_at')
            ->withTimestamps();
    }

    public function reservations() : HasMany
    {
        return $this->hasMany(ClientSession::class);
    }

    /** Clientes con reserva vigente (no cancelada ni ausente). */
    public function activeClients() : BelongsToMany
    {
        return $this->clients()->wherePivotIn('status', ClientSession::ACTIVE_STATUSES);
    }

    public function room() : BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function classType() : BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    // Accessor para obtener la hora de finalización
    public function getEndTimeAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_datetime);
        return $start->addMinutes($this->duration_minutes)->format('H:i');
    }

    // Accessor para obtener el día de la semana traducido
    public function getDayNameAttribute()
    {
        return $this->date->translatedFormat('l');
    }

}
