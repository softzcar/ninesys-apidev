#!/bin/bash

# ID de Empresa por defecto 160, o el primer argumento
ID_EMPRESA=${1:-160}

# Archivo de datos
DATA_FILE="./scripts/data_orden_prueba.txt"

if [ ! -f "$DATA_FILE" ]; then
    echo "Error: No se encuentra el archivo de datos $DATA_FILE"
    exit 1
fi

echo "Creando orden para Empresa ID: $ID_EMPRESA..."

curl -X POST "https://apidev.nineteengreen.com/ordenes/nueva/custom" \
     -H "Authorization: $ID_EMPRESA" \
     -H "Content-Type: application/x-www-form-urlencoded" \
     --data-raw "$(cat $DATA_FILE)"

echo ""
echo "Solicitud finalizada."
