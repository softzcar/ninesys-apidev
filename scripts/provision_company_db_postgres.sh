#!/bin/bash
# =============================================================================
# provision_company_db_postgres.sh
# Aprovisiona una nueva base de datos de empresa en PostgreSQL con:
# - Creación de la base de datos
# - Carga del schema desde el template SQL de PostgreSQL
# - Configuración de postgres_fdw hacia api_empresas
# - Permisos correctos para dev_user
#
# Uso: ./provision_company_db_postgres.sh <id_empresa>
# Ejemplo: ./provision_company_db_postgres.sh 195
# =============================================================================

set -e

ID_EMPRESA="$1"

if [ -z "$ID_EMPRESA" ]; then
  echo "❌ ERROR: Debes pasar el id_empresa como argumento."
  echo "Uso: $0 <id_empresa>"
  exit 1
fi

DB_NAME="api_emp_${ID_EMPRESA}"
DB_USER="dev_user"
DB_PASS="dev_pass"
SCHEMA_FILE="/home/api.nineteengreen.com/public_html/public/model/create_new_company_api_emp_N_postgres.sql"

echo "========================================================"
echo "  Aprovisionando BD PostgreSQL: ${DB_NAME}"
echo "========================================================"

# 1. Crear la base de datos
echo ">>> Paso 1: Creando base de datos ${DB_NAME}..."
psql -c "DROP DATABASE IF EXISTS ${DB_NAME};"
psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"

# 2. Cargar el schema de la empresa
echo ">>> Paso 2: Cargando schema PostgreSQL en ${DB_NAME}..."
psql -d "${DB_NAME}" -v ON_ERROR_STOP=on -f "${SCHEMA_FILE}"

# 3. Configurar postgres_fdw para acceso a api_empresas
echo ">>> Paso 3: Configurando postgres_fdw hacia api_empresas..."
psql -d "${DB_NAME}" << SQL
CREATE EXTENSION IF NOT EXISTS postgres_fdw;

CREATE SERVER IF NOT EXISTS api_empresas_server
  FOREIGN DATA WRAPPER postgres_fdw
  OPTIONS (host '127.0.0.1', port '5432', dbname 'api_empresas');

CREATE USER MAPPING IF NOT EXISTS FOR ${DB_USER}
  SERVER api_empresas_server
  OPTIONS (user '${DB_USER}', password '${DB_PASS}');

CREATE USER MAPPING IF NOT EXISTS FOR postgres
  SERVER api_empresas_server
  OPTIONS (user '${DB_USER}', password '${DB_PASS}');

CREATE SCHEMA IF NOT EXISTS api_empresas;
IMPORT FOREIGN SCHEMA public FROM SERVER api_empresas_server INTO api_empresas;

ALTER SCHEMA api_empresas OWNER TO ${DB_USER};
GRANT USAGE ON SCHEMA api_empresas TO ${DB_USER};
GRANT SELECT ON ALL TABLES IN SCHEMA api_empresas TO ${DB_USER};
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ${DB_USER};
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ${DB_USER};
SQL

# 4. Actualizar credenciales en api_empresas
echo ">>> Paso 4: Actualizando credenciales en api_empresas para empresa ${ID_EMPRESA}..."
psql -d api_empresas -c "UPDATE empresas SET db_user = '${DB_USER}', db_password = '${DB_PASS}', db_name = '${DB_NAME}', db_host = 'localhost' WHERE id_empresa = ${ID_EMPRESA};"

echo ""
echo "✅ Base de datos ${DB_NAME} aprovisionada exitosamente con PostgreSQL."
echo "========================================================"
