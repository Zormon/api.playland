<?php

namespace App\Controllers;
use Lib\Err;

class AuthController extends Controller {
    public function login() {
        $loginData = request()->validate([
            'loginid' => 'string|min:3',
            'password' => 'string',
          ]);
        if (!$loginData) {
            response()->json(Err::get('LOGIN_MISSING_FIELDS'), 400);
            return;
        }

        $logged = auth()->login($loginData);
        if (!$logged) {
            response()->plain(null, 401);
            return;
        }

        $authdata = auth()->data();
        unset($authdata->roles);
        response()->json($authdata);
    }
}
