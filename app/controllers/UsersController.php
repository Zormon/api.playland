<?php

namespace App\Controllers;

use Leaf\Helpers\Password;

use App\Models\User;
use App\Models\Adulto;
use App\traits\ValidateRequestData;

use App\Interfaces\ItemController;
use Lib\Err;
use Lib\Cache;
use Lib\Access;
use Illuminate\Database\QueryException;

/**
 * Controlador para la gestión de usuarios.
 * 
 * @todo: los adultos deberían ser gestionados por otro controlador con otro endpoint y tener los métodos PUT y PATCH adecuados.
 * Actualmente los adultos se actualizan con el método PATCH de este controlador.
 */
class UsersController extends Controller implements ItemController {
    use ValidateRequestData;

    private const array CACHE_KEYS = [
        'all' => 'users:all',
        'adulto' => 'users:filter-adulto',
        'noadulto' => 'users:filter-noadulto',
    ];

    private const ESTADO = [
        'activo',
        'email',
        'password',
    ];

    public array $fields = [
        'loginid' => 'string|min:3|max:20',
        'password' => 'string|min:4|max:32',
        'roles' => 'optional|array<string>',
        'adulto' => 'optional|array',
    ];

    private array $afields = [
        'adulto.DNI' => 'string|min:7|max:15',
        'adulto.nombre' => 'string|min:6|max:80',
        'adulto.email' => 'optional|email',
        'adulto.publi' => 'optional|boolean',
        'adulto.telefono' => 'optional|string|min:7|max:14',
        'adulto.estado' => 'in:[activo,email,password]',
    ];

    /**
     * Responde con todos los usuarios de la plataforma.
     *
     */
    public function all() {
        Access::can('users:viewall');

        // Filtro opcional por parámetro URL.
        $filter = request()->get('filter');

        $cacheKey = match ($filter) {
            'adulto'    => self::CACHE_KEYS['adulto'],
            'noadulto'  => self::CACHE_KEYS['noadulto'],
            default     => self::CACHE_KEYS['all'],
        };

        // Si se solicita sin caché, borrar la caché.
        if (request()->get('nocache')) {
            Cache::delete($cacheKey);
        }

        // Si no hay caché, obtener los datos de la base de datos y guardarlos en caché.
        if (!$json = Cache::get($cacheKey)) {
            $json = match ($filter) {
                'adulto'    => json_encode(User::with('adulto')->has('adulto')->get()),
                'noadulto'  => json_encode(User::doesntHave('adulto')->get()),
                default     => json_encode(User::with('adulto')->get()),
            };
            Cache::set($cacheKey, $json, 3600 * 24 * 30); // 30 días
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($json, 200);
    }

    /**
     * Responde con un usuario específico.
     *
     * @param int $id ID del usuario a devolver.
     */
    public function get(int $id) {
        $permission = Access::canAny(['users:viewall', 'users:viewself']);

        if ($permission === 'users:viewself') {
            $this->mustbeSelf($id);
        }

        if (!$user = User::with('adulto')->find($id)) {
            response()->exit(null, 404);
        }

        response()->json($user);
    }

    public function create() {
        Access::can('users:managueall');

        $requestData = $this->getItemData(request());

        // Crear el usuario.
        $user = auth()->createUserFor([
            'loginid' => $requestData['loginid'],
            'password' => $requestData['password'],
        ]);

        // Si el usuario no se ha creado, devolver el motivo.
        if (!$user) {
            response()->exit(auth()->errors(), 422);
        }

        // Asignar los roles al usuario.
        // TODO: esto es temporal hasta que se haga el endpoint de adultos, los roles deben ser obligatorios en este endpoint
        $requestData['roles'] ??= ['adulto'];
        $user->assign($requestData['roles']);

        // Si se ha enviado un adulto, validarlo y crearlo.
        if (isset($requestData['adulto'])) {
            $adultoData = $this->getItemData(request(), true, $this->afields)['adulto'];
            $adultoData['user_id'] = $user->id;
            $adulto = new Adulto($adultoData);

            try {
                $adulto->save();
            } catch (QueryException $e) {
                $this->handleDatabaseError($e);
            }
        }

        Cache::delete(self::CACHE_KEYS);
        response()->noContent();
    }

    /**
     * Actualiza un usuario específico y limpia la caché.
     *
     * @param int $id The ID of the user to update.
     */
    public function put($id) {
        Access::can('users:managueall');

        if (!$user = User::find($id)) {
            response()->exit(null, 404);
        }

        $requestData = $this->getItemData(request());

        //TODO: El usuario mismo no debería poder cambiarse el rol
        $updated = $user->update([
            'loginid' => $requestData['loginid'],
            'password' => Password::hash($requestData['password']),
            'roles' => json_encode($requestData['roles']),
        ]);

        if (!$updated) {
            response()->exit(auth()->errors(), 422);
        }

        Cache::delete(self::CACHE_KEYS);
        response()->noContent();
    }

    /**
     * Función para actualizar los datos de un adulto desde la taquilla.
     * @TODO: Crear un controlador específico para los adultos.
     * @param mixed $id
     * @return void
     */
    public function patch($id) {
        $permission = Access::canAny(['users:managueall', 'users:managueself']);

        if (!$user = User::find($id)) {
            response()->exit(null, 404);
        }

        if ($permission === 'users:managueself') {
            $this->mustbeSelf($id);
        }

        // Comprobar que el adulto existe.
        if (!$adulto = Adulto::where('user_id', $id)->first()) {
            response()->exit(Err::get('ADULT_NOT_FOUND'), 404);
        }

        // Validar los datos recibidos.
        $requestData = $this->getItemData(request(), true, [
            'loginid' => 'string|min:3|max:20',
            'password' => 'optional|string|min:4|max:32',
            'adulto.DNI' => 'string|min:7|max:15',
            'adulto.nombre' => 'string|min:6|max:80',
            'adulto.email' => 'optional|email',
            'adulto.publi' => 'optional|boolean',
            'adulto.telefono' => 'optional|string|min:7|max:14',
            'adulto.estado' => 'in:[' . implode(',', self::ESTADO) . ']',
        ]);

        // Actualizar el usuario y el adulto.
        $user->update(['loginid' => $requestData['loginid']]);
        if (!empty($requestData['password'])) {
            $user->update(['password' => Password::hash($requestData['password'])]);
        }

        try {
            $adulto->update($requestData['adulto']);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        Cache::delete(self::CACHE_KEYS);
        response()->noContent();
    }

    public function delete($id) {
        Access::can('users:managueall');

        if (!$user = User::find($id)) {
            response()->exit(null, 404);
        }

        try {
            $user->delete();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        Cache::delete(self::CACHE_KEYS);
        response()->noContent();
    }

    /**
     * Comprueba si el usuario autenticado es el adulto.
     *
     * @param int $uid ID del usuario a comprobar.
     */
    private function mustbeSelf($uid) {
        if (auth()->user()->id() != $uid) {
            response()->exit(null, 403);
        }
    }
}
