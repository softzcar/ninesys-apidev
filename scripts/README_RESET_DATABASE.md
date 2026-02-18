# Script de Reinicio de Base de Datos de Empresa

## 📋 Descripción

El script `reset_company_database.sh` permite reiniciar completamente la base de datos de una empresa específica, eliminando **TODOS** los datos operacionales pero manteniendo:
- ✅ Estructura de tablas
- ✅ Configuración base (`config`, `departamentos`, `sizes`)
- ✅ Catálogos (`catalogo_insumos_productos`, `catalogo_telas`, etc.)
- ✅ Productos configurados (`products`, `products_comisiones`, etc.)

## 🚨 Advertencia

Este script es **EXTREMADAMENTE PELIGROSO** y eliminará permanentemente:
- ❌ Todas las órdenes y sus productos
- ❌ Todos los clientes
- ❌ Todo el inventario y movimientos
- ❌ Todos los pagos y abonos
- ❌ Todos los diseños y revisiones
- ❌ Todos los lotes de producción
- ❌ **TODO** el historial operacional

## 📦 Características de Seguridad

1. **Backup automático**: Crea un backup completo antes de proceder
2. **Doble confirmación**: Requiere dos confirmaciones del usuario
3. **Validación robusta**: Verifica que la base de datos existe
4. **Logging detallado**: Registra cada operación en un archivo de log
5. **Manejo de errores**: Detiene el proceso si algo falla

## 🔧 Uso

### Sintaxis Básica

```bash
./scripts/reset_company_database.sh <ID_EMPRESA>
```

### Ejemplos

```bash
# Reiniciar la empresa 174
cd /home/developer/Escritorio/Antigravity/ninesys-apidev
./scripts/reset_company_database.sh 174

# Reiniciar la empresa 171 (pruebas)
./scripts/reset_company_database.sh 171
```

## 📝 Proceso de Ejecución

### 1. Validación
El script verifica:
- Que se proporcionó un ID de empresa
- Que el ID es numérico
- Que la base de datos existe

### 2. Confirmaciones de Seguridad

**Primera confirmación:**
```
¿Estás ABSOLUTAMENTE SEGURO de que deseas continuar?
Responder: SI ELIMINAR
```

**Segunda confirmación:**
```
Escribe el ID de la empresa (174) para proceder:
Responder: 174
```

### 3. Backup Automático

Se crea un backup completo en:
```
/home/backups/company_resets/backup_api_emp_174_20260206_112030.sql
```

### 4. Limpieza de Datos

El script trunca 38 tablas operacionales:
- `ordenes`, `ordenes_productos`, `ordenes_tmp`, etc.
- `customers`
- `metodos_de_pago`, `pagos`, `abonos`
- `lotes`, `lotes_detalles`, etc.
- `inventario`, `inventario_movimientos`, etc.
- `disenos`, `revisiones`
- Y muchas más...

### 5. Reset de AUTO_INCREMENT

Reinicia los contadores a 1 para:
- `ordenes` → La próxima orden será #1
- `customers` → El próximo cliente será ID 1
- `inventario` → El próximo insumo será ID 1
- Y todas las tablas principales

### 6. Datos de Prueba

Opcionalmente inserta un cliente de prueba (ID: 1) si no existen clientes.

## 📊 Salida del Script

### Durante la ejecución:
```
════════════════════════════════════════════════════════
  Verificando base de datos: api_emp_174
════════════════════════════════════════════════════════
✓ Base de datos encontrada: api_emp_174

⚠ ═══════════════════════════════════════════════════════════════
⚠                     ⚠️  ADVERTENCIA CRÍTICA  ⚠️
⚠ ═══════════════════════════════════════════════════════════════

...

════════════════════════════════════════════════════════
  Limpiando datos operacionales
════════════════════════════════════════════════════════
ℹ Programando limpieza: ordenes
ℹ Programando limpieza: ordenes_productos
...
✓ Limpieza de datos completada

════════════════════════════════════════════════════════
  ✅ PROCESO COMPLETADO EXITOSAMENTE
════════════════════════════════════════════════════════
```

## 📁 Archivos Generados

