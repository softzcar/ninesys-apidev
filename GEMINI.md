# GEMINI.md

## Instrucciones para el modelo:

- Siempre conversaremos en español
- Revisa la estructura de directorios y los archivos necesarios para que tengas el contexto más completo posible del proyecto
- **Gestión de Bitácora (CRÍTICO - OBLIGATORIO):**
    - **Al iniciar:** SIEMPRE actualízate leyendo los últimos 3-5 logs para entender el contexto reciente del proyecto
    
    - **Estrategia de Logging:** Se utilizarán archivos `.log` individuales para registrar CADA tarea completada. Estos archivos se almacenarán en la carpeta `logs_gemini/`.
    
    - **Nomenclatura de Archivos:** Cada archivo de log se nombrará siguiendo el formato `YYYY-MM-DD_HH-MM-SS_tarea-[descripcion_corta].log` para asegurar la unicidad y la cronología.
    
    - **⚠️ CREACIÓN OBLIGATORIA DE LOGS — SIN EXCEPCIÓN:**
        - **SIEMPRE** crea un log después de completar una tarea, **SIN EXCEPCIONES**
        - Esto aplica incluso si trabajas manualmente sin usar herramientas de IA
        - Incluso en días festivos o fines de semana, si trabajas, DEBES crear el log
        - **Sin log = trabajo NO documentado = NO aparece en el reporte semanal**
        - El log es la ÚNICA fuente primaria confiable para reconstruir el trabajo del día
    
    - **Registro de Tareas:** Después de CADA tarea completada, se debe crear un nuevo archivo `.log` con la siguiente estructura de información:
        ```markdown
        - **Solicitud del Usuario:** [Texto completo de la solicitud del usuario]
        - **Fecha y Hora:** [YYYY-MM-DD HH:MM:SS]
        - **Proyecto(s):** [Frontend / Backend / Ambos]
        - **Archivos Involucrados:** [Lista de rutas de archivos afectados o relevantes para la tarea]
        - **Commits Relacionados:**
            - Frontend: [ID Commit o N/A]
            - Backend: [ID Commit o N/A]
        - **Acción Realizada:** [Descripción detallada y técnica de la acción, incluyendo funciones modificadas, comandos ejecutados, etc.]
        - **Inconvenientes y Desafíos:** [Descripción de cualquier bloqueo, dificultad técnica o error encontrado durante el proceso]
        - **Decisión Final y Justificación:** [Razonamiento detrás de la solución técnica elegida y por qué se descartaron otras opciones]
        - **Herramienta(s) Utilizada(s):** [Ej: `write_to_file`, `run_command`, `replace_file_content`]
        - **Resultado:** [Éxito | Fallo | Parcial]
        - **Verificación:** [Descripción técnica de cómo se verificó la tarea, incluyendo resultados de pruebas, salidas de comandos, comportamiento observado, etc.]
        - **Logro Conseguido:** [Resumen conciso del valor aportado por esta tarea, ideal para el reporte semanal]
        - **Referencias de Sesión (Contexto):** 
            - Task: [task.md](file:///home/developer/.gemini/antigravity/brain/[CONVERSATION-ID]/task.md)
            - Walkthrough: [walkthrough.md](file:///home/developer/.gemini/antigravity/brain/[CONVERSATION-ID]/walkthrough.md)
        
        - **⚠️ NOTA DE PERSISTENCIA:** Los links anteriores son temporales. Para que la información sea PERMANENTE y aparezca en los reportes, **DEBES** detallar todo el proceso técnico en 'Acción Realizada', 'Verificación' e 'Inconvenientes'. No te limites a poner "ver walkthrough".
        
        - **Observaciones de Gemini:** [Cualquier detalle adicional relevante o auto-reflexión sobre la tarea]
        - **Respuesta de Gemini:** [La respuesta final que se le dio al usuario después de completar la tarea]
        ```
    
    - **Importancia:** Este registro es FUNDAMENTAL para el seguimiento del proyecto y para la generación de reportes. **La precisión y la inmediatez son IMPERATIVAS**. Sin logs, no hay manera de documentar el trabajo realizado.
- Siempre prefiere implementar código de la manera menos invasiva posible
- Evita hacer cambios o mejoras que no se te soliciten pero siempre puedes sugerirlas

---

