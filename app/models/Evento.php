<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Evento extends Model {
    protected $hidden = ['created_at', 'updated_at', 'fechaDesde', 'fechaHasta', 'entradas'];

    protected $appends = ['fecha', 'entradas_ids', 'current'];

    protected $fillable = [
        'nombre',
        'lugar',
        'fecha', // virtual attribute
        'fechaDesde',
        'fechaHasta',
        'data',
    ];

    public function entradas(): BelongsToMany {
        return $this->belongsToMany(Entrada::class);
    }

    protected function getFechaAttribute(): array {
        return [
            'desde' => $this->fechaDesde,
            'hasta' => $this->fechaHasta,
        ];
    }

    public function getEntradasIdsAttribute(): array {
        return $this->entradas->pluck('id')->toArray();
    }

    public function getCurrentAttribute(): bool {
        return $this->isCurrent();
    }

    // No he podido hacer esto con un mutator, de momento funciona así
    public function save(array $options = []) {
        if (isset($this->attributes['fecha'])) {
            $this->attributes['fechaDesde'] = $this->attributes['fecha']['desde'] ?? null;
            $this->attributes['fechaHasta'] = $this->attributes['fecha']['hasta'] ?? null;
            unset($this->attributes['fecha']);
        }

        parent::save($options);
    }

    public function isCurrent(): bool {
        $now = time();
        $desde = strtotime($this->fechaDesde);
        $hasta = strtotime($this->fechaHasta);
        return $desde <= $now && $hasta >= $now;
    }
}
