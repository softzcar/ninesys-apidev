# Plan de Migración Seguro - Empresa 163 (Nineteen Custom)

**Fecha:** 11 de enero de 2026  
**Hora:** 15:10  
**Empresa:** 163 - Nineteen Custom (PRODUCCIÓN)  
**Estado:** Base de datos con datos reales  
**Backup:** ✅ api_emp_163_bak (confirmado)

---

## ⚠️ ADVERTENCIA CRÍTICA

Esta base de datos contiene **datos reales en producción**. Cualquier error en la migración puede afectar las operaciones de la empresa. Se requiere máxima precaución.

---

## Resumen Ejecutivo

### ✅ Resultados del Análisis

- **Total de tablas comparadas:** 63
- **Tablas idénticas:** 61 (96.8%)
- **Tablas con diferencias:** 2 (3.2%)
- **Tablas faltantes:** 0
- **Columnas a agregar:** 4

### 📊 Impacto de la Migración

| Nivel de Riesgo | Descripción |
|-----------------|-------------|
| **🟢 BAJO** | Las modificaciones son solo adiciones de columnas (ALTER TABLE ADD COLUMN) |
| **🟢 BAJO** | No se modifican ni eliminan datos existentes |
| **🟢 BAJO** | Todas las columnas nuevas permiten NULL o tienen valores por defecto |
| **✅ SEGURO** | El backup está confirmado y disponible |

---

## 1. Diferencias Identificadas

### 1.1 Tabla `config`

**Columna faltante:**
- `multiplicador_precio` DECIMAL(5,2) NOT NULL DEFAULT 0.00

**Descripción:**
Campo para almacenar el multiplicador de precio predeterminado para conversión USD a VES. Este campo se usa en la funcionalidad de conversión de moneda que se implementó recientemente.

**Impacto:**
- ✅ **SIN RIESGO** - Tiene valor por defecto (0.00)
- ✅ Los datos existentes no se afectan
- ✅ La aplicación puede seguir funcionando normalmente

**Comando de migración:**
```sql
ALTER TABLE `config` 
ADD COLUMN `multiplicador_precio` DECIMAL(5,2) NOT NULL DEFAULT 0.00 
COMMENT 'Multiplicador de precio predeterminado para conversión USD a VES';
```

---

### 1.2 Tabla `presupuestos_productos`

**Columnas faltantes:**
- `id_products_attributes` INT(11) NULL
- `id_size` INT(11) NULL
- `id_tela` INT(11) NULL

**Descripción:**
Estos campos permiten relacionar los productos de presupuestos con:
- Atributos de productos (ej: color, acabado especial)
- Tallas específicas (referencia a tabla `sizes`)
- Tela específica del catálogo (referencia a tabla `catalogo_telas`)

**Impacto:**
- ✅ **SIN RIESGO** - Todas permiten NULL
- ✅ Los presupuestos existentes quedarán con estos campos en NULL
- ✅ Los nuevos presupuestos podrán usar estos campos
- ✅ No se pierden datos

**Comandos de migración:**
```sql
ALTER TABLE `presupuestos_productos` 
ADD COLUMN `id_products_attributes` INT(11) NULL 
COMMENT 'ID de la variante del producto';

ALTER TABLE `presupuestos_productos` 
ADD COLUMN `id_size` INT(11) NULL 
COMMENT 'ID de la talla';

ALTER TABLE `presupuestos_productos` 
ADD COLUMN `id_tela` INT(11) NULL 
COMMENT 'ID de la tela a utilizar del catálogo de telas';
```

---

## 2. Tablas Completamente Actualizadas

Las siguientes **61 tablas** ya están actualizadas y no requieren modificaciones:

<details>
<summary>Ver lista completa de tablas actualizadas (Click para expandir)</summary>

