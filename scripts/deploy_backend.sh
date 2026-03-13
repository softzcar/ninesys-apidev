#!/bin/bash

# === CONFIGURACIÓN DE ENTORNOS ===
BRANCH="refactor/modular-routes"

# VPS Aliases (configurados en ~/.ssh/config)
PROD_ALIAS="vps-contabo"
PROD_PATH="/home/api.nineteencustom.com/public_html"

DEV_ALIAS="vps-ninesys"
DEV_PATH="/home/api.nineteengreen.com/public_html"

echo "------------------------------------------------"
echo "  DESPLIEGUE DE BACKEND (API)"
echo "------------------------------------------------"
echo "1) Producción (Contabo - vps-contabo)"
echo "2) Desarrollo (Hostinger - vps-ninesys)"
echo "3) Ambos Servidores (Contabo + Hostinger)"
echo "q) Salir"
echo "------------------------------------------------"
echo "Opción [1-3]: "
read CHOICE

perform_backend_deploy() {
    echo ">>> Iniciando despliegue en $TARGET..."
    echo "Target: $TARGET"
    echo "Branch: $BRANCH"
    echo "Ruta:   $REMOTE_PATH"

    # 1. Asegurar cambios locales en el repositorio central
    echo ">>> Paso 1: Verificando sincronización con GitHub..."
    git fetch origin "$BRANCH" > /dev/null 2>&1
    
    LOCAL_REV=$(git rev-parse HEAD)
    REMOTE_REV=$(git rev-parse "origin/$BRANCH")
    
    if [ "$LOCAL_REV" = "$REMOTE_REV" ]; then
        echo "✅ El repositorio local ya está sincronizado con GitHub. Saltando push."
    else
        echo ">>> Subiendo cambios locales a GitHub (git push origin $BRANCH)..."
        git push origin "$BRANCH"
        if [ $? -ne 0 ]; then
            echo "¡ERROR! Falló el git push. El servidor no podrá actualizarse."
            return 1
        fi
    fi

    # 2. Actualizar el servidor remoto
    echo ">>> Paso 2: Actualizando servidor remoto ($TARGET)..."
    ssh "$REMOTE_ALIAS" "cd $REMOTE_PATH && git fetch origin && git checkout $BRANCH && git pull origin $BRANCH"

    if [ $? -eq 0 ]; then
        echo "✅ DESPLIEGUE EN $TARGET COMPLETADO CON ÉXITO"
    else
        echo "❌ ERROR DURANTE EL DESPLIEGUE EN $TARGET"
        return 1
    fi
    echo ""
}

case $CHOICE in
    1)
        TARGET="PRODUCCIÓN (Contabo)"
        REMOTE_ALIAS=$PROD_ALIAS
        REMOTE_PATH=$PROD_PATH
        perform_backend_deploy
        ;;
    2)
        TARGET="DESARROLLO (Hostinger)"
        REMOTE_ALIAS=$DEV_ALIAS
        REMOTE_PATH=$DEV_PATH
        perform_backend_deploy
        ;;
    3)
        echo "Iniciando despliegue dual de Backend..."
        # Despliegue en Desarrollo
        TARGET="DESARROLLO (Hostinger)"
        REMOTE_ALIAS=$DEV_ALIAS
        REMOTE_PATH=$DEV_PATH
        perform_backend_deploy
        
        # Despliegue en Producción
        TARGET="PRODUCCIÓN (Contabo)"
        REMOTE_ALIAS=$PROD_ALIAS
        REMOTE_PATH=$PROD_PATH
        perform_backend_deploy
        ;;
    *)
        echo "Saliendo..."
        exit 0
        ;;
esac
