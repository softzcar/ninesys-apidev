#!/bin/bash

# Script para sincronizar base de datos de una empresa de Producción (Contabo) a Desarrollo (Hostinger)
# Uso: ./sync_company_database.sh <ID_EMPRESA>

if [ -z "$1" ]; then
    echo "❌ Error: Debe proporcionar el ID de la empresa como argumento."
    echo "Uso: $0 <ID_EMPRESA>"
    exit 1
fi

ID_EMPRESA=$1
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Configuración Producción (Contabo)
PROD_SSH="vps-contabo"
PROD_DB_USER="api_adminemp"
PROD_DB_PASS="admEmp_n1ne_2026$"

# Configuración Desarrollo (Hostinger)
DEV_SSH="vps-ninesys"
DEV_DB_USER="root"
DEV_DB_PASS="ppbT5QsP5FgWIR"

echo "---------------------------------------------------------"
echo "Sincronizando Empresa ID: $ID_EMPRESA"
echo "Origen: Producción (Contabo)"
echo "Destino: Desarrollo (Hostinger)"
echo "---------------------------------------------------------"

# 1. Obtener detalles de la base de datos desde la central en Producción
echo "[1/4] Obteniendo detalles de la base de datos en Producción..."
PROD_DETAILS=$(ssh $PROD_SSH "mysql -u $PROD_DB_USER -p'$PROD_DB_PASS' api_empresas -N -s -e \"SELECT db_name, db_user, db_password FROM empresas WHERE id_empresa = '$ID_EMPRESA';\"" )

if [ -z "$PROD_DETAILS" ]; then
    echo "❌ Error: No se encontró la empresa $ID_EMPRESA en Producción."
    exit 1
fi

read -r DB_NAME PROD_USER PROD_PASS <<< "$PROD_DETAILS"
echo "✅ Origen: $DB_NAME ($PROD_USER)"

# 2. Obtener detalles de la base de datos en Desarrollo
echo "[2/4] Obteniendo detalles de la base de datos en Desarrollo..."
DEV_DETAILS=$(ssh $DEV_SSH "mysql -u $DEV_DB_USER -p'$DEV_DB_PASS' api_empresas -N -s -e \"SELECT db_user, db_password FROM empresas WHERE id_empresa = '$ID_EMPRESA';\"" )

if [ -z "$DEV_DETAILS" ]; then
    echo "❌ Error: No se encontró la empresa $ID_EMPRESA en Desarrollo."
    exit 1
fi

read -r DEV_USER DEV_PASS <<< "$DEV_DETAILS"
echo "✅ Destino: $DB_NAME ($DEV_USER)"

# 3. Sincronización vía Pipe
echo "[3/4] Ejecutando sincronización (Prod -> Dev)..."
# Usamos --skip-triggers para evitar errores de DEFINER=root
ssh $PROD_SSH "mysqldump -u $PROD_USER -p'$PROD_PASS' --single-transaction --quick --lock-tables=false --skip-triggers $DB_NAME" | \
ssh $DEV_SSH "mysql -u $DEV_USER -p'$DEV_PASS' $DB_NAME"

if [ $? -eq 0 ]; then
    echo "✅ Datos sincronizados en Hostinger (sin triggers)."
else
    echo "❌ Error durante la sincronización de datos."
    exit 1
fi

# 4. Verificación rápida
echo "[4/4] Verificando conteos..."
COUNT_PROD=$(ssh $PROD_SSH "mysql -u $PROD_USER -p'$PROD_PASS' $DB_NAME -N -s -e \"SELECT count(*) FROM ordenes;\"")
COUNT_DEV=$(ssh $DEV_SSH "mysql -u $DEV_USER -p'$DEV_PASS' $DB_NAME -N -s -e \"SELECT count(*) FROM ordenes;\"")

echo "📊 Conteo de órdenes:"
echo "   Producción: $COUNT_PROD"
echo "   Desarrollo: $COUNT_DEV"

if [ "$COUNT_PROD" == "$COUNT_DEV" ]; then
    echo "✨ Verificación exitosa: Los conteos coinciden."
else
    echo "⚠️ Advertencia: Los conteos de órdenes no coinciden ($COUNT_PROD vs $COUNT_DEV)."
fi

echo "---------------------------------------------------------"
echo "Proceso finalizado: $(date)"
echo "---------------------------------------------------------"
