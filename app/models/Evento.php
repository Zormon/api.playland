<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Evento extends Model {
    protected $hidden = ['created_at', 'updated_at', 'fechaDesde', 'fechaHasta', 'entradas', 'pruebas'];

    protected $appends = ['fecha', 'entradas_ids', 'pruebas_ids', 'current'];

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

    public function pruebas(): BelongsToMany {
        return $this->belongsToMany(Prueba::class);
    }

    protected function getFechaAttribute(): array {
        return [$this->fechaDesde, $this->fechaHasta];
    }

    public function getEntradasIdsAttribute(): array {
        return $this->entradas->pluck('id')->toArray();
    }

    public function getPruebasIdsAttribute(): array {
        return $this->pruebas->pluck('id')->toArray();
    }

    public function getCurrentAttribute(): bool {
        return $this->isCurrent();
    }

    /**
     * Verifica si hay eventos superpuestos en las fechas dadas
     *
     * @param string $fechaDesde Fecha de inicio del evento
     * @param string $fechaHasta Fecha de finalización del evento
     * @param int|null $exceptId ID del evento actual a excluir de la verificación (útil para actualizaciones)
     * @return bool true si hay superposición, false si no hay
     */
    public static function checkDateOverlap(string $fechaDesde, string $fechaHasta, ?int $exceptId = null): bool {
        $query = self::where(function ($q) use ($fechaDesde, $fechaHasta) {
            // Casos de superposición:
            // 1. El inicio del nuevo evento está dentro del rango de otro evento
            $q->where('fechaDesde', '<=', $fechaDesde)
                ->where('fechaHasta', '>=', $fechaDesde);
            // 2. El final del nuevo evento está dentro del rango de otro evento
            $q->orWhere('fechaDesde', '<=', $fechaHasta)
                ->where('fechaHasta', '>=', $fechaHasta);
            // 3. El nuevo evento engloba completamente a otro evento
            $q->orWhere('fechaDesde', '>=', $fechaDesde)
                ->where('fechaHasta', '<=', $fechaHasta);
        });

        // Si estamos actualizando un evento, excluirlo de la comprobación
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    // No he podido hacer esto con un mutator, de momento funciona así
    public function save(array $options = []) {
        if (isset($this->attributes['fecha'])) {
            $this->attributes['fechaDesde'] = $this->attributes['fecha'][0] ?? null;
            $this->attributes['fechaHasta'] = $this->attributes['fecha'][1] ?? null;
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

    /**
     * Obtiene el evento actual (si existe)
     */
    public static function getCurrent(): ?self {
        $now = date('Y-m-d H:i:s');
        return self::where('fechaDesde', '<=', $now)
            ->where('fechaHasta', '>=', $now)
            ->first();
    }

    public function getDurationAttribute(): int {
        return (strtotime($this->fechaHasta) - strtotime($this->fechaDesde)) / 86400;
    }
}
