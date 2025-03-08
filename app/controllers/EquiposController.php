<?php

namespace App\Controllers;

use Leaf\Http\Request;

use App\Models\Equipo;
use App\Models\Adulto;

use App\Interfaces\ItemController;
use Lib\Err;
use Lib\Cache;

class EquiposController extends Controller implements ItemController {
    private const array CACHE_KEYS = [
        'all' => 'equipos:all/json',
    ];

    public function all() {
        // Si no puede ver todos los equipos
        if (!auth()->user()->can('equipos:viewall')) {
            response()->plain(null, 403);
        }

        // Parámetros de la petición.
        $params = new \stdClass;
        $params->nocache = request()->get('nocache') ? true : false;

        $cacheKey = self::CACHE_KEYS['all'];
        if ($params->nocache || !$equipoList = Cache::get($cacheKey)) {
            $equipoList = json_encode(Equipo::all());
            Cache::set($cacheKey, $equipoList, 3600 * 24 * 30); // 30 días
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($equipoList, 200);
    }

    public function get(int $id) {
        // Verificar permisos y propiedad del equipo.
        $equipo = Equipo::find($id);
        if (empty($equipo)) {
            response()->exit(null, 404);
        }

        // Si no es el dueño del equipo ni puede ver todos los equipos, devolver error.
        if ($equipo->adulto_id !== auth()->user()->id && !auth()->user()->can('users:viewall')) {
            response()->exit(null, 403);
        }

        response()->json($equipo);
    }

    // @todo: Implementar permisos
    public function create() {
        $equipoData = $this->getPostData(request());

        // Crear el equipo.
        $equipo = new Equipo($equipoData);
        $equipo->save();

        Cache::delete(self::CACHE_KEYS);
        response()->plain(null, 201);
    }

    // @todo Implementar permisos
    public function put($id) {
        // Buscar el equipo.
        $equipo = Equipo::find($id);

        // Comprobar que existe el equipo.
        if (empty($equipo)) {
            response()->plain(null, 404);
            return;
        }

        $equipoData = $this->getPostData(request(), $id);
        
        if (!$equipoData) {
            return;
        }

        // Actualizar el equipo.
        $equipo->update($equipoData);
        
        Cache::delete(self::CACHE_KEYS);
        response()->plain(null, 200);
    }

    public function delete($id) {
        // Comprobar permiso.
        //TODO: El adulto puede borrar sus propios equipos, siempre que no tengan participaciones o reservas.
        if (!auth()->user()->can('equipos:delete')) {
            response()->plain(null, 403);
        }

        // Comprobar que existe el equipo.
        $equipo = Equipo::find($id);
        if (empty($equipo)) {
            response()->plain(null, 404);
            return;
        }

        // Eliminar el equipo.
        $equipo->delete();
        Cache::delete(self::CACHE_KEYS);
        response()->plain(null, 204);
    }

    /**
     * Valida y devuelve los datos de un equipo recibidos en la petición.
     *
     * @param Request $request Petición HTTP.
     * @param int|null $exclude ID del equipo a excluir.
     * @return array|bool Datos del equipo o false si no son válidos.
     */
    protected function getPostData(Request $request, ?int $exclude = null): array|bool {
        // Validar los datos recibidos.
        $reqBody = $request->validate([
            'titulo' => 'string|min:3|max:80',
            'nombre' => 'string|min:3|max:80',
            'nacimiento' => 'date',
            'notas' => 'optional|string',
            'adulto_id' => 'number',
        ]);

        // Si los datos no son válidos, devolver error.
        if (!$reqBody) {
            if ( app()->config('debug') == 'true' ) {
                response()->exit(request()->errors(), 400);
            }
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }

        // Comprobar que el adulto existe.
        if (!Adulto::find($reqBody['adulto_id'])) {
            response()->exit(Err::get('ADULT_NOT_FOUND'), 404);
        }

        return $reqBody;
    }
}
