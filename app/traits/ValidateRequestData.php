<?php

namespace App\traits;

use Leaf\Http\Request;
use Lib\Err;

/**
 * Trait ValidateRequestData
 * 
 * Este trait espera que el controlador que lo use tenga una propiedad $fields
 */
trait ValidateRequestData {
    protected function getItemData(Request $request): array {
        // Validar los datos de la petición.
        $data = $request->validate($this->fields ?? []);

        // Si los datos no son válidos, devolver error.
        if (!$data) {
            if ( app()->config('debug') == 'true' ) {
                response()->exit(request()->errors(), 400);
            }
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }

        return $data;
    }
}