#!/bin/bash

# ==============================================================================
# Script: fix_infrastructure_permissions.sh
# Descripción: Automatiza la asignación de permisos necesarios para la API central
#              y las bases de datos de empresas.
# ==============================================================================

# Configuración
ROOT_USER="root"
ROOT_PASS="ppbT5QsP5FgWIR" # Obtenido de logs previos
CENTRAL_ADMIN_USER="api_adminemp"
CENTRAL_DB="api_empresas"

echo "🔄 Iniciando corrección de permisos de infraestructura..."

# 1. Obtener lista de bases de datos de empresas (api_emp_*)
DATABASES=$(mysql -u$ROOT_USER -p$ROOT_PASS -e "SHOW DATABASES LIKE 'api_emp_%';" -sN)

if [ -z "$DATABASES" ]; then
    echo "⚠️ No se encontraron bases de datos que coincidan con 'api_emp_%'."
else
    for DB in $DATABASES; do
        echo "💾 Ajustando permisos para base de datos: $DB"
        
        # A. Permitir que el admin central consulte la base de datos de la empresa (Nómina, Auditoría, etc.)
        mysql -u$ROOT_USER -p$ROOT_PASS -e "GRANT SELECT ON \`$DB\`.* TO '$CENTRAL_ADMIN_USER'@'localhost';"
        
        # B. Identificar el usuario de la empresa (asumiendo formato api_user_ID)
        # Extraer el ID de la base de datos api_emp_ID
        EMP_ID=$(echo $DB | sed 's/api_emp_//')
        EMP_USER="api_user_$EMP_ID"
        
        # C. Asegurar que el usuario de la empresa tenga acceso SELECT y EXECUTE en api_empresas para consultas de sistema
        # Verificamos si el usuario existe antes de intentar el GRANT (pueden ser muchos)
        USER_EXISTS=$(mysql -u$ROOT_USER -p$ROOT_PASS -e "SELECT EXISTS(SELECT 1 FROM mysql.user WHERE user = '$EMP_USER');" -sN)
        
        if [ "$USER_EXISTS" -eq 1 ]; then
            echo "   👤 Otorgando permisos a $EMP_USER sobre $CENTRAL_DB"
            mysql -u$ROOT_USER -p$ROOT_PASS -e "GRANT SELECT, EXECUTE ON \`$CENTRAL_DB\`.* TO '$EMP_USER'@'localhost';"
            mysql -u$ROOT_USER -p$ROOT_PASS -e "GRANT SELECT, EXECUTE ON \`$CENTRAL_DB\`.* TO '$EMP_USER'@'%';"
        else
            echo "   ⚠️ Usuario $EMP_USER no encontrado en mysql.user"
        fi
    done
fi

# 2. Aplicar cambios
echo "⚙️ Aplicando cambios con FLUSH PRIVILEGES..."
mysql -u$ROOT_USER -p$ROOT_PASS -e "FLUSH PRIVILEGES;"

echo "✅ Proceso de permisos completado exitosamente."
