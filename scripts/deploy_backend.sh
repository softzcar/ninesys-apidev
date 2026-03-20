#!/bin/bash

# ==============================================================================
# SCRIPT DE DESPLIEGUE - NINESYS BACKEND (API)
# ==============================================================================

BRANCH="refactor/modular-routes"
PROD_ALIAS="vps-contabo"
PROD_PATH="/home/api.nineteencustom.com/public_html"
DEV_ALIAS="vps-ninesys"
DEV_PATH="/home/api.nineteengreen.com/public_html"

echo "------------------------------------------------"
echo "  DESPLIEGUE DE BACKEND (API) - PROTEGIDO"
echo "------------------------------------------------"
echo "1) Producción (Contabo - vps-contabo) - [BLOQUEADO POR SEGURIDAD]"
echo "2) Desarrollo (Hostinger - vps-ninesys)"
echo "q) Salir"
echo "------------------------------------------------"
echo "Opción [1-2]: "
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
            echo "❌ ¡ERROR! Falló el git push. El servidor no podrá actualizarse."
            return 1
        fi
    fi

    # 2. Actualizar el servidor remoto
    echo ">>> Paso 2: Actualizando servidor remoto ($TARGET)..."
    ssh "$REMOTE_ALIAS" "cd $REMOTE_PATH && \
        echo '>>> En el servidor: Fetching cambios...' && \
        git fetch origin && \
        echo '>>> En el servidor: Resetting --hard origin/$BRANCH...' && \
        git reset --hard origin/$BRANCH"

    if [ $? -eq 0 ]; then
        echo "✅ DESPLIEGUE EN $TARGET COMPLETADO CON ÉXITO"
        ssh "$REMOTE_ALIAS" "cd $REMOTE_PATH && echo 'HEAD Remoto: ' && git rev-parse --short HEAD"
    else
        echo "❌ ERROR DURANTE EL DESPLIEGUE EN $TARGET"
        return 1
    fi
    echo ""
}

case $CHOICE in
    1)
        echo "⚠️ CRÍTICO: El despliegue a PRODUCCIÓN está restringido por orden directa del usuario."
        echo "Introduce la clave de seguridad para confirmar que esto NO es un error: "
        read SAFETY_KEY
        if [ "$SAFETY_KEY" != "BORRAR_CONTABO_ES_PECADO" ]; then
            echo "❌ ACCESO DENEGADO. Abortando protección de servidor."
            exit 1
        fi
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
    *)
        echo "Saliendo..."
        exit 0
        ;;
esac