1. abonos ✓
2. aprobacion_clientes ✓
3. asistencias ✓
4. caja ✓
5. caja_cierres ✓
6. caja_fondos ✓
7. catalogo_impresoras ✓
8. catalogo_insumos_productos ✓
9. catalogo_telas ✓
10. categories ✓
11. check_tareas ✓
12. comisiones_pagados ✓
13. customers ✓
14. departamentos ✓
15. disenos ✓
16. disenos_ajustes_y_personalizaciones ✓
17. empleados_lotes_fabricacion ✓
18. empleados_lotes_fabricacion_items ✓
19. empleados_salario ✓
20. inventario ✓
21. inventario_movimientos ✓
22. lotes ✓
23. lotes_detalles ✓
24. lotes_detalles_empleados_asignados ✓
25. lotes_detalles_empleados_asignados_pausas ✓
26. lotes_fisicos ✓
27. lotes_historico_solicitadas ✓
28. lotes_movimientos ✓
29. metodos_de_pago ✓
30. ordenes ✓
31. ordenes_borrador_empleado ✓
32. ordenes_fila_orden ✓
33. ordenes_fila_orden_cambios ✓
34. ordenes_fila_reposiciones ✓
35. ordenes_observaciones ✓
36. ordenes_productos ✓
37. ordenes_tmp ✓
38. ordenes_vinculadas ✓
39. pagos ✓
40. pagos_abonos ✓
41. pagos_descuentos ✓
42. pagos_salarios ✓
43. piezas_cortadas ✓
44. presupuestos ✓
45. product_insumos_asignados ✓
46. products ✓
47. products_attributes ✓
48. products_attributes_values ✓
49. products_comisiones ✓
50. products_prices ✓
51. products_sizes_eficiencia ✓
52. products_tiempos_de_produccion ✓
53. rendimiento ✓
54. reposiciones ✓
55. retiros ✓
56. revisiones ✓
57. salario_carga_familiar ✓
58. sizes ✓
59. tinta_filtro ✓
60. tintas ✓
61. tintas_recargas ✓

</details>

---

## 3. Script de Migración SQL

### 3.1 Script Generado Automáticamente

```sql
-- =============================================================
-- SCRIPT DE MIGRACIÓN PARA EMPRESA 163 (Nineteen Custom)
-- Fecha de generación: 11 de enero de 2026
-- =============================================================
-- IMPORTANTE: Este script actualiza la estructura sin afectar datos
-- Backup disponible en: api_emp_163_bak
-- =============================================================

USE api_emp_163;

-- =============================================================
-- TABLA: config - Agregar campo multiplicador_precio
-- =============================================================
ALTER TABLE `config` 
ADD COLUMN `multiplicador_precio` DECIMAL(5,2) NOT NULL DEFAULT 0.00
COMMENT 'Multiplicador de precio predeterminado para conversión USD a VES';

-- =============================================================
-- TABLA: presupuestos_productos - Agregar campos de relación
-- =============================================================
ALTER TABLE `presupuestos_productos` 
ADD COLUMN `id_products_attributes` INT(11) NULL
COMMENT 'ID de la variante del producto';

ALTER TABLE `presupuestos_productos` 
ADD COLUMN `id_size` INT(11) NULL
COMMENT 'ID de la talla';

ALTER TABLE `presupuestos_productos` 
ADD COLUMN `id_tela` INT(11) NULL
COMMENT 'ID de la tela a utilizar del catálogo de telas';

-- =============================================================
-- FIN DEL SCRIPT DE MIGRACIÓN
-- =============================================================
```

---

## 4. Plan de Ejecución Seguro

### 4.1 Pre-requisitos (Verificación)

- [x] ✅ Backup de base de datos creado (`api_emp_163_bak`)
- [ ] 🔲 Verificar que el backup es reciente y completo
- [ ] 🔲 Notificar a usuarios sobre ventana de mantenimiento
- [ ] 🔲 Revisar el script de migración línea por línea

### 4.2 Pasos de Ejecución

#### Paso 1: Verificar el Backup

```bash
ssh vps-ninesys "mysql -u root -e 'SELECT COUNT(*) as total_tablas FROM information_schema.TABLES WHERE TABLE_SCHEMA = \"api_emp_163_bak\";'"
```

