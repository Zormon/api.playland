<?php

namespace App\Models;

class Adulto extends Model {
    protected $hidden = ['id', 'user_id', 'created_at', 'updated_at'];

    protected $fillable = [
        'DNI',
        'nombre',
        'email',
        'publi',
        'telefono',
        'estado',
        'user_id',
    ];
}
