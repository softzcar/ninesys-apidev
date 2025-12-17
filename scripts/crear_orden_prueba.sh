#!/bin/bash

# ID de Empresa por defecto 160, o el primer argumento
ID_EMPRESA=${1:-160}

# Cantidad de órdenes a crear (segundo argumento, por defecto 1)
CANTIDAD=${2:-1}

# Archivo de datos
DATA_FILE="./scripts/data_orden_prueba.txt"

if [ ! -f "$DATA_FILE" ]; then
    echo "Error: No se encuentra el archivo de datos $DATA_FILE"
    exit 1
fi

echo "Iniciando creación de $CANTIDAD órdenes para Empresa ID: $ID_EMPRESA..."

for ((i=1; i<=CANTIDAD; i++))
do
    echo "Enviando orden #$i de $CANTIDAD..."
    curl -X POST "https://apidev.nineteengreen.com/ordenes/nueva/custom" \
         -H "Authorization: $ID_EMPRESA" \
         -H "Content-Type: application/x-www-form-urlencoded" \
         --data-raw "$(cat $DATA_FILE)"
    echo "" # Salto de línea para separar salidas
done

echo "Proceso finalizado. Se crearon $CANTIDAD órdenes."
