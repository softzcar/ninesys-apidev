<?php
class SSE
{
    private $obj = "";
    private $previousData = "";

    public function __construct($obj = [])
    {
        $this->obj = $obj;
    }

    public function SsePrint()
    {
        // Establecer los encabezados SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        // Enviar eventos
        $count = 0;
        $localConnection = new LocalDB();
        while ($count < 2) {
            $currentData = [];
            foreach ($this->obj as $key => $value) {
                if (is_array($value["sql"])) {
                    // $currentData["is_array"] = true;
                    $name = $value["name"];
                    $mySql = array_unique($value["sql"]);
                    $currentData[$name] = [];

                    foreach ($mySql as $key => $sql_array) {
                        // $localConnection = new LocalDB($sql_array);
                        $localConnection->setSql($sql_array);
                        $resp = $localConnection->goQuery($sql_array);
                        if (!empty($resp)) {
                            // $currentData["sql_array"][] = $sql_array;
                            $resp = $localConnection->goQuery($sql_array);

                            if (!in_array($resp, $currentData[$name])) {
                                $currentData[$name][] = $localConnection->goQuery($sql_array);
                            }
                        }
                    }
                } else {
                    // $localConnection = new LocalDB($value["sql"]);
                    $localConnection->setSql($value["sql"]);
                    $currentData[$value["name"]] = $localConnection->goQuery($value["sql"]);
                }
            }

            $localConnection->disconnect();

            if ($currentData !== $this->previousData) {
                $this->previousData = $currentData;

                echo "event: message\n";
                // echo "data: " . json_encode($this->obj) . "\n\n";
                echo "data: " . json_encode($currentData) . "\n\n";
                ob_flush();
                // ob_end_flush();
                flush();
            } /* else {
             echo "event: message\n";
             // echo "data: " . json_encode($this->obj) . "\n\n";
             echo "data: NO DATA\n\n";
             ob_flush();
             ob_end_flush();
             flush();
             } */

            $count++;
            sleep(3);
        }
        // prueba de sincronizacion cno Filezilla 1
    }

    public function SseTest()
    {
        $count = 0;
        ob_implicit_flush(true);

        while (true) {
            // Realizar algún procesamiento y generar los datos a enviar

            // Imprimir los encabezados SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');

            // Enviar datos al cliente
            echo "event: message\n";
            echo "data: COUNT: " . $count . "\n\n";

            // Esperar un tiempo antes de la siguiente iteración
            sleep(1);
        }
    }
}