<?php

namespace App\Controllers;

use App\Models\Evento;
use App\Models\Reserva;
use App\Models\Participacion;
use Lib\Err;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class EventosController extends Controller implements ItemController {
    use ValidateRequestData;

    public array $fields = [
        'nombre' => 'string|min:3|max:50',
        'lugar' => 'optional|string|min:5|max:100',
        'fecha' => 'array<date>',
        'entradas_ids' => 'array<numeric>',
        'pruebas_ids' => 'array<numeric>',
        'data' => 'optional|json',
    ];

    public function all() {
        $format = request()->get('format');

        if ($format === 'full') {
            $eventos = Evento::with(['pruebas.obstaculos', 'entradas'])->get();
            $eventos->each(function($evento) {
                $evento->appendsFullRelations(['pruebas', 'entradas']);
            });
        } else {
            $eventos = Evento::with(['entradas', 'pruebas'])->get();
        }

        response()->json($eventos);
    }

    public function get(int $id) {
        if (!$evento = Evento::with(['entradas', 'pruebas'])->find($id)) {
            response()->exit(null, 404);
        }
        response()->json($evento);
    }

    public function create() {
        $eventoData = $this->getItemData(request());

        // Validar que las fechas de inicio y fin estén presentes
        if (sizeof($eventoData['fecha']) != 2) {
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }
        // La fecha de inicio no puede ser mayor que la fecha de fin
        if ($eventoData['fecha'][0] > $eventoData['fecha'][1]) {
            response()->exit(Err::get('INVALID_DATE_RANGE'), 400);
        }

        // Verificar superposición de fechas
        if (Evento::checkDateOverlap($eventoData['fecha'][0], $eventoData['fecha'][1])) {
            response()->exit(Err::get('EVENT_DATE_OVERLAP'), 422);
        }

        $evento = new Evento($eventoData);

        try {
            $evento->save();
            $evento->entradas()->sync($eventoData['entradas_ids']);
            $evento->pruebas()->sync($eventoData['pruebas_ids']);
        } catch (QueryException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        $eventoData = $this->getItemData(request());

        if (!$evento = Evento::find($id)) {
            response()->exit(null, 404);
        }

        // Validar que las fechas de inicio y fin estén presentes
        if (sizeof($eventoData['fecha']) != 2) {
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }
        // La fecha de inicio no puede ser mayor que la fecha de fin
        if ($eventoData['fecha'][0] > $eventoData['fecha'][1]) {
            response()->exit(Err::get('INVALID_DATE_RANGE'), 400);
        }

        // Verificar superposición de fechas
        if (Evento::checkDateOverlap($eventoData['fecha'][0], $eventoData['fecha'][1], $id)) {
            response()->exit(Err::get('EVENT_DATE_OVERLAP'), 422);
        }

        try {
            $evento->update($eventoData);
            $evento->entradas()->sync($eventoData['entradas_ids']);
            $evento->pruebas()->sync($eventoData['pruebas_ids']);
        } catch (QueryException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
        if (!$evento = Evento::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $evento->delete();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    /**
     * Get summary statistics for a specific event
     * 
     * @param int $id The event ID
     */
    public function summary(int $id) {
        // Check if the event exists
        if (!$evento = Evento::find($id)) {
            response()->exit(null, 404);
        }

        // Get reservations by entry type
        $reservasPorEntrada = Reserva::where('evento_id', $id)
            ->selectRaw('entrada_id, count(*) as total')
            ->groupBy('entrada_id')
            ->with('entrada:id,nombre')
            ->get()
            ->map(fn($item) => [
                'nombre' => $item->entrada->nombre,
                'cantidad' => $item->total
            ]
            );

        // Get participations by test
        $participacionesPorPrueba = Participacion::where('evento_id', $id)
            ->selectRaw('prueba_id, count(*) as total')
            ->groupBy('prueba_id')
            ->with('prueba:id,nombre')
            ->get()
            ->map(fn($item) => [
                'nombre' => $item->prueba->nombre,
                'cantidad' => $item->total
            ]
            );

        // Prepare the response
        $summary = [
            'evento_id' => $id,
            'nombre' => $evento->nombre,
            'reservas' => [
                'total' => $reservasPorEntrada->sum('cantidad'),
                'grupos' => $reservasPorEntrada,
            ],
            'participaciones' => [
                'total' => $participacionesPorPrueba->sum('cantidad'),
                'grupos' => $participacionesPorPrueba,
            ],
            'ranking' => [],
        ];

        response()->json($summary);
    }

    /**
     * Get the current ongoing event, if any
     */
    public function current() {
        $format = request()->get('format');

        $query = Evento::current();

        if ($format === 'full') {
            if ($evento = $query->with(['pruebas.obstaculos', 'entradas'])->first()) {
                $evento->appendsFullRelations(['pruebas', 'entradas']);
            }
        } else {
            $evento = $query->first();
        }

        response()->json($evento);
    }
}
