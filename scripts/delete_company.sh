#!/bin/bash

# ##############################################################################
# Script: delete_company.sh
# Descripción: Elimina una empresa (BD y Usuario) interactuando con la API
# Uso: ./delete_company.sh <id_usuario_admin>
# ##############################################################################

# === CONFIGURACIÓN ===
API_URL="https://api.nineteengreen.com/setup/user"

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

print_header "ELIMINAR EMPRESA (ENTORNO: DESARROLLO)"

# 1. Solicitar ID si no se pasó por argumento
ID_USUARIO=$1
if [ -z "$ID_USUARIO" ]; then
    echo "Ingrese el ID del usuario administrador de la empresa a eliminar:"
    read ID_USUARIO
fi

if [ -z "$ID_USUARIO" ]; then
    echo -e "${RED}Error: El ID de usuario es requerido.${NC}"
    exit 1
fi

# 2. Confirmación de seguridad
echo -e "${RED}⚠️  ADVERTENCIA: Esta acción eliminará permanentemente la base de datos y el usuario.${NC}"
echo -e "Escriba ${YELLOW}BORRAR${NC} para confirmar:"
read CONFIRMATION

if [ "$CONFIRMATION" != "BORRAR" ]; then
    echo -e "${BLUE}Operación cancelada.${NC}"
    exit 0
fi

echo -e "\n${BLUE}Eliminando empresa asociada al usuario ID: $ID_USUARIO...${NC}"

# 3. Hacer la petición DELETE a la API
RESPONSE=$(curl -s -w "\n%{http_code}" -X DELETE "$API_URL/$ID_USUARIO")

HTTP_STATUS=$(echo "$RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$RESPONSE" | sed '$d')

# 4. Procesar respuesta
if [ "$HTTP_STATUS" -eq 200 ]; then
    echo -e "${GREEN}✅ ¡Empresa y usuario eliminados correctamente!${NC}"
else
    ERROR_MSG=$(echo "$RESPONSE_BODY" | grep -o '"error":"[^"]*' | cut -d'"' -f4)
    if [ -z "$ERROR_MSG" ]; then
        ERROR_MSG=$RESPONSE_BODY
    fi
    echo -e "${RED}❌ Error ($HTTP_STATUS):${NC} $ERROR_MSG"
fi

echo ""
echo "Presione Enter para continuar..."
read DUMMY
