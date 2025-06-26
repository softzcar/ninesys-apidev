<?php

class EmailClienteBienvenida
{
    private $data;
    private $diferencia;
    private $linkProgreso;
    private $linkDiseno;

    public function __construct($data)
    {
        $this->data = $data;
        $this->linkProgreso = "http://";
        $this->linkDiseno = "http://";
        $this->diferencia = number_format((doubleval($data["orden"][0]["pago_total"]) - doubleval($data["orden"][0]["pago_abono"])), 0);
    }

    public function obtenerContenido()
    {
        $contenido = '<!DOCTYPE html>
            <html>
            <head>
                <title>Confirmación de Pedido</title>
                <style>
/* Estilos para el correo electrónico */
body {
    font-family: Arial, sans-serif;
    background-color: #f5f5f5;    
}
.container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
    background-color: #ffffff;
}
h1 {
    color: #333333;
    margin-bottom: 10px;
}
p {
    color: #666666;
    margin-bottom: 20px;
}
a {
    color: #8403fc;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
th, td {
    border: 1px solid #e5e5e5;
    padding: 8px;
    text-align: left;
}
th {
    background-color: #f5f5f5;
    font-weight: bold;
    font-size: 0.9rem;
}
td {
    font-size: 0.8rem;
    padding-top:6px;
    padding-bottom:4px;
}
.txt-observaciones {
    margin-bottom: 20px;
}
.txt-observaciones p {
    font-size: 0.8rem;
    padding:0px 2rem;
}
.total {
    font-weight: bold;
    text-align: right;
}
</style>
            </head>
            <body>
            <div class="container">
                <h1>Confirmación de Pedido</h1>
<p>' . $this->data["customer"]["nombre"] . ' gracias por tu compra. A continuación encontrarás los detalles de tu pedido:</p>
<p>El monto toal de tu orden es de <strong>$' . number_format($this->data["orden"][0]["pago_total"], 0) . '</strong>. Has hecho un abono de <strong>$' . number_format($this->data["orden"][0]["pago_abono"], 0) . '</strong> y te resta por abonar <strong>$' . $this->diferencia . '</strong>. La fecha de entrega de tu pedido es <strong>' . date('d/m/Y', strtotime($this->data["orden"][0]["fecha_entrega"])) . '</strong> </p>

<p>Usa estos enlaces para <a href="https://app.nineteengreen.com/clientes/progreso" target="_blank">ver el avance de tu pedido</a> o para <a href="#" target="_blank">Aprobar tu diseño</a></p>

                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Talla</th>
                            <th>Cantidad</th>
                            <th>Corte</th>
                            <th>Precio</th>
                        </tr>
                    </thead>
                    <tbody>';

        $total = 0;
        foreach ($this->data['productos'] as $producto) {
            $contenido .= '<tr>
                            <td>' . $producto['name'] . '</td>
                            <td>' . $producto['talla'] . '</td>
                            <td>' . $producto['cantidad'] . '</td>
                            <td>' . $producto['corte'] . '</td>
                            <td>$' . number_format($producto['cantidad'], 0) * number_format($producto['precio'], 0) . '</td>
                        </tr>';
            $total += (float) number_format($producto['cantidad'], 0) * number_format($producto['precio'], 0);
        }

        $contenido .= '</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="total">Total:</td>
                            <td><strong>$' . number_format($total, 0) . '</strong></td>
                        </tr>
                    </tfoot>
                </table>
                <h3>Observaciones</h3>
<em class="txt-observaciones">
' . $this->data['orden'][0]['observaciones'] . '
</em>
                <p>Gracias por elegir nuestros productos. Esperamos que disfrute de su compra.</p>
            </div>
            </body>
            </html>';

        return $contenido;
    }
}