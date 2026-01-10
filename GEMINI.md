# GEMINI.md

## Instrucciones para el modelo:

- Siempre conversaremos en español
- Revisa la estructura de directorios y los archivos necesarios para que tengas el contexto más completo posible del proyecto
- **Gestión de Bitácora (CRÍTICO - OBLIGATORIO):**
    - **Al iniciar:** SIEMPRE actualízate leyendo los últimos 3-5 logs para entender el contexto reciente del proyecto
    
    - **Estrategia de Logging:** Se utilizarán archivos `.log` individuales para registrar CADA tarea completada. Estos archivos se almacenarán en la carpeta `logs_gemini/`.
    
    - **Nomenclatura de Archivos:** Cada archivo de log se nombrará siguiendo el formato `YYYY-MM-DD_HH-MM-SS_tarea-[descripcion_corta].log` para asegurar la unicidad y la cronología.
    
    - **⚠️ CREACIÓN OBLIGATORIA DE LOGS:**
        - **SIEMPRE** crea un log después de completar una tarea, **SIN EXCEPCIONES**
        - Esto aplica incluso si trabajas manualmente sin usar herramientas de IA
        - Incluso en días festivos o fines de semana, si trabajas, DEBES crear el log
        - El log es la única forma de documentar el trabajo realizado para futuros reportes
    
    - **Registro de Tareas:** Después de CADA tarea completada, se debe crear un nuevo archivo `.log` con la siguiente estructura de información:
        ```
        - Solicitud del Usuario: [Texto completo de la solicitud del usuario]
        - Archivos Involucrados: [Lista de rutas de archivos afectados o relevantes para la tarea]
        - Acción Realizada: [Descripción detallada y técnica de mi acción, incluyendo funciones modificadas, comandos ejecutados, etc.]
        - Herramienta(s) Utilizada(s): [Ej: `write_to_file`, `run_command`, `replace_file_content`]
        - Resultado: [Éxito | Fallo | Parcial]
        - Verificación: [Descripción técnica de cómo se verificó la tarea, incluyendo resultados de pruebas, salidas de comandos, comportamiento observado, etc.]
        - Observaciones de Gemini: [Cualquier detalle adicional relevante o auto-reflexión sobre la tarea]
        - Respuesta de Gemini: [La respuesta final que se le dio al usuario después de completar la tarea]
        ```
    
    - **Importancia:** Este registro es FUNDAMENTAL para el seguimiento del proyecto y para la generación de reportes. **La precisión y la inmediatez son IMPERATIVAS**. Sin logs, no hay manera de documentar el trabajo realizado.
- Siempre prefiere implementar código de la manera menos invasiva posible
- Evita hacer cambios o mejoras que no se te soliciten pero siempre puedes sugerirlas

---

## Generación de Reportes Diarios (AUTOMATIZADO):

### Scripts Disponibles

Los scripts para generación de reportes se encuentran en `/home/developer/Escritorio/`:

1. **`generar_reportes_diarios.py`** - Genera reportes HTML consolidando logs de Frontend y Backend
2. **`convertir_html_a_pdf.py`** - Convierte los reportes HTML a PDF

### Cómo Generar Reportes

Cuando el usuario solicite "generar reporte del día X" o "crear reporte de los días X a Y":

**Opción 1: Usar el script existente (recomendado para rangos de fechas)**
```bash
cd /home/developer/Escritorio
python3 generar_reportes_diarios.py
```
El script está configurado para generar reportes del 28-12-2025 al 08-01-2026. Para otras fechas, modifica las variables `fecha_inicio` y `fecha_fin` en el script.

**Opción 2: Generar un reporte individual manualmente**

Si el usuario solicita un reporte de UN día específico:

1. Busca logs de ese día en ambos proyectos:
   ```bash
   find /home/developer/Escritorio/niesys/app_multi/logs_gemini -name "YYYY-MM-DD*"
   find /home/developer/Escritorio/Antigravity/ninesys-apidev/logs_gemini -name "YYYY-MM-DD*"
   ```

2. Si NO hay logs pero sí hay commits en Git, reconstruye desde Git:
   ```bash
   git log --all --since="YYYY-MM-DD 00:00:00" --until="YYYY-MM-DD 23:59:59" --pretty=format:"%H|%an|%ad|%s" --date=format:"%H:%M:%S"
   ```

3. Genera el reporte HTML usando la plantilla establecida (ver reportes existentes como ejemplo)

4. Convierte a PDF:
   ```bash  
   cd /home/developer/Escritorio
   python3 convertir_html_a_pdf.py
   ```

### Estructura del Reporte HTML

Cada reporte debe incluir:
- **Encabezado:** Fecha completa en español, proyecto(s), total de tareas
- **Resumen Ejecutivo:** Tabla con contador por proyecto (Frontend/Backend)
- **Detalle de Tareas:** Para cada tarea:
  - Hora (solo rango de commits, NO calcular tiempo de trabajo)
  - Proyecto
  - Resultado
  - Solicitud del Usuario
  - Acción Realizada
  - Verificación
  - Logro Conseguido
- **Estilos CSS:** Optimizados para impresión, profesionales
- **Botón de impresión:** Integrado en el HTML

### Ubicación de Reportes

- **HTML:** `/home/developer/Escritorio/reportes_logs/reporte_YYYY-MM-DD.html`
- **PDF:** `/home/developer/Escritorio/reportes_logs/reporte_YYYY-MM-DD.pdf`

### ⚠️ IMPORTANTE: No Calcular Tiempo de Trabajo

- **NUNCA** incluyas cálculos de "X horas de trabajo" basados en timestamps de commits
- Solo muestra "Rango de commits: HH:MM - HH:MM" como referencia
- Los commits no reflejan el tiempo real de trabajo (no incluyen pausas, trabajo post-commit, análisis, etc.)

---

## Elaboración de Reportes MD (Legacy - Opcional):

Si el usuario solicita un reporte en formato Markdown (menos común ahora que tenemos HTML/PDF):

- Generar un archivo `reporte.md`
- Estructura:
    - H1: `# Reporte Backend`
    - H2 con fecha completa: `## [Día de la semana], [día] de [mes] de [año]`
    - H2 y H3 para organizar tareas
    - Para cada tarea: resumen y resultado/logro
    - Sección final: logros generales y tareas pendientes

---

## Despliegue en Producción

Para actualizar la aplicación en el servidor de producción (VPS), utiliza el siguiente comando:

```bash
git fetch origin && git checkout refactor/modular-routes && git pull origin refactor/modular-routes
```
