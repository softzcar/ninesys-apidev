<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una operación de base de datos viola una restricción de
 * integridad referencial (clave foránea): errno MySQL 1451/1452/1216/1217.
 *
 * Forma parte de la "red de seguridad central" para la implementación de FKs:
 * en lugar de devolver un error silencioso (que la mayoría de los llamadores no
 * verifica), la violación se propaga hasta el manejador de errores de Slim
 * (HttpErrorHandler), que responde un HTTP 409 limpio y con mensaje claro.
 */
class DatabaseConstraintException extends RuntimeException
{
}
