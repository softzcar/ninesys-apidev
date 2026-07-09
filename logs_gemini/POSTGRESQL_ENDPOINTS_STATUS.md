# 📊 Evaluación de Endpoints — Soporte Dual MariaDB / PostgreSQL
## Proyecto: `ninesys-api` | Rama: `feature/postgresql-support`
**Fecha de evaluación:** 2026-07-09  
**Estado general:** En progreso — Infraestructura ✅ | Endpoints: ~25% adaptados

---

## 🔑 Referencia Rápida de Conversión MySQL → PostgreSQL

| Función MySQL | Equivalente PostgreSQL | Notas |
|---|---|---|
| `DATE_FORMAT(c, '%d/%m/%Y')` | `TO_CHAR(c, 'DD/MM/YYYY')` | |
| `DATE_FORMAT(c, '%H:%i')` | `TO_CHAR(c, 'HH24:MI')` | Hora 24h |
| `DATE_FORMAT(c, '%h:%i %p')` | `TO_CHAR(c, 'HH12:MI AM')` | Hora 12h con AM/PM |
| `DATE_FORMAT(c, '%W')` | `TO_CHAR(c, 'Day')` | Nombre día completo |
| `DATE_FORMAT(c, '%a')` | `TO_CHAR(c, 'Dy')` | Nombre día abreviado |
| `DATE_FORMAT(c, '%v')` | `EXTRACT(WEEK FROM c)` | Número de semana ISO |
| `DATE_FORMAT(c, '%d-%m-%Y')` | `TO_CHAR(c, 'DD-MM-YYYY')` | |
| `DATE_FORMAT(c, '%d/%m/%y')` | `TO_CHAR(c, 'DD/MM/YY')` | Año 2 dígitos |
| `DATE_FORMAT(c, '%d/%m/%Y %h:%i %p')` | `TO_CHAR(c, 'DD/MM/YYYY HH12:MI AM')` | |
| `YEARWEEK(c, 1) = YEARWEEK(NOW(), 1)` | `EXTRACT(WEEK FROM c) = EXTRACT(WEEK FROM NOW()) AND EXTRACT(YEAR FROM c) = EXTRACT(YEAR FROM NOW())` | |
| `WEEK(c, 1)` / `WEEK(NOW())` | `EXTRACT(WEEK FROM c)` | |
| `YEAR(c)` / `YEAR(NOW())` | `EXTRACT(YEAR FROM c)` | |
| `DAYOFWEEK(c)` (1=Dom..7=Sáb) | `EXTRACT(DOW FROM c)` (0=Dom..6=Sáb) | ⚠️ Diferencia de índice |
| `DAYNAME(c)` | `TO_CHAR(c, 'TMDay')` | |
| `TIMESTAMPDIFF(SECOND, a, b)` | `EXTRACT(EPOCH FROM (b::timestamp - a::timestamp))` | |
| `TIMESTAMPDIFF(MINUTE, a, b)` | `EXTRACT(EPOCH FROM (b::timestamp - a::timestamp)) / 60` | |
| `UNIX_TIMESTAMP(c)` | `EXTRACT(EPOCH FROM c::timestamp)::bigint` | |
| `GROUP_CONCAT(x SEPARATOR ',')` | `string_agg(x, ',')` | |
| `GROUP_CONCAT(DISTINCT x ORDER BY x)` | `string_agg(DISTINCT x, ',' ORDER BY x)` | ⚠️ Sintaxis distinta |
| `IFNULL(CONCAT('[', GROUP_CONCAT(...), ']'), '[]')` | `COALESCE('[' \|\| string_agg(...) \|\| ']', '[]')` | `\|\|` para concat |
| `FIELD(c, 'a','b','c')` | `CASE WHEN c='a' THEN 1 WHEN c='b' THEN 2 ... END` | No existe en PG |
| `GREATEST(0, expr)` | `GREATEST(0, expr)` | ✅ Compatible |
| `COALESCE(a, b)` | `COALESCE(a, b)` | ✅ Compatible |
| `api_empresas.tabla` (cross-DB) | `api_empresas.tabla` (vía postgres_fdw) | ✅ Transparente |
| `` `campo` `` (backticks) | `"campo"` (solo si es palabra reservada) | |

---

## ✅ ENDPOINTS YA ADAPTADOS

### 📁 `employees.php`

