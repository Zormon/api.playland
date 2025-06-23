<?php

namespace App\Controllers;

use App\Models\Evento;
use App\Models\Equipo;
use App\Models\Prueba;
use App\Models\Obstaculo;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;
use Lib\Err;

class MonitorController extends Controller {
    use ValidateRequestData;

    /**
     * Valida si un equipo puede participar en una prueba específica del evento actual y devuelve el evento si es válido.
     * No valida la integridad de los datos de la solicitud, solo si el equipo puede participar.
     * Se espera que los datos de la solicitud ya hayan sido validados por 'validateParticipationRequestData'.
     *
     * @param Equipo $equipo Equipo que participa
     * @param Prueba $prueba La prueba a participar
     * @param int|null $obstacleCode Código o ID del obstáculo, si la prueba es de tipo 'race'. Puede ser -2 (meta), -1 (salida) o un número positivo.
     * @throws \InvalidArgumentException Si el obstáculo es proporcionado para una prueba que no es de tipo 'race'
     * @return Evento El evento actual si todas las validaciones son correctas
     */
    private function validateParticipationEligibility(Equipo $equipo, Prueba $prueba, ?int $obstacleCode = null): Evento {
        // Obtener el evento actual
        if (!$evento = Evento::current()->first()) {
            response()->exit(Err::get('NO_CURRENT_EVENT'), 412);
        }

        // Verificar que la prueba está asociada al evento actual
        if (!$evento->pruebas()->where('id', $prueba->id)->first()) {
            response()->exit(Err::get('PRUEBA_NOT_IN_EVENT'), 422);
        }

        // Verificar que el equipo tiene una reserva para el evento actual para hoy
        $reserva = \App\Models\Reserva::today()
            ->where('equipo_id', $equipo->id)
            ->where('evento_id', $evento->id)
            ->with('entrada')
            ->first();

        // Verificar que la reserva existe y está pagada
        if (!$reserva || $reserva->pagado === 'no') {
            response()->exit(Err::get('TEAM_HAS_NOT_PAID_TODAY'), 402);
        }

        // Contar cuántos intentos ha hecho el equipo en esta prueba para este evento SOLO HOY
        $hoy = date('Y-m-d');
        $intentosRealizados = \App\Models\Participacion::where('equipo_id', $equipo->id)
            ->where('evento_id', $evento->id)
            ->where('prueba_id', $prueba->id)
            ->whereDate('created_at', $hoy)
            ->where('resultado', '!=', -1) // Excluir races en progreso
            ->count();

        // Verificar que no ha superado el límite de intentos de su entrada
        $maxIntentos = $reserva->entrada->intentos;
        if ($intentosRealizados >= $maxIntentos) {
            response()->exit(Err::get('TEAM_MAX_ATTEMPTS_REACHED'), 403);
        }

        // Validaciones específicas de obstáculos
        if ($obstacleCode) {
            if ($prueba->tipo !== 'race') { // Solo pruebas tipo race
                throw new \InvalidArgumentException('Obstacle ID provided for non-race prueba');
            }
            // Obtener la participación existente de la race de hoy para el equipo y evento
            $participacion = \App\Models\Participacion::where('equipo_id', $equipo->id)
                ->where('evento_id', $evento->id)
                ->where('prueba_id', $prueba->id)
                ->whereDate('created_at', $hoy)
                ->where('resultado', -1) // Resultado de salida
                ->first();

            switch ($obstacleCode) {
                case -1: // Salida de circuito
                    if ($participacion) {
                        response()->exit(Err::get('RACE_ALREADY_STARTED'), 409);
                    }
                    break;
                case -2: // Meta de circuito: verificar que existe un registro con -1 (salida)
                    if (!$participacion) {
                        response()->exit(Err::get('RACE_NOT_STARTED'), 422);
                    }
                    break;
                default: // Obstáculo normal
                    if (!$participacion) {
                        response()->exit(Err::get('RACE_NOT_STARTED'), 422);
                    }

                    // Verificar que el equipo no ha pasado ya por el obstáculo
                    if (array_find($participacion->data ?? [], fn($item) => abs($item) === $obstacleCode)) {
                        response()->exit(Err::get('OBSTACLE_ALREADY_PASSED'), 409);
                    }
            }
        }

        return $evento;
    }

