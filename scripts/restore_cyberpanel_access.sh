#!/bin/bash
# Script para restaurar el acceso seguro a CyberPanel tras reinicio
echo "🚀 Levantando túnel seguro para CyberPanel (Contabo)..."
echo "Mantén esta ventana abierta mientras uses el panel."
echo "URL de acceso: https://localhost:8090"
ssh -N -L 8090:localhost:8090 vps-contabo
