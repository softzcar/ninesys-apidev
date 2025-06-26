<?php

class WhatsAppAPIClient
{
    private $apiUrl;

    public function __construct($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    public function sendMessage($id_empresa, $id_orden, $template, $data)
    {
        $response = $this->getInfo($id_orden);

        $newResponse['data'] = [];  // Inicializar el array 'data'

        // Verificar si $response es un array y si tiene al menos un elemento
        if (is_array($response) && count($response) > 0) {
            // Usar isset() para verificar si los índices existen antes de acceder a ellos
            $newResponse['data']['id_orden'] = isset($response[0]['id_orden']) ? $response[0]['id_orden'] : null;
            $newResponse['data']['id_cliente'] = isset($response[0]['id_cliente']) ? $response[0]['id_cliente'] : null;
            $newResponse['data']['first_name'] = isset($response[0]['first_name']) ? $response[0]['first_name'] : null;
            $newResponse['data']['last_name'] = isset($response[0]['last_name']) ? $response[0]['last_name'] : null;
            $newResponse['data']['phone_admin'] = isset($response[0]['phone_admin']) ? $response[0]['phone_admin'] : null;
            $newResponse['data']['phone_client'] = isset($response[0]['phone_client']) ? $response[0]['phone_client'] : null;
            // $newResponse['data']['phone_client'] = '584147307169';  // Valor quemado para pruebas
            $newResponse['data']['email_client'] = isset($response[0]['email_client']) ? $response[0]['email_client'] : null;
        } else {
            // Manejar el caso en que $response no es válido
            return [
                'error' => 'Error al obtener información de la orden',
                'details' => 'La respuesta de getInfo() no es válida o está vacía.',
                'code' => 500
            ];
        }

        $newResponse['data']['template'] = $template;
        $newResponse['data']['data'] = $data;

        $this->apiUrl = 'http://194.195.86.253:3000/send-message/' . $id_empresa;  // Asegúrate de que la URL sea correcta para enviar mensajes

        try {
            // Usar file_get_contents con opciones para POST y manejo de errores
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($newResponse),
                    'timeout' => 10,  // Timeout más corto
                    'ignore_errors' => true,  // Para obtener la respuesta incluso con errores HTTP
                ],
            ];

            $context = stream_context_create($options);
            $result = @file_get_contents($this->apiUrl, false, $context);  // Suprimir warnings

            if ($result === false) {
                $error = error_get_last();
                throw new \Exception('Error al llamar a la API externa: ' . ($error ? $error['message'] : 'Error desconocido'));
            }

            // Obtener el código de respuesta HTTP
            $http_response_header_string = implode("\r\n", $http_response_header);
            preg_match('{HTTP\/\d+\.\d+ (\d+) }i', $http_response_header_string, $matches);
            $http_status_code = isset($matches[1]) ? (int) $matches[1] : 0;

            // Verificar el código de estado HTTP
            if ($http_status_code < 200 || $http_status_code >= 300) {
                throw new \Exception('Error HTTP ' . $http_status_code . ' al llamar a la API externa: ' . $result);
            }

            // Decodificar la respuesta JSON
            $responseData = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Respuesta de la API no es un JSON válido: ' . json_last_error_msg());
            }

