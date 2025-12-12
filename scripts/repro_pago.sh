#!/bin/bash

ID_EMPRESA=160
API_URL="https://apidev.nineteengreen.com"

echo "1. Creando Orden..."
./scripts/crear_orden_prueba.sh $ID_EMPRESA > orden_output.txt
cat orden_output.txt

# Extraer ID de la orden (chapuza con grep/sed)
# Asumiendo "La orden número X ha sido creada"
ID_ORDEN=$(grep -oP '"orden":\[\{"_id":\K\d+' orden_output.txt)
echo "ID Orden Creada: $ID_ORDEN"

if [ -z "$ID_ORDEN" ]; then
    echo "Fallo al crear orden"
    exit 1
fi

echo "2. Buscando Asignación (id_lotes_detalles_empleados_asignados)..."
# Necesitamos el ID de la asignacion. Asumimos que se creó automáticamente (?).
# Consultamos BD para obtener el ultimo ID de asignacion para esa orden.
ID_ASIGNACION=$(ssh vps-ninesys "mysql -N -u api_user_160 -p'0126e26ef574f5f9f8225253' api_emp_160 -e 'SELECT _id FROM lotes_detalles_empleados_asignados WHERE id_orden = $ID_ORDEN ORDER BY _id DESC LIMIT 1;'")

echo "ID Asignación encontrado: $ID_ASIGNACION"

if [ -z "$ID_ASIGNACION" ]; then
    echo "No se encontró asignación. Intentando con ID de lotes_detalles..."
     ID_ASIGNACION=$(ssh vps-ninesys "mysql -N -u api_user_160 -p'0126e26ef574f5f9f8225253' api_emp_160 -e 'SELECT _id FROM lotes_detalles WHERE id_orden = $ID_ORDEN ORDER BY _id DESC LIMIT 1;'")
     echo "ID Lote Detalle: $ID_ASIGNACION"
fi

if [ -z "$ID_ASIGNACION" ]; then
    echo "Fatal: No se encontró ID válido para procesar."
    exit 1
fi

echo "3. Procesando Pago (Simulación Frontend)..."
# Payload simulado. Unidades = 7 (incluyendo diseño)
curl -X POST "$API_URL/registrar-paso-empleado" \
  -H "Authorization: $ID_EMPRESA" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "id_empleado=499" \
  -d "id_departamento=1" \
  -d "id_lotes_detalles=$ID_ASIGNACION" \
  -d "id_orden=$ID_ORDEN" \
  -d "tipo=fin" \
  -d "es_reposicion=0" \
  -d "unidades=7" \
  -d "departamento=Impresión" \
  -d "orden_proceso=2" \
  -d "paso_actual=2" \
  > pago_output.json

echo ""
echo "Respuesta del Pago:"
cat pago_output.json | grep "debug_fix" -A 10
