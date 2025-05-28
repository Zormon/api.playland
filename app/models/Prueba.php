<?php

namespace App\Models;

class Prueba extends Model {
    protected $hidden = ['created_at', 'updated_at', 'obstaculos', 'pivot'];
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

    /**
     * Ajusta los atributos hidden y appends según las relaciones cargadas
     */
    public function appendsFullRelations(array $relations): void {
        $hidden = $this->hidden;
        $appends = $this->appends;

        foreach ($relations as $relation) {
            $hidden = array_filter($hidden, fn($item) => $item !== $relation);
            $appends = array_filter($appends, fn($item) => $item !== "{$relation}_ids");
        }

        $this->hidden = $hidden;
        $this->appends = $appends;
    }
}
