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
        while (true) {
            foreach ($this->obj as $key => $value) {
                if (is_array($value["sql"])) {
                    // $currentData["is_array"] = true;
                    $name = $value["name"];
                    $mySql = array_unique($value["sql"]);
                    $currentData[$name] = [];

                    foreach ($mySql as $key => $sql_array) {
                        $localConnection = new LocalDB($sql_array);
                        $resp = $localConnection->goQuery();
                        if (!empty($resp)) {
                            // $currentData["sql_array"][] = $sql_array;
                            $resp = $localConnection->goQuery();

                            if (!in_array($resp, $currentData[$name])) {
                                $currentData[$name][] = $localConnection->goQuery();
                            }
                        }
                    }
                } else {
                    $localConnection = new LocalDB($value["sql"]);
                    $currentData[$value["name"]] = $localConnection->goQuery();
                }
            }

            if ($currentData !== $this->previousData) {
                $this->previousData = $currentData;

                echo "event: message\n";
                // echo "data: " . json_encode($this->obj) . "\n\n";
                echo "data: " . json_encode($currentData) . "\n\n";
                ob_flush();
                flush();
            }

            sleep(3);
        }
    }
}