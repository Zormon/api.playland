<?php

namespace App\Controllers;
use App\Models\User;
use App\Models\Adulto;
use Leaf\Http\Request;
use Lib\Err;
use Lib\Cache;


class UsersController extends Controller {
    /**
     * Responde con todos los usuarios de la plataforma.
     *
     */
    public function all() {
        // Si no puede ver todos los usuarios
        if (!auth()->user()->can('users:viewall')) {
            response()->plain(null, 403);
        }

        // Parámetros de la petición.
        $params = new \stdClass;
        $params->nocache = request()->get('nocache') ? true : false;
        $params->filter = request()->get('filter');
        $params->range = request()->get('range');


        $cacheKey = 'users:all/json';
        if ($params->filter) {
            // Si hay filtros especificados
            $cacheKey = 'users:filter-' . $params->filter . '/json';
            if ($params->nocache || !$userlist = Cache::get($cacheKey)) {
                $userlist = match ($params->filter) {
                    'adulto'    => json_encode(User::with('adulto')->has('adulto')->get()),
                    'noadulto'  => json_encode(User::doesntHave('adulto')->get()),
                    default     => response()->json(Err::get('GETUSER_INVALID_FILTER'), 400),
                };
                if ($userlist === null) { return; }
            }
        } else {
            // Si no hay filtros, devolver todos los usuarios.
            if ($params->nocache || !$userlist = Cache::get($cacheKey)) {
                $userlist = json_encode(User::with('adulto')->get());
            }
        }

        Cache::set($cacheKey, $userlist, 3600 * 24 * 30); // 30 días
        response()->withHeader('Content-Type', 'application/json');
        response()->custom($userlist, 200);
    }

    /**
     * Responde con un usuario específico.
     *
     * @param int $id ID del usuario a devolver.
     */
    public function get(int $id) {
        if (!( // Si no puede ver todos los usuario y está intentando ver otro o no puede ver su propio usuario
            auth()->user()->can('users:viewall')
            || (auth()->user()->can('users:viewself') && auth()->user()->id() == $id)
        )) {
            response()->plain(null, 403);
        }

        // Buscar el usuario.
        $user = User::with('adulto')->find($id);
        if (empty($user)) {
            response()->plain(null, 404);
        }
        response()->json($user);
    }

    /*
     * De momento, cualquier usuario puede crear usuarios (para el registro).
     * @TODO: Permitir solo a los usuarios con permiso 'users:create' crear usuarios y usar otra via para el registro.
     */
    public function create() {
        [$userData, $adultoData] = $this->getUserPostData(request());

        // Crear el usuario.
        $user = auth()->createUserFor([
            'loginid' => $userData['loginid'],
            'password' => $userData['password'],
        ]);

        // Si el usuario no se ha creado, devolver el motivo.
        if (!$user) {
            response()->json(auth()->errors(), 422);
            return;
        }

        // Asignar los roles al usuario.
        $user->assign($userData['roles']);

        // Si se ha enviado un adulto, crearlo.
        if ($adultoData) {
            $adulto = new Adulto($adultoData);
            $adulto->user_id = $user->id;
            $adulto->save();
        }

        response()->plain(null, 201);
    }

    public function update($id) {
        if (!( // Si no puede editar todos los usuarios y está intentando editar otro o no puede editar su propio usuario
            auth()->user()->can('users:editall')
            || (auth()->user()->can('users:editself') && auth()->user()->id() == $id)
        )) {
            response()->plain(null, 403);
        }

        $user = auth()->find($id);

        // Comprobar que existe el usuario.
        if (empty($user)) {
            response()->plain(null, 404);
            return;
        }

        [$userData, $adultoData] = $this->getUserPostData(request(), $id);

        // Actualizar el usuario.
        if (!empty($adultoData)) {
        }

        $updated = $user->update([
            'loginid' => $userData['loginid'],
            'password' => $userData['password'],
        ]);

        if (!$updated) {
            response()->json(auth()->errors(), 422);
            return;
        }
    }

    public function delete($id) {
        // Comprobar permiso.
        if (!auth()->user()->can('users:delete')) {
            response()->plain(null, 403);
        }

        // Comprobar que existe el usuario.
        $user = User::with('adulto')->find($id);
        if (empty($user)) {
            response()->plain(null, 404);
            return;
        }

        // Eliminar el usuario.
        $user->adulto->delete();
        $user->delete();
        response()->plain(null, 204);
    }

    /**
     * Comprueba los datos del usuario recibidos en la petición y los devuelve
     * Si se reciben datos de adulto, también se validan y se añade al array devuelto.
     *
     * @param Request $request Petición recibida.
     * @param int|null $exclude Id de usuario a excluir de la comprobación de duplicados (para actualizaciones).
     * @return array|bool Datos del usuario si son válidos, false si no lo son.
     * @comprobar si el usuario ya existe, y también usar throw en lugar de devolver false
     */
    private function getUserPostData(Request $request, ?int $exclude = null): array|bool {
        // Validar los datos recibidos.
        $userData = $request->validate([
            'loginid' => 'string|min:3',
            'password' => 'string|min:8',
            'roles' => 'array',
        ]);

        // Si los datos no son válidos, devolver error.
        if (!$userData) {
            response()->json(Err::get('REGISTER_INVALID_FIELDS'), 400);
            return false;
        }

        // Si ya existe el loginid, devolver error.
        $loginIdDuplicate = $exclude
            ? User::where('loginid', $userData['loginid'])->where('id', '!=', $exclude)->exists()
            : User::where('loginid', $userData['loginid'])->exists();
        if ($loginIdDuplicate) {
            response()->json(Err::get('REGISTER_LOGINID_EXISTS'), 409);
            return false;
        }

        // Comprobar que los roles recibidos existen.
        if (!empty(array_diff_key(array_flip($userData['roles']), auth()->roles()))) {
            response()->json(Err::get('REGISTER_INVALID_ROLES'), 400);
            return false;
        }

        // Si se envía DNI, se debe recibir todos los datos de un adulto.
        if ($request->get('DNI')) {
            $adulto = $request->validate([
                'DNI' => 'string|min:7|max:15',
                'nombre' => 'string|min:6',
                'email' => 'email',
                'publi' => 'boolean',
                'telefono' => 'optional|string|min:7|max:14',
            ]);

            // Si los datos de adulto no son válidos, devolver error.
            if (!$adulto) {
                response()->json(Err::get('REGISTER_INVALID_ADULT_FIELDS'), 400);
                return false;
            }

            // Si el DNI ya existe, devolver error.
            $dniDuplicate = $exclude
                ? Adulto::where('DNI', $adulto['DNI'])->where('user_id', '!=', $exclude)->exists()
                : Adulto::where('DNI', $adulto['DNI'])->exists();
            if ($dniDuplicate) {
                response()->json(Err::get('REGISTER_DNI_EXISTS'), 409);
                return false;
            }

            // Si el email ya existe, devolver error.
            $emailDuplicate = $exclude
                ? Adulto::where('email', $adulto['email'])->where('user_id', '!=', $exclude)->exists()
                : Adulto::where('email', $adulto['email'])->exists();
            if ($emailDuplicate) {
                response()->json(Err::get('REGISTER_EMAIL_EXISTS'), 409);
                return false;
            }

            $adulto['publi'] = $adulto['publi'] ? 1 : 0;
        }

        return [$userData, $adulto ?? null];
    }
}
