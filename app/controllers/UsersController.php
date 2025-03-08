<?php

namespace App\Controllers;

use Leaf\Http\Request;
use Leaf\Helpers\Password;

use App\Models\User;
use App\Models\Adulto;

use App\Interfaces\ItemController;
use Lib\Err;
use Lib\Cache;

/**
 * Controlador para la gestión de usuarios.
 * 
 * @todo: los adultos deberían ser gestionados por otro controlador con otro endpoint y tener los métodos PUT y PATCH adecuados.
 * Actualmente los adultos se actualizan con el método PATCH de este controlador.
 */
class UsersController extends Controller implements ItemController {
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

    /**
     * Responde con todos los usuarios de la plataforma.
     *
     */
    public function all() {
        // Si no puede ver todos los usuarios
        if (!auth()->user()->can('users:viewall')) {
            response()->exit(null, 403);
        }

        // Parámetros de la petición.
        $p_nocache = request()->get('nocache') ? true : false;
        $p_filter = request()->get('filter');

        $cacheKey = self::CACHE_KEYS['all'];
        if ($p_filter) {
            // Si hay filtros especificados
            $cacheKey = self::CACHE_KEYS[$p_filter] ?? null;
            if ($p_nocache || !$json = Cache::get($cacheKey)) {
                $json = match ($p_filter) {
                    'adulto'    => json_encode(User::with('adulto')->has('adulto')->get()),
                    'noadulto'  => json_encode(User::doesntHave('adulto')->get()),
                    default     => response()->exit(null, 400),
                };
                if ($json === null) { return; }
            }
        } else {
            // Si no hay filtros, devolver todos los usuarios.
            if ($p_nocache || !$json = Cache::get($cacheKey)) {
                $json = json_encode(User::with('adulto')->get());
            }
        }

        Cache::set($cacheKey, $json, 3600 * 24 * 30); // 30 días
        response()->withHeader('Content-Type', 'application/json');
        response()->custom($json, 200);
    }

    /**
     * Responde con un usuario específico.
     *
     * @param int $id ID del usuario a devolver.
     */
    public function get(int $id) {
        // Buscar el usuario.
        if (!$user = User::with('adulto')->find($id)) {
            response()->exit(null, 404);
        }

        // Si no puede ver todos los usuarios y no es el usuario que intenta ver
        if (auth()->user()->id() != $id && !auth()->user()->can('users:viewall')) {
            response()->exit(null, 403);
        }

        response()->json($user);
    }

