<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassType extends Model
{
    use HasFactory;

    protected $fillable= [
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /** Tipos que se ofrecen al crear una clase nueva. */
    public function scopeActive(Builder $query) : Builder
    {
        return $query->where('status', true);
    }

    public function sessions() : HasMany
    {
        return $this->hasMany(Session::class);
    }
}