            return $responseData;  // Devolver el array asociativo
        } catch (\Exception $e) {
            $errorDetail = [
                'error' => 'Error al generar el formato de mensaje 001',
                'details' => $e->getMessage(),
                'url' => $this->apiUrl,
                'response' => isset($http_response_header_string) ? $http_response_header_string : 'No response headers',
                'code' => $http_status_code > 0 ? $http_status_code : 500  // Incluir el código de estado o 500 por defecto
            ];
            return $errorDetail;
        }
    }

    public function sendMessageCustom($id_empresa, $id_orden, $phone, $msg)
    {
        // $response = $this->getInfo($id_orden);

        // $newResponse['data'] = [];  // Inicializar el array 'data'

        $newResponse['phone'] = $phone;
        $newResponse['id_orden'] = $id_orden;
        $newResponse['message'] = $msg;

        // return $newResponse['data']['message'];

        $this->apiUrl = 'http://194.195.86.253:3000/send-message-custom/' . $id_empresa;  // Asegúrate de que la URL sea correcta para enviar mensajes

        try {
            // Usar file_get_contents con opciones para POST y manejo de errores
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($newResponse),
                    'timeout' => 10,  // Timeout más corto
                    'ignore_errors' => true,  // Para obtener la respuesta incluso con errores HTTP
                ],
            ];

            $context = stream_context_create($options);
            $result = @file_get_contents($this->apiUrl, false, $context);  // Suprimir warnings

            if ($result === false) {
                $error = error_get_last();
                throw new \Exception('Error al llamar a la API externa: ' . ($error ? $error['message'] : 'Error desconocido'));
            }

            // Obtener el código de respuesta HTTP
            $http_response_header_string = implode("\r\n", $http_response_header);
            preg_match('{HTTP\/\d+\.\d+ (\d+) }i', $http_response_header_string, $matches);
            $http_status_code = isset($matches[1]) ? (int) $matches[1] : 0;

            // Verificar el código de estado HTTP
            if ($http_status_code < 200 || $http_status_code >= 300) {
                throw new \Exception('Error HTTP ' . $http_status_code . ' al llamar a la API externa: ' . $result);
            }

            // Decodificar la respuesta JSON
            $responseData = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Respuesta de la API no es un JSON válido: ' . json_last_error_msg());
            }

            return $responseData;  // Devolver el array asociativo
        } catch (\Exception $e) {
            $errorDetail = [
                'error' => 'Error al generar el formato de mensaje 002',
                'e' => $e,
                'details' => $e->getMessage(),
                'url' => $this->apiUrl,
                'response' => isset($http_response_header_string) ? $http_response_header_string : 'No response headers',
                'code' => $http_status_code > 0 ? $http_status_code : 500,  // Incluir el código de estado o 500 por defecto
                'request' => $newResponse
            ];
            return $errorDetail;
        }
    }

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

        // return json_encode($data);
        return $data;
    }

    public function getWSSeesionInfo($id_empresa)
    {
        $this->apiUrl = 'http://194.195.86.253:3000/session-info/' . $id_empresa;

        try {
            // Usar file_get_contents con opciones simplificadas para GET
            $options = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,  // Timeout más corto (10 segundos)
                    'ignore_errors' => true,  // Importante para obtener la respuesta incluso con errores HTTP
                ],
            ];

            $context = stream_context_create($options);
            $result = @file_get_contents($this->apiUrl, false, $context);  // Suprimir warnings con @

            if ($result === false) {
                $error = error_get_last();
                throw new \Exception('Error al llamar a la API externa: ' . ($error ? $error['message'] : 'Error desconocido'));
            }

            // Obtener el código de respuesta HTTP
            $http_response_header_string = implode("\r\n", $http_response_header);
            preg_match('{HTTP\/\d+\.\d+ (\d+) }i', $http_response_header_string, $matches);
            $http_status_code = isset($matches[1]) ? (int) $matches[1] : 0;

            // Verificar el código de estado HTTP
            if ($http_status_code < 200 || $http_status_code >= 300) {
                throw new \Exception('Error HTTP ' . $http_status_code . ' al llamar a la API externa: ' . $result);
            }

            // Decodificar la respuesta JSON
            $responseData = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Respuesta de la API no es un JSON válido: ' . json_last_error_msg());
            }

            return $responseData;  // Devolver el array asociativo
        } catch (\Exception $e) {
            $errorDetail = [
                'error' => 'Error al obtener datos de la conexión a WhatsApp',
                'details' => $e->getMessage(),
                'url' => $this->apiUrl,
                'response' => isset($http_response_header_string) ? $http_response_header_string : 'No response headers'
            ];
            return $errorDetail;
        }
    }

    /**
     * Llama al endpoint /send-direct-message/:companyId de la API de Node.js
     *
     * @param string $id_empresa El ID de la empresa (companyId para Node.js)
     * @param string $phone El número de teléfono destino (sin @c.us)
     * @param string $message El mensaje a enviar
     * @return array Respuesta de la API de Node.js o array de error
     */
    public function sendDirectMessageToNode($id_empresa, $phone, $message)
    {
        // $apiUrl = $this->baseApiUrl . '/send-direct-message/' . $id_empresa;
        $this->apiUrl = 'http://194.195.86.253:3000/send-direct-message/' . $id_empresa;
        $payload = [
            'phone' => $phone,
            'message' => $message,
        ];

        // return $payload;

        try {
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n"
                        . "Accept: application/json\r\n",
                    'content' => json_encode($payload),
                    'timeout' => 15,  // Timeout un poco más largo por si la API de WhatsApp demora
                    'ignore_errors' => true,
                ],
            ];

            $context = stream_context_create($options);
            $result = @file_get_contents($this->apiUrl, false, $context);

            if ($result === false) {
                $error = error_get_last();
                throw new \Exception('Error al conectar con la API de Node.js para enviar mensaje directo: ' . ($error ? $error['message'] : 'Error desconocido'));
            }

            $http_response_header_string = implode("\r\n", $http_response_header ?? []);
            preg_match('{HTTP\/\d+\.\d+ (\d+) }i', $http_response_header_string, $matches);
            $http_status_code = isset($matches[1]) ? (int) $matches[1] : 0;

            $responseData = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Incluso si no es JSON, si el código es 2xx podría ser un éxito con respuesta no JSON (aunque tu API Node devuelve JSON)
                // O podría ser un error HTML del servidor Node si algo falla antes de tu controlador.
                throw new \Exception('Respuesta de la API de Node.js no es un JSON válido. Crudo: ' . substr($result, 0, 500));
            }

            // Revisar el success flag de tu API Node.js si existe, además del código HTTP
            if ($http_status_code < 200 || $http_status_code >= 300) {
                $errorMessage = 'Error HTTP ' . $http_status_code . ' desde la API de Node.js.';
                if (isset($responseData['message'])) {
                    $errorMessage .= ' Mensaje: ' . $responseData['message'];
                } elseif (isset($responseData['error'])) {
                    $errorMessage .= ' Error: ' . $responseData['error'];
                }
                throw new \Exception($errorMessage . '. Respuesta completa: ' . $result);
            }

            // Si tu API Node.js siempre devuelve un campo 'success', puedes verificarlo:
            // if (isset($responseData['success']) && $responseData['success'] === false) {
            //    throw new \Exception('La API de Node.js reportó un fallo: ' . ($responseData['message'] ?? 'Sin mensaje detallado'));
            // }

            return $responseData;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error en WhatsAppAPIClient::sendDirectMessageToNode',
                'details' => $e->getMessage(),
                'url_called' => $this->apiUrl,
                'payload_sent' => $payload,  // Cuidado con datos sensibles en logs de producción
                'http_code_received' => $http_status_code ?? null,
                'raw_response_received' => $result ?? null,  // Cuidado con datos sensibles
            ];
        }
    }
}
