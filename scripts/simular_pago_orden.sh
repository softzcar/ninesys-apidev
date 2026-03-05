#!/bin/bash
# Script para simular el cálculo de pagos de una orden
# Uso: ./scripts/simular_pago_orden.sh <ID_EMPRESA> <ID_ORDEN>
# Ejemplo: ./scripts/simular_pago_orden.sh 174 1

if [ $# -lt 2 ]; then
    echo "Uso: $0 <ID_EMPRESA> <ID_ORDEN>"
    exit 1
fi

ID_EMPRESA=$1
ID_ORDEN=$2

DB_NAME="api_emp_${ID_EMPRESA}"

echo "=========================================================="
echo " SIMULADOR DE PAGOS DE LOTES MULTI-ASIGNADOS"
echo " Empresa: $ID_EMPRESA | Orden: $ID_ORDEN"
echo "=========================================================="
echo "Consultando asignaciones y calculando estimado..."
echo "Asegúrate de que la orden tenga asignaciones y productos."
echo ""

QUERY="
SELECT 
    eu.nombre AS 'Empleado',
    eu.salario_tipo AS 'Compensacion',
    IF(eu.salario_tipo = 'Salario', 'N/A', eu.comision_tipo) AS 'Tipo_Comision',
    d.departamento AS 'Departamento',
    a.procentaje_comision AS 'Asignacion(%)',
    SUM(op.cantidad * (a.procentaje_comision / 100)) AS 'Piezas_Asignadas',
    IF(eu.salario_tipo = 'Salario', 0,
        IF(eu.comision_tipo = 'porcentaje', eu.comision_porcentaje, 
            IF(eu.comision_tipo = 'variable', IFNULL(pc.comision, 0), eu.comision)
        )
    ) AS 'Tarifa/Valor',
    IF(eu.salario_tipo = 'Salario', 0,
        SUM(
            CASE 
                WHEN eu.comision_tipo = 'fija' THEN op.cantidad * eu.comision
                WHEN eu.comision_tipo = 'porcentaje' THEN op.cantidad * op.precio_unitario * (eu.comision_porcentaje / 100)
                WHEN eu.comision_tipo = 'variable' THEN op.cantidad * IFNULL(pc.comision, 0)
                ELSE 0
            END
        ) * (a.procentaje_comision / 100)
    ) AS 'Pago_Calculado_Esperado($)'
FROM 
    lotes_detalles_empleados_asignados a
JOIN 
    api_empresas.empresas_usuarios eu ON a.id_empleado = eu.id_usuario
JOIN 
    departamentos d ON a.id_departamento = d._id
JOIN 
    ordenes_productos op ON a.id_orden = op.id_orden
JOIN 
    products p ON op.id_woo = p._id
LEFT JOIN 
    products_comisiones pc ON pc.id_product = op.id_woo AND pc.id_departamento = a.id_departamento
WHERE 
    a.id_orden = ${ID_ORDEN}
    AND (p.fisico = 1 OR p.fisico IS NULL)
    AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
GROUP BY 
    a._id, eu.id_usuario, eu.nombre, d.departamento, a.procentaje_comision, eu.salario_tipo, eu.comision_tipo, 'Tarifa/Valor'
ORDER BY 
    eu.nombre, d.orden_proceso;
"

mysql -u root "$DB_NAME" -t -e "$QUERY"
