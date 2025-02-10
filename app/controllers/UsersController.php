<?php

namespace App\Controllers;
use \Leaf\Http\Request;
use App\Models\User;
use App\Models\Adulto;
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
        $userlist = Cache::get('allusers:json');
        if (!$userlist) {
            $userlist = json_encode(User::with('adulto')->get());
            Cache::set('allusers:json', $userlist, 3600 * 24 * 30); // 30 días
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($userlist, 200);
        
    }

    /**
     * Responde con un usuario específico.
     *
     * @param int $id ID del usuario a devolver.
     */
    public function get(int $id) {
        // Si no puede ver todos los usuario y está intentando ver otro o no puede ver su propio usuario
        if (!(
            auth()->user()->can('users:viewall')
            || (auth()->user()->can('users:viewself') && auth()->user()->id() == $id)
        )) {
            response()->plain(null, 403);
        }

        $user = User::with('adulto')->find($id);
        if (!$user) {
            response()->plain(null, 404);
        }
        response()->json($user);
    }

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
        $this->getUserPostData(request());
    }

    public function delete($id) {

    }

    /**
     * Comprueba los datos del usuario recibidos en la petición y los devuelve
     * Si se reciben datos de adulto, también se validan y se añade al array devuelto.
     *
     * @param Request $request Petición recibida.
     * @return array|bool Datos del usuario si son válidos, false si no lo son.
     * @comprobar si el usuario ya existe, y también usar throw en lugar de devolver false
     */
    private function getUserPostData(Request $request): array|bool {
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
        if (User::where('loginid', $userData['loginid'])->exists()) {
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
            if (Adulto::where('DNI', $adulto['DNI'])->exists()) {
                response()->json(Err::get('REGISTER_DNI_EXISTS'), 409);
                return false;
            }

            // Si el email ya existe, devolver error.
            if (Adulto::where('email', $adulto['email'])->exists()) {
                response()->json(Err::get('REGISTER_EMAIL_EXISTS'), 409);
                return false;
            }

            $adulto['publi'] = $adulto['publi'] ? 1 : 0;

        }

        return [$userData, $adulto ?? null];
    }
}
