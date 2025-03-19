<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Evento extends Model {
    protected $hidden = ['created_at', 'updated_at', 'fechaDesde', 'fechaHasta', 'entradas'];

    protected $appends = ['fecha', 'entradas_ids'];

    protected $fillable = [
        'nombre',
        'lugar',
        'geo',
        'fecha', // virtual attribute
        'fechaDesde',
        'fechaHasta',
        'data',
    ];

    public function entradas(): BelongsToMany {
        return $this->belongsToMany(Entrada::class);
    }

    protected function geo(): Attribute {
        return new Attribute(function ($value) {
            if ($value === null) {
                return null;
            }
            $coords = explode(',', $value);
            return [
                'lat' => $coords[0],
                'lon' => $coords[1],
            ];
        }, function ($value) {
            return $value === null ? null : $value['lat'] . ',' . $value['lon'];

        });
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
        return $this->fechaDesde <= $now && $this->fechaHasta >= $now;
    }
}
