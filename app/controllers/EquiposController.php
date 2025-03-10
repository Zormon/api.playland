<?php

namespace App\Controllers;

use App\Models\Equipo;
use App\traits\ValidateRequestData;

use App\Interfaces\ItemController;
use Lib\Cache;
use Lib\Access;
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
        $permission = Access::canAny(['equipos:viewall', 'equipos:viewself']);
        $USERID = auth()->user()->id;

        $cacheKey = match ($permission) {
            'equipos:viewall' => self::CACHE_KEYS['all'],
            'equipos:viewself' => self::aCacheKey($USERID),
        };

        // Si se solicita sin caché, borrar la caché.
        if (request()->get('nocache')) {
            Cache::delete($cacheKey);
        }

        // Si no hay caché, obtener los datos de la base de datos y guardarlos en caché.
        if (!$json = Cache::get($cacheKey)) {
            $json = match ($permission) {
                'equipos:viewall' => json_encode(Equipo::all()),
                'equipos:viewself' => json_encode(Equipo::where('adulto_id', $USERID)->get()),
            };
            Cache::set($cacheKey, $json, 3600 * 24 * 30); // 30 días
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($json, 200);
    }

    public function get(int $id) {
        $permission = Access::canAny(['equipos:viewall', 'equipos:viewself']);

        if (!$equipo = Equipo::find($id)) {
            response()->exit(null, 404);
        }

        // Si el usuario no puede ver todos los equipos, exigir que sea el dueño del equipo.
        if ($permission === 'equipos:viewself') {
            $this->mustOwn($equipo);
        }

        response()->json($equipo);
    }

    public function create() {
        $permission = Access::canAny(['equipos:managueall', 'equipos:managueself']);

        $requestData = $this->getItemData(request());
        $equipo = new Equipo($requestData);

        // Si el usuario no puede gestionar todos los equipos, exigir que sea el dueño del equipo.
        if ($permission === 'equipos:managueself') {
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
        $permission = Access::canAny(['equipos:managueall', 'equipos:managueself']);

        $requestData = $this->getItemData(request(), true);

        if (!$equipo = Equipo::find($id)) {
            response()->exit(null, 404);
        }

        // Si el usuario no puede gestionar todos los equipos, exigir que sea el dueño del equipo.
        if ($permission === 'equipos:managueself') {
            $this->mustOwn($equipo);
        }

        try {
            $equipo->update($requestData);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }
        
        Cache::delete(self::aCacheKey($equipo->adulto_id));
        response()->noContent();
    }

    //TODO: El adulto puede borrar sus propios equipos, siempre que no tengan participaciones o reservas.
    public function delete($id) {
        Access::can('equipos:managueall');

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
