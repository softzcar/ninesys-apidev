<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

  // Estrategias de obtención de tasa por código de moneda (Fase 3 del rediseño de
  // monedas/métodos de pago). Cada estrategia normaliza su resultado al mismo
  // contrato {tasa, fuente, fecha} sin importar la tecnología usada para obtenerla
  // (scraping, API de terceros, etc.) -- el consumidor final nunca necesita saberlo.
  // Convención (importante): toda estrategia automática devuelve la tasa
  // expresada "unidades de esa moneda por 1 USD" -- un ancla fija, sin
  // importar cuál sea la moneda base real de la empresa. GET /tasas-cambio
  // es quien, más abajo, triangula ese valor contra la moneda base actual.
  // Se eligió USD como ancla porque es lo que ya publican las fuentes
  // externas usadas (BCV cotiza en bolívares por dólar, jsdelivr cotiza en
  // pesos por dólar) -- normalizar aquí evita que cada estrategia tenga que
  // saber nada sobre la empresa que la está consultando.
  // No tocan ni comparten caché con GET /bcv-rates (routes.php) para no arriesgar
  // ese endpoint ya en uso -- fuentes de datos iguales, implementación independiente.

  // El BCV publica, en la misma página, la cotización de varias divisas
  // frente al bolívar (dólar, euro, yuan, lira, rublo). Se scrapea una sola
  // vez y se cachean juntas -- evita pedidos duplicados y evita que dólar y
  // euro puedan quedar leídos en momentos distintos entre sí.
  $obtenerCotizacionesBCV = function () {
    $cacheFile = sys_get_temp_dir() . '/ninesys_cotizaciones_bcv.json';
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

    $vesPorUsd = null;
    $vesPorEur = null;
    $fuente = null;

    if ($code < 400 && $html) {
      $dom = new DOMDocument();
      libxml_use_internal_errors(true);
      $dom->loadHTML($html);
      libxml_clear_errors();
      libxml_use_internal_errors(false);
      $xpath = new DOMXPath($dom);

      $leerNodo = function ($id) use ($xpath) {
        $nodes = $xpath->query('//*[@id="' . $id . '"]//strong');
        if (!$nodes || $nodes->length === 0) {
          return null;
        }
        $raw = trim($nodes->item(0)->textContent);
        if ($raw === '') {
          return null;
        }
        $cleaned = preg_replace('/[^\d,.-]/', '', $raw);
        $cleaned = str_replace('.', '', $cleaned);
        $cleaned = str_replace(',', '.', $cleaned);
        return (is_numeric($cleaned) && floatval($cleaned) > 0) ? floatval($cleaned) : null;
      };

      $vesPorUsd = $leerNodo('dolar');
      $vesPorEur = $leerNodo('euro');
      if ($vesPorUsd !== null) {
        $fuente = 'bcv_oficial';
      }
    }

    if ($vesPorUsd === null) {
      // Fallback: DolarAPI oficial (mismo fallback que GET /bcv-rates). Solo
      // cubre el dólar -- si el BCV está caído, el euro queda sin resolver
      // por esta vía (cae a tasa manual o "sin_configurar" más abajo).
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
          $vesPorUsd = (float) $data['promedio'];
          $fuente = 'dolarapi_fallback';
        }
      }
    }

    if ($vesPorUsd === null) {
      return null;
    }

    $resultado = [
      'ves_por_usd' => $vesPorUsd,
      'ves_por_eur' => $vesPorEur,
      'fuente' => $fuente,
      'fecha' => date('c'),
    ];
    @file_put_contents($cacheFile, json_encode($resultado));
    return $resultado;
  };

  // VES: mismo mecanismo que GET /bcv-rates, para que la tasa resultante sea
  // idéntica a la que ya se usa hoy. Ya viene expresada "VES por 1 USD".
  $obtenerTasaVES = function () use ($obtenerCotizacionesBCV) {
    $cot = $obtenerCotizacionesBCV();
    if (!$cot || $cot['ves_por_usd'] === null) {
      return null;
    }
    return ['tasa' => $cot['ves_por_usd'], 'fuente' => $cot['fuente'], 'fecha' => $cot['fecha']];
  };

  // EUR: el BCV publica euro cotizado en bolívares, no en dólares -- se
  // triangula a través del bolívar (mismo momento, misma página) para
  // obtener "EUR por 1 USD", que es el contrato común de todas las
  // estrategias automáticas.
  $obtenerTasaEUR = function () use ($obtenerCotizacionesBCV) {
    $cot = $obtenerCotizacionesBCV();
    if (!$cot || $cot['ves_por_usd'] === null || $cot['ves_por_eur'] === null) {
      return null;
    }
    $eurPorUsd = $cot['ves_por_usd'] / $cot['ves_por_eur'];
    return ['tasa' => $eurPorUsd, 'fuente' => $cot['fuente'], 'fecha' => $cot['fecha']];
  };

  // COP: misma fuente que hoy usa directamente el frontend (jsdelivr currency-api),
  // movida al backend para que el navegador deje de llamar 3 URLs externas fijas.
  // Ya viene expresada "COP por 1 USD".
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
    'EUR' => $obtenerTasaEUR,
    'COP' => $obtenerTasaCOP,
  ];

  // GET /tasas-cambio -- tasas normalizadas SOLO para las monedas activas de la
  // empresa autenticada (catalogo_monedas), sin importar de dónde vinieron. La
  // moneda base de la empresa siempre reporta tasa=1. Generaliza el propósito de
  // GET /bcv-rates (que se deja intacto, sigue siendo consumido por
  // plugins/currency-rates.js) para que el frontend pueda, en una fase posterior,
  // dejar de llamar URLs externas fijas y en su lugar pedir "las tasas que le
  // corresponden a mi empresa".
  //
  // Las estrategias automáticas siempre devuelven "por 1 USD" (ver comentario
  // arriba de $obtenerCotizacionesBCV). Como la moneda base de una empresa es
  // configurable (Fase 4/8 del rediseño) y no siempre es USD, aquí se
  // triangula cada resultado automático contra el valor "por USD" de la
  // moneda base real -- así el resultado final es siempre "unidades de X por
  // 1 unidad de la moneda base", que es lo que espera el frontend
  // (equivalenteEnBase() en MetodosPagoDinamico.vue). Para la empresa 194
  // real (base = USD) esto da exactamente el mismo resultado que antes,
  // porque dividir entre 1 no cambia nada.
  $app->get('/tasas-cambio', function (Request $request, Response $response) use ($estrategiasPorCodigo) {
    if (!defined('ID_EMPRESA') || !ID_EMPRESA) {
      $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    try {
      $localConnection = new LocalDB();
      $monedas = $localConnection->goQuery('SELECT codigo, es_base, tasa_manual, tasa_manual_actualizado_en FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0 ORDER BY es_base DESC');
      $localConnection->disconnect();
    } catch (Exception $e) {
      // catalogo_monedas todavía no existe en empresas no migradas a la Fase 2 --
      // no es un error real, simplemente no hay monedas configuradas que resolver.
      $response->getBody()->write(json_encode(['data' => []]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // Paso 1: resolver cada moneda no-USD contra su fuente (automática,
    // triangulada "por USD"; o manual, ya expresada "por la base actual").
    // USD siempre es 1 por definición -- es el ancla, exista o no como fila
    // de catalogo_monedas para esta empresa.
    $codigoBase = null;
    $resueltas = ['USD' => ['tasa' => 1.0, 'fuente' => 'ancla', 'fecha' => date('c'), 'esManual' => false]];

    foreach ($monedas as $moneda) {
      $codigo = $moneda['codigo'];
      if ((int) $moneda['es_base'] === 1) {
        $codigoBase = $codigo;
      }
      if ($codigo === 'USD') {
        continue; // ya resuelto como ancla
      }

      $resultado = null;
      if (isset($estrategiasPorCodigo[$codigo])) {
        $resultado = $estrategiasPorCodigo[$codigo]();
      }

      if ($resultado !== null) {
        $resultado['esManual'] = false;
        $resueltas[$codigo] = $resultado;
        continue;
      }

      // Respaldo manual: si no hay estrategia automática, o la que existe
      // falló (ej. bcv.org.ve caído), usar la tasa cargada a mano en el
      // Gestor de Monedas. A diferencia de las automáticas, el valor manual
      // ya está expresado "por la moneda base actual" (es lo que el
      // administrador ve y llena en el Gestor de Monedas) -- no se triangula.
      if ($moneda['tasa_manual'] !== null) {
        $resueltas[$codigo] = [
          'tasa' => (float) $moneda['tasa_manual'],
          'fuente' => 'manual',
          'fecha' => $moneda['tasa_manual_actualizado_en'],
          'esManual' => true,
        ];
      }
    }

    // Paso 2: factor de la moneda base respecto a USD, necesario para
    // triangular las automáticas. Si la base no tiene un valor automático
    // resuelto (ej. base=EUR pero el BCV no respondió y tampoco hay tasa
    // manual para EUR), no hay forma confiable de triangular nada --
    // cualquier automática no-base queda "sin_configurar" en vez de
    // arriesgar un cálculo con datos parciales.
    $baseFactorPorUsd = null;
    if ($codigoBase === 'USD') {
      $baseFactorPorUsd = 1.0;
    } elseif (isset($resueltas[$codigoBase]) && empty($resueltas[$codigoBase]['esManual'])) {
      $baseFactorPorUsd = $resueltas[$codigoBase]['tasa'];
    }

    // Paso 3: construir la respuesta final, siempre "por la moneda base
    // actual", sin omitir nunca una moneda activa.
    $data = [];
    foreach ($monedas as $moneda) {
      $codigo = $moneda['codigo'];

      if ($codigo === $codigoBase) {
        $data[] = ['codigo' => $codigo, 'tasa' => 1.0, 'fuente' => 'base', 'fecha' => date('c')];
        continue;
      }

      $resultado = $resueltas[$codigo] ?? null;

      if ($resultado !== null && !empty($resultado['esManual'])) {
        // Ya está en términos de la base actual, se usa tal cual.
        $data[] = [
          'codigo' => $codigo,
          'tasa' => $resultado['tasa'],
          'fuente' => $resultado['fuente'],
          'fecha' => $resultado['fecha'],
        ];
        continue;
      }

      if ($resultado !== null && $baseFactorPorUsd !== null && $baseFactorPorUsd > 0) {
        $data[] = [
          'codigo' => $codigo,
          'tasa' => $resultado['tasa'] / $baseFactorPorUsd,
          'fuente' => $resultado['fuente'],
          'fecha' => $resultado['fecha'],
        ];
        continue;
      }

      // Nunca se omite una moneda activa del contrato: si no hay forma de
      // resolverla (ni automática ni manual, o la base misma no se pudo
      // triangular), se reporta explícitamente sin resolver, para que el
      // frontend pueda avisar en vez de asumir en silencio una conversión 1:1.
      $data[] = ['codigo' => $codigo, 'tasa' => null, 'fuente' => 'sin_configurar', 'fecha' => null];
    }

    $response->getBody()->write(json_encode(['data' => $data]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withStatus(200);
  });
};
