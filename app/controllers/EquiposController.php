<?php

namespace App\Controllers;
use App\Models\Equipo;
use App\Models\Adulto;
use Leaf\Http\Request;
use Lib\Err;
use Lib\Cache;

class EquiposController extends Controller {
    public function all() {
        // Si no puede ver todos los usuarios
        if (!auth()->user()->can('users:viewall')) {
            response()->plain(null, 403);
        }

        // Parámetros de la petición.
        $params = new \stdClass;
        $params->nocache = request()->get('nocache') ? true : false;

        $cacheKey = 'equipos:all/json';
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
            response()->plain(null, 404);
        }

        // Si no es el dueño del equipo ni puede ver todos los equipos, devolver error.
        if ($equipo->adulto_id !== auth()->user()->id && !auth()->user()->can('users:viewall')) {
            response()->plain(null, 403);
        }

        response()->json($equipo);
    }

    public function create() {
        $equipoData = $this->getEquipoPostData(request());

        // Crear el equipo.
        $equipo = new Equipo($equipoData);
        $equipo->save();

        response()->plain(null, 201);
    }

    public function update($id) {
        // Buscar el equipo.
        $equipo = Equipo::find($id);

        // Comprobar que existe el equipo.
        if (empty($equipo)) {
            response()->plain(null, 404);
            return;
        }

        $equipoData = $this->getEquipoPostData(request(), $id);

        // Actualizar el equipo.
        $equipo->update($equipoData);
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
        response()->plain(null, 204);
    }

    /**
     * Valida y devuelve los datos de un equipo recibidos en la petición.
     *
     * @param Request $request Petición HTTP.
     * @param int|null $exclude ID del equipo a excluir.
     * @return array|bool Datos del equipo o false si no son válidos.
     */
    private function getEquipoPostData(Request $request, ?int $exclude = null): array|bool {
        // Validar los datos recibidos.
        $equipoData = $request->validate([
            'titulo' => 'string|min:3',
            'nombre' => 'string|min:3',
            'nacimiento' => 'date',
            'notas' => 'optional|string',
            'adulto_id' => 'integer',
        ]);

        if (!Adulto::find($equipoData['adulto_id'])) {
            response()->json(Err::get('ADULT_NOT_FOUND'), 400);
            return false;
        }

        // Si los datos no son válidos, devolver error.
        if (!$equipoData) {
            response()->json(Err::get('INVALID_FIELDS'), 400);
            return false;
        }

        return $equipoData;
    }
}