**Resultado esperado:** Debe mostrar 63 tablas

#### Paso 2: Verificar Conexión a la Base de Datos

```bash
ssh vps-ninesys "mysql -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' api_emp_163 -e 'SELECT DATABASE();'"
```

**Resultado esperado:** `api_emp_163`

#### Paso 3: Ejecutar el Script de Migración

**Opción A: Ejecución Remota (Recomendada)**
```bash
ssh vps-ninesys "mysql -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' api_emp_163" < /tmp/migration_script_163.sql
```

**Opción B: Ejecución Directa en el VPS**
```bash
# Copiar script al VPS
scp /tmp/migration_script_163.sql vps-ninesys:/tmp/

# Ejecutar en el VPS
ssh vps-ninesys "mysql -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' api_emp_163 < /tmp/migration_script_163.sql"
```

#### Paso 4: Verificar la Migración

**Verificar campo `multiplicador_precio` en tabla `config`:**
```bash
ssh vps-ninesys "mysql -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' api_emp_163 -e 'DESCRIBE config;' | grep multiplicador"
```

**Verificar campos nuevos en `presupuestos_productos`:**
```bash
ssh vps-ninesys "mysql -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' api_emp_163 -e 'DESCRIBE presupuestos_productos;' | grep -E '(id_products_attributes|id_size|id_tela)'"
```

#### Paso 5: Comparación Post-Migración

Re-ejecutar el script de comparación para confirmar que ahora son idénticas:
```bash
/tmp/analyze_migration_163.sh
```

**Resultado esperado:** 0 diferencias, 63 tablas idénticas

### 4.3 Plan de Rollback (En caso de problemas)

Si algo sale mal durante la migración:

```sql
-- Restaurar desde el backup
DROP DATABASE api_emp_163;
CREATE DATABASE api_emp_163;

-- Restaurar datos
mysqldump api_emp_163_bak | mysql api_emp_163
```

O usando el usuario de la aplicación:
```bash
ssh vps-ninesys "mysqladmin -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' drop api_emp_163 --force"
ssh vps-ninesys "mysqladmin -u root create api_emp_163"
ssh vps-ninesys "mysqldump -u root api_emp_163_bak | mysql -u root api_emp_163"
```

---

## 5. Cronograma Recomendado

### Ventana de Mantenimiento Sugerida

| Duración Estimada | Tiempo de Ejecución Real |
|-------------------|--------------------------|
| 5-10 minutos | Los 4 ALTER TABLE son muy rápidos (columnas nuevas sin datos) |

**Horario recomendado:**
- Fuera del horario laboral de la empresa
- Preferiblemente en horario de bajo tráfico
- Con personal técnico disponible para monitoreo

**Cronograma detallado:**
1. T-0: Notificar a usuarios (emails/WhatsApp)
2. T+0: Inicio de ventana de mantenimiento
3. T+1min: Verificación de backup
4. T+2min: Ejecución del script de migración
5. T+4min: Verificación de cambios
6. T+5min: Pruebas básicas de funcionalidad
7. T+10min: Fin de mantenimiento, notificar a usuarios

---

## 6. Pruebas Post-Migración

### 6.1 Pruebas de Integridad

1. **Verificar que la tabla `config` tiene el nuevo campo:**
   ```sql
   SELECT multiplicador_precio FROM config WHERE _id = 1;
   ```
   **Resultado esperado:** 0.00

2. **Verificar que `presupuestos_productos` tiene los nuevos campos:**
   ```sql
   DESCRIBE presupuestos_productos;
   ```
   **Resultado esperado:** Mostrar las 3 columnas nuevas

3. **Verificar que los datos existentes no se afectaron:**
   ```sql
   -- Contar registros en tablas críticas
   SELECT COUNT(*) as ordenes FROM ordenes;
   SELECT COUNT(*) as presupuestos FROM presupuestos;
   SELECT COUNT(*) as clientes FROM customers;
   ```
   **Resultado esperado:** Mismo conteo que antes de la migración

