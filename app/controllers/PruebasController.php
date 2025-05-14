<?php

namespace App\Controllers;

use App\Models\Prueba;
use Lib\Cache;
use Lib\Err;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class PruebasController extends Controller implements ItemController {
    use ValidateRequestData;

    private const CACHE_KEYS = [
        'all' => 'pruebas:all',
    ];

    public array $fields = [
        'tipo' => 'in:[puntos,aguante,velocidad,race]',
        'nombre' => 'string|min:3|max:50',
        'info' => 'optional|string',
        'obstaculos' => 'optional|array',
    ];

    public function all() {
        // If no-cache is requested, delete the cache
        if (request()->get('nocache')) {
            Cache::delete(self::CACHE_KEYS['all']);
        }

        // If no cache exists, get data from database and store in cache
        if (!$json = Cache::get(self::CACHE_KEYS['all'])) {
            $json = json_encode(Prueba::all());
            Cache::set(self::CACHE_KEYS['all'], $json, 3600 * 24 * 30); // 30 days
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($json, 200);
    }

    public function get(int $id) {
        if (!$prueba = Prueba::find($id)) {
            response()->exit(null, 404);
        }
        response()->json($prueba);
    }

    public function create() {
        $requestData = $this->getItemData(request());
        $obstaculos = $requestData['obstaculos'] ?? [];
        unset($requestData['obstaculos']);

        // No permitir obstaculos si el tipo no es 'race'
        if ($requestData['tipo'] !== 'race' && !empty($obstaculos)) {
            response()->exit(Err::get('OBSTACLES_NOT_ALLOWED'), 400);
        }

        $prueba = new Prueba($requestData);

        try {
            $prueba->save();
            // Only sync obstacles if the type is 'race'
            if (!empty($obstaculos) && $prueba->tipo === 'race') {
                $prueba->obstaculos()->sync($obstaculos);
            }

            // Clear the cache after creating a new prueba
            Cache::delete(self::CACHE_KEYS);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        $requestData = $this->getItemData(request(), true);
        $obstaculos = $requestData['obstaculos'] ?? null;
        unset($requestData['obstaculos']);

        if (!$prueba = Prueba::find($id)) {
            response()->exit(null, 404);
        }

        // No permitir obstaculos si el tipo no es 'race'
        if ($requestData['tipo'] !== 'race' && !empty($obstaculos)) {
            response()->exit(Err::get('OBSTACLES_NOT_ALLOWED'), 400);
        }

        try {
            $prueba->update($requestData);
            if (!empty($obstaculos) && $prueba->tipo === 'race') {
                // Only sync obstacles if the type is 'race' and obstacles are provided
                $prueba->obstaculos()->sync($obstaculos);
            } else {
                // If the type is changed from 'race' to something else, remove all obstacles
                $prueba->obstaculos()->sync([]);
            }

            // Clear the cache after updating a prueba
            Cache::delete(self::CACHE_KEYS);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
        if (!$prueba = Prueba::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $prueba->delete();

            // Clear the cache after deleting a prueba
            Cache::delete(self::CACHE_KEYS);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }
}
