# 📊 Evaluación de Endpoints — Soporte Dual MariaDB / PostgreSQL
## Proyecto: `ninesys-api` | Rama: `feature/postgresql-support`
**Fecha de evaluación:** 2026-07-09  
**Estado general:** En progreso — Infraestructura ✅ | Endpoints: ~10% adaptados

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

| Endpoint | Método | Funciones adaptadas | Verificado |
|---|---|---|---|
| `/empleados` | GET | `DATE_FORMAT`, `WEEK`, `YEAR`, `GROUP_CONCAT` (deps), `IFNULL` | ✅ HTTP 200 |
| `/asistencias/semanal` | GET | `DATE_FORMAT` hora/fecha, `DAYOFWEEK`, `YEARWEEK` | ✅ HTTP 200 — 3 registros |
| `/empleados/dashboard-stats/{id}/{dep}` | GET | `TIMESTAMPDIFF(SECOND)` ×4, `DATE_FORMAT('%W')` ×2, `YEARWEEK` ×2 | ✅ HTTP 200 — todos los campos |

---

## 🔴 ENDPOINTS PENDIENTES DE ADAPTACIÓN

### 📁 `employees.php` — Pendientes

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| 639 | `/asistencias/tabla/{fecha}` | 🔴 Alta | `WEEK(NOW())`, `DATE_FORMAT` ×3, `UNIX_TIMESTAMP`, `DAYNAME`, `FIELD()` |
| 670 | `/asistencias/reporte/resumen/{fecha_inicio}/{fecha_fin}` | 🟡 Media | `TIMESTAMPDIFF(MINUTE)` ×2, `DATE_FORMAT` ×2, `FIELD()` |

---

### 📁 `tables.php` — Pendientes (23 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| 12 | `/ordenes-reporte-semanal-produccion/{fecha}` | 🔴 Alta | `DATE_FORMAT` ×2 |
| 80 | `/ordenes-reporte-semanal/{fecha}` | 🔴 Alta | `DATE_FORMAT` |
| 289 | `/ordenes/borrador/reporte-semanal/{id}/{dep}` | 🔴 Alta | `YEARWEEK`, `DATE_FORMAT` |
| 302 | Filtro semana actual (ruta anidada) | 🔴 Alta | `YEARWEEK(a.moment, 1) = YEARWEEK(CURDATE(), 1)` |
| 509 | `/table/ordenes-activas/{id}` | 🔴 Alta | `GROUP_CONCAT` ×3 |
| 683 | `/table/ordenes-todas` | 🔴 Alta | `GROUP_CONCAT`, `DATE_FORMAT` |
| 786 | `/ordenes/guardadas` | 🟡 Media | `DATE_FORMAT` ×3 |
| 843 | `/table/ordenes-con-deuda` | 🟡 Media | `DATE_FORMAT` ×2 |

---

### 📁 `manufacturing.php` — Pendientes (32 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| ~3216 | Fecha entrega (múltiples endpoints) | 🔴 Alta | `DATE_FORMAT('%d-%m-%Y')` ×3 |
| ~3513 | Órdenes con departamentos | 🔴 Alta | `IFNULL(CONCAT('[', GROUP_CONCAT...` ×2 |
| ~1944 | Ruta manufactura principal | 🟡 Media | `GROUP_CONCAT` |
| ~2820 | Tiempo por orden | 🟡 Media | `TIMESTAMPDIFF(SECOND)` ×2 |
| ~3836 | Eficiencia producción | 🟡 Media | `TIMESTAMPDIFF(SECOND)` ×6 |
| ~4132 | Reporte eficiencia extendido | 🟡 Media | `TIMESTAMPDIFF(SECOND)` ×2 |

---