### Backup
```
/home/backups/company_resets/backup_api_emp_174_20260206_112030.sql
```

### Log
```
/var/log/ninesys/reset_api_emp_174_20260206_112030.log
```

Ejemplo de log:
```
[2026-02-06 11:20:30] Iniciando proceso de reset para empresa ID: 174 (DB: api_emp_174)
[2026-02-06 11:20:30] Confirmaciones completadas - Iniciando reset
[2026-02-06 11:20:31] Iniciando backup a: /home/backups/company_resets/backup_api_emp_174_20260206_112030.sql
[2026-02-06 11:20:35] Backup completado: /home/backups/company_resets/backup_api_emp_174_20260206_112030.sql (2.3M)
[2026-02-06 11:20:35] Iniciando truncado de tablas
[2026-02-06 11:20:35] Agregando TRUNCATE para tabla: ordenes
...
[2026-02-06 11:20:36] Limpieza completada exitosamente
[2026-02-06 11:20:36] AUTO_INCREMENT reseteado para: ordenes
...
[2026-02-06 11:20:37] Proceso completado exitosamente
```

## 🛡️ Recuperación de Datos

Si necesitas restaurar el backup:

```bash
# Listar backups disponibles
ls -lh /home/backups/company_resets/

# Restaurar un backup específico
mysql -u root api_emp_174 < /home/backups/company_resets/backup_api_emp_174_20260206_112030.sql
```

## ⚙️ Configuración

Si necesitas ajustar el script, las variables principales están al inicio:

```bash
BACKUP_DIR="/home/backups/company_resets"
LOG_DIR="/var/log/ninesys"
MYSQL_USER="root"
MYSQL_HOST="localhost"
```

## 🔍 Tablas que se Mantienen Intactas

Estas tablas **NO** se truncan (mantienen su configuración):

- `config` - Configuración general de la empresa
- `departamentos` - Departamentos de producción
- `sizes` - Catálogo de tallas
- `catalogo_insumos_productos` - Tipos de insumos
- `catalogo_telas` - Catálogo de telas
- `catalogo_impresoras` - Impresoras registradas
- `categories` - Categorías de productos
- `products` - Catálogo de productos
- `products_comisiones` - Comisiones por producto/departamento
- `products_prices` - Precios de productos
- `products_tiempos_de_produccion` - Tiempos de producción
- `product_insumos_asignados` - Insumos asignados a productos
- `products_attributes` - Atributos de productos
- `products_sizes_eficiencia` - Eficiencia por talla
- `empleados_salario` - Configuración salarial
- `salario_carga_familiar` - Carga familiar
- `tinta_filtro` - Filtro de tintas

## 📌 Casos de Uso

### Caso 1: Resetear empresa de pruebas
```bash
# Empresa 171 es para pruebas
./scripts/reset_company_database.sh 171
```

### Caso 2: Reiniciar empresa para nuevo año fiscal
```bash
# Resetear empresa 174 al iniciar nuevo año
./scripts/reset_company_database.sh 174
```

### Caso 3: Limpiar datos después de migración fallida
```bash
# Si una migración salió mal, resetear y volver a intentar
./scripts/reset_company_database.sh 163
```

## ⚠️ Precauciones

1. **NUNCA** ejecutes este script en producción sin un backup manual previo
2. **SIEMPRE** verifica el ID de la empresa antes de confirmar
3. **Guarda** los backups en un lugar seguro
4. **Avisa** a los usuarios que la empresa estará temporalmente fuera de servicio
5. **Prueba** primero en una empresa de desarrollo (171)

## 🔒 Permisos

El script debe ser ejecutable:
```bash
chmod +x scripts/reset_company_database.sh
```

Y requiere acceso root a MySQL (o credenciales con permisos suficientes).

## 📞 Soporte

Si el script falla:
1. Revisa el log en `/var/log/ninesys/reset_api_emp_*.log`
2. Verifica que el backup se creó correctamente
3. Si es necesario, restaura desde el backup
4. Contacta al administrador del sistema

---

**Última actualización**: 2026-02-06
**Versión**: 1.0
**Mantenedor**: Equipo de Desarrollo NINESYS
