<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

  // Estrategias de obtención de tasa por código de moneda (Fase 3 del rediseño de
  // monedas/métodos de pago). Cada estrategia normaliza su resultado al mismo
  // contrato {tasa, fuente, fecha} sin importar la tecnología usada para obtenerla
  // (scraping, API de terceros, etc.) -- el consumidor final nunca necesita saberlo.
  // No tocan ni comparten caché con GET /bcv-rates (routes.php) para no arriesgar
  // ese endpoint ya en uso -- fuentes de datos iguales, implementación independiente.

  // VES: mismo mecanismo que GET /bcv-rates (scrape a bcv.org.ve con fallback a
  // dolarapi.com), para que la tasa resultante sea idéntica a la que ya se usa hoy.
  $obtenerTasaVES = function () {
    $cacheFile = sys_get_temp_dir() . '/ninesys_tasa_ves.json';
    $cacheTtl = 600; // 10 minutos
    if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
      $cached = json_decode(file_get_contents($cacheFile), true);
      if ($cached) {
        return $cached;
      }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.bcv.org.ve/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
      'Accept-Language: es-VE,es;q=0.9,en;q=0.8',
      'Referer: https://www.bcv.org.ve/',
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tasa = null;
    $fuente = null;

    if ($code < 400 && $html) {
      $dom = new DOMDocument();
      libxml_use_internal_errors(true);
      $dom->loadHTML($html);
      libxml_clear_errors();
      libxml_use_internal_errors(false);
      $xpath = new DOMXPath($dom);
      $nodes = $xpath->query('//*[@id="dolar"]//strong');
      if ($nodes && $nodes->length > 0) {
        $raw = trim($nodes->item(0)->textContent);
        if ($raw !== '') {
          $cleaned = preg_replace('/[^\d,.-]/', '', $raw);
          $cleaned = str_replace('.', '', $cleaned);
          $cleaned = str_replace(',', '.', $cleaned);
          if (is_numeric($cleaned) && floatval($cleaned) > 0) {
            $tasa = floatval($cleaned);
            $fuente = 'bcv_oficial';
          }
        }
      }
    }

    if ($tasa === null) {
      // Fallback: DolarAPI oficial (mismo fallback que GET /bcv-rates)
      $chFallback = curl_init();
      curl_setopt($chFallback, CURLOPT_URL, 'https://ve.dolarapi.com/v1/dolares/oficial');
      curl_setopt($chFallback, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($chFallback, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($chFallback, CURLOPT_TIMEOUT, 5);
      $fbResult = curl_exec($chFallback);
      $fbCode = curl_getinfo($chFallback, CURLINFO_HTTP_CODE);
      curl_close($chFallback);

      if ($fbCode === 200 && $fbResult) {
        $data = json_decode($fbResult, true);
        if (isset($data['promedio']) && $data['promedio'] > 0) {
          $tasa = (float) $data['promedio'];
          $fuente = 'dolarapi_fallback';
        }
      }
    }

    if ($tasa === null) {
      return null;
    }

    $resultado = ['tasa' => $tasa, 'fuente' => $fuente, 'fecha' => date('c')];
    @file_put_contents($cacheFile, json_encode($resultado));
    return $resultado;
  };

  // COP: misma fuente que hoy usa directamente el frontend (jsdelivr currency-api),
  // movida al backend para que el navegador deje de llamar 3 URLs externas fijas.
  $obtenerTasaCOP = function () {
    $cacheFile = sys_get_temp_dir() . '/ninesys_tasa_cop.json';
    $cacheTtl = 600; // 10 minutos
    if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
      $cached = json_decode(file_get_contents($cacheFile), true);
      if ($cached) {
        return $cached;
      }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$result) {
      return null;
    }

    $data = json_decode($result, true);
    $tasa = $data['usd']['cop'] ?? null;
    if (!$tasa || $tasa <= 0) {
      return null;
    }

    $resultado = ['tasa' => (float) $tasa, 'fuente' => 'jsdelivr_currency_api', 'fecha' => date('c')];
    @file_put_contents($cacheFile, json_encode($resultado));
    return $resultado;
  };

  $estrategiasPorCodigo = [
    'VES' => $obtenerTasaVES,
    'COP' => $obtenerTasaCOP,
  ];

  // GET /tasas-cambio -- tasas normalizadas SOLO para las monedas activas de la
  // empresa autenticada (catalogo_monedas), sin importar de dónde vinieron. La
  // moneda base de la empresa siempre reporta tasa=1. Generaliza el propósito de
  // GET /bcv-rates (que se deja intacto, sigue siendo consumido por
  // plugins/currency-rates.js) para que el frontend pueda, en una fase posterior,
  // dejar de llamar URLs externas fijas y en su lugar pedir "las tasas que le
  // corresponden a mi empresa".
  $app->get('/tasas-cambio', function (Request $request, Response $response) use ($estrategiasPorCodigo) {
    if (!defined('ID_EMPRESA') || !ID_EMPRESA) {
      $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    try {
      $localConnection = new LocalDB();
      $monedas = $localConnection->goQuery('SELECT codigo, es_base FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0 ORDER BY es_base DESC');
      $localConnection->disconnect();
    } catch (Exception $e) {
      // catalogo_monedas todavía no existe en empresas no migradas a la Fase 2 --
      // no es un error real, simplemente no hay monedas configuradas que resolver.
      $response->getBody()->write(json_encode(['data' => []]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $data = [];
    foreach ($monedas as $moneda) {
      $codigo = $moneda['codigo'];

      if ((int) $moneda['es_base'] === 1) {
        $data[] = ['codigo' => $codigo, 'tasa' => 1.0, 'fuente' => 'base', 'fecha' => date('c')];
        continue;
      }

      if (isset($estrategiasPorCodigo[$codigo])) {
        $resultado = $estrategiasPorCodigo[$codigo]();
        if ($resultado !== null) {
          $data[] = array_merge(['codigo' => $codigo], $resultado);
        }
      }
    }

    $response->getBody()->write(json_encode(['data' => $data]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withStatus(200);
  });
};