### 📁 `orders.php` — Pendientes (46 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| ~758 | `/ordenes/abono/{id}` | 🔴 Alta | `DATE_FORMAT('%d/%m/%Y')`, `DATE_FORMAT('%h:%i %p')` ×6 |
| ~984 | `/orden/asignacion/{id}` | 🔴 Alta | `DATE_FORMAT('%d-%m-%Y')`, `GROUP_CONCAT(DISTINCT)` ×2 |
| ~1285 | `/comercializacion/dashboard/{id}/{dep}` | 🔴 Alta | `DATE_FORMAT`, `TIMESTAMPDIFF` |
| ~1470 | `/ordenes/reporte/{id}` | 🔴 Alta | `GROUP_CONCAT`, `DATE_FORMAT` |
| ~890 | `/reportes/resumen/disenadores/{id}/{dep}` | 🟡 Media | `TIMESTAMPDIFF(SECOND)` ×2, `GROUP_CONCAT(DISTINCT)` ×2 |
| ~935 | `/reportes/resumen/empleados/{id}/{dep}` | 🟡 Media | `TIMESTAMPDIFF(SECOND)` ×2, `GROUP_CONCAT(DISTINCT)` ×2 |
| ~1628 | `/comercializacion/ordenes/reporte` | 🟡 Media | `DATE_FORMAT`, `GROUP_CONCAT` |
| ~1660 | `/comercializacion/ordenes/reporte/terminadas/{rango}` | 🟡 Media | `DATE_FORMAT`, `YEARWEEK` |

---

### 📁 `payments.php` — Pendientes (34 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| ~603 | Pagos general (listado) | 🔴 Alta | `DATE_FORMAT('%a')`, `DATE_FORMAT('%v')`, `DATE_FORMAT('%d/%m/%y')` ×6 |
| ~742 | Subquery última fecha pago | 🔴 Alta | `DATE_FORMAT`, `WEEK(pa.fecha_pago, 1)`, `YEAR()` |
| ~814 | Pago directo por fecha | 🟡 Media | `DATE_FORMAT('%d/%m/%Y')` ×2 |
| ~1088 | Pagos detalle | 🟡 Media | `DATE_FORMAT('%d/%m/%Y')` |
| ~1164 | Pagos con fecha terminado | 🟡 Media | `DATE_FORMAT('%a')`, `DATE_FORMAT('%v')` |

---

### 📁 `production.php` — Pendientes (15 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| ~133 | Inicio de producción | 🔴 Alta | `DATE_FORMAT('%h:%i:%s %p')`, `DATE_FORMAT('%d-%m-%Y')` |
| ~396 | Registro de momento | 🔴 Alta | `DATE_FORMAT('%d/%m/%Y')`, `DATE_FORMAT('%I:%i %p')` ×2 |
| ~478 | Órdenes con insumos | 🟡 Media | `IFNULL(CONCAT('[', GROUP_CONCAT...` |
| ~982 | Reposiciones creadas | 🟡 Media | `DATE_FORMAT` ×2 |
| ~1032 | Consumo de tinta | 🟡 Media | `DATE_FORMAT('%d/%m/%Y %h:%i %p')` ×5 |

---

### 📁 `reports.php` — Pendientes (8 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| ~212 | `/reportes/costos-produccion/{inicio}/{fin}` | 🟡 Media | `GROUP_CONCAT(DISTINCT)` |
| ~671 | `/reportes/mano-obra-por-orden/{id}` | 🟡 Media | `TIMESTAMPDIFF(SECOND)` → horas |
| ~893 | Subquery categorías | 🟡 Media | `GROUP_CONCAT(DISTINCT cat2._id ORDER BY cat2._id SEPARATOR ',')` |
| ~990 | `/reportes/semanal-detallado` | 🟡 Media | `DATE_FORMAT` ×4 |
| ~1037 | `/reportes/employee-efficiency-global` | 🟡 Media | `TIMESTAMPDIFF(SECOND)` |

---

### 📁 `finance.php` — Pendientes (8 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| ~66 | Listado financiero | 🟡 Media | `IFNULL(GROUP_CONCAT...)` ×2 |
| ~88 | Detalle de movimiento | 🟡 Media | `DATE_FORMAT('%d/%m/%Y')`, `DATE_FORMAT('%h:%i %p')` ×3 |

---

### 📁 `products_reports.php` — Pendientes (6 ocurrencias)

