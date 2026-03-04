#!/bin/bash

# ##############################################################################
# Script: delete_company.sh
# Descripción: Elimina una empresa por completo (DB y registros en api_empresas)
# Uso: ./delete_company.sh
# ##############################################################################

# === CONFIGURACIÓN DE SERVIDORES ===
PROD_HOST="vps-contabo"
DEV_HOST="vps-ninesys"
PROD_DB_PASS="MyR5jRHuwj6kWA"
DEV_DB_PASS=""


# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() {
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
}

# 1. Selección de Servidor
echo "------------------------------------------------"
echo "  SELECCIONE EL SERVIDOR"
echo "------------------------------------------------"
echo "1) Producción (Contabo - vps-contabo)"
echo "2) Desarrollo (Hostinger - vps-ninesys)"
echo "q) Salir"
echo "------------------------------------------------"
echo "Opción [1-2]: "
read SERVER_CHOICE

case $SERVER_CHOICE in
    1)
        TARGET_HOST=$PROD_HOST
        TARGET_PASS=$PROD_DB_PASS
        TARGET_NAME="PRODUCCIÓN"
        ;;
    2)
        TARGET_HOST=$DEV_HOST
        TARGET_PASS=$DEV_DB_PASS
        TARGET_NAME="DESARROLLO"
        ;;
    *)
        echo "Operación cancelada."
        exit 0
        ;;
esac

if [ -z "$TARGET_PASS" ]; then
    SQL_CMD="mysql -u root"
else
    SQL_CMD="mysql -u root -p'$TARGET_PASS'"
fi

# 2. Listar Empresas
print_header "Listando empresas en $TARGET_NAME..."
ssh "$TARGET_HOST" "$SQL_CMD api_empresas -e 'SELECT id_empresa, nombre, moment as fecha_creacion FROM empresas;'"

echo ""
echo "Ingrese el ID de la empresa a ELIMINAR: "
read COMPANY_ID

if [ -z "$COMPANY_ID" ]; then
    echo -e "${RED}Error: ID no proporcionado.${NC}"
    exit 1
fi

# 3. Regla de Seguridad Crítica
if [ "$TARGET_HOST" == "$PROD_HOST" ] && [ "$COMPANY_ID" == "163" ]; then
    echo -e "${RED}❌ ERROR CRÍTICO: La empresa ID 163 (NineteenCustom) NO PUEDE ser eliminada de Producción.${NC}"
    exit 1
fi

# 4. Obtener detalles de la empresa para confirmación
DETAILS=$(ssh "$TARGET_HOST" "$SQL_CMD api_empresas -N -e \"SELECT nombre, db_name, db_user FROM empresas WHERE id_empresa = $COMPANY_ID;\"")

if [ -z "$DETAILS" ]; then
    echo -e "${RED}Error: Empresa ID $COMPANY_ID no encontrada en $TARGET_NAME.${NC}"
    exit 1
fi

EMP_NOMBRE=$(echo "$DETAILS" | cut -f1)
EMP_DB=$(echo "$DETAILS" | cut -f2)
EMP_USER=$(echo "$DETAILS" | cut -f3)

echo -e "${YELLOW}"
echo "------------------------------------------------"
echo "  ⚠️ PELIGRO: ESTO ELIMINARÁ TODO ⚠️"
echo "------------------------------------------------"
echo "  Servidor: $TARGET_NAME"
echo "  Empresa:  $EMP_NOMBRE (ID $COMPANY_ID)"
echo "  Base de Datos: $EMP_DB"
echo "  Usuario DB:    $EMP_USER"
echo "------------------------------------------------"
echo -e "${NC}"

echo "Para confirmar, ESCRIBA EL ID DE LA EMPRESA ($COMPANY_ID): "
read CONFIRM_ID

if [ "$CONFIRM_ID" != "$COMPANY_ID" ]; then
    echo -e "${YELLOW}Confirmación incorrecta. Operación abortada.${NC}"
    exit 1
fi

echo -e "${BLUE}Iniciando eliminación total...${NC}"

# 5. Ejecutar Eliminación
# Usamos un pipe a SSH para evitar que Bash evalúe las comillas invertidas remotamente
cat <<EOF | ssh "$TARGET_HOST" "$SQL_CMD"
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Eliminar Registros en api_empresas
USE api_empresas;
DELETE FROM empresas_gastos WHERE id_empresa = $COMPANY_ID;
DELETE FROM empresas_usuarios_departamentos WHERE id_empleado IN (SELECT id_usuario FROM empresas_usuarios WHERE id_empresa = $COMPANY_ID);
DELETE FROM empresas_usuarios WHERE id_empresa = $COMPANY_ID;
DELETE FROM empresas WHERE id_empresa = $COMPANY_ID;

-- 2. Eliminar Base de Datos de la empresa
DROP DATABASE IF EXISTS \`$EMP_DB\`;

-- 3. Eliminar Usuario de la empresa (si existe)
DROP USER IF EXISTS '$EMP_USER'@'localhost';
DROP USER IF EXISTS '$EMP_USER'@'%';

SET FOREIGN_KEY_CHECKS = 1;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Empresa ID $COMPANY_ID eliminada exitosamente de $TARGET_NAME.${NC}"
else
    echo -e "${RED}❌ Ocurrió un error durante la eliminación. Verifique manualmente.${NC}"
fi
