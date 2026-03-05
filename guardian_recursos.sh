#!/bin/bash
# Ninesys Guardian: Scripts para asegurar la prioridad de MariaDB tras cada reinicio
sleep 10
# Dar prioridad maxima a la base de datos para que responda bajo carga pesada
renice -n -15 -p $(pgrep mariadbd)