    /**
     * Valida los datos de la solicitud de participación. Solo valida la integridad de los datos, no si el equipo puede participar.
     *
     * @param int $equipoId ID del equipo (dorsal)
     * @param int $pruebaId ID de la prueba
     * @param bool $checkScore Indica si se debe validar el score (para comprobar la disponibilidad de la prueba a un equipo sin enviar resultado)
     * @param int|null $obstacleCode Código o ID del obstáculo, si la prueba es de tipo 'race'
     * @return array {equipo: Equipo, prueba: Prueba, score: int|null}
     */
    private function validateParticipationRequestData(int $equipoId, int $pruebaId, bool $checkScore, int $obstacleCode = null) {
        // Verificar que el equipo y la prueba existen
        if (!$equipo = Equipo::find($equipoId)) {
            response()->exit(Err::get('TEAM_NOT_FOUND'), 404);
        }
        if (!$prueba = Prueba::find($pruebaId)) {
            response()->exit(Err::get('PRUEBA_NOT_FOUND'), 404);
        }

        $score = request()->get('score');

        if ($prueba->tipo === 'race') {
            // Validar valores requeridos para pruebas tipo race
            if ($obstacleCode === -1 || $obstacleCode === -2) {
                // Si es salida o meta, no se espera un score
                if ($score !== null) {
                    response()->exit(Err::get('INVALID_FIELDS'), 400);
                }
            } else {
                // Si es un obstáculo normal, verificar que el obstáculo existe, pertenece a la prueba y que el score es 0 o 1
                if (!$obstacleCode) {
                    response()->exit(Err::get('INVALID_OBSTACLE'), 400);
                }
                if (!$obstaculo = Obstaculo::find($obstacleCode)) {
                    response()->exit(Err::get('OBSTACLE_NOT_FOUND'), 404);
                }
                if (!$prueba->obstaculos()->where('id', $obstaculo->id)->exists()) {
                    response()->exit(Err::get('OBSTACLE_NOT_IN_PRUEBA'), 422);
                }
                if ($checkScore && $score !== 0 && $score !== 1) {
                    response()->exit(Err::get('INVALID_OBSTACLE_SCORE'), 400);
                }
            }
        } else {
            // Si la prueba no es de tipo 'race', no se acepta un obstáculo pero se espera un score.
            if ($obstacleCode !== null) {
                response()->exit(Err::get('INVALID_FIELDS'), 400);
            }

            if ($checkScore && $score === null) {
                response()->exit(Err::get('MISSING_SCORE_PARAM'), 400);
            }
        }

        return [$equipo, $prueba, $score];
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
     * @param int|null $obstacleCode Código o ID del obstáculo, si la prueba es de tipo 'race'. Puede ser -2 (meta), -1 (salida) o un número positivo.
     */
    public function canParticipate($equipoId, $pruebaId, $obstacleCode = null) {
        [$equipo, $prueba] = $this->validateParticipationRequestData($equipoId, $pruebaId, false, $obstacleCode);
        $this->validateParticipationEligibility($equipo, $prueba, $obstacleCode);

        // Si llega aquí es porque puede participar
        response()->plain(null, 200);
    }

    /**
     * Registra una nueva participación después de realizar las mismas comprobaciones
     * que el método canParticipate
     * 
     * @param int $equipoId ID del equipo (dorsal)
     * @param int $pruebaId ID de la prueba
     * @TODO: usar prop de clase lazyload para obtener la participacion en funciones de validacion si procede y recuperarla aqui, para evitar doble consulta.
     */
    public function registerParticipation(int $equipoId, int $pruebaId, ?int $obstacleCode = null) {
        [$equipo, $prueba, $score] = $this->validateParticipationRequestData($equipoId, $pruebaId, true, $obstacleCode);
        $evento = $this->validateParticipationEligibility($equipo, $prueba, $obstacleCode);

        // Si hay un obstacleCode, se trata de una prueba tipo race
        if ($obstacleCode !== null) {
            if ($obstacleCode === -1) { // Manejo especial para salida de circuito (obstacleCode = -1)
                // Crear una nueva participación con resultado -1 (en progreso)
                $participacion = new \App\Models\Participacion([
                    'evento_id' => $evento->id,
                    'equipo_id' => $equipo->id,
                    'prueba_id' => $prueba->id,
                    'resultado' => -1, // En progreso
                ]);
            } else if ($obstacleCode === -2) { // Meta de circuito (obstacleCode = -2)
                // Buscar el registro de salida existente
                $participacion = \App\Models\Participacion::where('equipo_id', $equipo->id)
                    ->where('evento_id', $evento->id)
                    ->where('prueba_id', $prueba->id)
                    ->where('resultado', -1)
                    ->whereDate('created_at', date('Y-m-d'))
                    ->first();

                // Calcular el tiempo transcurrido desde la salida
                $startTime = $participacion->created_at;
                $currentTime = new \DateTime();
                $elapsedTime = ($currentTime->getTimestamp() * 1000 + (int) ($currentTime->format('u') / 1000)) -
                    ($startTime->getTimestamp() * 1000 + (int) ($startTime->format('u') / 1000));

                // Actualizar el registro existente con el tiempo final
                $participacion->resultado = $elapsedTime;
            } else {
                // Resto de obstáculos normales
                $participacion = \App\Models\Participacion::where('equipo_id', $equipo->id)
                    ->where('evento_id', $evento->id)
                    ->where('prueba_id', $prueba->id)
                    ->where('resultado', -1) // Resultado de salida
                    ->whereDate('created_at', date('Y-m-d'))
                    ->first();

                $data = $participacion->data ?? [];
                $data[] = $score ? $obstacleCode : -$obstacleCode;
                $participacion->data = $data;
            }
        } else {
            // Crear la participación para las pruebas normales
            $participacion = new \App\Models\Participacion([
                'evento_id' => $evento->id,
                'equipo_id' => $equipo->id,
                'prueba_id' => $prueba->id,
                'resultado' => $score
            ]);
        }

        try {
            $participacion->save();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        } finally {
            // Limpiar caché de participaciones
            \Lib\Cache::delete("participaciones:{$evento->id}/json");
        }

        response()->plain(null, 201);

    }
}