| Endpoint | Método | Estado | Funciones adaptadas | Verificado |
|---|---|---|---|---|
| `/empleados` | GET | ✅ **Adaptado** | `DATE_FORMAT`, `WEEK`, `YEAR`, `GROUP_CONCAT` (deps+carga), `IFNULL` | ✅ HTTP 200 |
| `/asistencias/semanal` | GET | ✅ **Adaptado** | `DATE_FORMAT` hora/fecha, `DAYOFWEEK`, `YEARWEEK` | ✅ HTTP 200, 3 registros |
| `/empleados/dashboard-stats/{id}/{dep}` | GET | ✅ **Adaptado** | `TIMESTAMPDIFF(SECOND)` ×4, `DATE_FORMAT('%W')` ×2, `YEARWEEK` ×2 | ✅ HTTP 200, todos los campos |
| `/asistencias/tabla/{fecha}` | GET | ✅ **Adaptado** | `DATE_FORMAT` ×2, `WEEK` ×2, `UNIX_TIMESTAMP`, `DAYNAME`, GROUP BY fix | ✅ HTTP 200 |
| `/asistencias/reporte/resumen/{fecha_inicio}/{fecha_fin}` | GET | ✅ **Adaptado** | `TIMESTAMPDIFF(MINUTE)` ×2, `DATE_FORMAT` ×2, `FIELD` replacement, GROUP BY fix | ✅ HTTP 200 |

### 📁 `tables.php`

| Endpoint | Método | Estado | Funciones adaptadas | Verificado |
|---|---|---|---|---|
| `/ordenes-reporte-semanal-produccion/{fecha}` | GET | ✅ **Adaptado** | `DATE_FORMAT` (con NULLIF/casteo VARCHAR), `WEEK` | ✅ HTTP 200 |
| `/ordenes-reporte-semanal/{fecha}` | GET | ✅ **Adaptado** | `WEEK`, `SUM` ORDER BY fix | ✅ HTTP 200 |
| `/ordenes/borrador/reporte-semanal/{id}/{dep}` | GET | ✅ **Adaptado** | `YEARWEEK` | ✅ HTTP 200 |
| `/table/ordenes-activas/{id}` | GET | ✅ **Adaptado** | `json_agg`/`json_build_object` (sustituto `GROUP_CONCAT` + `JSON_OBJECT`), `FIND_IN_SET` replacement, `COALESCE` | ✅ HTTP 200 |
| `/table/ordenes-todas` | GET | ✅ **Adaptado** | `json_agg` replacement para categorías, `FIND_IN_SET` replacement | ✅ HTTP 200 |
| `/table/ordenes-con-deuda` | GET | ✅ **Adaptado** | `TO_CHAR(moment)` | ✅ HTTP 200 |

### 📁 `manufacturing.php`

| Endpoint | Método | Estado | Funciones adaptadas | Verificado |
|---|---|---|---|---|
| `/empleados/ordenes-asignadas/v2/...` | GET | ✅ **Adaptado** | `DATE_FORMAT(fecha_entrega)` condicional, `IF` ➔ `CASE WHEN`, `IFNULL` ➔ `COALESCE` | ✅ HTTP 200 |
| `/sse/empleados/ordenes-asignadas/{id_empleado}` | GET | ✅ **Adaptado** | `DATE_FORMAT` condicional | — (SSE stream) |
| `/empleados-todos` | GET | ✅ **Adaptado** | `GROUP_CONCAT` ➔ subconsulta string_agg, `IFNULL` | ✅ HTTP 200 |

---

## 🔴 ENDPOINTS PENDIENTES DE ADAPTACIÓN

### Prioridad por Uso en la App

> **Criterio de prioridad:**  
> 🔴 Alta — Usados frecuentemente en la interfaz (dashboard, listas principales)  
> 🟡 Media — Reportes específicos y consultas bajo demanda  
> 🟢 Baja — Endpoints legacy, rara vez invocados

---

### 📁 `employees.php` — Pendientes

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| 116 | Subquery en `/empleados` | GET | 🔴 | `GROUP_CONCAT` (carga_familiar), `IFNULL(CONCAT('[', GROUP_CONCAT...` (**parte que falta**) |

---

### 📁 `orders.php` — Pendientes (46 ocurrencias)

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| ~758 | `/ordenes/abono/{id}` | GET | 🔴 | `DATE_FORMAT('%d/%m/%Y')`, `DATE_FORMAT('%h:%i %p')` ×6 |
| ~890 | `/reportes/resumen/disenadores/{id}/{dep}` | GET | 🟡 | `TIMESTAMPDIFF(SECOND)` ×2, `GROUP_CONCAT(DISTINCT)` ×2 |
| ~935 | `/reportes/resumen/empleados/{id}/{dep}` | GET | 🟡 | `TIMESTAMPDIFF(SECOND)` ×2, `GROUP_CONCAT(DISTINCT)` ×2 |
| ~984 | `/orden/asignacion/{id}` | GET | 🔴 | `DATE_FORMAT('%d-%m-%Y')`, `GROUP_CONCAT(DISTINCT)` ×2 |
| ~1470 | `/ordenes/reporte/{id}` | GET | 🔴 | `GROUP_CONCAT`, `DATE_FORMAT` |
| ~1628 | `/comercializacion/ordenes/reporte` | GET | 🟡 | Múltiples `DATE_FORMAT`, `GROUP_CONCAT` |
| ~1660 | `/comercializacion/ordenes/reporte/terminadas/{rango}` | GET | 🟡 | `DATE_FORMAT`, `YEARWEEK` |
| ~1285 | `/comercializacion/dashboard/{id}/{dep}` | GET | 🔴 | `DATE_FORMAT`, `TIMESTAMPDIFF` |

