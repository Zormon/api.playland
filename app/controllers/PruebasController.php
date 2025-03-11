<?php

namespace App\Controllers;

use App\Models\Prueba;
use Lib\Access;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class PruebasController extends Controller implements ItemController {
    use ValidateRequestData;

    public array $fields = [
        'tipo' => 'in:[puntos,aguante,velocidad,race]',
        'nombre' => 'string|min:3|max:50',
        'info' => 'optional|string',
        'data' => 'optional|json',
    ];

    public function all() {
        Access::can('pruebas:view');
        response()->json(Prueba::all());
    }

    public function get(int $id) {
        Access::can('pruebas:view');
        if (!$prueba = Prueba::find($id)) {
            response()->exit(null, 404);
        }
        response()->json($prueba);
    }

    public function create() {
        Access::can('pruebas:manague');

        $requestData = $this->getItemData(request());
        $prueba = new Prueba($requestData);

        try {
            $prueba->save();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        Access::can('pruebas:manague');

        $requestData = $this->getItemData(request(), true);

        if (!$prueba = Prueba::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $prueba->update($requestData);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
        Access::can('pruebas:manague');

        if (!$prueba = Prueba::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $prueba->delete();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }
}
