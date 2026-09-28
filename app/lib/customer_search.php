<?php

/**
 * Búsqueda de clientes por nombre completo (multi-palabra) + teléfono/cédula.
 *
 * Problema que resuelve: nombres y apellidos se guardan en columnas separadas
 * (first_name / last_name) y a veces un campo trae varias palabras
 * ("Jose Luis" / "Carrero"). Comparar cada columna por separado contra el término
 * completo hace que "Ozcar vela" no encuentre a first_name='Ozcar', last_name='Vela'.
 *
 * Estrategia: tokenizar el término y exigir que CADA palabra aparezca en el
 * nombre completo concatenado (first_name + ' ' + last_name), en cualquier orden.
 * En Postgres se envuelve con unaccent() para ignorar tildes (citext ya cubre
 * mayúsculas/minúsculas). El término completo también se busca en phone/cedula
 * para consultas numéricas.
 *
 * @param string $buscar  término de búsqueda
 * @param string $alias   prefijo de columna con punto, ej. 'c.' o '' (sin alias)
 * @param string $driver  'pgsql' | 'mysql'
 * @return array{0:string,1:array} [whereSql, params]. whereSql ya viene entre
 *         paréntesis y SIN el " AND " inicial; '' y [] si el término está vacío.
 */
function ninesys_customer_search_where($buscar, $alias = '', $driver = 'pgsql')
{
    $buscar = trim((string) $buscar);
    if ($buscar === '') {
        return ['', []];
    }

    $isPg = ($driver === 'pgsql');
    $op = $isPg ? 'ILIKE' : 'LIKE';

    $fullname = "CONCAT({$alias}first_name, ' ', COALESCE({$alias}last_name, ''))";
    $fullnameExpr = $isPg ? "unaccent({$fullname})" : $fullname;

    // Tokens (máx 6) para el AND sobre el nombre completo.
    $tokens = preg_split('/\s+/', $buscar, -1, PREG_SPLIT_NO_EMPTY);
    $tokens = array_slice($tokens, 0, 6);

    $conds = [];
    $params = [];
    foreach ($tokens as $t) {
        $conds[] = $isPg ? "{$fullnameExpr} {$op} unaccent(?)" : "{$fullnameExpr} {$op} ?";
        $params[] = '%' . $t . '%';
    }
    $nameClause = '(' . implode(' AND ', $conds) . ')';

    // Teléfono / cédula con el término completo (búsquedas numéricas).
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';

    $where = "({$nameClause} OR {$alias}phone {$op} ? OR {$alias}cedula {$op} ?)";
    return [$where, $params];
}