### 6.2 Pruebas Funcionales

1. **Crear un nuevo presupuesto** desde la aplicación
2. **Verificar el multiplicador de precio** en la configuración de la empresa
3. **Revisar logs de la aplicación** para errores relacionados con campos faltantes

---

## 7. Beneficios de la Actualización

### 7.1 Nuevas Funcionalidades Habilitadas

1. **Multiplicador de precios USD→VES**
   - Permite configurar un multiplicador predeterminado para conversión de moneda
   - Facilita la gestión de precios en bolívares
   
2. **Presupuestos más detallados**
   - Relación directa con atributos de productos
   - Vinculación con tallas específicas del catálogo
   - Referencia a telas del inventario

### 7.2 Compatibilidad con Código Actual

- ✅ El código de la aplicación ya está preparado para usar estos campos
- ✅ Los endpoints de la API ya manejan estos campos
- ✅ No se requieren cambios en el frontend ni backend

---

## 8. Archivos Generados

### 8.1 Scripts y Reportes

| Archivo | Ubicación | Descripción |
|---------|-----------|-------------|
| Script de análisis | `/tmp/analyze_migration_163.sh` | Script Bash de comparación |
| Reporte técnico | `/tmp/db_migration_analysis_163_to_171.txt` | Análisis detallado de diferencias |
| Script SQL | `/tmp/migration_script_163.sql` | Comandos ALTER TABLE para migración |
| Este plan | `logs_gemini/2026-01-11_15-15-00_plan-migracion-empresa-163.md` | Plan completo de migración |

### 8.2 Credenciales de Acceso

**Empresa 163 (Nineteen Custom - PRODUCCIÓN):**
- Base de datos: `api_emp_163`
- Backup: `api_emp_163_bak`
- Usuario: `api_user_163`
- Password: `c45ff25ef00ce4ebb0fca422`

---

## 9. Recomendaciones Finales

### 9.1 Antes de Ejecutar

1. ✅ Confirmar que el backup `api_emp_163_bak` es reciente
2. ✅ Notificar a los usuarios de Nineteen Custom sobre el mantenimiento
3. ✅ Revisar el script SQL línea por línea
4. ✅ Tener preparado el plan de rollback

### 9.2 Durante la Ejecución

1. ⏱️ Monitorear el tiempo de ejecución
2. 📝 Documentar cualquier error o advertencia
3. 🔍 Verificar cada paso antes de continuar al siguiente

### 9.3 Después de la Ejecución

1. ✅ Ejecutar todas las pruebas de verificación
2. ✅ Confirmar con usuarios que todo funciona correctamente
3. ✅ Crear un log de la migración exitosa
4. ✅ Actualizar documentación

---

## 10. Comandos Resumidos para Ejecución

### Ejecución Completa en Una Sola Línea

```bash
# Verificar backup → Ejecutar migración → Verificar resultado
ssh vps-ninesys "mysql -u root -e 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=\"api_emp_163_bak\";'" && \
scp /tmp/migration_script_163.sql vps-ninesys:/tmp/ && \
ssh vps-ninesys "mysql -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' api_emp_163 < /tmp/migration_script_163.sql" && \
ssh vps-ninesys "mysql -u api_user_163 -p'c45ff25ef00ce4ebb0fca422' api_emp_163 -e 'DESCRIBE config;' | grep multiplicador" && \
echo "✅ Migración completada exitosamente"
```

---

## 11. Conclusión

La migración es **de bajo riesgo** y solo involucra agregar columnas nuevas sin modificar datos existentes. Con el backup confirmado y los pasos claramente definidos, la actualización se puede realizar de manera segura.

**Estado: LISTO PARA EJECUTAR** ✅

---

**Preparado por:** Gemini AI Assistant  
**Revisado:** Pendiente  
**Aprobado para ejecución:** Pendiente  
**Fecha de ejecución planeada:** A definir por el usuario

---

**FIN DEL PLAN DE MIGRACIÓN**
