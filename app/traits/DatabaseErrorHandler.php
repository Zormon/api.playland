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
        
        switch ($errorCode) {
            // Entrada duplicada
            case 1062:
                // Extraer el campo duplicado
                preg_match("/Duplicate entry '.*' for key '(.*)'/", $e->getMessage(), $matches);
                $duplicateField = $matches[1] ?? 'unknown';
                response()->exit([
                    'error' => 'duplicate_entry',
                    'field' => $duplicateField,
                ], 409);

            // Foreign key constraint violation (MySQL error 1451, 1452)
            case 1451: // DELETE - record in use by foreign key
            case 1452: // INSERT/UPDATE - foreign key constraint fails
                response()->exit([
                    'error' => 'foreign_key_constraint',
                ], 409);

            // Data too long for column (MySQL error 1406)
            case 1406:
                preg_match("/Data too long for column '(.*)' at/", $e->getMessage(), $matches);
                $field = $matches[1] ?? 'unknown';
                response()->exit([
                    'error' => 'data_too_long',
                    'field' => $field,
                ], 400);

            // Column cannot be null (MySQL error 1048)
            case 1048:
                preg_match("/Column '(.*)' cannot be null/", $e->getMessage(), $matches);
                $field = $matches[1] ?? 'unknown';
                response()->exit([
                    'error' => 'null_constraint',
                    'field' => $field,
                ], 400);

            // Incorrect integer value (MySQL error 1366)
            case 1366:
                response()->exit([
                    'error' => 'invalid_data_format',
                ], 400);
        }
    }
}