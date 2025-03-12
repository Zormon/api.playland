<?php

namespace App\Models;

class Obstaculo extends Model {
    protected $hidden = ['created_at', 'updated_at', 'pivot'];

    protected $fillable = ['nombre', 'puntos'];

    protected $casts = [
        'puntos' => 'integer',
    ];
    
    public function pruebas() {
        return $this->belongsToMany(Prueba::class, 'obstaculo_prueba');
    }
}
