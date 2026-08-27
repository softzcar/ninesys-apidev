# 🚀 Instrucciones para Claude Code: Continuar Adaptación a PostgreSQL

Este documento contiene el plan detallado, el contexto y los requerimientos del proyecto para continuar con la migración dual MariaDB / PostgreSQL de la API en el repositorio `ninesys-api`.

---

## 📅 Fases del Proyecto de Migración a PostgreSQL

El proyecto se estructuró originalmente en 4 fases de migración lógica:

1. **Fase 1: Flujo de Autenticación y Tablas Compartidas (100% Completado ✅)**
   - Corrección del login (`auth.php`).
   - Mapeo de vistas cruzadas del esquema `api_empresas` en la base de datos de PostgreSQL de la empresa piloto.
2. **Fase 2: Módulos de Operaciones y Procesos Core (100% Completado ✅)**
   - Adaptación de la API de empleados (`employees.php`), tablas maestras (`tables.php`), órdenes (`orders.php`), pagos (`payments.php`), finanzas (`finance.php`), producción (`production.php`) y manufactura (`manufacturing.php`).
3. **Fase 3: Módulos de Analíticas y Reportes (0% Completado ➔ En Progreso 🛠️)**
   - Adaptación de reportes generales de costos, eficiencia y semanal detallado (`reports.php`).
   - Adaptación de reportes de costos y análisis de productos (`products_reports.php`).
4. **Fase 4: Configuración y Administración General (Pendiente ⏳)**
   - Adaptación de rutas de administración (`administration.php`) e inventario (`inventory.php`).

---

## 📌 Contexto del Entorno de Trabajo

1. **Rama de Git activa:** `feature/postgresql-support` en `ninesys-api`.
2. **Base de Datos de Desarrollo (PostgreSQL):** 
   - Host: `localhost` (dentro del VPS de desarrollo `vps-contabo-dev`).
   - Puerto: `5432`.
   - Base de datos principal de configuración/control: `api_empresas`.
   - Base de datos piloto de empresa: `api_emp_194`.
   - Usuario: `dev_user` / Contraseña: `dev_pass`.
3. **Mecanismo de Federación (FDW - Foreign Data Wrapper):**
   - En PostgreSQL, la base de datos `api_emp_194` se conecta a `api_empresas` a través del esquema federado `api_empresas` (vía `postgres_fdw`).
   - Por ende, en consultas hechas a la base de datos de la empresa, la sintaxis `JOIN api_empresas.empresas_usuarios` es 100% válida.
   - En la base de datos `api_empresas` se crearon vistas de redirección bajo el esquema `api_empresas` hacia `public` para que las consultas con prefijo `api_empresas.tabla` funcionen de forma nativa sin alterar la compatibilidad con MySQL.

---

## 🚨 Reglas Críticas del Proyecto (Obligatorio)

1. **NO compilar el frontend** (`npm run build` / `nuxt build`). Las modificaciones se prueban en caliente.
2. **NO hay base de datos local en la máquina de desarrollo**. Todo acceso se realiza contra el VPS de desarrollo.
3. **Ubicación de Scripts de Despliegue:** Ejecutar únicamente desde el repositorio unificado de herramientas:
   - Backend Dev: `/home/developer/Escritorio/niesys/ninesys-hub/bin/deploy_backend.sh` (opción 2).
4. **Registro de Bitácora:** Al completar cada endpoint o conjunto de endpoints, se debe crear un log individual en `logs_gemini/` con formato:
   `YYYY-MM-DD_HH-MM-SS_tarea-[nombre_corto_de_tarea].log`

---

## 🎯 Tarea Pendiente 1: Adaptación de `app/routes/reports.php`

En este archivo se deben adaptar 4 endpoints para que funcionen con PostgreSQL manteniendo compatibilidad con MySQL.

### Estrategia de Conexión Cross-DB en PG vs. MySQL:
En MySQL, las consultas inician conectándose a `api_empresas` y haciendo saltos a `$companyDB.tabla`. En PostgreSQL, para evitar errores de aislamiento de base de datos, **debemos conectarnos directamente a la base de datos de la empresa** (`LOCAL_DNS`) y definir `$companyDB = 'public'`.

#### 1. Endpoint `/reportes/costos-produccion/{inicio}/{fin}` (Línea ~13)
* **Paso 1.1:** Condicionar la creación de la conexión y el prefijo `$companyDB`:
  ```php
  if (DB_DRIVER === 'pgsql') {
      $dbEmpresas = new LocalDB('', LOCAL_DNS, LOCAL_USER, LOCAL_PASS);
      $companyDB = 'public';
  } else {
      $adminDNS = str_replace('127.0.0.1', 'localhost', EMPRESAS_DNS);
      $dbEmpresas = new LocalDB('', $adminDNS, EMPRESAS_USER, EMPRESAS_PASS);
      $companyDB = LOCAL_DB;
  }
  ```
* **Paso 1.2:** Condicionar la definición de `$companyDB` más adelante (evitando que se sobrescriba con `LOCAL_DB` si el driver es `pgsql`).
* **Paso 1.3:** Adaptar `$empAsigSql` (Línea ~210). Reemplazar `GROUP_CONCAT` y `TIMEDIFF`:
  - **PG:** `string_agg(DISTINCT id_empleado::text, ',') as empleados` y `SUM(EXTRACT(EPOCH FROM (fecha_terminado::timestamp - fecha_inicio::timestamp)) / 3600) as tiempo_total`
  - **MySQL:** Se mantiene la consulta original.
