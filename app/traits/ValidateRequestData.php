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
    /**
     * Obtiene los datos de la petición y los valida.
     * 
     * @param Request $request
     * @param bool $stringOptionals Si se deben llenar los campos opcionales con cadenas vacías.
     * @param array|null $fields Los campos a validar. Si no se pasan, se usan los campos del controlador.
     * @return array
     */
    protected function getItemData(Request $request, bool $stringOptionals = false, ?array $fields = null): array {
        // Si no se pasan campos, usar los campos del controlador.
        if ($fields === null) {
            $fields = $this->fields;
        }

        // Validar los datos de la petición.
        $data = $request->validate($fields ?? []);

        // Si los datos no son válidos, devolver error.
        if (!$data) {
            if ( app()->config('debug') == 'true' ) {
                response()->exit(request()->errors(), 400);
            }
            response()->exit(Err::get('INVALID_FIELDS'), 400);
        }

        // Llenar los campos opcionales.
        if ($stringOptionals) {
            foreach ($fields as $field => $rules) {
                $value = $this->getArrayValueByPath($data, $field);
                if (explode('|', $rules)[0] === 'optional' && $value === null) {
                    $data = $this->setArrayValueByPath($data, $field, '');
                }
            }
        }

        return $data;
    }
    
    /**
     * Gets a value from an array using dot notation
     * 
     * @param array $array The array to search in
     * @param string $path The path to the value using dot notation
     * @return mixed|null The value or null if not found
     */
    private function getArrayValueByPath(array $array, string $path) {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Sets a value in an array using dot notation
     * 
     * @param array $array The array to modify
     * @param string $path The path to the value using dot notation
     * @param mixed $value The value to set
     * @return array The modified array
     */
    private function setArrayValueByPath(array $array, string $path, $value): array {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;

        return $array;
    }
}