---

### 📁 `payments.php` — Pendientes (34 ocurrencias)

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| ~603 | (endpoint de pagos general) | GET | 🔴 | `DATE_FORMAT('%a')`, `DATE_FORMAT('%v')`, `DATE_FORMAT('%d/%m/%y')` ×6 |
| ~742 | Subquery en listado de empleados | GET | 🔴 | `DATE_FORMAT`, `WEEK(pa.fecha_pago, 1)`, `YEAR()` |
| ~814 | Pago directo por fecha | GET | 🟡 | `DATE_FORMAT('%d/%m/%Y')` ×2 |
| ~1088 | Pagos detalle | GET | 🟡 | `DATE_FORMAT('%d/%m/%Y')` |
| ~1164 | Pagos con fecha terminado | GET | 🟡 | `DATE_FORMAT('%a')`, `DATE_FORMAT('%v')` |

---

### 📁 `manufacturing.php` — Pendientes (29 ocurrencias)

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| ~1944 | (ruta de manufactura) | GET | 🟡 | `GROUP_CONCAT` |
| ~2820 | Tiempo por orden | GET | 🟡 | `TIMESTAMPDIFF(SECOND)` ×2 |
| ~3836 | Eficiencia producción | GET | 🟡 | `TIMESTAMPDIFF(SECOND)` ×6 |
| ~4132 | Reporte eficiencia extendido | GET | 🟡 | `TIMESTAMPDIFF(SECOND)` ×2 |

---

### 📁 `production.php` — Pendientes (15 ocurrencias)

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| ~133 | Inicio de producción | GET | 🔴 | `DATE_FORMAT('%h:%i:%s %p')`, `DATE_FORMAT('%d-%m-%Y')` |
| ~396 | Registro de momento | GET | 🔴 | `DATE_FORMAT('%d/%m/%Y')`, `DATE_FORMAT('%I:%i %p')` ×2 |
| ~478 | Órdenes con insumos | GET | 🟡 | `IFNULL(CONCAT('[', GROUP_CONCAT...` |
| ~982 | Reposiciones creadas | GET | 🟡 | `DATE_FORMAT('%d/%m/%Y')`, `DATE_FORMAT('%h:%i %p')` ×2 |
| ~1032 | Consumo de tinta | GET | 🟡 | `DATE_FORMAT('%d/%m/%Y %h:%i %p')` ×5 |

---

### 📁 `reports.php` — Pendientes (8 ocurrencias)

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| ~212 | `/reportes/costos-produccion/{inicio}/{fin}` | GET | 🟡 | `GROUP_CONCAT(DISTINCT)` |
| ~671 | `/reportes/mano-obra-por-orden/{id}` | GET | 🟡 | `TIMESTAMPDIFF(SECOND)` → horas |
| ~893 | Subquery de categorías | GET | 🟡 | `GROUP_CONCAT(DISTINCT cat2._id ORDER BY cat2._id SEPARATOR ',')` |
| ~990 | `/reportes/semanal-detallado` | GET | 🟡 | `DATE_FORMAT` ×4 |
| ~1037 | `/reportes/employee-efficiency-global` | GET | 🟡 | `TIMESTAMPDIFF(SECOND)` |

---

### 📁 `finance.php` — Pendientes (8 ocurrencias)

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| ~66 | Listado financiero | GET | 🟡 | `IFNULL(GROUP_CONCAT...)` ×2 |
| ~88 | Detalle de movimiento | GET | 🟡 | `DATE_FORMAT('%d/%m/%Y')`, `DATE_FORMAT('%h:%i %p')` ×3 |

---

### 📁 `products_reports.php` — Pendientes (6 ocurrencias)

| Línea | Endpoint | Método | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|---|
| ~238 | `/reportes/costos-productos` | GET | 🟡 | `GREATEST(0, TIMESTAMPDIFF(SECOND,...))` ×6 |
| ~566 | `/reportes/costos-productos/categorias` | GET | 🟡 | `GREATEST(0, TIMESTAMPDIFF(SECOND,...))` ×2 |
| ~870 | `/reportes/costos-productos/{id}/detalle` | GET | 🟡 | `GREATEST(0, TIMESTAMPDIFF(SECOND,...))` ×2 |

