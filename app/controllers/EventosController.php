<?php

namespace App\Controllers;

use App\Models\Evento;
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
        'data' => 'optional|json',
    ];

    public function all() {
        $eventos = Evento::with('entradas')->get();
        response()->json($eventos);
    }

    public function get(int $id) {
        if (!$evento = Evento::with('entradas')->find($id)) {
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
     * Get the current ongoing event, if any
     */
    public function current() {
        $now = date('Y-m-d H:i:s');
        $evento = Evento::where('fechaDesde', '<=', $now)
            ->where('fechaHasta', '>=', $now)
            ->first();

        response()->json($evento);
    }
}
