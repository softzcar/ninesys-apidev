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
