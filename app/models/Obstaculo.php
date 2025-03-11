<?php

namespace App\Models;

class Obstaculo extends Model {
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['nombre', 'puntos'];

    protected $casts = [
        'puntos' => 'integer',
    ];
}
