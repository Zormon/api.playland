<?php

namespace App\Controllers;

use App\Models\Equipo;
use App\traits\ValidateRequestData;

use App\Interfaces\ItemController;
use Lib\Cache;
use Illuminate\Database\QueryException;

class EquiposController extends Controller implements ItemController {
    use ValidateRequestData;

    public array $fields = [
        'titulo' => 'string|min:3|max:80',
        'nombre' => 'string|min:3|max:80',
        'nacimiento' => 'date',
        'notas' => 'optional|string',
        'adulto_id' => 'number',
    ];

    private const array CACHE_KEYS = [
        'all' => 'equipos:all/json',
        'adulto' => 'equipos:@/json',
    ];

    public function all() {
        $USERID = auth()->user()->id;

        $cacheKey = auth()->user()->is('adulto') ?
            self::aCacheKey($USERID) :
            self::CACHE_KEYS['all'];

        // Si se solicita sin caché, borrar la caché.
        if (request()->get('nocache')) {
            Cache::delete($cacheKey);
        }

        // Si no hay caché, obtener los datos de la base de datos y guardarlos en caché.
        if (!$json = Cache::get($cacheKey)) {
            $json = auth()->user()->is('adulto') ?
                json_encode(Equipo::where('adulto_id', $USERID)->get()) :
                json_encode(Equipo::all());
            Cache::set($cacheKey, $json, 3600 * 24 * 30); // 30 días
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($json, 200);
    }

    public function get(int $id) {
        if (!$equipo = Equipo::find($id)) {
            response()->exit(null, 404);
        }

        // Si el usuario no puede ver todos los equipos, exigir que sea el dueño del equipo.
        if (auth()->user()->is('adulto')) {
            $this->mustOwn($equipo);
        }

        response()->json($equipo);
    }

    public function create() {
        $requestData = $this->getItemData(request());
        $equipo = new Equipo($requestData);

        // Si el usuario no puede gestionar todos los equipos, exigir que sea el dueño del equipo.
        if (auth()->user()->is('adulto')) {
            $this->mustOwn($equipo);
        }

        try {
            $equipo->save();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        Cache::delete([self::CACHE_KEYS['all'], self::aCacheKey($equipo->adulto_id)]);
        response()->plain(null, 201);
    }

    public function put($id) {
        $requestData = $this->getItemData(request(), true);

        if (!$equipo = Equipo::find($id)) {
            response()->exit(null, 404);
        }

        // Si el usuario no puede gestionar todos los equipos, exigir que sea el dueño del equipo.
        if (auth()->user()->is('adulto')) {
            $this->mustOwn($equipo);
        }

        try {
            $equipo->update($requestData);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        Cache::delete([self::CACHE_KEYS['all'], self::aCacheKey($equipo->adulto_id)]);
        response()->noContent();
    }

    //TODO: El adulto puede borrar sus propios equipos, siempre que no tengan participaciones o reservas.
    public function delete($id) {
        if (!$equipo = Equipo::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $equipo->delete();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        Cache::delete([self::CACHE_KEYS['all'], self::aCacheKey($equipo->adulto_id)]);
        response()->noContent();
    }

    /**
     * Comprueba que el usuario es dueño del equipo, si no lo es, devuelve un error 403.
     *
     * @param Equipo $equipo El equipo a comprobar
     * @return void
     */
    private function mustOwn(Equipo $equipo) {
        if ($equipo->adulto_id !== auth()->user()->id) {
            response()->exit(null, 403);
        }
    }

    private static function aCacheKey($id) {
        return str_replace('@', $id, self::CACHE_KEYS['adulto']);
    }
}
