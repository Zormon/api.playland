<?php

namespace App\Controllers;

use App\Models\Entrada;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class EntradasController extends Controller implements ItemController {
    use ValidateRequestData;

    public array $fields = [
        'nombre' => 'string|min:3|max:50',
        'intentos' => 'numeric|min:1|max:255',
        'precio_web' => 'numeric',
        'precio_taquilla' => 'numeric',
        'descripcion' => 'optional|string|min:3|max:255',
    ];

    public function all() {
        response()->json(Entrada::all());
    }

    public function get(int $id) {
        if (!$entrada = Entrada::find($id)) {
            response()->exit(null, 404);
        }
        response()->json($entrada);
    }

    public function create() {
        $entradaData = $this->getItemData(request());
        $entrada = new Entrada($entradaData);

        try {
            $entrada->save();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
            // Si el error no fue manejado, lanzar una respuesta genérica
            response()->exit('Database error occurred', 500);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        $entradaData = $this->getItemData(request());
        $entradaData['descripcion'] ??= '';

        if (!$entrada = Entrada::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $entrada->update($entradaData);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
            //
            response()->exit('Database error occurred', 500);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
        if (!$entrada = Entrada::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $entrada->delete();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
            response()->exit('Database error occurred', 500);
        }

        response()->plain(null, 204);
    }
}
