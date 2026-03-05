#!/bin/bash

# === CONFIGURACIÓN MAESTRA DE ENTORNOS ===
# Producción (Contabo)
PROD_REMOTE_HOST="vps-contabo"
PROD_REMOTE_PATH="/home/app.nineteencustom.com/public_html"

# Pruebas (Hostinger)
TEST_REMOTE_HOST="vps-ninesys"
TEST_REMOTE_PATH="/home/app.nineteengreen.com/public_html"

# === SELECCIÓN DE ENTORNO ===
echo "------------------------------------------------"
echo "  SELECCIONE EL ENTORNO PARA ROLLBACK"
echo "------------------------------------------------"
echo "1) Producción (Contabo - nineteencustom.com)"
echo "2) Pruebas (Hostinger - nineteengreen.com)"
echo "q) Salir"
echo "------------------------------------------------"
echo "Opción [1-2]: "
read ENV_CHOICE

case $ENV_CHOICE in
    1)
        TARGET_NAME="PRODUCCIÓN (Contabo)"
        REMOTE_HOST=$PROD_REMOTE_HOST
        REMOTE_PATH=$PROD_REMOTE_PATH
        ;;
    2)
        TARGET_NAME="PRUEBAS (Hostinger)"
        REMOTE_HOST=$TEST_REMOTE_HOST
        REMOTE_PATH=$TEST_REMOTE_PATH
        ;;
    *)
        echo "Opción inválida o salida seleccionada."
        exit 0
        ;;
esac

REMOTE_USER="root"
REMOTE_BACKUP_ROOT="${REMOTE_PATH%/*}/backups_deploys"

echo "Conectando a $REMOTE_HOST para listar respaldos..."
BACKUPS=$(ssh "$REMOTE_USER@$REMOTE_HOST" "ls -1d $REMOTE_BACKUP_ROOT/app_backup_* 2>/dev/null | sort -r | head -n 10")

if [ -z "$BACKUPS" ]; then
    echo "No se encontraron respaldos en $REMOTE_BACKUP_ROOT"
    exit 1
fi

echo "------------------------------------------------"
echo "  RESPALDOS DISPONIBLES (Últimos 10)"
echo "------------------------------------------------"
select BACKUP_PATH in $BACKUPS; do
    if [ -n "$BACKUP_PATH" ]; then
        echo "Has seleccionado: $BACKUP_PATH"
        echo "¿Confirmar RESTAURACIÓN a este respaldo? (s/N): "
        read -r REPLY
        echo
        if [[ $REPLY =~ ^[Ss]$ ]]; then
            echo "Iniciando rollback..."
            # Paso 1: Mover actual a un backup temporal por seguridad
            TEMP_BACKUP="${REMOTE_PATH%/*}/temp_before_rollback"
            ssh "$REMOTE_USER@$REMOTE_HOST" "
                rm -rf $TEMP_BACKUP && 
                mv $REMOTE_PATH $TEMP_BACKUP && 
                mkdir -p $REMOTE_PATH && 
                cp -r $BACKUP_PATH/* $REMOTE_PATH/
            "
            if [ $? -eq 0 ]; then
                echo "✅ Rollback completado con éxito."
                echo "El estado anterior se guardó temporalmente en: $TEMP_BACKUP"
            else
                echo "❌ ERROR durante el rollback. Verifique el estado manual en el VPS."
            fi
        else
            echo "Rollback cancelado."
        fi
        break
    else
        echo "Selección inválida."
    fi
done
