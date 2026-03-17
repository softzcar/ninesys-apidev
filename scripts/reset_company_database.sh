#!/bin/bash

################################################################################
# Script: reset_company_database.sh
# Descripción: Reinicia completamente la base de datos de una empresa
#              eliminando TODOS los datos operacionales y dejando solo
#              la estructura y configuración base.
# Uso: ./reset_company_database.sh <ID_EMPRESA>
# Ejemplo: ./reset_company_database.sh 174
# 
# ADVERTENCIA: Este script eliminará PERMANENTEMENTE todos los datos de:
#   - Órdenes y productos
#   - Clientes
#   - Inventario y movimientos
#   - Pagos y abonos
#   - Diseños y revisiones
#   - Lotes de producción
#   - TODO el historial operacional
#
# Funcionalidades:
#   - Backup automático antes de proceder
#   - Validación de entrada
#   - Confirmación doble del usuario
#   - Log detallado de operaciones
#   - Restauración de AUTO_INCREMENT a 1
################################################################################

set -e  # Salir en caso de error

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Sin color

# Configuración
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Si estamos en VPS, usar rutas del servidor
if [ -d "/home/apidev.nineteengreen.com" ]; then
    BACKUP_DIR="/home/backups/company_resets"
    LOG_DIR="/home/apidev.nineteengreen.com/logs_reset"
else
    # Si estamos en local, usar rutas del proyecto
    PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
    BACKUP_DIR="$PROJECT_DIR/backups/company_resets"
    LOG_DIR="$PROJECT_DIR/logs_gemini"
fi
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Credenciales MySQL (Ajustar según entorno de ejecución)
MYSQL_USER="${DB_USER:-root}"
MYSQL_PASS="${DB_PASS:-}"
MYSQL_HOST="${DB_HOST:-localhost}"

################################################################################
# Funciones auxiliares
################################################################################

