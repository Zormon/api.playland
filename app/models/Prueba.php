<?php

namespace App\Models;

class Prueba extends Model {
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['tipo', 'nombre', 'info', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function scopeRace($query) {
        return $query->where('tipo', 'race');
    }
}
