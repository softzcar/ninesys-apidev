<?php

class CorsMiddleware 
{
    public function call()
    {
        $request = $this->app->request();
        $response = $this->app->response();

        // Establecer los encabezados CORS
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');

        // Manejar solicitudes OPTIONS (preflight)
        if ($request->isOptions()) {
            $response->status(200);
            $response->write('');
            return;
        }

        $this->next->call();
    }
}
