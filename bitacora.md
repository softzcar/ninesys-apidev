---
**Interacción con Gemini CLI - [Fecha: 2025-07-27]**

**Contexto Actual:**
Hemos logrado instalar PHP 8.1 y PhpSpreadsheet en el VPS. Confirmamos que `vendor/autoload.php` se carga correctamente al inicio de la aplicación. También hemos resuelto los errores de compatibilidad de PHP 8.1 en `ActionPayload.php` y `ActionError.php` añadiendo el tipo de retorno `mixed` a los métodos `jsonSerialize()`.

El endpoint `/products-massive-upload` fue modificado para generar un archivo Excel de prueba con PhpSpreadsheet, guardándolo en `public/downloads/carga_productos/` y devolviendo una URL accesible. Esta prueba confirmó que PhpSpreadsheet funciona y que la gestión de `use` statements es correcta.

**Últimos Avances:**
*   Se corrigió el error "Creation of dynamic property LocalDB::$pass is deprecated" en `app/model/LocalDB.php` declarando explícitamente las propiedades `$dsn`, `$user` y `$pass`.
*   El endpoint `/products-massive-upload` ahora se conecta a la base de datos y realiza consultas para obtener `atributos` y `categorias`.
*   La respuesta del endpoint confirma que los datos de la base de datos se están recuperando correctamente.

**Objetivo Principal:**
Implementar la funcionalidad de carga masiva de productos mediante una plantilla Excel dinámica generada por el backend (Slim Framework con PhpSpreadsheet). Esta plantilla permitirá la definición flexible de precios por producto con descripciones personalizadas.

**Plan de Implementación Propuesto (Discutido y Pendiente de Confirmación):**

1.  **Definición Detallada de la Estructura de Datos del Producto (CONFIRMADO):**
    *   **Hoja 1: "Productos"**
        *   **SKU**: Texto, Obligatorio. Identificador único.
        *   **Nombre**: Texto, Obligatorio. Nombre principal del producto.
        *   **Descripción**: Texto, Opcional. Descripción detallada.
        *   **Stock**: Numérico, Obligatorio. Cantidad inicial de inventario.
        *   **Categoría**: Texto, Obligatorio. Campo "Select" (lista desplegable). Opciones desde `categorias` (ID, Nombre).
        *   **Atributos**: Texto, Opcional. Campo complejo, lista de atributos desde `atributos`.
        *   **Precio Venta**: Numérico, Obligatorio. Precio de venta principal/por defecto.
        *   **Precio Costo**: Numérico, Opcional. Costo de producción/adquisición.
        *   **Activo**: Texto, Opcional. Campo "Select" (ej. "Sí", "No").

    *   **Hoja 2: "Precios"**
        *   **SKU_Producto**: Texto, Obligatorio. Campo "Select" dinámico. Opciones desde la columna `SKU` de la Tabla de Excel en la hoja "Productos".
        *   **Valor_Precio**: Numérico, Obligatorio. Valor numérico para este tipo de precio.
        *   **Descripción_Precio**: Texto, Obligatorio. Texto libre para descripción personalizada del precio.

    *   **Lógica para el Endpoint de Generación de Plantilla:**
        *   Obtener datos para "Selects" de `categorias` y `atributos`.
        *   Construir Excel:
            *   Hoja "Productos".
            *   Hoja oculta "ListadoCategorias" con nombres de categorías.
            *   Validación de datos para "Categoría" en "Productos" referenciando "ListadoCategorias".
            *   Hoja "Precios".
            *   Definir columna "SKU" en "Productos" como una Tabla de Excel (ej. `Tabla_Productos`).
            *   Validación de datos para "SKU_Producto" en "Precios" referenciando `Tabla_Productos[SKU]`.

2.  **Diseño de la Estructura de la Plantilla Excel:**
    *   **Hoja "Productos":** Definir columnas, qué columnas serán parte de la Tabla de Excel (al menos SKU), y referencias para "selects".
    *   **Hoja "Precios":** Definir columnas (`SKU_Producto`, `Valor_Precio`, `Descripción_Precio`), formato de `Valor_Precio`, y si `Descripción_Precio` es texto libre.
    *   **Hojas Ocultas:** Definir `ListadoCategorias` y otras necesarias (atributos, unidades de medida, etc.), incluyendo sus columnas (ID, Nombre).

3.  **Implementación del Endpoint de Generación de Plantilla (Backend - Slim Framework):**
    *   Crear endpoint `GET` (ej. `/api/products/template-excel`).
    *   Lógica: Obtener `ID_EMPRESA`, consultar DB para datos dinámicos, usar PhpSpreadsheet para crear el Excel (hojas, tablas, validaciones de datos dinámicas), y servir el archivo para descarga.

4.  **Consideraciones para la Carga del Archivo (Frontend y Backend):**
    *   **Frontend (Nuxt.js):** Manejo de subida, lectura de múltiples hojas con `xlsx` (SheetJS), asociación de precios por SKU, y estructura JSON para el backend.
    *   **Backend (Slim Framework):** Endpoint `POST` para recibir datos, validación de datos, inserción/actualización de productos y precios en DB, manejo de transacciones.

**Puntos de Discusión Clave antes de Escribir Código (CONFIRMADOS):**

1.  **Estructura de la Base de Datos para Precios:**
    *   Tabla: `products_prices`
    *   Columnas:
        *   `_id`: `int(11)` (Primary Key, Auto-increment)
        *   `id_product`: `int(11)` (Foreign Key a la tabla `products`)
        *   `price`: `decimal(7,2)`
        *   `descripcion`: `varchar(128)`

2.  **Manejo de Errores:**
    *   **Comunicación al usuario:** Si la plantilla no se puede generar, se devolverá un mensaje genérico y amigable (sin detalles técnicos).
    *   **Formato de respuesta JSON:** Siempre incluirá `success` (booleano) y `message` (cadena de texto). En caso de éxito, puede incluir otras propiedades (ej. `file_url`).

---