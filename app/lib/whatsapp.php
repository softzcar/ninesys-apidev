<?php
// namespace App\Lib;

class WhatsAppAPIClient
{
    private $apiUrl;
    private $dbConnection;

    public function __construct($apiUrl)
    {
        $this->apiUrl = $apiUrl;
        // El constructor ahora inicializa la conexión a la base de datos de empresas.
        // Utiliza las constantes globales que ya deben estar definidas en el entorno.
        $this->dbConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    }

    /**
     * Obtiene un token JWT válido, ya sea desde la base de datos o solicitando uno nuevo si ha expirado.
     *
     * @param int $id_empresa El ID de la empresa para la cual obtener el token.
     * @return string El token JWT válido.
     * @throws Exception Si no se puede obtener un token.
     */
    private function getToken($id_empresa)
    {
        $sql = 'SELECT ws_token, ws_token_expires_at FROM empresas WHERE id_empresa = ?';
        $token_data = $this->dbConnection->goQuery($sql, [$id_empresa]);

        // Comprueba si el token existe y si la fecha de expiración es futura.
        if (!empty($token_data) && isset($token_data[0]['ws_token']) && new DateTime() < new DateTime($token_data[0]['ws_token_expires_at'])) {
            return $token_data[0]['ws_token'];
        }

        // Si el token no es válido o no existe, solicita uno nuevo.
        return $this->loginAndStoreToken($id_empresa);
    }

    /**
     * Inicia sesión en la API de WhatsApp, decodifica el JWT para obtener la expiración y lo almacena en la base de datos.
     *
     * @param int $id_empresa El ID de la empresa.
     * @return string El nuevo token JWT.
     * @throws Exception Si las credenciales no están configuradas o si el login falla.
     */
    private function loginAndStoreToken($id_empresa)
    {
        $sql = 'SELECT ws_username, ws_password FROM empresas WHERE id_empresa = ?';
        $credentials = $this->dbConnection->goQuery($sql, [$id_empresa]);

        if (empty($credentials) || empty($credentials[0]['ws_username'])) {
            throw new Exception('Credenciales de WhatsApp no configuradas para la empresa ID: ' . $id_empresa);
        }

        $loginUrl = $this->apiUrl . 'login';
        $postData = json_encode([
            'username' => $credentials[0]['ws_username'],
            'password' => $credentials[0]['ws_password']
        ]);

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $postData,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($loginUrl, false, $context);
        $response = json_decode($result, true);

        if ($result === false || !isset($response['token'])) {
            throw new Exception('Fallo al iniciar sesión en la API de WhatsApp: ' . $result);
        }

        $token = $response['token'];

        // Decodificar el payload del JWT para obtener la fecha de expiración (exp)
        try {
            $tokenParts = explode('.', $token);
            $tokenPayload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
            $jwtPayload = json_decode($tokenPayload);
            $expires_at = date('Y-m-d H:i:s', $jwtPayload->exp);
        } catch (Exception $e) {
            // Si la decodificación falla, establece una expiración corta por seguridad (ej. 1 hora)
            $expires_at = date('Y-m-d H:i:s', time() + 3600);
        }


        $sqlUpdate = 'UPDATE empresas SET ws_token = ?, ws_token_expires_at = ? WHERE id_empresa = ?';
        $this->dbConnection->goQuery($sqlUpdate, [$token, $expires_at, $id_empresa]);

        return $token;
    }

