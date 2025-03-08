<?php

namespace App\Models;

class Entrada extends Model {
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'nombre',
        'intentos',
        'precio_web',
        'precio_taquilla',
        'descripcion',
    ];
}
