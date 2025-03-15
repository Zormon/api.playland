<?php

namespace App\Models;

class Participacion extends Model {
    protected $table = 'participaciones';
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = ['evento_id', 'equipo_id', 'prueba_id', 'resultado', 'data'];
    protected $casts = [
        'data' => 'array',
        'resultado' => 'float',
    ];

    public function evento() {
        return $this->belongsTo(Evento::class);
    }

    public function equipo() {
        return $this->belongsTo(Equipo::class);
    }

    public function prueba() {
        return $this->belongsTo(Prueba::class);
    }
}
