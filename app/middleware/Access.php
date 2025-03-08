<?php

namespace App\Middleware;

class Access {
    public static function roles($roles) {
        if(!auth()->user()->hasRole($roles)) {
            response()->exit(null, 403);
        }
    }

    public static function can($permission) {
        if(!auth()->user()->can($permission)) {
            response()->exit(null, 403);
        }
    }
}
