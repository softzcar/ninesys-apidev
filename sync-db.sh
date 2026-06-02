#!/bin/bash

# Configuration
VPS_ALIAS="vps-contabo-dev"
LOCAL_USER="dev_user"
LOCAL_PASS="dev_pass"
TEMP_DIR="/tmp"

echo "🔄 Iniciando sincronización de base de datos desde Producción..."

# 1. Export remote databases
echo "📥 Descargando dumps desde VPS..."
ssh $VPS_ALIAS 'mysqldump -u api_adminemp -p"rkyaFy!dAs8L5Lq8" api_empresas > /tmp/api_empresas.sql'
ssh $VPS_ALIAS 'mysqldump -u api_user_152 -p"cf747993a6231d6e0a15f731" api_emp_152 > /tmp/api_emp_152.sql'

# 2. Download dumps
echo "⬇️ Transfiriendo archivos..."
scp -C $VPS_ALIAS:/tmp/api_empresas.sql $TEMP_DIR/
scp -C $VPS_ALIAS:/tmp/api_emp_152.sql $TEMP_DIR/

# 3. Import to Local MariaDB
echo "📦 Importando api_empresas..."
mysql -u $LOCAL_USER -p$LOCAL_PASS -e "CREATE DATABASE IF NOT EXISTS api_empresas;"
mysql -u $LOCAL_USER -p$LOCAL_PASS api_empresas < $TEMP_DIR/api_empresas.sql

echo "📦 Importando api_emp_152..."
mysql -u $LOCAL_USER -p$LOCAL_PASS -e "CREATE DATABASE IF NOT EXISTS api_emp_152;"
mysql -u $LOCAL_USER -p$LOCAL_PASS api_emp_152 < $TEMP_DIR/api_emp_152.sql

# 4. Update Credentials for Local Dev
echo "🔧 Ajustando credenciales locales en tabla empresas..."
mysql -u $LOCAL_USER -p$LOCAL_PASS api_empresas -e "UPDATE empresas SET db_user='$LOCAL_USER', db_password='$LOCAL_PASS', db_host='127.0.0.1';"

echo "✅ Sincronización completada exitosamente."
echo "   - api_empresas: Sincronizada y parcheada para dev local"
echo "   - api_emp_152: Sincronizada"