    /**
     * Realiza una petición genérica a la API de WhatsApp, incluyendo el token de autorización.
     *
     * @param string $method 'GET' o 'POST'.
     * @param string $url La URL completa del endpoint.
     * @param int $id_empresa El ID de la empresa para obtener el token.
     * @param array|null $payload El cuerpo de la petición para POST.
     * @return array La respuesta decodificada de la API.
     * @throws Exception Si la petición falla.
     */
    private function makeRequest($method, $url, $id_empresa, $payload = null)
    {
        $token = $this->getToken($id_empresa);

        $headers = "Authorization: Bearer " . $token . "\r\n";
        $options = [
            'http' => [
                'method' => $method,
                'header' => $headers,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];

        if ($method === 'POST' && $payload !== null) {
            $options['http']['header'] .= "Content-Type: application/json\r\n";
            $options['http']['content'] = json_encode($payload);
        }

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $error = error_get_last();
            throw new Exception('Error al llamar a la API externa: ' . ($error['message'] ?? 'Error desconocido'));
        }

        $http_response_header_string = implode("\r\n", $http_response_header ?? []);
        preg_match('{HTTP/\d+\.\d+ (\d+) }i', $http_response_header_string, $matches);
        $http_status_code = $matches[1] ?? 0;

        if ($http_status_code >= 400) {
            // Si el token es inválido (401 o 403), forzar un nuevo login la próxima vez
            if ($http_status_code == 401 || $http_status_code == 403) {
                $this->dbConnection->goQuery('UPDATE empresas SET ws_token = NULL, ws_token_expires_at = NULL WHERE id_empresa = ?', [$id_empresa]);
            }
            throw new Exception('Error HTTP ' . $http_status_code . ' desde la API externa: ' . $result);
        }

        $responseData = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Respuesta de la API no es un JSON válido: ' . json_last_error_msg());
        }

