<?php

class IdEmpresaMiddleware 
{
    public function call()
    {
        // global $id_empresa;  // Definir variable global
        // $id_empresa = 99;
        /*$request = $this->app->request();

        // Obtener el parámetro de consulta `id_empresa`
        $queryParams = $request->getQueryParams();
        $id_empresa = $queryParams['id_empresa'] ?? null;*/

        $this->next->call();
    }
}
