<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Entrada extends Model {
    protected $hidden = ['created_at', 'updated_at', 'pivot'];

    protected $fillable = [
        'nombre',
        'intentos',
        'precio_web',
        'precio_taquilla',
        'descripcion',
    ];

    public function eventos(): BelongsToMany{
        return $this->belongsToMany(Evento::class);
    }
}
