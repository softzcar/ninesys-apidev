#!/bin/bash

# ##############################################################################
# Script: create_company.sh
# Descripción: Crea una nueva empresa en el servidor de Desarrollo interactuando con la API
# Uso: ./create_company.sh
# ##############################################################################

# === CONFIGURACIÓN ===
API_URL="https://api.nineteengreen.com/setup/user"

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

print_header() {
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
}

print_header "CREAR NUEVA EMPRESA (ENTORNO: DESARROLLO)"
echo -e "${YELLOW}NOTA: Este script creará una empresa exclusivamente en Hostinger.${NC}"
echo ""

# 1. Solicitar Email
echo "Ingrese el correo electrónico para el nuevo usuario administrador:"
read EMAIL

if [ -z "$EMAIL" ]; then
    echo -e "${RED}Error: El correo electrónico es requerido.${NC}"
    exit 1
fi

# Validar formato de email básico con regex de bash
if [[ ! "$EMAIL" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$ ]]; then
    echo -e "${RED}Error: Formato de correo electrónico inválido.${NC}"
    exit 1
fi

echo ""
echo -e "${CYAN}Conectando a la API de Desarrollo ($API_URL)...${NC}"

# 2. Hacer la petición POST a la API usando curl
# Guardamos HTTP status en HTTP_STATUS y el body de la respuesta en RESPONSE_BODY
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\"}")

# Extraer el código HTTP (última línea) y el body (todo menos la última línea)
HTTP_STATUS=$(echo "$RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$RESPONSE" | sed '$d')

# 3. Procesar respuesta
if [ "$HTTP_STATUS" -eq 200 ] || [ "$HTTP_STATUS" -eq 201 ]; then
    echo -e "${GREEN}✅ ¡Empresa creada exitosamente!${NC}"
    echo ""
    
    # Extraer datos usando jq (si está instalado) o grep/sed como fallback
    if command -v jq >/dev/null 2>&1; then
        ID_EMPRESA=$(echo "$RESPONSE_BODY" | jq -r '.id_empresa // empty')
        EMAIL_RES=$(echo "$RESPONSE_BODY" | jq -r '.email // empty')
        PASSWORD=$(echo "$RESPONSE_BODY" | jq -r '.password // empty')
    else
        # Fallback usando grep para extraer del JSON
        ID_EMPRESA=$(echo "$RESPONSE_BODY" | grep -o '"id_empresa":"[^"]*' | cut -d'"' -f4 | head -n 1)
        # Si id_empresa es numero sin comillas
        if [ -z "$ID_EMPRESA" ]; then
            ID_EMPRESA=$(echo "$RESPONSE_BODY" | grep -o '"id_empresa":[0-9]*' | cut -d':' -f2 | head -n 1)
        fi
        EMAIL_RES=$(echo "$RESPONSE_BODY" | grep -o '"email":"[^"]*' | cut -d'"' -f4 | head -n 1)
        PASSWORD=$(echo "$RESPONSE_BODY" | grep -o '"password":"[^"]*' | cut -d'"' -f4 | head -n 1)
    fi
    
    echo -e "${CYAN}════════ CREDENCIALES GENERADAS ════════${NC}"
    echo -e "🏢 ${YELLOW}ID Empresa:${NC} $ID_EMPRESA"
    echo -e "✉️  ${YELLOW}Usuario:${NC}    ${EMAIL_RES:-$EMAIL}"
    echo -e "🔑 ${YELLOW}Contraseña:${NC} $PASSWORD"
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo ""
    echo -e "La empresa ha sido inicializada y está lista para ser configurada en el Setup Portal."

elif [ "$HTTP_STATUS" -eq 409 ]; then
    # Conflicto: La empresa o el correo ya existen
    ERROR_MSG=$(echo "$RESPONSE_BODY" | grep -o '"error":"[^"]*' | cut -d'"' -f4)
    if [ -z "$ERROR_MSG" ]; then
        ERROR_MSG="El correo o empresa ya se encuentran registrados."
    fi
    echo -e "${YELLOW}⚠️ Advertencia:${NC} $ERROR_MSG"
else
    # Otros errores (500, 400, etc)
    ERROR_MSG=$(echo "$RESPONSE_BODY" | grep -o '"error":"[^"]*' | cut -d'"' -f4)
    if [ -z "$ERROR_MSG" ]; then
        ERROR_MSG=$RESPONSE_BODY
    fi
    echo -e "${RED}❌ Error ($HTTP_STATUS):${NC} $ERROR_MSG"
fi

echo ""
echo "Presione Enter para continuar..."
read DUMMY
