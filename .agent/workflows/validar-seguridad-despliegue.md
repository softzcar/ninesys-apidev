---
description: Validación obligatoria de seguridad antes de cualquier despliegue o interacción remota.
---

Este workflow es de **EJECUCIÓN OBLIGATORIA** antes de realizar cualquier `push`, `pull`, `rsync` o ejecución de scripts de despliegue en cualquiera de los entornos (Hostinger o Contabo).

### Pasos de Validación:

1. **Identificación del Objetivo:** Pregúntate conscientemente: ¿A qué servidor estoy intentando acceder?
   - Si la respuesta es **vps-contabo (Producción)**: **DETENTE**.
   - Busca en el historial de la conversación la orden EXPLÍCITA del usuario para este despliegue. 
   - Si no existe una orden literal de hace menos de 10 minutos, **ESTÁ PROHIBIDO**.

2. **Revisión de Instrucciones:** Lee nuevamente la sección "Despliegue y Entornos" en el archivo `GEMINI.md` del proyecto correspondiente.

3. **Verificación de Rama:** Asegúrate de que estás en la rama correcta y de que los cambios han sido probados y aprobados en LOCAL primero.

4. **Confirmación de Seguridad:** Si vas a usar un script de despliegue (como `deploy_backend.sh`), verifica que tiene activos los bloqueos de seguridad y contraseñas.

5. **Consentimiento Final:** Antes de ejecutar el comando de despliegue, DEBES escribir en tu pensamiento:
   > "He verificado que el usuario me pidió explícitamente desplegar en [SERVIDOR] para la tarea [TAREA] en el Step Id [ID]."

### Sanción por Incumplimiento:
El incumplimiento de este workflow resulta en la caída del sistema de producción y pérdida total de confianza del usuario. No hay margen de error.