| Línea | Endpoint | Prio | Funciones MySQL a reemplazar |
|---|---|---|---|
| ~238 | `/reportes/costos-productos` | 🟡 Media | `GREATEST(0, TIMESTAMPDIFF(SECOND,...))` ×2 |
| ~566 | `/reportes/costos-productos/categorias` | 🟡 Media | `GREATEST(0, TIMESTAMPDIFF(SECOND,...))` ×2 |
| ~870 | `/reportes/costos-productos/{id}/detalle` | 🟡 Media | `GREATEST(0, TIMESTAMPDIFF(SECOND,...))` ×2 |

---

### 📁 Archivos de baja prioridad

| Archivo | Ocurrencias | Funciones | Prioridad |
|---|---|---|---|
| `administration.php` | 4 | `DATE_FORMAT`, `IFNULL` | 🟢 Baja |
| `auth.php` | 1 | `DATE_FORMAT` | 🟢 Baja |
| `buscar.php` | 2 | `DATE_FORMAT` ×2 | 🟢 Baja |
| `catalogs.php` | 1 | `DATE_FORMAT` | 🟢 Baja |
| `communications.php` | 2 | `DATE_FORMAT` ×2 | 🟢 Baja |
| `inventory.php` | 7 | `DATE_FORMAT`, `GROUP_CONCAT` | 🟢 Baja |
| `msg_service.php` | 4 | `GROUP_CONCAT` | 🟢 Baja |
| `printers.php` | 2 | `GROUP_CONCAT`, `JSON_OBJECT` | 🟡 Media |

---

## 📈 Progreso General

| Archivo | Total ocurrencias | Adaptadas | % |
|---|---|---|---|
| `employees.php` | 38 | ~24 (3 endpoints) | **63%** |
| `orders.php` | 46 | 0 | 0% |
| `payments.php` | 34 | 0 | 0% |
| `manufacturing.php` | 32 | 0 | 0% |
| `tables.php` | 23 | 0 | 0% |
| `finance.php` | 22 | 0 | 0% |
| `production.php` | 15 | 0 | 0% |
| `reports.php` | 8 | 0 | 0% |
| `products_reports.php` | 6 | 0 | 0% |
| Otros menores | ~23 | 0 | 0% |
| **TOTAL** | **~247** | **~24** | **~10%** |

---

## 🗺️ Orden de Trabajo Recomendado

### Fase 1 — Alta prioridad (interfaz activa) 
1. `tables.php` → `/table/ordenes-activas`, `/table/ordenes-todas`, `/ordenes-reporte-semanal*`
2. `employees.php` → `/asistencias/tabla/{fecha}` (faltan: FIELD, UNIX_TIMESTAMP, DAYNAME)
3. `manufacturing.php` → endpoints de `fecha_entrega` y `departamentos`
4. `production.php` → inicio de producción, registro de momento

### Fase 2 — Pagos y finanzas
5. `payments.php` → listado de pagos (usan `%a`, `%v` de DATE_FORMAT)
6. `orders.php` → abono, asignación, dashboard comercialización
7. `finance.php` → detalle de movimientos

### Fase 3 — Reportes
8. `production.php` → consumo de tinta, reposiciones
9. `orders.php` → reportes de órdenes y comercialización
10. `reports.php` → reportes globales de eficiencia
11. `products_reports.php` → costos de productos
12. `manufacturing.php` → eficiencia de producción

### Fase 4 — Baja prioridad
13. `auth.php`, `buscar.php`, `catalogs.php`, `communications.php`, `msg_service.php`, `printers.php`, `administration.php`, `inventory.php`

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
curl -s -H "Authorization: 194" "https://api.nineteengreen.com/empleados/dashboard-stats/1/7"

# 4. Ver funciones MySQL pendientes en el siguiente archivo (tables.php)
grep -n "DATE_FORMAT\|GROUP_CONCAT\|YEARWEEK" \
  /home/developer/Escritorio/niesys/ninesys-api/app/routes/tables.php

# 5. Tras modificar un archivo, commit y deploy:
git -C /home/developer/Escritorio/niesys/ninesys-api add app/routes/<archivo>.php
git -C /home/developer/Escritorio/niesys/ninesys-api commit -m "feat(pgsql): adaptar <endpoint> para PostgreSQL"
echo "2" | /home/developer/Escritorio/niesys/ninesys-hub/bin/deploy_backend.sh
```
