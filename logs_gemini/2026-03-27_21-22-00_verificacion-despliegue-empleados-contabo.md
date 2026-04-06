# Verificación de Despliegue - Endpoint `/empleados` en Contabo

Fecha: 2026-03-27 21:22:00

## Contexto de Entornos
- Desarrollo (Hostinger): `api.nineteengreen.com` → `/home/api.nineteengreen.com/public_html`
- Producción (Contabo): `api.nineteencustom.com` → `/home/api.nineteencustom.com/public_html`

## Objetivo
Validar que el refactor de consulta del endpoint `/empleados` quedó desplegado en producción y comparar tiempos contra desarrollo.

## Verificación Funcional de Versión
Se consultó `GET /empleados` con `Authorization: 163` y se comparó `sql_len` del campo `sql` retornado:

- `api.nineteengreen.com` → `sql_len=3447`
- `api.nineteencustom.com` → `sql_len=3447`

Conclusión: ambos entornos ejecutan la nueva consulta refactorizada.

## Medición de Rendimiento (curl -w)
- Desarrollo:
  - `time_total=3.113941`
  - `time_starttransfer=2.818845`
  - `http_code=200`
- Producción:
  - `time_total=29.916795`
  - `time_starttransfer=29.892048`
  - `http_code=200`

## Conclusión Técnica
- El despliegue en Contabo quedó aplicado correctamente.
- Persisten tiempos altos en producción aun con la consulta optimizada.
- La diferencia restante apunta a factores de infraestructura/BD en producción (índices ausentes, carga del servidor, configuración SQL, I/O o latencia interna entre servicios), no a divergencia de código entre entornos.

## Siguiente Paso Recomendado
Ejecutar auditoría SQL en Contabo para confirmar plan de ejecución e índices:
1) `EXPLAIN` de la consulta de `/empleados`.
2) revisión de índices en `pagos`, `pagos_salarios`, `salario_carga_familiar`, `empresas_usuarios_departamentos`.
3) creación de índices faltantes y nueva medición.

