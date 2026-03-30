# Log de Tarea: Auditoría y Optimización de `/empleados` (Prod vs Dev)

Fecha: 2026-03-27 21:05:00

## Solicitud
Investigar por qué `https://api.nineteencustom.com/empleados` (producción) tarda ~34s y `https://api.nineteengreen.com/empleados` (desarrollo) tarda ~1s.

## Evidencia (medición de red vs servidor)
Se midió con `curl -w` usando `Authorization: 163` en ambos hosts.

- Dev (`api.nineteengreen.com`):
  - `http_code=200`
  - `time_total=2.269589`
  - `time_starttransfer=2.109800`
  - `time_connect=0.530827`
  - `time_appconnect=0.875934`
  - `size_download=10607`

- Prod (`api.nineteencustom.com`):
  - `http_code=200`
  - `time_total=48.075741`
  - `time_starttransfer=48.065616`
  - `time_connect=0.325889`
  - `time_appconnect=0.811228`
  - `size_download=10672`

Interpretación: DNS/TCP/TLS son similares; el cuello de botella es **servidor/BD** antes de empezar a transferir (`time_starttransfer`).

## Comparación de payload
Ambos endpoints retornan:
- SQL de longitud `2563`
- `items_len=13`
Lo que sugiere que el tiempo extra en producción no proviene del tamaño del payload, sino del costo de ejecutar la consulta.

## Causa técnica probable
El endpoint `/empleados` estaba usando:
- Subconsultas correlacionadas por empleado con `ORDER BY ... LIMIT 1` sobre `pagos/pagos_salarios`
- `GROUP_CONCAT DISTINCT` sobre joins directos (departamentos y carga familiar)
- `GROUP BY` amplio por todas las columnas del empleado

En producción, con tablas de pagos más grandes o índices insuficientes, esto escala mal y puede disparar tiempos de 30–50s.

## Corrección aplicada (sin cambiar la estructura funcional del response)
Se refactorizó la consulta del endpoint `/empleados` para eliminar subconsultas correlacionadas por empleado y reducir multiplicación de filas:
- Departamentos y carga familiar pasan a subconsultas agregadas por `id_empleado` y se unen por LEFT JOIN.
- Última semana/año pagado y última fecha de pago semanal pasan a subconsultas agregadas (`MAX(moment)`/`MAX(fecha_pago)`) y se unen por LEFT JOIN.
- Se elimina el `GROUP BY` global porque las subconsultas ya entregan 1 fila por empleado.

Archivo modificado:
- [employees.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/employees.php)

## Índices recomendados (para asegurar rendimiento en producción)
Ejecutar en la base LOCAL de la empresa (por ejemplo `api_emp_163`) y en `api_empresas` según aplique:

- `api_emp_163.pagos`
  - `(id_empleado, moment)`
  - `(id_empleado, fecha_pago)`
  - `(fecha_pago, id_empleado)` (opcional, depende de planes)
- `api_emp_163.pagos_salarios`
  - `(id_pago)`
  - `(numero_semana)` (si se filtra con frecuencia por semana)
- `api_emp_163.salario_carga_familiar`
  - `(id_empleado)`
- `api_empresas.empresas_usuarios_departamentos`
  - `(id_empleado, id_departamento)`

## Verificación
- Validación de sintaxis PHP: `php -l` OK.
- Verificación funcional final recomendada en Hostinger/Contabo:
  - Medir nuevamente con `curl -w` y comparar `time_starttransfer`.
  - (Opcional) obtener `EXPLAIN` del SQL generado en producción.

