<?php

namespace App\Controllers;

use App\Models\Obstaculo;
use Lib\Access;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class ObstaculosController extends Controller implements ItemController {
    use ValidateRequestData;

    public array $fields = [
        'nombre' => 'string|min:3|max:50',
        'puntos' => 'numeric|max:255',
    ];

    public function all() {
        Access::can('pruebas:view');
        response()->json(Obstaculo::all());
    }

    public function get(int $id) {
        Access::can('pruebas:view');
        if (!$obstaculo = Obstaculo::find($id)) {
            response()->exit(null, 404);
        }
        response()->json($obstaculo);
    }

    public function create() {
        Access::can('pruebas:manague');

        $requestData = $this->getItemData(request());
        $obstaculo = new Obstaculo($requestData);

        try {
            $obstaculo->save();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        Access::can('pruebas:manague');

        $requestData = $this->getItemData(request(), true);

        if (!$obstaculo = Obstaculo::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $obstaculo->update($requestData);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
        Access::can('pruebas:manague');

        if (!$obstaculo = Obstaculo::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $obstaculo->delete();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }
}
