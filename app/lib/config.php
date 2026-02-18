<?php

/** CONFIGURACION DELS SITEMA */

/** Woocommerce ninesys */
define('BASE_URL', 'https://tiendademo.nineteencustom.com/wp-json/');

/** Local API */
define('LOCAL_API', 'http://ninesys.ddns.net/');

/** Ping DIR */
define('PING_URL', 'nineteencustom.com');
define('MSG_URL', 'http://194.195.86.253:3000/send-message');

define('EMPRESAS_DNS', 'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';dbname=' . (getenv('DB_NAME') ?: 'api_empresas'));
define('EMPRESAS_USER', getenv('DB_USER') ?: 'api_adminemp');
define('EMPRESAS_PASS', getenv('DB_PASS') ?: 'rkyaFy!dAs8L5Lq8');

// define('LOCAL_DSN', 'mysql:host=localhost;dbname=api_ninesys');  // ninesys PRUEBAS RICARDO
// define('LOCAL_USER', 'api_admin');
// define('LOCAL_PASS', 'ninesys.25');
