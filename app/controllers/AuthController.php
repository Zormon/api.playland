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
            response()->json(Err::get('INVALID_FIELDS'), 400);
            return;
        }

        $logged = auth()->login($loginData);
        if (!$logged) {
            response()->plain(null, 401);
            return;
        }

        $data = [
            'loginid' => auth()->user()->loginid,
            'role' => auth()->user()->roles()[0],
            'accessToken' => auth()->data()->accessToken,
        ];

        response()->json($data);
    }

    public function user() {
        $data = [
            'loginid' => auth()->user()->loginid,
            'role' => auth()->user()->roles()[0],
        ];
        response()->json($data);
    }
}
