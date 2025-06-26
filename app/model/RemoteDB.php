<?php
/** Obntener datos directamente del servidor remoto */

Class RemoteDB {
    protected $object;
    protected $url;
    protected $dataDecoded = array();
    protected $dataTMP  = array();
    protected $dataTMP1 = array();
    protected $dataTMP2 = array();

    function __construct($url){
        $this->url = $url;
    }

    function getData() {
        $continue = true;
        $nextPage = 1;
        // $this->dataDecoded = array();
        // $this->dataDecoded['x'] = 'xxx';

        do {
            $url = BASE_URL . $this->url . '/?per_page=10&page=' . $nextPage;
            $peticion = new Handler($url);
            $data = $peticion->responseWC(WC_CK, WC_CS);
            // $this->dataDecoded = json_decode($data, true);
            $this->dataTMP = json_decode($data, true);

            if ($data == '[]') {
                $continue = false;
            } else {
                # TODO Crear vector noc lols datos de cad pagina
                // array_push($this->dataDecoded,$this->dataTMP);

                $this->dataTMP1[] = $this->dataDecoded;
                $this->dataTMP2[] = $this->dataTMP;

                $this->dataDecoded = array_merge($this->dataTMP1,$this->dataTMP2);
                 
            }

            $nextPage = $nextPage + 1;
        } while ($continue);

        // $this->object = array();
     
        // return $url;
        return $this->dataDecoded;
    }
}

Class RemoteCustomers extends RemoteDB
{
    function getData(){
        return parent::getData();

        /* foreach ($this->dataDecoded as $key => $item) {
            $this->object['data'][$key]->nombre = ucfirst(strtolower($item['first_name'] . ' ' . $item['last_name']));
            $this->object['data'][$key]->cedula = $item['billing']['company'];
            $this->object['data'][$key]->email  = strtolower($item['email']);
        }

        return $this->object; */
    }
}