print_header() {
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

################################################################################
# Validación de parámetros
################################################################################

if [ $# -eq 0 ]; then
    print_error "Error: No se proporcionó el ID de la empresa"
    echo ""
    echo "Uso: $0 <ID_EMPRESA>"
    echo "Ejemplo: $0 174"
    exit 1
fi

COMPANY_ID=$1
DB_NAME="api_emp_${COMPANY_ID}"
DB_USER="api_user_${COMPANY_ID}"
BACKUP_FILE="${BACKUP_DIR}/backup_${DB_NAME}_${TIMESTAMP}.sql"
LOG_FILE="${LOG_DIR}/reset_${DB_NAME}_${TIMESTAMP}.log"

# Validar que el ID sea numérico
if ! [[ "$COMPANY_ID" =~ ^[0-9]+$ ]]; then
    print_error "Error: El ID de empresa debe ser numérico"
    exit 1
fi

# Crear directorios si no existen
mkdir -p "$BACKUP_DIR"
mkdir -p "$LOG_DIR"

################################################################################
# Verificar que la base de datos existe
################################################################################

print_header "Verificando base de datos: $DB_NAME"

if ! mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    print_error "Error: La base de datos '$DB_NAME' no existe"
    exit 1
fi

print_success "Base de datos encontrada: $DB_NAME"
log_message "Iniciando proceso de reset para empresa ID: $COMPANY_ID (DB: $DB_NAME)"

################################################################################
# Mostrar advertencia y solicitar confirmación
################################################################################

echo ""
print_warning "═══════════════════════════════════════════════════════════════"
print_warning "                    ⚠️  ADVERTENCIA CRÍTICA  ⚠️"
print_warning "═══════════════════════════════════════════════════════════════"
echo ""
print_warning "Estás a punto de ELIMINAR PERMANENTEMENTE todos los datos de:"
echo ""
echo "  📊 Base de datos: $DB_NAME"
echo "  🏢 Empresa ID: $COMPANY_ID"
echo ""
print_warning "Se eliminarán:"
echo "  • Todas las ÓRDENES y sus productos"
echo "  • Todos los CLIENTES"
echo "  • Todo el INVENTARIO y sus movimientos"
echo "  • Todos los PAGOS y abonos"
echo "  • Todos los DISEÑOS y revisiones"
echo "  • Todos los LOTES de producción"
echo "  • TODO el historial operacional"
echo ""
print_info "Se mantendrán:"
echo "  • Estructura de tablas"
echo "  • Configuración base (config, departamentos, sizes, etc.)"
echo "  • Catálogos básicos"
echo ""
print_success "Se creará un backup en: $BACKUP_FILE"
echo ""
print_warning "═══════════════════════════════════════════════════════════════"
echo ""

echo "¿Estás ABSOLUTAMENTE SEGURO de que deseas continuar? (escribe 'SI ELIMINAR' para confirmar): "
read CONFIRM1

if [ "$CONFIRM1" != "SI ELIMINAR" ]; then
    print_warning "Operación cancelada por el usuario"
    log_message "Operación cancelada: confirmación 1 fallida"
    exit 0
fi

echo ""
echo "Segunda confirmación - Escribe el ID de la empresa ($COMPANY_ID) para proceder: "
read CONFIRM2

if [ "$CONFIRM2" != "$COMPANY_ID" ]; then
    print_warning "Operación cancelada: ID de empresa no coincide"
    log_message "Operación cancelada: confirmación 2 fallida"
    exit 0
fi

echo ""
print_header "Confirmación recibida - Iniciando proceso"
log_message "Confirmaciones completadas - Iniciando reset"

################################################################################
# Crear backup de la base de datos
################################################################################

print_info "Creando backup de seguridad..."
log_message "Iniciando backup a: $BACKUP_FILE"

if mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    print_success "Backup creado exitosamente: $BACKUP_FILE ($BACKUP_SIZE)"
    log_message "Backup completado: $BACKUP_FILE ($BACKUP_SIZE)"
else
    print_error "Error al crear el backup"
    log_message "ERROR: Falló la creación del backup"
    exit 1
fi

################################################################################
# Desactivar verificación de llaves foráneas y limpiar datos
################################################################################

print_header "Limpiando datos operacionales"

# Lista de tablas a truncar (datos operacionales)
TABLES_TO_TRUNCATE=(
    "ordenes"
    "ordenes_productos"
    "ordenes_vinculadas"
    "ordenes_observaciones"
    "ordenes_fila_orden"
    "ordenes_fila_orden_cambios"
    "ordenes_fila_reposiciones"
    "ordenes_borrador_empleado"
    "ordenes_auditoria"
    "customers"
    "presupuestos"
    "presupuestos_productos"
    "abonos"
    "metodos_de_pago"
    "pagos"
    "pagos_abonos"
    "pagos_descuentos"
    "pagos_salarios"
    "comisiones_pagados"
    "lotes"
    "lotes_detalles"
    "lotes_detalles_empleados_asignados"
    "lotes_detalles_empleados_asignados_pausas"
    "lotes_fisicos"
    "lotes_corte_ajustes"
    "lotes_historico_solicitadas"
    "lotes_movimientos"
    "empleados_lotes_fabricacion"
    "empleados_lotes_fabricacion_items"
    "inventario"
    "inventario_movimientos"
    "inventario_movimientos_historial"
    "inventario_remanentes"
    "disenos"
    "disenos_ajustes_y_personalizaciones"
    "revisiones"
    "aprobacion_clientes"
    "reposiciones"
    "check_tareas"
    "piezas_cortadas"
    "rendimiento"
    "tintas"
    "tintas_recargas"
    "asistencias"
    "caja"
    "caja_cierres"
    "caja_fondos"
    "retiros"
    "inventario_corte"
    "products_attributes_values"
)

# Tablas que NO se deben truncar (configuración y catálogos)
# config, departamentos, sizes, catalogo_*, products, products_comisiones,
# products_prices, products_tiempos_de_produccion, product_insumos_asignados,
# categories, products_attributes, empleados_salario, salario_carga_familiar

log_message "Iniciando truncado de tablas"

# Crear script SQL temporal
SQL_SCRIPT="/tmp/reset_${DB_NAME}_${TIMESTAMP}.sql"
cat > "$SQL_SCRIPT" <<EOF
USE $DB_NAME;
SET FOREIGN_KEY_CHECKS = 0;
SET AUTOCOMMIT = 0;
START TRANSACTION;

EOF

# Agregar TRUNCATE para cada tabla
for TABLE in "${TABLES_TO_TRUNCATE[@]}"; do
    echo "TRUNCATE TABLE \`$TABLE\`;" >> "$SQL_SCRIPT"
    print_info "Programando limpieza: $TABLE"
    log_message "Agregando TRUNCATE para tabla: $TABLE"
done

# Finalizar script SQL
cat >> "$SQL_SCRIPT" <<EOF

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
EOF

# Ejecutar el script SQL
print_info "Ejecutando limpieza de datos..."
log_message "Ejecutando script SQL de limpieza"

if mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" < "$SQL_SCRIPT" 2>/dev/null; then
    print_success "Limpieza de datos completada"
    log_message "Limpieza completada exitosamente"
    rm -f "$SQL_SCRIPT"
else
    print_error "Error durante la limpieza de datos"
    log_message "ERROR: Falló la limpieza de datos"
    print_warning "El backup está disponible en: $BACKUP_FILE"
    rm -f "$SQL_SCRIPT"
    exit 1
fi

################################################################################
# Resetear AUTO_INCREMENT a 1 para tablas principales
################################################################################

print_header "Reseteando contadores AUTO_INCREMENT"

TABLES_TO_RESET_AI=(
    "ordenes"
    "ordenes_productos"
    "customers"
    "abonos"
    "metodos_de_pago"
    "pagos"
    "lotes"
    "lotes_detalles"
    "inventario"
    "inventario_movimientos"
    "disenos"
    "revisiones"
    "reposiciones"
)

for TABLE in "${TABLES_TO_RESET_AI[@]}"; do
    mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -e "ALTER TABLE \`$TABLE\` AUTO_INCREMENT = 1;" 2>/dev/null
    print_info "Reseteado: $TABLE → AUTO_INCREMENT = 1"
    log_message "AUTO_INCREMENT reseteado para: $TABLE"
done

print_success "Contadores reseteados"

################################################################################
# Insertar registros de prueba (opcional)
################################################################################

print_header "Insertando datos de prueba básicos"

# Verificar si ya existe un cliente de prueba
CUSTOMER_COUNT=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM customers;" 2>/dev/null)

if [ "$CUSTOMER_COUNT" -eq 0 ]; then
    mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" <<EOF 2>/dev/null
    INSERT INTO customers (_id, first_name, last_name, username, cedula, address, billing_city, phone, email)
    VALUES (1, 'Cliente', 'de Pruebas', 'Cliente Prueba', 'V12345678', 'Dirección de prueba', 'Caracas', '584240000000', 'clientepruebas@email.com');
EOF
    print_success "Cliente de prueba agregado (ID: 1)"
    log_message "Cliente de prueba insertado"
else
    print_info "Ya existen clientes en la base de datos"
fi

# Insertar inventario inicial (mismos datos que new company)
INVENTORY_COUNT=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM inventario;" 2>/dev/null)
if [ "$INVENTORY_COUNT" -eq 0 ]; then
    mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" <<EOF 2>/dev/null
    INSERT INTO \`inventario\` (\`_id\`, \`sku\`, \`id_catalogo\`, \`tipo_insumo\`, \`insumo\`, \`unidad\`, \`costo\`, \`rendimiento\`, \`cantidad\`, \`cantidad_inicial\`, \`color\`, \`ancho\`, \`elongacion\`, \`detalles\`, \`departamento\`, \`moment\`) VALUES
    (1, 'PAP_001', 1, 'general', 'Papel de pruebas', 'Mts', 20.00, 1.0, 250.00, 250.00, 'BLANCO', 0.90, NULL, 'Papel para pruebas de impresión', 'Impresión', CURRENT_TIMESTAMP),
    (2, 'TEL_001', 6, 'tela', 'Tela de pruebas', 'Kg', 80.00, 3.96, 24.00, 24.00, 'BLANCO', 1.50, 'HORIZONTAL', 'Tela para pruebas de estampado', 'Estampado', CURRENT_TIMESTAMP),
    (3, 'TIN_C_001', 4, 'tinta', 'Tinta Cyan', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'CYAN', NULL, NULL, 'Tinta cyan para impresoras', 'Impresión', CURRENT_TIMESTAMP),
    (4, 'TIN_M_001', 4, 'tinta', 'Tinta Magenta', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'MAGENTA', NULL, NULL, 'Tinta magenta para impresoras', 'Impresión', CURRENT_TIMESTAMP),
    (5, 'TIN_Y_001', 4, 'tinta', 'Tinta Yellow', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'YELLOW', NULL, NULL, 'Tinta yellow para impresoras', 'Impresión', CURRENT_TIMESTAMP),
    (6, 'TIN_K_001', 4, 'tinta', 'Tinta Black', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'BLACK', NULL, NULL, 'Tinta negra para impresoras', 'Impresión', CURRENT_TIMESTAMP),
    (7, 'BOT_001', 3, 'general', 'Botones blancos', 'Und', 0.50, 1.0, 1000.00, 1000.00, 'BLANCO', NULL, NULL, 'Botones blancos para prendas', 'Costura', CURRENT_TIMESTAMP);
EOF
    print_success "Inventario de prueba insertado"
    log_message "Inventario de prueba insertado"
else
    print_info "Ya existia inventario en la base de datos"
fi

################################################################################
# Resumen final
################################################################################

echo ""
print_header "✅ PROCESO COMPLETADO EXITOSAMENTE"
echo ""
print_success "Base de datos reiniciada: $DB_NAME"
print_success "Empresa ID: $COMPANY_ID"
echo ""
print_info "Archivos generados:"
echo "  📦 Backup: $BACKUP_FILE"
echo "  📝 Log: $LOG_FILE"
echo ""
print_success "La base de datos está lista para comenzar desde la ORDEN #1"
echo ""

# Mostrar estadísticas de la base de datos
print_header "Estadísticas de la base de datos"

TOTAL_TABLES=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null)
print_info "Total de tablas: $TOTAL_TABLES"

# Contar registros en tablas principales
ORDER_COUNT=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM ordenes;" 2>/dev/null)
CUSTOMER_COUNT=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM customers;" 2>/dev/null)
PRODUCT_COUNT=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM products;" 2>/dev/null)

echo ""
echo "  Órdenes: $ORDER_COUNT"
echo "  Clientes: $CUSTOMER_COUNT"
echo "  Productos: $PRODUCT_COUNT"
echo ""

log_message "Proceso completado exitosamente"
log_message "Estadísticas finales - Órdenes: $ORDER_COUNT, Clientes: $CUSTOMER_COUNT, Productos: $PRODUCT_COUNT"

print_success "Proceso de reset completado. Revisa el log para más detalles."
echo ""