---

### 📁 Archivos con impacto menor

| Archivo | Ocurrencias | Funciones | Acción requerida |
|---|---|---|---|
| `auth.php` | 1 | `DATE_FORMAT` | 🟢 Baja prioridad |
| `buscar.php` | 2 | `DATE_FORMAT` ×2 | 🟢 Baja prioridad |
| `catalogs.php` | 1 | `DATE_FORMAT` | 🟢 Baja prioridad |
| `communications.php` | 2 | `DATE_FORMAT` ×2 | 🟢 Baja prioridad |
| `msg_service.php` | 4 | `GROUP_CONCAT` | 🟢 Baja prioridad |
| `printers.php` | 2 | `GROUP_CONCAT`, `JSON_OBJECT` | 🟡 Media |
| `administration.php` | 4 | `DATE_FORMAT`, `IFNULL` | 🟡 Media |
| `inventory.php` | 7 | `DATE_FORMAT`, `GROUP_CONCAT` | 🟡 Media |

---

## 📈 Resumen de Progreso

| Archivo | Total ocurrencias MySQL | Adaptadas | % |
|---|---|---|---|
| `employees.php` | 38 | 37 (5 endpoints) | **97%** ✅ |
| `tables.php` | 23 | 23 (6 endpoints) | **100%** ✅ |
| `manufacturing.php` | 32 | 3 (3 endpoints) | **9%** |
| `orders.php` | 46 | 0 | **0%** |
| `payments.php` | 34 | 0 | **0%** |
| `finance.php` | 22 | 0 | **0%** |
| `production.php` | 15 | 0 | **0%** |
| `reports.php` | 8 | 0 | **0%** |
| `products_reports.php` | 6 | 0 | **0%** |
| **Otros menores** | ~23 | 0 | **0%** |
| **TOTAL** | **~247** | **~63** | **~25%** |

---

## 🗺️ Orden de Trabajo Recomendado

### Fase 1 — Alta prioridad (interfaz activa) 
1. `manufacturing.php` → endpoints de `fecha_entrega` y `departamentos` (avanzar los de eficiencia ~3836)
2. `production.php` → inicio de producción, registro de momento

### Fase 2 — Pagos y finanzas
3. `payments.php` → listado de pagos (usan `%a`, `%v` de DATE_FORMAT)
4. `orders.php` → abono, asignación, dashboard comercialización
5. `finance.php` → detalle de movimientos

### Fase 3 — Reportes
6. `production.php` → consumo de tinta, reposiciones
7. `orders.php` → reportes de órdenes y comercialización
8. `reports.php` → reportes globales de eficiencia
9. `products_reports.php` → costos de productos
10. `manufacturing.php` → eficiencia de producción

### Fase 4 — Baja prioridad
11. `auth.php`, `buscar.php`, `catalogs.php`, `communications.php`, `msg_service.php`, `printers.php`, `administration.php`, `inventory.php`

---

## 🔧 Estado de la Infraestructura

| Componente | Estado |
|---|---|
| PostgreSQL 16 en `vps-contabo-dev` | ✅ Activo |
| `api_empresas` en PostgreSQL | ✅ Migrada |
| `api_emp_163`, `api_emp_193`, `api_emp_194`, `api_emp_195` | ✅ Aprovisionadas |
| `postgres_fdw` en todas las BDs | ✅ Configurado |
| Constante `DB_DRIVER` en `config.php` | ✅ Activa |
| `LocalDB.php` resiliencia locale/timezone | ✅ try/catch |
| Script `scripts/provision_company_db_postgres.sh` | ✅ Funcional |
| Rama Git `feature/postgresql-support` | ✅ Activa |

---

## 📋 Cómo Retomar el Trabajo

```bash
# 1. Verificar rama correcta
git -C /home/developer/Escritorio/niesys/ninesys-api branch

# 2. Ver BDs en el VPS
ssh vps-contabo-dev "su - postgres -c \"psql -l | grep api_\""

# 3. Probar el último endpoint adaptado
curl -s -H "Authorization: 194" "https://api.nineteengreen.com/empleados-todos"

# 4. Continuar adaptando manufacturing.php (por ejemplo, buscar TIMESTAMPDIFF en línea ~3836)
grep -n "TIMESTAMPDIFF" /home/developer/Escritorio/niesys/ninesys-api/app/routes/manufacturing.php

# 5. Tras modificar un archivo, commit y deploy:
git -C /home/developer/Escritorio/niesys/ninesys-api add app/routes/<archivo>.php
git -C /home/developer/Escritorio/niesys/ninesys-api commit -m "feat(pgsql): adaptar <endpoint> para PostgreSQL"
echo "2" | /home/developer/Escritorio/niesys/ninesys-hub/bin/deploy_backend.sh
```
