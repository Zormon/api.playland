<?php

namespace App\Models;

class Evento extends Model {
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'nombre',
        'lugar',
        'geo',
        'fechaDesde',
        'fechaHasta',
        'data',
    ];
}