    /*
     * De momento, cualquier usuario puede crear usuarios (para el registro).
     * @TODO: Permitir solo a los usuarios con permiso 'users:create' crear usuarios y usar otra via para el registro.
     */
    public function create() {
        [$userData, $adultoData] = $this->getPostData(request());

        // Crear el usuario.
        $user = auth()->createUserFor([
            'loginid' => $userData['loginid'],
            'password' => $userData['password'],
        ]);

        // Si el usuario no se ha creado, devolver el motivo.
        if (!$user) {
            response()->exit(auth()->errors(), 422);
        }

        // Asignar los roles al usuario.
        $user->assign($userData['roles']);

        // Si se ha enviado un adulto, crearlo.
        if ($adultoData) {
            $adulto = new Adulto($adultoData);
            $adulto->user_id = $user->id;
            $adulto->save();
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
        // Buscar el usuario.
        if (!$user = User::find($id)) {
            response()->exit(null, 404);
        }

        // Si no puede editar todos los usuarios y está intentando editar otro o no puede editar su propio usuario
        if (!( auth()->user()->can('users:editall')
            || (auth()->user()->can('users:editself') && auth()->user()->id() == $id)
        )) {
            response()->exit(null, 403);
        }

        // Validar los datos recibidos.
        [$userData, $adultoData] = $this->getPostData(request(), $id);

        // Actualizar el adulto, si se ha enviado.
        if (!empty($adultoData)) {
            if (!$adulto = Adulto::where('user_id', $id)->first()) {
                response()->exit(Err::get('ADULT_NOT_FOUND'), 404);
            }

            $adulto->update($adultoData);
        }

        // Actualizar el usuario.
        $updated = $user->update([
            'loginid' => $userData['loginid'],
            'password' => Password::hash($userData['password']),
            'roles' => json_encode($userData['roles']),
        ]);

        // TODO: Reasignar los roles enviados.

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
        // Buscar el usuario.
        if (!$user = User::find($id)) {
            response()->exit(null, 404);
        }

        // Comprobar que el adulto existe.
        if (!$adulto = Adulto::where('user_id', $id)->first()) {
            response()->exit(Err::get('ADULT_NOT_FOUND'), 404);
        }

        // Si no puede editar todos los usuarios y está intentando editar otro o no puede editar su propio usuario
        if (!( auth()->user()->can('users:editall')
            || (auth()->user()->can('users:editself') && auth()->user()->id() == $id)
        )) {
            response()->exit(null, 403);
        }

        // Validar los datos recibidos.
        $postData = request()->validate([
            'loginid' => 'string|min:3|max:20',
            'password' => 'optional|string|min:4|max:32',
            'adulto.DNI' => 'string|min:7|max:15',
            'adulto.nombre' => 'string|min:6|max:80',
            'adulto.email' => 'optional|email',
            'adulto.publi' => 'optional|boolean',
            'adulto.telefono' => 'optional|string|min:7|max:14',
            'adulto.estado' => 'in:[' . implode(',', self::ESTADO) . ']',
        ]);

        // Si los datos no son válidos, devolver error.
        if (!$postData) {
            if ( app()->config('debug') == 'true' ) {
                response()->exit(request()->errors(), 400);
            }
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }

        // Actualizar el usuario y el adulto.
        $user->update(['loginid' => $postData['loginid']]);
        if (!empty($postData['password'])) {
            $user->update(['password' => Password::hash($postData['password'])]);
        }

        // Si no se envia email o telefono, se establecen a vacio (esto debería hacerse en el método PUT...).
        $postData['adulto']['email'] ??= '';
        $postData['adulto']['telefono'] ??= '';

        $adulto->update($postData['adulto']);

        Cache::delete(self::CACHE_KEYS);
        response()->noContent();
    }

    public function delete($id) {
        // Buscar el usuario.
        if (!$user = User::find($id)) {
            response()->exit(null, 404);
        }

        // Comprobar permiso.
        if (!auth()->user()->can('users:delete')) {
            response()->exit(null, 403);
        }

        $user->delete();
        Cache::delete(self::CACHE_KEYS);
        response()->noContent();
    }

    private function getPostData(Request $request, ?int $exclude = null): array {
        // Validar los datos recibidos.
        $reqBody = $request->validate([
            'loginid' => 'string|min:3|max:20',
            'password' => 'string|min:4|max:32',
            'roles' => 'optional|array<string>',
        ]);

        // Si los datos no son válidos, devolver error.
        if (!$reqBody) {
            if ( app()->config('debug') == 'true' ) {
                response()->exit(request()->errors(), 400);
            }
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }

        // Si ya existe el loginid, devolver error.
        $loginIdDuplicate = $exclude
            ? User::where('loginid', $reqBody['loginid'])->where('id', '!=', $exclude)->exists()
            : User::where('loginid', $reqBody['loginid'])->exists();
        if ($loginIdDuplicate) {
            response()->exit(Err::get('USER_ALREADY_EXISTS'), 409);
        }

        if (empty($reqBody['roles'])) {
            $reqBody['roles'] = ['adulto'];
        // Comprobar que los roles recibidos existen si se han enviado.
        } else if (!empty(array_diff_key(array_flip($reqBody['roles']), auth()->roles()))) {
            response()->exit(Err::get('INVALID_ROLES'), 400);
        }

        // Si se envía adulto, validar los datos.
        if ($request->get('adulto')) {
            $adulto = $request->validate([
                'adulto.DNI' => 'string|min:7|max:15',
                'adulto.nombre' => 'string|min:6',
                'adulto.email' => 'email',
                'adulto.publi' => 'optional|boolean',
                'adulto.telefono' => 'optional|string|min:7|max:14',
            ]);

            // Si los datos de adulto no son válidos, devolver error.
            if (!$adulto) {
                if ( app()->config('debug') == 'true' ) {
                    response()->exit(request()->errors(), 400);
                }
                response()->exit(Err::get('INVALID_FIELDS'), 400);
            }
            $adulto = $adulto['adulto'];

            // Si el DNI ya existe, devolver error.
            $dniDuplicate = $exclude
                ? Adulto::where('DNI', $adulto['DNI'])->where('user_id', '!=', $exclude)->exists()
                : Adulto::where('DNI', $adulto['DNI'])->exists();
            if ($dniDuplicate) {
                response()->exit(Err::get('DNI_ALREADY_EXISTS'), 409);
            }

            // Si el email ya existe, devolver error.
            $emailDuplicate = $exclude
                ? Adulto::where('email', $adulto['email'])->where('user_id', '!=', $exclude)->exists()
                : Adulto::where('email', $adulto['email'])->exists();
            if ($emailDuplicate) {
                response()->exit(Err::get('EMAIL_ALREADY_EXISTS'), 409);
            }
        }

        return [$reqBody, $adulto ?? null];
    }
}
