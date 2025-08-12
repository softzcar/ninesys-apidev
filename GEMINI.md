# Instrucciones para Gemini

- La comunicación debe ser siempre en español.
- **Gestión de Bitácora (CRÍTICO):**
    - **Estrategia de Logging:** Se utilizarán archivos `.log` individuales para registrar cada tarea completada. Estos archivos se almacenarán en la carpeta `/logs_gemini`.
    - **Nomenclatura de Archivos:** Cada archivo de log se nombrará siguiendo el formato `YYYY-MM-DD_HH-MM-SS_tarea-[descripcion_corta].log` para asegurar la unicidad y la cronología.
    - **Registro de Tareas:** Después de CADA tarea completada, se debe crear un nuevo archivo `.log` con la siguiente estructura de información:
        ```
        - Solicitud del Usuario: [Texto completo de la solicitud del usuario]
        - Archivos Involucrados: [Lista de rutas de archivos afectados o relevantes para la tarea]
        - Acción Realizada: [Descripción detallada y técnica de mi acción, incluyendo funciones modificadas, comandos ejecutados, etc.]
        - Herramienta(s) Utilizada(s): [Ej: `default_api.write_file`, `default_api.run_shell_command`]
        - Resultado: [Éxito | Fallo | Parcial]
        - Verificación: [Descripción técnica de cómo se verificó la tarea, incluyendo resultados de pruebas, salidas de comandos, comportamiento observado, etc.]
        - Observaciones de Gemini: [Cualquier detalle adicional relevante o auto-reflexión sobre la tarea]
        - Respuesta de Gemini: [La respuesta final que se le dio al usuario después de completar la tarea]
        ```
    - Este registro es fundamental para el seguimiento del proyecto y para la generación de reportes. **La precisión y la inmediatez son IMPERATIVA.`

## Elaboración de Reportes:

- Al final de cada sesión de trabajo, o cuando el usuario lo solicite, se deberá generar un reporte en un archivo `reporte.md`.
- El reporte debe seguir la siguiente estructura:
    - Iniciar con un encabezado H1: `# Reporte Frontend`.
    - Añadir un H2 con la fecha completa: `## [Día de la semana], [día] de [mes] de [año]`.
    - Utilizar encabezados H2 y H3 para organizar la información de las tareas realizadas.
    - Para cada tarea, se debe incluir:
        - Un resumen de la tarea realizada, basado en la información de la bitácora.
        - El resultado o logro conseguido con dicha tarea.
    - Al final del reporte, se debe incluir una sección de resumen con:
        - Un listado de los logros generales de la jornada.
        - Un listado de las tareas que quedaron pendientes o que requieren acciones futuras.
