<?php

namespace Lib;

class Access {
    public static function role(string $role) {
        if(!auth()->user()->hasRole($role)) {
            response()->exit(null, 403);
        }
    }

    /**
     * Check if the user has the permission.
     * Returns the permission if the user has it.
     * 
     * @param string $permission
     */
    public static function can($permission): ?string {
        if(!auth()->user()->can($permission)) {
            response()->exit(null, 403);
        }
        return $permission;
    }

    /**
     * Check if the user has any of the permissions.
     * Returns the first permission that the user has.
     * 
     * @param array $permissions
     */
    public static function canAny(array $permissions): ?string {
        foreach($permissions as $permission) {
            if (auth()->user()->can($permission)) {
                response()->next($permission);
                return $permission;
            }
        }
        response()->exit(null, 403);
    }
}
