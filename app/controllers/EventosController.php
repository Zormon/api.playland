<?php

namespace App\Controllers;

use Leaf\Http\Request;

use App\Models\Evento;

use App\Interfaces\ItemController;
use Lib\Err;
use Illuminate\Database\QueryException;

class EventosController extends Controller implements ItemController {
    public function all() {
        response()->json(Evento::all());
    }

    public function get(int $id) {
        if (!$evento = Evento::find($id)) {
            response()->exit(null, 404);
        }
        response()->json($evento);
    }

    public function create() {
        $eventoData = $this->getPostData(request());
        $evento = new Evento($eventoData);

        try {
            $evento->save();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
            // Si el error no fue manejado, lanzar una respuesta genérica
            response()->exit('Database error occurred', 500);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        $eventoData = $this->getPostData(request());

        if (!$evento = Evento::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $evento->update($eventoData);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
            //
            response()->exit('Database error occurred', 500);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
    }

    private function getPostData(Request $request): array {
        // Validar los datos de la petición.
        $data = $request->validate([
            'nombre' => 'string|min:3|max:50',
            'intentos' => 'numeric|min:1|max:255',
            'precio_web' => 'numeric',
            'precio_taquilla' => 'numeric',
            'descripcion' => 'optional|string|min:3|max:255',
        ]);

        // Si los datos no son válidos, devolver error.
        if (!$data) {
            if ( app()->config('debug') == 'true' ) {
                response()->exit(request()->errors(), 400);
            }
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }

        return $data;
    }
}
