<?php

namespace App\traits;

use Illuminate\Database\QueryException;

trait DatabaseErrorHandler {
    /**
     * Maneja los errores comunes de base de datos y retorna respuestas adecuadas
     * 
     * @param QueryException $e La excepción lanzada por la base de datos
     * @todo Implementar más casos de error y terminar de documentar
     */
    protected function handleDatabaseError(QueryException $e): void {
        // Obtener el código de error específico del driver MySQL
        $errorCode = $e->errorInfo[1] ?? 0;

        $response = ['code' => 0, 'body' => []];
        switch ($errorCode) {
            // Entrada duplicada
            case 1062:
                // Extraer el campo duplicado
                preg_match("/Duplicate entry '.*' for key '(.*)'/", $e->getMessage(), $matches);
                $duplicateField = $matches[1] ?? 'unknown';
                $response = [
                    'code' => 409,
                    'body' => [
                        'error' => 'duplicate_entry',
                        'field' => $duplicateField,
                    ]
                ];
                break;

            // Foreign key constraint violation (MySQL error 1451, 1452)
            case 1451: // DELETE - record in use by foreign key
            case 1452: // INSERT/UPDATE - foreign key constraint fails
                // Extraer el nombre de la clave foránea
                preg_match("/FOREIGN KEY \(`(.*)`\) REFERENCES/", $e->getMessage(), $matches);
                $foreignKey = $matches[1] ?? 'unknown';
                $response = [
                    'code' => 409,
                    'body' => [
                        'error' => 'foreign_key_violation',
                        'field' => $foreignKey,
                    ]
                ];
                break;

            // Data too long for column (MySQL error 1406)
            case 1406:
                preg_match("/Data too long for column '(.*)' at/", $e->getMessage(), $matches);
                $field = $matches[1] ?? 'unknown';
                $response = [
                    'code' => 400,
                    'body' => [
                        'error' => 'data_too_long',
                        'field' => $field,
                    ]
                ];
                break;

            // Column cannot be null (MySQL error 1048)
            case 1048:
                preg_match("/Column '(.*)' cannot be null/", $e->getMessage(), $matches);
                $field = $matches[1] ?? 'unknown';
                $response = [
                    'code' => 400,
                    'body' => [
                        'error' => 'required_field',
                        'field' => $field,
                    ]
                ];
                break;

            // Incorrect integer value (MySQL error 1366)
            case 1366:
                $response = [
                    'code' => 400,
                    'body' => [
                        'error' => 'incorrect_integer_value',
                    ]
                ];
                break;

            // Unknown error
            default:
                $response = [
                    'code' => 500,
                    'body' => [
                        'error' => 'database_error',
                    ]
                ];
                break;
        }

        if ( app()->config('debug') == 'true' ) {
            $response['body']['debug'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ];
        }

        response()->json($response['body'], $response['code']);
    }
}