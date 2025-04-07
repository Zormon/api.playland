<?php

namespace App\Controllers;

use App\Models\Evento;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class EventosController extends Controller implements ItemController {
    use ValidateRequestData;

    public array $fields = [
        'nombre' => 'string|min:3|max:50',
        'lugar' => 'string|min:5|max:100',
        'geo.lat' => 'numeric',
        'geo.lon' => 'numeric',
        'fecha.desde' => 'date',
        'fecha.hasta' => 'date',
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
