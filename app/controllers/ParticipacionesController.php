<?php

namespace App\Controllers;

use App\Models\Participacion;
use Lib\Err;
use Lib\Cache;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class ParticipacionesController extends Controller implements ItemController {
    use ValidateRequestData;

    private const CACHE_KEYS = [
        'evento' => 'participaciones:@/json',
    ];

    public array $fields = [
        'evento_id' => 'number',
        'equipo_id' => 'number',
        'prueba_id' => 'number',
        'resultado' => 'number',
        'data' => 'optional|array',
    ];

    public function all() {
        if (!$evid = request()->get('event')) {
            response()->exit(Err::get('MISSING_EVENT_PARAM'), 400);
        }

        $cacheKey = $this->evCacheKey($evid);

        // If no-cache is requested, delete the cache
        if (request()->get('nocache')) {
            Cache::delete($cacheKey);
        }

        // If no cache exists, get data from database and store in cache
        if (!$json = Cache::get($cacheKey)) {
            $json = json_encode(Participacion::where('evento_id', $evid)->get());
            Cache::set($cacheKey, $json, 3600 * 24 * 7); // 7 days
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($json, 200);
    }

    public function get(int $id) {
        if (!$participacion = Participacion::find($id)) {
            response()->exit(null, 404);
        }
        response()->json($participacion);
    }

    public function create() {
        $requestData = $this->getItemData(request());

        try {
            $participacion = new Participacion($requestData);
            $participacion->save();

            // Clear the cache after creating a new participacion
            Cache::delete($this->evCacheKey($participacion->evento_id));
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        $requestData = $this->getItemData(request(), true);

        if (!$participacion = Participacion::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $oldEventId = $participacion->evento_id;
            $participacion->update($requestData);

            // Clear the cache after updating a participacion
            Cache::delete($this->evCacheKey($participacion->evento_id));
            // If the event ID has changed, clear the old cache as well
            if ($oldEventId !== $participacion->evento_id) {
                Cache::delete($this->evCacheKey($oldEventId));
            }
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
        if (!$participacion = Participacion::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $participacion->delete();

            // Clear the cache of the evento of this participacion
            Cache::delete($this->evCacheKey($participacion->evento_id));
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    private function evCacheKey($id) {
        return str_replace('@', $id, self::CACHE_KEYS['evento']);
    }
}
