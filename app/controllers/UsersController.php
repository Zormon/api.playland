<?php

namespace App\Controllers;

use Leaf\Http\Request;
use Leaf\Helpers\Password;

use App\Models\User;
use App\Models\Adulto;

use Lib\Err;
use Lib\Cache;


class UsersController extends Controller {
    private const CACHE_KEY_ALL = 'users:all';
    private const CACHE_KEY_FILTER = 'users:filter-';

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

        $cacheKey = self::CACHE_KEY_ALL;
        if ($p_filter) {
            // Si hay filtros especificados
            $cacheKey = self::CACHE_KEY_FILTER . $p_filter;
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

    /**
     * Actualiza un usuario específico y limpia la caché.
     *
     * @param int $id The ID of the user to update.
     */
    public function update($id) {
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
        try {
            [$userData, $adultoData] = $this->getUserPostData(request(), $id);
        } catch (\Exception $e) {
            response()->exit(Err::get($e->getMessage()), $e->getCode());
        }

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

        Cache::delete(self::CACHE_KEY_ALL);
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
        response()->noContent();
    }

    /*
     * De momento, cualquier usuario puede crear usuarios (para el registro).
     * @TODO: Permitir solo a los usuarios con permiso 'users:create' crear usuarios y usar otra via para el registro.
     */
    public function create() {
        try {
            [$userData, $adultoData] = $this->getUserPostData(request());
        } catch (\Exception $e) {
            response()->exit(Err::get($e->getMessage()), $e->getCode());
        }

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

        Cache::delete(self::CACHE_KEY_ALL);
        response()->noContent();
    }

    /**
     * Comprueba los datos del usuario recibidos en la petición y los devuelve
     * Si se reciben datos de adulto, también se validan y se añade al array devuelto.
     *
     * @param Request $request Petición recibida.
     * @param int|null $exclude Id de usuario a excluir de la comprobación de duplicados (para actualizaciones).
     * @return array Datos del usuario si son válidos.
     * @throws \Exception Si los datos no son válidos.
     */
    private function getUserPostData(Request $request, ?int $exclude = null): array {
        // Validar los datos recibidos.
        $userData = $request->validate([
            'loginid' => 'string|min:3',
            'password' => 'string|min:8',
            'roles' => 'array<string>',
        ]);

        // Si los datos no son válidos, devolver error.
        if (!$userData) {
            throw new \Exception('INVALID_FIELDS', 400);
        }

        // Si ya existe el loginid, devolver error.
        $loginIdDuplicate = $exclude
            ? User::where('loginid', $userData['loginid'])->where('id', '!=', $exclude)->exists()
            : User::where('loginid', $userData['loginid'])->exists();
        if ($loginIdDuplicate) {
            throw new \Exception('USER_ALREADY_EXISTS', 409);
        }

        // Comprobar que los roles recibidos existen.
        if (!empty(array_diff_key(array_flip($userData['roles']), auth()->roles()))) {
            throw new \Exception('INVALID_ROLES', 400);
        }

        // Si se envía DNI, se debe recibir todos los datos de un adulto.
        if ($request->get('DNI')) {
            $adulto = $request->validate([
                'DNI' => 'string|min:7|max:15',
                'nombre' => 'string|min:6',
                'email' => 'email',
                'publi' => 'in:[true,false]',
                'telefono' => 'optional|string|min:7|max:14',
            ]);

            // Si los datos de adulto no son válidos, devolver error.
            if (!$adulto) {
                throw new \Exception('INVALID_FIELDS', 400);
            }

            // Si el DNI ya existe, devolver error.
            $dniDuplicate = $exclude
                ? Adulto::where('DNI', $adulto['DNI'])->where('user_id', '!=', $exclude)->exists()
                : Adulto::where('DNI', $adulto['DNI'])->exists();
            if ($dniDuplicate) {
                throw new \Exception('DNI_ALREADY_EXISTS', 409);
            }

            // Si el email ya existe, devolver error.
            $emailDuplicate = $exclude
                ? Adulto::where('email', $adulto['email'])->where('user_id', '!=', $exclude)->exists()
                : Adulto::where('email', $adulto['email'])->exists();
            if ($emailDuplicate) {
                throw new \Exception('EMAIL_ALREADY_EXISTS', 409);
            }

            $adulto['publi'] = $adulto['publi'] == 'true' ? 1 : 0;
        }

        return [$userData, $adulto ?? null];
    }
}
