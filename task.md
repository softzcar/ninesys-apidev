# Auditoría de Consumo de CPU VPS (Feb 2026 - Evento 2)

## Objetivo
Identificar los procesos responsables del consumo del 100% de CPU reportado desde el sábado 14-02-2026 (~20:00) hasta el 18-02-2026, y determinar si el origen es interno o externo (Steal Time).

## Checklist
- [x] Verificar conectividad SSH y carga actual (uptime)
- [x] Extraer logs históricos de `sar` (Feb 14-18) para analizar %user vs %st
- [x] Listar procesos con mayor consumo de recursos (top/ps)
- [/] Verificar estado post-reinicio (Steal Time y Load Average)
- [x] Crear reporte de optimización de recursos y procesos
- [ ] Crear informe final y log de la tarea
