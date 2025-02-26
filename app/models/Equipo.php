<?php

namespace App\Models;

class Equipo extends Model {
    protected $hidden = ['created_at', 'updated_at', 'qrToken'];

    protected $fillable = ['titulo', 'nombre', 'nacimiento', 'notas', 'adulto_id'];

    public function adulto() {
        return $this->belongsTo(Adulto::class, 'adulto_id');
    }
}
