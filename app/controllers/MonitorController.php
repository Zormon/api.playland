<?php

namespace App\Controllers;

use App\Models\Evento;
use App\traits\ValidateRequestData;
use Lib\Err;

class MonitorController extends Controller {
    use ValidateRequestData;

    /**
     * Valida si un equipo puede participar en una prueba específica del evento actual.
     * Realiza todas las comprobaciones necesarias y devuelve el evento si es válido.
     * 
     * @param int $equipoId ID del equipo (dorsal)
     * @param int $pruebaId ID de la prueba
     */
    private function validateParticipationEligibility($equipoId, $pruebaId): Evento {
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

        return $evento;
    }

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
     * 
     * @param int $equipoId ID del equipo (dorsal)
     * @param int $pruebaId ID de la prueba
     */
    public function canParticipate($equipoId, $pruebaId) {
        $this->validateParticipationEligibility($equipoId, $pruebaId);

        // Si llega aquí es porque puede participar
        response()->plain(null, 200);
    }

    /**
     * Registra una nueva participación después de realizar las mismas comprobaciones
     * que el método canParticipate
     * 
     * @param int $equipoId ID del equipo (dorsal)
     * @param int $pruebaId ID de la prueba
     */
    public function registerParticipation($equipoId, $pruebaId) {
        // Validar datos de entrada
        $requestData = request()->validate([
            'score' => 'number'
        ]);

        if (!$requestData) {
            response()->exit(Err::get('MISSING_SCORE'), 400);
        }

        $evento = $this->validateParticipationEligibility($equipoId, $pruebaId);

        // Crear la participación
        try {
            $participacion = new \App\Models\Participacion([
                'evento_id' => $evento->id,
                'equipo_id' => $equipoId,
                'prueba_id' => $pruebaId,
                'resultado' => $requestData['score']
            ]);
            $participacion->save();

            // Limpiar caché de participaciones
            \Lib\Cache::delete("participaciones:{$evento->id}/json");
        } catch (\Illuminate\Database\QueryException $e) {
            error_log("Database error: " . $e->getMessage());
            response()->exit(Err::get('DATABASE_ERROR'), 500);
        }

        response()->plain(null, 201);
    }
}
