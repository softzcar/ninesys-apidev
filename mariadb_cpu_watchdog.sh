#!/bin/bash
# Ninesys Watchdog: Evita un colapso en el servidor si MariaDB entra en bucle infinito
# Obtenemos el uso de CPU de MariaDB (mariadbd)
MARIADB_PID=$(pgrep mariadbd)
if [ -z "$MARIADB_PID" ]; then
    exit 0
fi

# Extraer el % de CPU y redondear al entero
CPU_USAGE=$(ps -p $MARIADB_PID -o %cpu= | awk '{print int($1)}')

# Si el uso es mayor al 85%, lo matamos/reiniciamos preventivamente para evitar lockout
if [ "$CPU_USAGE" -gt 85 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [ALERTA] MariaDB excedió el 85% de CPU ($CPU_USAGE%). Reiniciando..." >> /var/log/mariadb_watchdog.log
    systemctl restart mariadb
fi
