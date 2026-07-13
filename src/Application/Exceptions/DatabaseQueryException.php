<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una operación de base de datos falla por un error SQL que
 * NO es una violación de FK (App\Application\Exceptions\DatabaseConstraintException,
 * -> 409) ni una violación de índice único (errno MySQL 1062 / SQLSTATE 23505 de
 * Postgres, que se preserva como retorno "suave" porque varios endpoints la usan
 * intencionalmente para mostrar mensajes de "ya existe").
 *
 * Cualquier otro error (sintaxis, columna inexistente, tipo de dato ambiguo, etc.)
 * indica un bug real, no una regla de negocio esperada. Antes de esta excepción,
 * goQuery() devolvía un array con status=error que la mayoría de los llamadores
 * no verifica, lo que podía traducirse en la app mostrando datos falsos o vacíos
 * sin que el usuario se enterara del fallo real. Se propaga hasta el manejador de
 * errores de Slim (HttpErrorHandler), que responde un HTTP 500 honesto.
 */
class DatabaseQueryException extends RuntimeException
{
}
