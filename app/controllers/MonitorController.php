<?php

namespace App\Controllers;

use App\Models\Evento;
use Lib\Err;

class MonitorController extends Controller {
    /**
     * Devuelve los datos para la aplicación de monitorización del evento actual (si existe).
     */
    public function summary() {
        // Obtener el evento actual
        $evento = Evento::current();

        // Verificar si existe un evento actual
        if (!$evento) {
            response()->exit(null, 404);
        }

        // Cargar las pruebas del evento y sus obstáculos
        $evento->load(['pruebas', 'pruebas.obstaculos']);

        // Obtener las pruebas del evento
        $pruebas = $evento->pruebas->map(function ($prueba) {
            $pruebaData = [
                'id' => $prueba->id,
                'nombre' => $prueba->nombre,
                'tipo' => $prueba->tipo,
            ];

            // Incluir obstáculos solo si es tipo 'race'
            if ($prueba->tipo === 'race') {
                $pruebaData['obstaculos'] = $prueba->obstaculos->map(fn($obstaculo) => [
                    'id' => $obstaculo->id,
                    'nombre' => $obstaculo->nombre,
                    'puntos' => $obstaculo->puntos,
                ]);
            }

            return $pruebaData;
        });

        response()->json([
            'evento' => [
                'nombre' => $evento->nombre,
                'fechaDesde' => $evento->fechaDesde,
                'fechaHasta' => $evento->fechaHasta,
            ],
            'pruebas' => $pruebas,
        ]);
    }

    /**
     * Verifica si un equipo puede participar en una prueba específica del evento actual.
     * Comprueba:
     * 1. Que el equipo existe
     * 2. Que el equipo tiene una reserva para el evento actual para hoy
     * 3. Que el equipo no ha superado el número máximo de intentos permitidos por su entrada
     * 
     * @param int $equipoId ID del equipo (dorsal)
     * @param int $pruebaId ID de la prueba
     */
    public function canParticipate($equipoId, $pruebaId) {
        // Obtener el evento actual
        if (!$evento = Evento::current()->first()) {
            response()->exit(Err::get('NO_CURRENT_EVENT'), 412);
        }

        // Verificar que el equipo existe
        if (!\App\Models\Equipo::find($equipoId)) {
            response()->exit(Err::get('TEAM_NOT_FOUND'), 404);
        }

        // Verificar que la prueba existe y está asociada al evento actual
        if (!$evento->pruebas()->where('id', $pruebaId)->first()) {
            response()->exit(Err::get('PRUEBA_NOT_IN_EVENT'), 422);
        }

        // Verificar que el equipo tiene una reserva para el evento actual para hoy
        $reserva = \App\Models\Reserva::today()
            ->where('equipo_id', $equipoId)
            ->where('evento_id', $evento->id)
            ->with('entrada')
            ->first();

        // Verificar que la reserva existe y está pagada
        if (!$reserva || $reserva->pagado === 'no') {
            response()->exit(Err::get('TEAM_HAS_NOT_PAID_TODAY'), 402);
        }

        // Contar cuántos intentos ha hecho el equipo en esta prueba para este evento
        $intentosRealizados = \App\Models\Participacion::where('equipo_id', $equipoId)
            ->where('evento_id', $evento->id)
            ->where('prueba_id', $pruebaId)
            ->count();

        // Verificar que no ha superado el límite de intentos de su entrada
        $maxIntentos = $reserva->entrada->intentos;
        if ($intentosRealizados >= $maxIntentos) {
            response()->exit(Err::get('TEAM_MAX_ATTEMPTS_REACHED'), 403);
        }

        // Puede participar
        response()->plain(null, 200);
    }
}
