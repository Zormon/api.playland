<?php

namespace App\Models;

class Reserva extends Model {
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'equipo_id',
        'evento_id',
        'entrada_id',
        'dia',
        'pagado'
    ];

    protected $casts = [
        'dia' => 'date',
        'pagado' => 'string',
    ];

    /**
     * Get the equipo associated with the reserva.
     */
    public function equipo() {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Get the evento associated with the reserva.
     */
    public function evento() {
        return $this->belongsTo(Evento::class);
    }

    /**
     * Get the entrada associated with the reserva.
     */
    public function entrada() {
        return $this->belongsTo(Entrada::class);
    }

    /**
     * Scope a query to only include reservas for a specific equipo.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $equipoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEquipo($query, $equipoId) {
        return $query->where('equipo_id', $equipoId);
    }

    /**
     * Scope a query to only include reservas for a specific evento.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $eventoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEvento($query, $eventoId) {
        return $query->where('evento_id', $eventoId);
    }

    /**
     * Scope a query to only include reservas for a specific date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDate($query, $date) {
        return $query->whereDate('dia', $date);
    }

    /**
     * Scope a query to only include reservas with a specific payment status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPaymentStatus($query, $status) {
        return $query->where('pagado', $status);
    }

    /**
     * Scope a query to only include reservas for today.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeToday($query) {
        $today = date('Y-m-d');
        return $query->whereDate('dia', $today);
    }
}
