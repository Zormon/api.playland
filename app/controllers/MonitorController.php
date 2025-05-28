<?php

namespace App\Controllers;

use App\Models\Evento;

class MonitorController extends Controller {
    /**
     * Devuelve los datos para la aplicación de monitorización del evento actual (si existe).
     */
    public function summary() {
        // Obtener el evento actual
        $evento = Evento::getCurrent();

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
}
