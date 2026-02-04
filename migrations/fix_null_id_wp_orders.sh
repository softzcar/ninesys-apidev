#!/bin/bash

# Script para corregir órdenes existentes con id_wp NULL
# Empresa 163 - NineteenCustom

echo "========================================="
echo "Migración: Corregir id_wp NULL en órdenes"
echo "Empresa: 163 - NineteenCustom"
echo "========================================="
echo ""

# Credenciales de la base de datos
DB_USER="api_user_163"
DB_PASS="c45ff25ef00ce4ebb0fca422"
DB_NAME="api_emp_163"

echo "1. Identificando órdenes con id_wp NULL..."
ssh vps-ninesys "mysql -u $DB_USER -p$DB_PASS $DB_NAME -e '
SELECT _id, cliente_nombre, cliente_cedula FROM ordenes WHERE id_wp IS NULL ORDER BY _id;
'"

echo ""
echo "2. Ejecutando migración..."
echo ""

# Orden 3528 (sin nombre ni cédula)
echo "Procesando Orden 3528..."
ssh vps-ninesys "mysql -u $DB_USER -p'$DB_PASS' $DB_NAME -e \"
-- Crear cliente para orden 3528
INSERT INTO customers (first_name, last_name, cedula, phone, email, address)
VALUES ('Cliente', 'Sin Datos', '', '', 'cliente3528@email.com', 'none');

-- Actualizar orden 3528 con el ID del cliente recién creado
UPDATE ordenes SET id_wp = LAST_INSERT_ID() WHERE _id = 3528;

-- Verificar
SELECT _id as orden_id, cliente_nombre, id_wp FROM ordenes WHERE _id = 3528;
\""

echo ""

# Orden 3529 (Ozcar Atencio)
echo "Procesando Orden 3529..."
ssh vps-ninesys "mysql -u $DB_USER -p'$DB_PASS' $DB_NAME -e \"
-- Buscar si ya existe cliente con cédula V-11912520
SELECT _id FROM customers WHERE cedula = 'V-11912520' LIMIT 1;
\""

# Si existe, usar ese ID. Si no, crear nuevo.
# Por simplicidad, vamos a crear directamente o actualizar si existe

ssh vps-ninesys "mysql -u $DB_USER -p'$DB_PASS' $DB_NAME -e \"
-- Intentar insertar, si falla por duplicado, obtener el ID existente
INSERT INTO customers (first_name, last_name, cedula, phone, email, address)
VALUES ('Ozcar', 'Atencio', 'V-11912520', '', 'oatencio@email.com', 'none')
ON DUPLICATE KEY UPDATE _id=LAST_INSERT_ID(_id);

-- Actualizar orden 3529
UPDATE ordenes SET id_wp = LAST_INSERT_ID() WHERE _id = 3529;

-- Verificar
SELECT _id as orden_id, cliente_nombre, id_wp FROM ordenes WHERE _id = 3529;
\""

echo ""

# Orden 3530 (Ozcar Atencio - mismo cliente que 3529)
echo "Procesando Orden 3530..."
ssh vps-ninesys "mysql -u $DB_USER -p'$DB_PASS' $DB_NAME -e \"
-- Buscar ID del cliente con cédula V-11912520
SET @customer_id = (SELECT _id FROM customers WHERE cedula = 'V-11912520' LIMIT 1);

-- Actualizar orden 3530 con el mismo ID
UPDATE ordenes SET id_wp = @customer_id WHERE _id = 3530;

-- Verificar
SELECT _id as orden_id, cliente_nombre, id_wp FROM ordenes WHERE _id = 3530;
\""

echo ""
echo "3. Verificación final..."
ssh vps-ninesys "mysql -u $DB_USER -p$DB_PASS $DB_NAME -e '
SELECT 
  o._id as orden_id,
  o.cliente_nombre,
  o.cliente_cedula,
  o.id_wp,
  c.first_name,
  c.last_name,
  c.phone
FROM ordenes o
LEFT JOIN customers c ON c._id = o.id_wp
WHERE o._id IN (3528, 3529, 3530)
ORDER BY o._id;
'"

echo ""
echo "========================================="
echo "Migración completada"
echo "========================================="