## Despliegue y Entornos de Prueba (⚠️ REGLAS CRÍTICAS - LECTURA OBLIGATORIA)

- **Servidor de Producción (Contabo - `nineteencustom.com`):** 
    - > [!CAUTION]
    - > **PROHIBICIÓN TOTAL:** NUNCA actualices, despliegues o realices `git reset --hard / pull` en Contabo (ni Backend ni Frontend) sin una petición explícita y directa del usuario para esa acción específica. Es un entorno de producción crítico.
- **Servidor de Pruebas (Hostinger - `api.nineteengreen.com`):**
    - **Backend (API):** Es **OBLIGATORIO** actualizar el backend en Hostinger (`git fetch && git pull`) CADA VEZ que realices cambios en el código de la API. Esto es necesario para que las pruebas locales del frontend (que se conectan a esta API) funcionen correctamente.
    - **Frontend:** **NO** actualices el frontend en Hostinger a menos que el usuario lo solicite explícitamente. Las pruebas de frontend se validan ejecutando `npm run dev` localmente.
- **Desarrollo Local (Frontend):** Las pruebas del Frontend se hacen EXCLUSIVAMENTE de forma local ejecutando `npm run dev`. No es necesario desplegar al VPS para validar ajustes visuales o de lógica.

---

## Generación de Reportes Diarios (AUTOMATIZADO):

### Scripts Disponibles

Los scripts para generación de reportes se encuentran en `/home/developer/Escritorio/`:

1. **`generar_reportes_diarios.py`** - Genera reportes HTML consolidando logs de Frontend y Backend
2. **`convertir_html_a_pdf.py`** - Convierte los reportes HTML a PDF

### Cómo Generar Reportes

Cuando el usuario solicite "generar reporte del día X" o "crear reporte de los días X a Y":

**Paso 1: Recopilar información de TODAS las fuentes disponibles (en orden de prioridad)**

```bash
# 1a. Logs .log (fuente primaria) - Backend
find /home/developer/Escritorio/Antigravity/ninesys-apidev/logs_gemini -name "YYYY-MM-DD*" | sort

# 1b. Logs .log (fuente primaria) - Frontend
find /home/developer/Escritorio/niesys/app_multi/logs_gemini -name "YYYY-MM-DD*" | sort

# 1c. Si NO hay logs: revisar commits de Git (fuente secundaria)
# Backend:
cd /home/developer/Escritorio/Antigravity/ninesys-apidev
git log --all --since="YYYY-MM-DD 00:00:00" --until="YYYY-MM-DD 23:59:59" --pretty=format:"%ad | %s" --date=format:"%H:%M"

# Frontend:
cd /home/developer/Escritorio/niesys/app_multi
git log --all --since="YYYY-MM-DD 00:00:00" --until="YYYY-MM-DD 23:59:59" --pretty=format:"%ad | %s" --date=format:"%H:%M"

# 1d. Si hay conversaciones de esa fecha: revisar walkthrough.md y task.md en:
# /home/developer/.gemini/antigravity/brain/[CONVERSATION-ID]/walkthrough.md
# /home/developer/.gemini/antigravity/brain/[CONVERSATION-ID]/task.md
```

> **⚠️ IMPORTANTE:** Si no hay archivos `.log` para un día donde sí se trabajó, 
> SIEMPRE reconstruye desde commits de Git. Nunca omitas un día solo porque falten logs.
> Indica en el reporte que las tareas fueron reconstruidas desde Git.

**Paso 2: Generar el reporte HTML**

Usando la plantilla establecida (ver reportes existentes en `/home/developer/Escritorio/reportes_logs/`).

**Paso 3: Convertir a PDF (OBLIGATORIO)**

```bash
python3 /home/developer/Escritorio/convertir_html_a_pdf.py
```

El script convierte automáticamente todos los HTML en `reportes_logs/` que no tengan PDF.


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

## Reinicio de Empresas (Database Reset)

### ⚠️ Operación Crítica

El reinicio de una empresa **elimina permanentemente** todos los datos operacionales (órdenes, clientes, pagos, inventario, diseños, lotes) pero **preserva la configuración** (departamentos, productos, catálogos, comisiones).

### Script de Reinicio

**Ubicación:** `/home/developer/Escritorio/Antigravity/ninesys-apidev/scripts/reset_company_database.sh`