* **Paso 1.4:** Adaptar `$sqlNoTracked` (Línea ~310). Cambiar `DATE(fecha_terminado)` a `fecha_terminado::date` cuando sea PG.
* **Paso 1.5:** Adaptar `$sqlTareas` (Línea ~379). Reemplazar `TIME_TO_SEC(TIMEDIFF(...))`:
  - **PG:** `EXTRACT(EPOCH FROM (a.fecha_terminado::timestamp - a.fecha_inicio::timestamp)) / 60 AS minutos_transcurridos`

#### 2. Endpoint `/reportes/mano-obra-por-orden/{id}` (Línea ~646)
* **Paso 2.1:** Condicionar `$dbEmpresas` (Línea ~651) para que se conecte a `LOCAL_DNS` en PostgreSQL y consulte el horario laboral en el esquema federado `api_empresas.empresas`.
* **Paso 2.2:** Adaptar `$sqlSalarios` (Línea ~666). Reemplazar `TIMESTAMPDIFF`:
  - **PG:** `SUM(EXTRACT(EPOCH FROM (COALESCE(ldea.fecha_terminado, NOW())::timestamp - ldea.fecha_inicio::timestamp)) / 3600) AS horas_trabajadas`

#### 3. Endpoint `/reportes/semanal-detallado` (Línea ~729)
* **Paso 3.1:** Condicionar la conexión `$dbEmpresas` y `$companyDB` al inicio de la verificación de fechas.
* **Paso 3.2:** Reemplazar `DATE(fecha_terminado)` por `fecha_terminado::date` en las consultas de `$sqlTotalProdPeriodo` y `$sqlNoTracked`.

#### 4. Endpoint `/reportes/employee-efficiency-global` (Línea ~808)
* **Paso 4.1:** Adaptar `$sqlOrders` (Línea ~875):
  - Cambiar `DATE(fecha_terminado)` a `fecha_terminado::date`.
  - Reemplazar `GROUP_CONCAT` de categorías y el `FIND_IN_SET` para buscar categorías:
    - **PG:** `string_agg(DISTINCT cat2._id::text, ',' ORDER BY cat2._id::text) AS _category_ids`
    - **PG:** `JOIN categories cat2 ON cat2._id::text = ANY(string_to_array(p2.category_ids, ','))`
* **Paso 4.2:** Adaptar `$sqlTop` y `$sqlAll` cambiando `DATE(fecha_terminado)` a `fecha_terminado::date`.
* **Paso 4.3:** Adaptar `$sqlCategories` (Línea ~952) cambiando `FIND_IN_SET` a `ANY(string_to_array(...))` y `DATE(fecha_terminado)` a `fecha_terminado::date`.
* **Paso 4.4:** Adaptar `$sqlTasks` (Línea ~982) cambiando `DATE_FORMAT` a `TO_CHAR(MIN(ldea.fecha_inicio), 'DD/MM/YYYY HH12:MI AM')`.
* **Paso 4.5:** Adaptar `$sqlTime` (Línea ~1028) cambiando `TIMESTAMPDIFF` a `EXTRACT(EPOCH)`.

---

## 🎯 Tarea Pendiente 2: Adaptación de `app/routes/products_reports.php`

En este archivo se calcula el costo de producción de productos. Contiene múltiples llamadas a `TIMESTAMPDIFF` envueltas en `GREATEST(0, ...)` para evitar tiempos negativos en tareas en curso.

### Endpoints a modificar:
1. `/reportes/costos-productos` (Línea ~238 y ~265).
2. `/reportes/costos-productos/categorias` (Línea ~566 y ~592).
3. `/reportes/costos-productos/{id}/detalle` (Línea ~870 y ~924).

### Equivalencia PostgreSQL para la fórmula de tiempo real:
- **MySQL:**
  `GREATEST(0, TIMESTAMPDIFF(SECOND, ldea.fecha_inicio, COALESCE(ldea.fecha_terminado, NOW())) / 3600)`
- **PostgreSQL:**
  `GREATEST(0, EXTRACT(EPOCH FROM (COALESCE(ldea.fecha_terminado, NOW())::timestamp - ldea.fecha_inicio::timestamp)) / 3600)`

Se debe estructurar cada consulta SQL de los endpoints anteriores usando un condicional `if (DB_DRIVER === 'pgsql')` para implementar la versión de PostgreSQL correspondiente.

---

## 🛠️ Flujo de Trabajo Sugerido para Claude Code:

1. **Editar `app/routes/reports.php`:** Implementar los condicionales indicados arriba.
2. **Validar sintaxis:** Ejecutar `php -l app/routes/reports.php`.
3. **Comprometer cambios:** Hacer commit en git (`git commit -m "fix(pgsql): ..."`) y desplegar a desarrollo mediante:
   `echo "2" | /home/developer/Escritorio/niesys/ninesys-hub/bin/deploy_backend.sh`
4. **Verificar vía API:** Crear scripts PHP temporales en `public/` (ej: `test_reports.php`) para enviar peticiones mock a los endpoints `/reportes/costos-produccion/2026-07-01/2026-07-07` y `/reportes/employee-efficiency-global?inicio=2026-07-01&fin=2026-07-07` y ejecutarlos en el VPS.
5. **Repetir el proceso** para `app/routes/products_reports.php`.
6. **Escribir las Bitácoras correspondientes** en `logs_gemini/`.
