<?php

namespace App\Models;

class Prueba extends Model {
    protected $hidden = ['created_at', 'updated_at', 'obstaculos'];
    protected $fillable = ['tipo', 'nombre', 'info'];
    protected $appends = ['obstaculos_ids'];

    public function toArray() {
        $array = parent::toArray();
        
        // Hide obstaculos field for non-race type pruebas
        if ($this->tipo !== 'race') {
            unset($array['obstaculos_ids']);
        }
        
        return $array;
    }

    public function getObstaculosIdsAttribute(): array {
        return $this->obstaculos->pluck('id')->toArray();
    }

    public function scopeRace($query) {
        return $query->where('tipo', 'race');
    }

    public function obstaculos() {
        return $this->belongsToMany(Obstaculo::class, 'obstaculo_prueba');
    }
}
