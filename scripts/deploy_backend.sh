#!/bin/bash

# === CONFIGURACIÓN DE ENTORNOS ===
BRANCH="refactor/modular-routes"

# Producción (Contabo)
PROD_IP="217.216.95.32"
PROD_PASS="vpsnineteen2026"
PROD_PATH="/home/api.nineteengreen.com/public_html"

# Desarrollo (Hostinger)
DEV_ALIAS="vps-ninesys"
DEV_PATH="/home/api.nineteengreen.com/public_html"

echo "------------------------------------------------"
echo "  DESPLIEGUE DE BACKEND (API)"
echo "------------------------------------------------"
echo "1) Producción (Contabo - 217.216.95.32)"
echo "2) Desarrollo (Hostinger - vps-ninesys)"
echo "q) Salir"
echo "------------------------------------------------"
echo "Opción [1-2]: "
read CHOICE

case $CHOICE in
    1)
        TARGET="PRODUCCIÓN (Contabo)"
        SSH_CMD="sshpass -p '$PROD_PASS' ssh -o StrictHostKeyChecking=no root@$PROD_IP"
        REMOTE_PATH=$PROD_PATH
        ;;
    2)
        TARGET="DESARROLLO (Hostinger)"
        SSH_CMD="ssh $DEV_ALIAS"
        REMOTE_PATH=$DEV_PATH
        ;;
    *)
        echo "Saliendo..."
        exit 0
        ;;
esac

echo "Target: $TARGET"
echo "Branch: $BRANCH"
echo "Ruta:   $REMOTE_PATH"
echo "¿Confirmar despliegue de backend (s/N)?: "
read -r REPLY
echo
if [[ ! $REPLY =~ ^[Ss]$ ]]; then
    echo "Despliegue cancelado."
    exit 1
fi

# 1. Asegurar cambios locales en el repositorio central
echo ">>> Paso 1: Subiendo cambios locales a GitHub (git push)..."
git push origin "$BRANCH"
if [ $? -ne 0 ]; then
    echo "¡ERROR! Falló el git push. El servidor no podrá actualizarse."
    exit 1
fi

# 2. Actualizar el servidor remoto
echo ">>> Paso 2: Actualizando servidor remoto ($TARGET)..."
$SSH_CMD "cd $REMOTE_PATH && git fetch origin && git checkout $BRANCH && git pull origin $BRANCH"

if [ $? -eq 0 ]; then
    echo "------------------------------------------------"
    echo "✅ DESPLIEGUE DE BACKEND COMPLETADO CON ÉXITO"
    echo "------------------------------------------------"
else
    echo "------------------------------------------------"
    echo "❌ ERROR DURANTE EL DESPLIEGUE"
    echo "------------------------------------------------"
    exit 1
fi