        return $responseData;
    }

    private function shouldSend($phone = null, $payload = null)
    {
        $targetPhone = null;
        if (!empty($phone)) {
            $targetPhone = $phone;
        } elseif (is_array($payload) && isset($payload['phone_client'])) {
            $targetPhone = $payload['phone_client'];
        } elseif (is_numeric($payload)) {
            // Es el id_orden, buscamos el teléfono del cliente
            try {
                $db = new LocalDB('', LOCAL_DNS, LOCAL_USER, LOCAL_PASS);
                $sql = 'SELECT b.phone FROM ordenes a LEFT JOIN customers b ON b._id = a.id_wp WHERE a._id = ?';
                $res = $db->goQuery($sql, [$payload]);
                $db->disconnect();
                if (!empty($res) && isset($res[0]['phone'])) {
                    $targetPhone = $res[0]['phone'];
                }
            } catch (Exception $e) {
                error_log("Error al buscar teléfono para orden $payload: " . $e->getMessage());
            }
        }

        if (empty($targetPhone)) {
            return true; // Si no hay teléfono, dejamos pasar
        }

        // Limpiar número
        $cleanPhone = preg_replace('/[^0-9]/', '', $targetPhone);
        if (empty($cleanPhone)) {
            return true;
        }

        try {
            $db = new LocalDB('', LOCAL_DNS, LOCAL_USER, LOCAL_PASS);
            // Buscamos recibir_notificaciones de este número (coincidencia parcial final)
            $sql = 'SELECT recibir_notificaciones FROM customers WHERE phone LIKE ? LIMIT 1';
            $result = $db->goQuery($sql, ['%' . substr($cleanPhone, -9)]);
            $db->disconnect();

            if (!empty($result) && isset($result[0]['recibir_notificaciones'])) {
                return (int)$result[0]['recibir_notificaciones'] === 1;
            }
        } catch (Exception $e) {
            error_log("Error al verificar opt-out para el teléfono $targetPhone: " . $e->getMessage());
        }

        return true; // Por defecto enviar
    }

    public function sendMessage($id_empresa, $payload)
    {
        if (!$this->shouldSend(null, $payload)) {
            return [
                'success' => false,
                'status' => 'skipped',
                'message' => 'El cliente ha optado por no recibir mensajes automáticos.'
            ];
        }
        $url = $this->apiUrl . 'send-message/' . $id_empresa;
        return $this->makeRequest('POST', $url, $id_empresa, $payload);
    }

    public function sendMessageCustom($id_empresa, $id_orden, $phone, $msg)
    {
        if (!$this->shouldSend($phone)) {
            return [
                'success' => false,
                'status' => 'skipped',
                'message' => 'El cliente ha optado por no recibir mensajes automáticos.'
            ];
        }
        $url = $this->apiUrl . 'send-message-custom/' . $id_empresa;
        $payload = [
            'phone' => $phone,
            'id_orden' => $id_orden,
            'message' => $msg,
        ];
        return $this->makeRequest('POST', $url, $id_empresa, $payload);
    }

    public function getWSSeesionInfo($id_empresa)
    {
        $url = $this->apiUrl . 'session-info/' . $id_empresa;
        return $this->makeRequest('GET', $url, $id_empresa);
    }

    public function sendDirectMessageToNode($id_empresa, $phone, $message, $isAutomatic = true)
    {
        if ($isAutomatic && !$this->shouldSend($phone)) {
            return [
                'success' => false,
                'status' => 'skipped',
                'message' => 'El cliente ha optado por no recibir mensajes automáticos.'
            ];
        }
        $url = $this->apiUrl . 'send-direct-message/' . $id_empresa;
        $payload = [
            'phone' => $phone,
            'message' => $message,
        ];
        return $this->makeRequest('POST', $url, $id_empresa, $payload);
    }

    // El método getInfo y sendMessage_old se mantienen por si son usados en otras partes,
    // pero no se les añade la lógica de autenticación JWT para marcarlos como obsoletos.

    private function getInfo($id_orden)
    {
        $sql = 'SELECT
                a._id id_orden,
                a.id_wp id_cliente,
                b.first_name first_name,
                b.last_name last_name,
                b.phone phone_client,
                b.email email_client,
                c.id_usuario id_empleado,
                c.telefono phone_admin
            FROM
                ordenes a
            LEFT JOIN customers b ON
                b._id = a.id_wp
            LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = a.responsable
            WHERE
                a._id = ' . $id_orden;

        $localConnection = new LocalDB();
        $data = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        return $data;
    }

    public function sendMessage_old($id_empresa, $id_orden, $template, $data)
    {
        $response = $this->getInfo($id_orden);
        $newResponse['data'] = [];
        if (is_array($response) && count($response) > 0) {
            $newResponse['data']['id_orden'] = $response[0]['id_orden'] ?? null;
            $newResponse['data']['id_cliente'] = $response[0]['id_cliente'] ?? null;
            $newResponse['data']['first_name'] = $response[0]['first_name'] ?? null;
            $newResponse['data']['last_name'] = $response[0]['last_name'] ?? null;
            $newResponse['data']['phone_admin'] = $response[0]['phone_admin'] ?? null;
            $newResponse['data']['phone_client'] = $response[0]['phone_client'] ?? null;
            $newResponse['data']['email_client'] = $response[0]['email_client'] ?? null;
        } else {
            return [
                'error' => 'Error al obtener información de la orden',
                'details' => 'La respuesta de getInfo() no es válida o está vacía.',
                'code' => 500
            ];
        }
        $newResponse['data']['template'] = $template;
        $newResponse['data']['object'] = $data['object'];
        $this->apiUrl = rtrim($this->apiUrl, '/') . '/'; // Asegurar que termina en slash
        $api_url = $this->apiUrl . 'send-message/' . $id_empresa;
        try {
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($newResponse),
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ];
            $context = stream_context_create($options);
            $result = @file_get_contents($api_url, false, $context);
            if ($result === false) {
                $error = error_get_last();
                throw new Exception('Error al llamar a la API externa: ' . ($error ? $error['message'] : 'Error desconocido'));
            }
            $http_response_header_string = implode("\r\n", $http_response_header);
            preg_match('{HTTP/\d+\.\d+ (\d+) }i', $http_response_header_string, $matches);
            $http_status_code = isset($matches[1]) ? (int) $matches[1] : 0;
            if ($http_status_code < 200 || $http_status_code >= 300) {
                throw new Exception('Error HTTP ' . $http_status_code . ' al llamar a la API externa: ' . $result);
            }
            $responseData = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Respuesta de la API no es un JSON válido: ' . json_last_error_msg());
            }
            return $responseData;
        } catch (Exception $e) {
            $errorDetail = [
                'error' => 'Error al generar el formato de mensaje 001',
                'details' => $e->getMessage(),
                'url' => $this->apiUrl,
                'response' => isset($http_response_header_string) ? $http_response_header_string : 'No response headers',
                'code' => $http_status_code > 0 ? $http_status_code : 500
            ];
            return $errorDetail;
        }
    }
}