**Documentación completa:** `scripts/README_RESET_DATABASE.md`

### Cómo Reiniciar una Empresa en el VPS

El script debe ejecutarse **directamente en el VPS**, no en local.

#### Paso 1: Copiar el script al VPS

```bash
cd /home/developer/Escritorio/Antigravity/ninesys-apidev
scp scripts/reset_company_database.sh vps-ninesys:/tmp/reset_company_database.sh
```

#### Paso 2: Ejecutar en el VPS

```bash
ssh vps-ninesys "chmod +x /tmp/reset_company_database.sh && /tmp/reset_company_database.sh <ID_EMPRESA>"
```

**Ejemplos:**
```bash
# Reiniciar empresa 174 (pruebas)
ssh vps-ninesys "chmod +x /tmp/reset_company_database.sh && /tmp/reset_company_database.sh 174"

# Reiniciar empresa 163 (producción - ⚠️ usar con extrema precaución)
ssh vps-ninesys "chmod +x /tmp/reset_company_database.sh && /tmp/reset_company_database.sh 163"
```

#### Paso 3: Proporcionar confirmaciones

El script solicitará **dos confirmaciones**:

1. **Primera confirmación:** Escribir `SI ELIMINAR`
2. **Segunda confirmación:** Escribir el ID de la empresa (ej: `174`)

### Proceso Automático del Script

1. ✅ Verifica que la base de datos existe
2. 📦 Crea backup automático (ej: `/home/backups/company_resets/backup_api_emp_174_YYYYMMDD_HHMMSS.sql`)
3. 🗑️ Trunca 46 tablas operacionales
4. 🔄 Resetea AUTO_INCREMENT a 1 (próxima orden será #1)
5. ➕ Inserta cliente de prueba (ID: 1)
6. 📝 Genera log detallado

### Qué se Elimina

- ❌ Todas las **órdenes** y sus productos
- ❌ Todos los **clientes**
- ❌ Todo el **inventario** y sus movimientos
- ❌ Todos los **pagos** y abonos  
- ❌ Todos los **diseños** y revisiones
- ❌ Todos los **lotes** de producción
- ❌ Todo el **historial** operacional

### Qué se Mantiene

- ✅ **Configuración** general (tabla `config`)
- ✅ **Departamentos** y su orden de proceso
- ✅ **Catálogos** (telas, insumos, impresoras)
- ✅ **Productos** y sus configuraciones
- ✅ **Tallas** (`sizes`)
- ✅ **Comisiones** por producto/departamento
- ✅ **Tiempos** de producción
- ✅ **Insumos** asignados a productos

### Empresas Disponibles

Según la base de datos central `api_empresas`:

| ID  | Nombre | Base de Datos | Uso |
|-----|--------|---------------|-----|
| 163 | NineteenCustom | `api_emp_163` | 🏭 Producción |
| 171 | Pruebas | `api_emp_171` | 🧪 Testing |
| 174 | Pruebas Dev | `api_emp_174` | 🔧 Desarrollo |

### Recuperación de Datos

Si necesitas restaurar un backup:

```bash
# Listar backups disponibles en el VPS
ssh vps-ninesys "ls -lh /home/backups/company_resets/"

# Restaurar un backup específico
ssh vps-ninesys "mysql -u root api_emp_174 < /home/backups/company_resets/backup_api_emp_174_20260206_113511.sql"
```

### Notas Importantes

1. **Siempre probar primero en empresa 171 o 174** antes de resetear empresa de producción (163)
2. El script **detecta automáticamente** si está en VPS o local y ajusta las rutas
3. Los backups se guardan en `/home/backups/company_resets/` en el VPS
4. Los logs se guardan en `/home/api.nineteengreen.com/logs_reset/` en el VPS
5. El reset tarda aproximadamente **10-30 segundos** dependiendo del tamaño de los datos

---

Para actualizar la aplicación en el servidor de producción (VPS Contabo), **SOLO BAJO PETICIÓN DEL USUARIO**, utiliza el siguiente comando:

```bash
# RECUERDA: Solo ejecutar esto si el usuario lo pide explícitamente
ssh vps-contabo "cd /home/api.nineteencustom.com/public_html && git fetch origin && git checkout refactor/modular-routes && git pull origin refactor/modular-routes"
```

