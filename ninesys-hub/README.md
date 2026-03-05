# Ninesys Dev Hub 🚀

Centro de control centralizado para la gestión de scripts de desarrollo, despliegue y operaciones.

## Cómo usar el Hub

1.  **Iniciar el Servidor**:
    ```bash
    cd /home/developer/Escritorio/Antigravity/ninesys-apidev/ninesys-hub/server
    npm install
    npm start
    ```

2.  **Acceder a la Interfaz**:
    Abre tu navegador en: [http://localhost:4000](http://localhost:4000)

## Funcionalidades
- **Ejecución en un clic**: Lanza scripts de despliegue, sincronización o reportes desde la web.
- **Consola Real-time**: Visualiza la salida de los scripts (stdout/stderr) en tiempo real mediante SSE.
- **Descripciones Claras**: Cada script incluye una breve descripción de su función y parámetros.

## Estructura
- `bin/`: Enlaces simbólicos a los scripts originales.
- `server/`: Backend en Node.js para la ejecución de procesos.
- `ui/`: Interfaz web moderna (Vanilla JS/CSS).
