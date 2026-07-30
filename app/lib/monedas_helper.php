<?php

/**
 * Servicio compartido de resolución de IDs de moneda/método de pago
 * (Fase 6 del rediseño de monedas/métodos de pago).
 *
 * Dado el string histórico de moneda (y opcionalmente de método de pago) que
 * ya se escribe hoy en metodos_de_pago/caja/retiros, resuelve los _id reales
 * de catalogo_monedas/catalogo_metodos_pago de la empresa (misma conexión ya
 * usada por el endpoint que llama), para poder escribirlos como dual-write
 * junto a las columnas de texto existentes -- sin reemplazarlas.
 *
 * Nunca lanza excepción: si catalogo_monedas no existe todavía (empresa no
 * migrada a la Fase 2) o el nombre no coincide con ninguna fila (ej. una
 * variante sin tilde ya conocida como "Dolares"/"Banesco Panama" en
 * convertir-a-orden), devuelve [null, null] -- el caller sigue escribiendo la
 * columna de texto exactamente igual que hoy, solo la columna FK queda vacía.
 *
 * @param LocalDB $localConnection Conexión ya abierta a la base de la empresa.
 * @param string $nombreMoneda Valor exacto de la columna `moneda` (ej. "Dólares").
 * @param string|null $nombreMetodo Valor exacto de la columna `metodo_pago` (ej. "Efectivo"), opcional.
 * @return array{0: int|null, 1: int|null} [id_moneda, id_metodo_pago]
 */
function resolverIdsMonedaMetodo($localConnection, $nombreMoneda, $nombreMetodo = null)
{
  $idMoneda = null;
  $idMetodoPago = null;

  try {
    $monedaResult = $localConnection->goQuery(
      'SELECT _id FROM catalogo_monedas WHERE nombre = ? AND eliminado = 0 LIMIT 1',
      [$nombreMoneda]
    );

    if (!empty($monedaResult)) {
      $idMoneda = (int) $monedaResult[0]['_id'];

      if ($nombreMetodo !== null && $nombreMetodo !== '') {
        $metodoResult = $localConnection->goQuery(
          'SELECT _id FROM catalogo_metodos_pago WHERE id_moneda = ? AND nombre = ? AND eliminado = 0 LIMIT 1',
          [$idMoneda, $nombreMetodo]
        );

        if (!empty($metodoResult)) {
          $idMetodoPago = (int) $metodoResult[0]['_id'];
        }
      }
    }
  } catch (\Exception $e) {
    // Empresa aún no migrada a catalogo_monedas (Fase 2) -- no es un error real.
  }

  return [$idMoneda, $idMetodoPago];
}

/**
 * Servicio genérico de registro de pagos (Fase 7 del rediseño de monedas).
 *
 * Resuelve un método de pago por su _id REAL de catalogo_metodos_pago (no por
 * nombre/string), junto con los datos de su moneda -- para que el código que
 * registra un pago nunca necesite un literal de moneda/método hardcodeado
 * ("Dólares", "Bolívares", etc.). Esto es lo que permite que una empresa
 * agregue una moneda o método nuevo desde el Gestor de Monedas y el sistema
 * pueda registrar pagos con ella sin ningún cambio de código futuro.
 *
 * @param LocalDB $localConnection Conexión ya abierta a la base de la empresa.
 * @param int $idMetodoPago _id de catalogo_metodos_pago.
 * @return array|null {id_metodo_pago, metodo_nombre, requiere_referencia, es_efectivo, id_moneda, moneda_codigo, moneda_nombre} o null si no existe, está eliminado, o pertenece a una moneda eliminada.
 */
function resolverMetodoPagoPorId($localConnection, $idMetodoPago)
{
  $idMetodoPago = (int) $idMetodoPago;
  if ($idMetodoPago <= 0) {
    return null;
  }

  $result = $localConnection->goQuery(
    'SELECT cmp._id AS id_metodo_pago, cmp.nombre AS metodo_nombre, cmp.requiere_referencia, cmp.es_efectivo,
            cm._id AS id_moneda, cm.codigo AS moneda_codigo, cm.nombre AS moneda_nombre
     FROM catalogo_metodos_pago cmp
     JOIN catalogo_monedas cm ON cm._id = cmp.id_moneda
     WHERE cmp._id = ? AND cmp.eliminado = 0 AND cm.eliminado = 0',
    [$idMetodoPago]
  );

  return !empty($result) ? $result[0] : null;
}

/**
 * Inserta una fila en metodos_de_pago a partir de un método ya resuelto por
 * resolverMetodoPagoPorId() -- sin ningún literal de moneda/método.
 */
function insertarMetodoPagoGenerico($localConnection, $idOrden, $tipoDePago, $metodo, $monto, $detalle, $tasa)
{
  $sql = 'INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
  return $localConnection->goQuery($sql, [
    $idOrden,
    $tipoDePago,
    $metodo['moneda_nombre'],
    $metodo['metodo_nombre'],
    $monto,
    $detalle,
    $tasa,
    $metodo['id_moneda'],
    $metodo['id_metodo_pago'],
  ]);
}

/**
 * Inserta el movimiento de caja correspondiente a un pago en efectivo (solo
 * cuando el método resuelto tiene es_efectivo=1 -- los medios electrónicos o
 * de terceros nunca generan movimiento de caja física).
 */
function insertarCajaGenerico($localConnection, $monto, $metodo, $tasa, $tipo, $idEmpleado, $detalle)
{
  $sql = 'INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle, id_moneda) VALUES (?, ?, ?, ?, ?, ?, ?)';
  return $localConnection->goQuery($sql, [
    $monto,
    $metodo['moneda_nombre'],
    $tasa,
    $tipo,
    $idEmpleado,
    $detalle,
    $metodo['id_moneda'],
  ]);
}

/**
 * Inserta una fila en retiros a partir de un método ya resuelto por
 * resolverMetodoPagoPorId() -- sin ningún literal de moneda/método.
 */
function insertarRetiroGenerico($localConnection, $idEmpleado, $metodo, $monto, $detalle, $tasa)
{
  $sql = 'INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
  return $localConnection->goQuery($sql, [
    $idEmpleado,
    $metodo['moneda_nombre'],
    $metodo['metodo_nombre'],
    $monto,
    $detalle,
    $tasa,
    $metodo['id_moneda'],
    $metodo['id_metodo_pago'],
  ]);
}

/**
 * Decodifica el parámetro `pagos` (array genérico de pagos, Fase 7) que puede
 * llegar como array real (JSON con Content-Type correcto) o como string JSON
 * (form-urlencoded). Devuelve [] si no hay pagos genéricos -- el caller debe
 * usar eso como señal para caer al camino legado (formulario aún no migrado).
 */
function decodificarPagosGenericos($data)
{
  if (empty($data['pagos'])) {
    return [];
  }

  $decoded = is_string($data['pagos']) ? json_decode($data['pagos'], true) : $data['pagos'];
  return is_array($decoded) ? $decoded : [];
}
