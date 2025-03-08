<?php

namespace App\Models;

class EventoEntrada extends Model {
    protected $table = 'eventos_entradas';

    protected $fillable = [
        'evento',
        'entrada',
    ];
}
