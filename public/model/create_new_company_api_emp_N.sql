SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */
;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */
;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */
;
/*!40101 SET NAMES utf8mb4 */
;
CREATE TABLE `abonos` (
  `_id` int(11) NOT NULL COMMENT 'ID de la talba',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `abono` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'monto del abono',
  `descuento` decimal(10, 2) DEFAULT 0.00 COMMENT 'Descuento del abono',
  `detalle` varchar(60) DEFAULT NULL COMMENT 'Descripción del abono',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha del abono'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Abonos realizados a órdenes. Registra pagos parciales efectuados por clientes para reducir el saldo pendiente de una orden.';
CREATE TABLE `aprobacion_clientes` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_diseno` int(11) DEFAULT NULL,
  `check` tinyint(1) NOT NULL DEFAULT 1,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro para notificación de aprobación de diseño';
CREATE TABLE `asistencias` (
  `_id` int(11) NOT NULL COMMENT 'ID unico del registro',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `registro` varchar(14) DEFAULT NULL COMMENT 'Entrada Mañana, Salida Mañana, Entrada Tarde, Salida Tarde',
  `detalle` mediumtext DEFAULT NULL COMMENT 'Detalle de el registro si se requiere',
  `moment` datetime DEFAULT current_timestamp() COMMENT 'Momento de la acción'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de control de asistencia de empleados. Almacena entradas y salidas con timestamp para reportes semanales y cálculo de horas trabajadas.';
CREATE TABLE `caja` (
  `_id` int(11) NOT NULL,
  `id_caja_cierres` int(11) DEFAULT NULL COMMENT 'ID del cierre de la caja',
  `monto` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'monto de la moneda',
  `moneda` varchar(10) NOT NULL DEFAULT '0' COMMENT 'dolares, pesos, bolivares',
  `tasa` decimal(12, 2) NOT NULL DEFAULT 1.00 COMMENT 'tasa de conversion para el dia',
  `detalle` text DEFAULT NULL,
  `tipo` varchar(20) DEFAULT NULL COMMENT 'orden_nueva, orden_abono, otro_abono, retiro, cierre_de_caja, ajuste',
  `id_empleado` int(11) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registros de los movimientos del efectivo en la caja antes del cierre, luego se reinicia, el histroico de ingresos queda en la tabla metodos_de_pago';
CREATE TABLE `caja_cierres` (
  `_id` int(11) NOT NULL COMMENT 'ID único del cierre',
  `dolares` decimal(10, 0) NOT NULL DEFAULT 0 COMMENT 'Total recaudado en dólares',
  `pesos` decimal(10, 0) NOT NULL DEFAULT 0 COMMENT 'Total recaudado en pesos',
  `bolivares` decimal(10, 0) NOT NULL DEFAULT 0 COMMENT 'Total recaudado en bolívares',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha del cierre',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado que realizó el cierre'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Historial de cierres de caja. Almacena totales por moneda, empleado responsable del cierre y fecha de corte para conciliación contable.';
CREATE TABLE `caja_fondos` (
  `_id` int(11) NOT NULL COMMENT 'ID único del registro',
  `id_caja_cierres` int(11) DEFAULT NULL COMMENT 'ID del cierre de la caja',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del Vendedor',
  `dolares` decimal(12, 0) NOT NULL DEFAULT 0 COMMENT 'Fondo en dólares',
  `pesos` decimal(12, 0) NOT NULL DEFAULT 0 COMMENT 'Fondo en pesos',
  `bolivares` decimal(12, 0) NOT NULL DEFAULT 0 COMMENT 'Fondo en bolívares',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha del registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Fondo de caja chica. Registra el efectivo base que permanece en caja después de cada cierre para operaciones del siguiente período.';
CREATE TABLE `catalogo_impresoras` (
  `_id` int(11) NOT NULL,
  `codigo_interno` varchar(50) NOT NULL COMMENT 'Identificador único y fácil de leer para el empleado. Ej: SUBLIMACION-01, EPSON-F570-A',
  `marca` varchar(50) DEFAULT NULL COMMENT 'Marca del fabricante. Ej: Epson, Roland',
  `modelo` varchar(100) DEFAULT NULL COMMENT 'Nombre comercial del modelo. Ej: SureColor F570',
  `capacidad_contenedor` decimal(7, 2) DEFAULT NULL COMMENT 'Capacidad del contenedor de la tinta',
  `ubicacion` varchar(100) DEFAULT NULL COMMENT 'Ubicación física para ayudar al empleado a identificarla. Ej: Taller de Estampado',
  `tipo_tecnologia` varchar(50) DEFAULT NULL COMMENT 'Tecnología para agrupar o filtrar. Ej: Sublimación, DTG, DTF',
  `estado` varchar(20) NOT NULL DEFAULT 'activa' COMMENT 'Estado actual. Ej: activa, inactiva, en_mantenimiento',
  `notas` text DEFAULT NULL COMMENT 'Cualquier información adicional relevante.',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de impresoras de la empresa. Almacena información de equipos de impresión para asignación de trabajos y control de producción.';
INSERT INTO `catalogo_impresoras` (`_id`, `codigo_interno`, `marca`, `modelo`, `capacidad_contenedor`, `ubicacion`, `tipo_tecnologia`, `estado`, `notas`, `moment`) VALUES
(1, 'IMPRESORA PRINCIPAL', 'EPSON', 'EPS_9902', 1000.00, 'PISO 1', 'CMYK', 'activa', 'Impresora con cabezales originales', CURRENT_TIMESTAMP),
(2, 'IMPRESORA SECUNDARIA', 'Mimaki', 'MK_09890', 750.00, 'PISO 2', 'CMYKW', 'activa', 'Impresora para usar solo con tintas originales', CURRENT_TIMESTAMP);
CREATE TABLE `catalogo_insumos_productos` (
  `_id` int(11) NOT NULL COMMENT 'ID único del catálogo',
  `nombre` varchar(128) NOT NULL COMMENT 'Nombre del tipo de insumo',
  `id_product` int(11) NOT NULL COMMENT 'ID del producto (FK a products._id)',
  `id_departamento` int(11) NOT NULL COMMENT 'ID del departamento',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo maestro de tipos de insumos para producción. Define categorías de materiales (telas, tintas, botones, etc.) que se asignan a productos.';

INSERT INTO `catalogo_insumos_productos` (`_id`, `nombre`, `id_product`, `id_departamento`) VALUES
(1, 'Papel para sublimación', 1, 1),
(2, 'Tela Atlética', 1, 3),
(3, 'Botones', 1, 4),
(4, 'Tinta', 1, 1),
(5, 'Tela Licra', 1, 3),
(6, 'Tela Algodón', 1, 3),
(7, 'Diseño Gráfico', 2, 7);
CREATE TABLE `catalogo_telas` (
  `_id` int(11) NOT NULL COMMENT 'Identificador unico de la tabla',
  `tela` varchar(45) DEFAULT NULL COMMENT 'Nombre de la tela',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de telas disponibles. Almacena tipos de tela con características para selección en órdenes de producción.';
INSERT INTO `catalogo_telas` (`_id`, `tela`, `moment`)
VALUES (1, 'Tela de Prueba', '2025-09-25 16:51:19');
CREATE TABLE `categories` (
  `_id` int(11) NOT NULL COMMENT 'ID único de la categoría',
  `nombre` varchar(100) DEFAULT NULL COMMENT 'Nombre de la categoría'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Categorías de productos del catálogo. Organiza productos en grupos para clasificación, filtrado y reportes de ventas.';
INSERT INTO `categories` (`_id`, `nombre`)
VALUES (1, 'Categoría de Pruebas');
CREATE TABLE `check_tareas` (
  `_id` int(11) NOT NULL COMMENT 'ID unico',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_lotes_detalles_empleados_asigandos` int(11) DEFAULT NULL COMMENT 'ID del empleado asignado en lotes_detalles',
  `id_ordenes_productos` int(11) DEFAULT NULL COMMENT 'ID del producto de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fin de tarea'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Lista de verificación de tareas por empleado. Controla el checklist de actividades completadas durante el proceso de producción de cada orden.';
CREATE TABLE `config` (
  `_id` int(11) NOT NULL,
  `app_key` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indica si el cliente tiene acceso a el sitema o está suspendido',
  `nombre_empresa` varchar(45) DEFAULT NULL,
  `identificador_fiscal` varchar(60) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL COMMENT 'Dirección de la empresa',
  `telefonos` int(60) DEFAULT NULL COMMENT 'Teléfonos de la empresa',
  `email` int(255) DEFAULT NULL COMMENT 'Email de la empresa',
  `msg_welcome` text DEFAULT NULL COMMENT 'Mensaje de bienvenida a el cliente',
  `msg_bye` text DEFAULT NULL COMMENT 'Mensajde de despedida al cliente',
  `msg_saldo` text DEFAULT NULL COMMENT 'Mensaje de saldo pendiente del cliente',
  `sys_mostrar_detalle_terminar_indicidual` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si se muestra el formaulario de ingresar detalle de la terminación del item indicidual en el módulo de empleados al momento de terminar una tarea individual',
  `sys_mostrar_rollo_en_empleado_corte` tinyint(1) DEFAULT 0 COMMENT 'Muestra la opción sedeleccionar rollo en el módulo de empleados depatament de Corte',
  `sys_mostrar_rollo_en_empleado_estampado` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Mostrar el rollo de tela al emplado de Estampado',
  `sys_mostrar_insumo_en_empleado_costura` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Mostrar select de insumos en modulo de empleados',
  `sys_mostrar_insumo_en_empleado_limpieza` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'empleados',
  `sys_mostrar_insumo_en_empleado_revision` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'empleados',
  `sys_comision_de_costura` varchar(8) NOT NULL DEFAULT 'producto' COMMENT 'Define si a costura se le calclua comision por el porcentaje en la tabla empleados o el porcentaje ne la tabla productos',
  `multiplicador_precio` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Multiplicador de precio predeterminado para conversión USD a VES'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Configuración general de la empresa. Almacena parámetros del sistema como datos fiscales, mensajes de WhatsApp, opciones de visualización en módulos y tipo de comisión.';
INSERT INTO `config` (
    `_id`,
    `app_key`,
    `activo`,
    `nombre_empresa`,
    `identificador_fiscal`,
    `direccion`,
    `telefonos`,
    `email`,
    `msg_welcome`,
    `msg_bye`,
    `msg_saldo`,
    `sys_mostrar_detalle_terminar_indicidual`,
    `sys_mostrar_rollo_en_empleado_corte`,
    `sys_mostrar_rollo_en_empleado_estampado`,
    `sys_mostrar_insumo_en_empleado_costura`,
    `sys_mostrar_insumo_en_empleado_limpieza`,
    `sys_mostrar_insumo_en_empleado_revision`,
    `sys_comision_de_costura`,
    `multiplicador_precio`
  )
VALUES (
    1,
    NULL,
    1,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    0,
    0,
    1,
    0,
    0,
    0,
    'producto',
    0.00
  );
CREATE TABLE `customers` (
  `_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) DEFAULT NULL,
  `username` varchar(60) DEFAULT NULL,
  `cedula` varchar(12) DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  `billing_city` varchar(60) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de clientes. Almacena datos de contacto (nombre, cédula, teléfono, email, dirección) para facturación y comunicación.';
INSERT INTO `customers` (
    `_id`,
    `first_name`,
    `last_name`,
    `username`,
    `cedula`,
    `address`,
    `billing_city`,
    `phone`,
    `email`,
    `moment`
  )
VALUES (
    1,
    'Cliente',
    'de Pruebas',
    'Cliente Prueba',
    'V12345678',
    'Dirección de prueba',
    'Caracas',
    '584240000000',
    'clientepruebas@email.com',
    '2025-09-25 16:25:44'
  );
CREATE TABLE `departamentos` (
  `_id` int(11) NOT NULL,
  `id_modulo` int(11) DEFAULT NULL COMMENT 'ID del módulo asignado al departamento',
  `orden_proceso` int(11) NOT NULL DEFAULT 0 COMMENT 'indica el orden del proceso de fabricación',
  `departamento` varchar(256) DEFAULT NULL COMMENT 'Nombre del departamento',
  `asignar_numero_de_paso` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Interviene en proceso Es un paso de proceso de fabricación',
  `enviar_mensaje` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enviar mensaje al cliente al iniciar el paso',
  `mensaje` text DEFAULT NULL COMMENT 'Mensaje para el cliente máximo 255 caracters',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de creación'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Departamentos de la empresa con su orden en el flujo de producción. Define la secuencia de pasos de manufactura y configuración de mensajes al cliente.';
INSERT INTO `departamentos` (
    `_id`,
    `id_modulo`,
    `orden_proceso`,
    `departamento`,
    `asignar_numero_de_paso`,
    `enviar_mensaje`,
    `mensaje`,
    `moment`
  )
VALUES (
    1,
    4,
    2,
    'Impresión',
    1,
    1,
    NULL,
    '2025-09-24 19:50:20'
  ),
  (
    2,
    4,
    3,
    'Estampado',
    1,
    1,
    NULL,
    '2025-09-24 19:50:20'
  ),
  (
    3,
    4,
    4,
    'Corte',
    1,
    1,
    NULL,
    '2025-09-24 19:50:20'
  ),
  (
    4,
    4,
    5,
    'Costura',
    1,
    1,
    NULL,
    '2025-09-24 19:50:20'
  ),
  (
    5,
    1,
    0,
    'Administración',
    0,
    0,
    NULL,
    '2025-09-24 19:50:20'
  ),
  (
    6,
    2,
    0,
    'Comecialización',
    0,
    0,
    NULL,
    '2025-09-24 19:50:20'
  ),
  (
    7,
    3,
    0,
    'Diseño',
    0,
    0,
    NULL,
    '2025-09-24 19:50:20'
  ),
  (
    8,
    5,
    0,
    'Producción',
    0,
    0,
    NULL,
    '2025-09-24 19:50:20'
  );
CREATE TABLE `disenos` (
  `_id` int(11) NOT NULL COMMENT 'ID de la tabla',
  `id_orden` int(11) DEFAULT NULL COMMENT 'IDn de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del diseÑador tabla empleados',
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto asociado al diseño',
  `origen` varchar(25) NOT NULL DEFAULT 'orden_inicial' COMMENT 'Identifica el Origen del registro, puede ser ''origen_inicial'' si se crea al momento de la facturación o ''agregado_posterior'' si proviene de la creación de una revisión',
  `codigo_diseno` varchar(6) DEFAULT NULL COMMENT 'Codigo de diseño de uso interno de 6 digitos formato XX-XXX',
  `tipo` varchar(128) DEFAULT NULL COMMENT 'Tipo de diseÑo modas ó gráfico',
  `terminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el diseño ya ha sido terminado',
  `linkdrive` text DEFAULT NULL COMMENT 'Link a google drive',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de creación'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de diseños gráficos asociados a órdenes. Almacena tipo de diseño, diseñador asignado, código interno, estado de terminación y enlace a archivos en Drive.';
CREATE TABLE `disenos_ajustes_y_personalizaciones` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_diseno` int(11) DEFAULT NULL COMMENT 'ID de la tabla disenos',
  `tipo` varchar(15) DEFAULT NULL COMMENT 'Si es ajuste o personalizacion',
  `cantidad` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad de piezas trabajadas',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de creación'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de ajustes y personalizaciones de prendas. Almacena modificaciones solicitadas por el cliente con cantidad de piezas afectadas por cada ajuste.';
CREATE TABLE `empleados_lotes_fabricacion` (
  `_id` int(11) NOT NULL,
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID emleado que ejecuta la tarea',
  `id_departamento_creador` int(11) DEFAULT NULL,
  `id_departamento_actual` int(11) DEFAULT NULL,
  `estado` varchar(11) DEFAULT 'pendiente' COMMENT 'pendiente, en_curso, terminado',
  `fecha_inicio` timestamp NULL DEFAULT NULL COMMENT 'Fecha de inicio del pprocesamiento en lotes',
  `fecha_fin` timestamp NULL DEFAULT NULL COMMENT 'FEcha de finlización del porcesamienteo en lotes',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'FEcha de creación del registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'ordenes que se procesan el lotes en el modulo de Empleados';
CREATE TABLE `empleados_lotes_fabricacion_items` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_lote` int(11) DEFAULT NULL COMMENT 'ID del lote de fabricación',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Órdenes incluidas en cada lote de fabricación. Vincula las órdenes individuales con su lote padre para procesamiento en grupo.';
CREATE TABLE `inventario` (
  `_id` int(11) NOT NULL COMMENT 'Identificador unico',
  `sku` varchar(128) DEFAULT NULL COMMENT 'SKU del Item de inventario',
  `id_catalogo` int(11) DEFAULT NULL COMMENT 'ID de catalogo_insumos_productos',
  `insumo` varchar(45) DEFAULT NULL COMMENT 'Nombre del insumo',
  `unidad` varchar(6) DEFAULT NULL COMMENT 'Unidd de medida del articulo CD, LTS, ML UND',
  `costo` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio de costo del insumo',
  `rendimiento` decimal(3, 1) DEFAULT NULL,
  `cantidad` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantiad actual del insumo',
  `cantidad_inicial` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor incial del insumo',
  `color` varchar(64) DEFAULT NULL COMMENT 'Color del insumo',
  `ancho` decimal(7, 2) DEFAULT 0.00 COMMENT 'ancho del insumo',
  `elongacion` varchar(32) DEFAULT NULL COMMENT 'Elongación del material',
  `detalles` text DEFAULT NULL COMMENT 'Detalles del insumo',
  `departamento` varchar(14) DEFAULT NULL COMMENT 'Departamento al que pertence el insumo',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de insumos disponibles en inventario. Almacena materiales de producción con SKU, cantidad actual e inicial, costo, unidad de medida y departamento asignado.';

INSERT INTO `inventario` (`_id`, `sku`, `id_catalogo`, `insumo`, `unidad`, `costo`, `rendimiento`, `cantidad`, `cantidad_inicial`, `color`, `ancho`, `elongacion`, `detalles`, `departamento`, `moment`) VALUES
(1, 'PAP_001', 1, 'Papel de pruebas', 'Mts', 20.00, 1.0, 250.00, 250.00, 'BLANCO', 0.90, NULL, 'Papel para pruebas de impresión', 'Impresión', CURRENT_TIMESTAMP),
(2, 'TEL_001', 6, 'Tela de pruebas', 'Kg', 80.00, 3.96, 24.00, 24.00, 'BLANCO', 1.50, 'HORIZONTAL', 'Tela para pruebas de estampado', 'Estampado', CURRENT_TIMESTAMP),
(3, 'TIN_C_001', 4, 'Tinta Cyan', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'CYAN', NULL, NULL, 'Tinta cyan para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(4, 'TIN_M_001', 4, 'Tinta Magenta', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'MAGENTA', NULL, NULL, 'Tinta magenta para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(5, 'TIN_Y_001', 4, 'Tinta Yellow', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'YELLOW', NULL, NULL, 'Tinta yellow para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(6, 'TIN_K_001', 4, 'Tinta Black', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'BLACK', NULL, NULL, 'Tinta negra para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(7, 'BOT_001', 3, 'Botones blancos', 'Und', 0.50, 1.0, 1000.00, 1000.00, 'BLANCO', NULL, NULL, 'Botones blancos para prendas', 'Costura', CURRENT_TIMESTAMP);
CREATE TABLE `inventario_movimientos` (
  `_id` int(11) NOT NULL COMMENT 'Identificador unico',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la  orden - lote',
  `id_producto` int(11) DEFAULT NULL COMMENT 'ID del catálogo de productos',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `id_insumo` int(11) DEFAULT NULL COMMENT 'Id del insumoa signado',
  `id_catalogo_insumos_prodcutos` int(11) DEFAULT NULL COMMENT 'ID del catálogo seleccionado por el empleado al momento de usar el insumo',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'Id del departamento del empleado',
  `departamento` varchar(20) DEFAULT NULL COMMENT 'Nombre del departamento',
  `valor_inicial` decimal(7, 2) DEFAULT NULL COMMENT 'Valor inicial del insumo',
  `valor_final` decimal(7, 2) DEFAULT NULL COMMENT 'Valor Final del insumo ',
  `fecha` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'fecha del registro',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de movimientos de inventario de insumos. Almacena cada consumo de material vinculado a orden, producto, empleado y departamento.';
CREATE TABLE `inventario_movimientos_historial` (
  `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID único del registro',
  `id_movimiento` int(11) NOT NULL COMMENT 'ID del movimiento modificado (FK a inventario_movimientos._id)',
  `campo_modificado` varchar(50) NOT NULL COMMENT 'Nombre del campo que fue modificado',
  `valor_anterior` decimal(10, 2) DEFAULT NULL COMMENT 'Valor anterior del campo',
  `valor_nuevo` decimal(10, 2) DEFAULT NULL COMMENT 'Valor nuevo del campo',
  `id_usuario_modificacion` int(11) NOT NULL COMMENT 'ID del usuario que realizó el cambio',
  `fecha_modificacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora del cambio',
  `observaciones` text DEFAULT NULL COMMENT 'Observaciones o motivo del cambio',
  PRIMARY KEY (`_id`),
  INDEX `idx_movimiento` (`id_movimiento`),
  INDEX `idx_fecha` (`fecha_modificacion`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Historial de cambios en inventario_movimientos. Registra modificaciones de material_consumido para auditoría y trazabilidad.';
CREATE TABLE `lotes` (
  `_id` int(11) NOT NULL COMMENT 'ID Autonumérico',
  `lote` mediumtext DEFAULT NULL COMMENT 'Código del Lote',
  `fecha` date DEFAULT NULL COMMENT 'Fecha de creación del lote',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_departamento_actual` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `prioridad` int(1) NOT NULL DEFAULT 0 COMMENT '0 NORMAL, 1 URGENTE',
  `piezas_actuales` int(11) DEFAULT NULL COMMENT 'Cantidad de piezasa ctuales',
  `paso` varchar(128) DEFAULT 'responsable' COMMENT 'Paso actual del proceso, Corte, estampado, impresion, etc.',
  `detalles` mediumtext DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Controla el proceso de fabricacion';
CREATE TABLE `lotes_detalles` (
  `_id` int(10) NOT NULL COMMENT 'ID único del registro',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden de trabajo',
  `id_woo` int(11) DEFAULT NULL COMMENT 'ID del producto en Woocommerce',
  `progreso` varchar(11) NOT NULL DEFAULT 'por iniciar' COMMENT 'Nos indica el estado de desarrollo de la tarea: por niciar, en curso, terminada',
  `id_ordenes_productos` int(11) NOT NULL DEFAULT 0 COMMENT 'ID del producto ordenes_productos',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'id del empleado responsable de la producción',
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de en caso de ser una reposción',
  `terminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si la tarea se ha terminado para la lista de verificación en el módulo de empleados',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `departamento` varchar(256) DEFAULT NULL COMMENT 'Departamento al cual pertenecen las unidades, se guarda como histórico del registro en caso que el nombre del departamento sea editado posteriormente',
  `unidades_solicitadas` int(11) DEFAULT 0 COMMENT 'Unidades para el calculo de pago',
  `comision` decimal(8, 2) DEFAULT 0.00 COMMENT 'Porcentaje para el cálculo de la comisión',
  `detalles` varchar(255) DEFAULT NULL COMMENT 'Información adicional del producto',
  `fecha_inicio` timestamp NULL DEFAULT NULL COMMENT 'Momento en que el primer empleado asignado ha iniciado el trabajo	',
  `fecha_terminado` timestamp NULL DEFAULT NULL COMMENT 'Momento en que el último empleado afirma haber terminado el trabajo',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Controla el proceso de fabciacion x producto y empleado';
CREATE TABLE `lotes_detalles_empleados_asignados` (
  `_id` int(11) NOT NULL,
  `id_lotes_detalles` int(11) DEFAULT NULL COMMENT 'ID de lotes_detalles',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID empleado',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `progreso` varchar(11) DEFAULT 'por iniciar' COMMENT 'Nos indica el estado de desarrollo de la tarea: por iniciar, en curso, terminada por cada empleado para el control de su proceso en el modulo de empleados',
  `procentaje_comision` decimal(8, 2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje para el cálculo de la comisión',
  `terminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si la tarea se ha terminado para la lista de verificación en el módulo de empleados',
  `fecha_inicio` timestamp NULL DEFAULT NULL COMMENT 'Indica el momento en que el empleado indica que iniciado',
  `fecha_terminado` timestamp NULL DEFAULT NULL COMMENT 'Indica el momento en que el empleado indica que ha terminado la tarea'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Empleados asignados a tareas de producción con su porcentaje';
CREATE TABLE `lotes_detalles_empleados_asignados_pausas` (
  `_id` int(11) NOT NULL,
  `id_lotes_detalles_empleados_asignados` int(11) DEFAULT NULL,
  `pausa_inicio` timestamp NULL DEFAULT NULL,
  `pausa_fin` timestamp NULL DEFAULT NULL,
  `motivo` mediumtext NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci;
CREATE TABLE `lotes_fisicos` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL COMMENT 'id de la orden',
  `id_woo` int(11) DEFAULT NULL COMMENT 'id del producto en woocommerce',
  `piezas_actuales` int(11) DEFAULT NULL COMMENT 'Cantidad de unidades en el lote',
  `tela` varchar(120) DEFAULT NULL COMMENT 'Tela del corte',
  `talla` varchar(5) DEFAULT NULL COMMENT 'Nombre de la talla',
  `corte` varchar(24) DEFAULT NULL COMMENT 'Tipo de corte, dama caballeto etc',
  `categoria` int(11) DEFAULT NULL COMMENT 'ID de la categoría en woocommerce',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Controla la cantidad de piezas cortadas existentes';
CREATE TABLE `lotes_historico_solicitadas` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden que solicitó el corte del lote',
  `id_lotes_fisicos` int(11) DEFAULT NULL,
  `unidades_produccion` int(11) DEFAULT NULL COMMENT 'Unidades que se solicitan en produccion',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Histórico de unidades solicitadas';
CREATE TABLE `lotes_movimientos` (
  `_id` int(11) NOT NULL,
  `id_lotes_detalles` int(11) DEFAULT NULL COMMENT 'ID del detalle del lote',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `unidades_existentes` int(11) DEFAULT NULL COMMENT 'unidades existentes en elote al momento de el registro',
  `unidades_solicitadas_corte` int(11) DEFAULT NULL COMMENT 'Unidades solicitadas para cortar',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registra los movimientos que se efectúan sobre los lotes';
CREATE TABLE `metodos_de_pago` (
  `_id` int(11) NOT NULL COMMENT 'ID unico de la tabla',
  `id_orden` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la orden',
  `id_caja_cierres` int(11) DEFAULT NULL COMMENT 'ID del cierre de caja, nos indica si el pago ya ha sido retirado ',
  `moneda` varchar(10) DEFAULT NULL COMMENT 'tipo de moneda',
  `metodo_pago` varchar(20) DEFAULT NULL COMMENT 'Método de pago',
  `detalle` varchar(140) DEFAULT NULL COMMENT 'Detalle en caso de que el tipo de pago sea abonos u otros',
  `tipo_de_pago` varchar(13) NOT NULL DEFAULT 'Orden nueva' COMMENT 'Procedencia del pago para identificar el tipo de ingreso',
  `monto` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Monto cancelado en cada metodo de pago',
  `tasa` decimal(12, 0) DEFAULT NULL COMMENT 'Tasa de conversion con relacion al dolar',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de transacciones de pago asociadas a órdenes. Almacena método, moneda, monto, tasa de conversión y referencia al cierre de caja.';
CREATE TABLE `ordenes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_wp` int(11) DEFAULT NULL,
  `id_wp_order` int(11) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `tipo` varchar(6) NOT NULL DEFAULT 'custom',
  `responsable` int(11) DEFAULT NULL,
  `cliente_nombre` varchar(256) DEFAULT NULL,
  `cliente_cedula` varchar(45) DEFAULT NULL,
  `lote_id` varchar(33) DEFAULT NULL,
  `fecha_inicio` varchar(45) DEFAULT NULL,
  `fecha_entrega` varchar(45) DEFAULT NULL,
  `fecha_creacion` date DEFAULT NULL,
  `token` varchar(45) DEFAULT NULL,
  `pago_descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pago_total` decimal(12,2) DEFAULT 0.00,
  `pago_abono` decimal(12,2) DEFAULT 0.00,
  `pago_comision` varchar(9) NOT NULL DEFAULT 'pendiente',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de creación',
  PRIMARY KEY (`_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Tabla principal de órdenes de trabajo. Almacena información del pedido: cliente, vendedor, fechas, montos, estado del proceso y comisión.';
CREATE TABLE `ordenes_borrador_empleado` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `id_departamento` int(11) NOT NULL COMMENT 'ID del departamento',
  `borrador` mediumtext DEFAULT NULL COMMENT 'El detalle de la orden editado por el empleado',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Notas personales de empleados sobre órdenes. Permite guardar observaciones privadas por empleado y departamento.';
CREATE TABLE `ordenes_fila_orden` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `orden_fila` int(6) DEFAULT NULL COMMENT 'Orden en la fila de producción'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Cola de prioridad de órdenes de producción. Define el orden de fabricación mediante posición numérica, configurable por drag and drop.';
DELIMITER ##
CREATE TRIGGER `ordenes_fila_orden_cambios_trigger_delete`
AFTER DELETE ON `ordenes_fila_orden` FOR EACH ROW BEGIN
-- Obtiene todos los registros de ordenes_fila_orden ordenados por orden_fila
SET @cambio = (
    SELECT CONCAT(
        '[',
        GROUP_CONCAT(
          JSON_OBJECT(
            '_id',
            _id,
            'id_orden',
            id_orden,
            'orden_fila',
            orden_fila
          )
        ),
        ']'
      )
    FROM ordenes_fila_orden
    ORDER BY orden_fila ASC
  );
-- Inserta el cambio en la tabla ordenes_fila_orden_cambios
INSERT INTO ordenes_fila_orden_cambios (cambio)
VALUES (@cambio);
-- Limpieza: mantener solo los últimos 3 registros
DELETE FROM ordenes_fila_orden_cambios 
WHERE id NOT IN (
    SELECT id FROM (
        SELECT id FROM ordenes_fila_orden_cambios 
        ORDER BY fecha_cambio DESC 
        LIMIT 3
    ) AS ultimos_tres
);
END ##
DELIMITER ;

DELIMITER ##
CREATE TRIGGER `ordenes_fila_orden_cambios_trigger_insert`
AFTER INSERT ON `ordenes_fila_orden` FOR EACH ROW BEGIN
-- Obtiene todos los registros de ordenes_fila_orden ordenados por orden_fila
SET @cambio = (
    SELECT CONCAT(
        '[',
        GROUP_CONCAT(
          JSON_OBJECT(
            '_id',
            _id,
            'id_orden',
            id_orden,
            'orden_fila',
            orden_fila
          )
        ),
        ']'
      )
    FROM ordenes_fila_orden
    ORDER BY orden_fila ASC
  );
-- Inserta el cambio en la tabla ordenes_fila_orden_cambios
INSERT INTO ordenes_fila_orden_cambios (cambio)
VALUES (@cambio);
-- Limpieza: mantener solo los últimos 3 registros
DELETE FROM ordenes_fila_orden_cambios 
WHERE id NOT IN (
    SELECT id FROM (
        SELECT id FROM ordenes_fila_orden_cambios 
        ORDER BY fecha_cambio DESC 
        LIMIT 3
    ) AS ultimos_tres
);
END ##
DELIMITER ;

DELIMITER ##
CREATE TRIGGER `ordenes_fila_orden_cambios_trigger_update`
AFTER UPDATE ON `ordenes_fila_orden` FOR EACH ROW BEGIN
-- Obtiene todos los registros de ordenes_fila_orden ordenados por orden_fila
SET @cambio = (
    SELECT CONCAT(
        '[',
        GROUP_CONCAT(
          JSON_OBJECT(
            '_id',
            _id,
            'id_orden',
            id_orden,
            'orden_fila',
            orden_fila
          )
        ),
        ']'
      )
    FROM ordenes_fila_orden
    ORDER BY orden_fila ASC
  );
-- Inserta el cambio en la tabla ordenes_fila_orden_cambios
INSERT INTO ordenes_fila_orden_cambios (cambio)
VALUES (@cambio);
-- Limpieza: mantener solo los últimos 3 registros
DELETE FROM ordenes_fila_orden_cambios 
WHERE id NOT IN (
    SELECT id FROM (
        SELECT id FROM ordenes_fila_orden_cambios 
        ORDER BY fecha_cambio DESC 
        LIMIT 3
    ) AS ultimos_tres
);
END ## 
DELIMITER ;

CREATE TABLE `ordenes_fila_orden_cambios` (
  `id` int(11) NOT NULL COMMENT 'ID único',
  `cambio` mediumtext NOT NULL COMMENT 'Snapshot JSON del estado de la fila',
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha del cambio'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Historial de cambios en la cola de prioridad. Almacena snapshots JSON del estado de la fila. Mantiene solo los últimos 3 registros mediante triggers.';
CREATE TABLE `ordenes_fila_reposiciones` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de la reposición',
  `orden_fila` smallint(6) DEFAULT NULL COMMENT 'Orden en la fila de producción'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Cola de prioridad de reposiciones de producción. Define el orden de fabricación de reposiciones mediante posición numérica.';
CREATE TABLE `ordenes_observaciones` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) NOT NULL,
  `observaciones` longtext DEFAULT NULL COMMENT 'Observaciones de la orden desde QuillEditor'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Observaciones de las ordenes en html e imágenes incrustadas';
CREATE TABLE `ordenes_productos` (
  `_id` int(11) NOT NULL COMMENT 'ID del registro',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_woo` int(11) DEFAULT NULL COMMENT 'ID del producto en woocommerce',
  `id_tela` int(11) DEFAULT NULL COMMENT 'ID de la tela a utilizar del catálogo de telas',
  `id_category` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la catagoria en WooCommerce ',
  `id_products_attributes` int(11) DEFAULT NULL COMMENT 'ID de la variante del producto',
  `category_name` varchar(20) DEFAULT NULL COMMENT 'NOMBRE de la categoria en woocommerce',
  `name` varchar(240) DEFAULT NULL COMMENT 'Nombre del producto',
  `cantidad` DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT 'Cantidad del producto',
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `talla` varchar(8) DEFAULT NULL COMMENT 'Talla del producto',
  `corte` varchar(32) DEFAULT NULL COMMENT 'Dama, caballero, niño',
  `metros` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Metros de material utilizado',
  `desperdicio` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Restos del material',
  `rollo` int(11) DEFAULT NULL COMMENT 'ID de el catálogo de telas',
  `tela` varchar(128) DEFAULT NULL COMMENT 'Tela principal seleccionada desde Comercialización',
  `precio_unitario` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del producto',
  `precio_woo` decimal(10, 2) DEFAULT NULL COMMENT 'Precio de Woocommerce',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Productos incluidos en cada orden. Detalla cada ítem con cantidad, talla, corte, tela, precio unitario y categoría.';
CREATE TABLE `ordenes_tmp` (
  `_id` int(11) NOT NULL COMMENT 'Clave primaria',
  `form` longtext DEFAULT NULL COMMENT 'Datos del formulario',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del vendedor',
  `tipo` varchar(11) NOT NULL DEFAULT 'Orden' COMMENT 'Orden o Presupuesto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Ordenes guardadas pendientes por terminar';
CREATE TABLE `ordenes_vinculadas` (
  `_id` int(11) NOT NULL COMMENT 'id de la tabla',
  `id_father` int(11) DEFAULT NULL COMMENT 'ID de la orden principal',
  `id_child` int(11) DEFAULT NULL COMMENT 'ID de la orden secundaria',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Relación padre-hijo entre órdenes. Permite vincular órdenes relacionadas para procesamiento y facturación conjunta.';
CREATE TABLE `pagos` (
  `_id` int(11) NOT NULL COMMENT 'ID unico',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de la reposición se usa para identificar la reposición en los pagos y filtrar las reposiciones terminadas en el modulo de empleados',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento del empleado, lo utilizamos para identificar si es reposición a cual departamento de los que tenga asignados el empleado pertenece el pago. ',
  `id_metodos_de_pago` int(11) DEFAULT NULL COMMENT 'ID de la tabla metodos_de_pago',
  `id_lotes_detalles` int(11) DEFAULT NULL COMMENT 'ID del registro asociado al pago',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `cantidad` int(11) DEFAULT NULL COMMENT 'Cantidad de items a calcular',
  `monto_pago` decimal(12, 2) DEFAULT NULL COMMENT 'Monto a pagar',
  `comision` decimal(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Comision usada para el calculo del pago',
  `comision_tipo` varchar(64) DEFAULT NULL COMMENT 'Tipo de comision: fija, variable o monto fijo',
  `detalle` varchar(16) DEFAULT NULL COMMENT 'Detalle de el pago, en el caso de diseño pra diferenciar si el pago es por ajuste, personalizacion etc, en el caso de los empleados no es relevante pues es un pago unico por item trabajado registrado en la tabla id_lotes_detalles',
  `estatus` varchar(9) DEFAULT NULL COMMENT '`aprobado` es el estado por defecto, se crea al terminar la tarea desde el modulo del empleado y `rechazado` se asigna cuando hay una revision y se vuelve a asignar cuando el empleado repite la tarea',
  `fecha_pago` timestamp NULL DEFAULT NULL COMMENT 'Fecha en que se raliza el pago si es NULL no se ha realizado el pago',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de pagos a empleados, vendedores y diseñadores. Almacena monto, comisión, estado y fecha de pago.';
CREATE TABLE `piezas_cortadas` (
  `_id` int(11) NOT NULL COMMENT 'ID unico',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_inventario` int(11) DEFAULT NULL COMMENT 'ID del insumo',
  `id_ordenes_productos` int(11) DEFAULT NULL COMMENT 'ID de los detalles de el producto cortado',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado que hizo el corte',
  `peso` decimal(5, 2) DEFAULT NULL COMMENT 'Peso en Gramos de los cortes',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Detalles de las piezas cortadas';
CREATE TABLE `presupuestos` (
  `_id` int(11) NOT NULL,
  `id_wp` int(10) unsigned DEFAULT NULL COMMENT 'ID del cliente (FK a customers._id)',
  `id_wp_order` int(11) DEFAULT NULL COMMENT 'ID de la orden generada en Wocommerce',
  `status` varchar(45) DEFAULT NULL COMMENT 'Status de la orden: activa, pausada, cancelada, terminada, entregada',
  `tipo` varchar(6) NOT NULL DEFAULT 'custom' COMMENT 'Identificar si la orden pertence a custom o a sport',
  `responsable` int(11) DEFAULT NULL COMMENT 'ID del Vendedor',
  `cliente_nombre` varchar(40) DEFAULT NULL COMMENT 'Nombre del cliente',
  `cliente_cedula` varchar(45) DEFAULT NULL COMMENT 'Cedula del cliente',
  `lote_id` varchar(33) DEFAULT NULL COMMENT 'ID del Lote',
  `fecha_inicio` varchar(45) DEFAULT NULL COMMENT 'Fecha de inicio de la orden',
  `fecha_entrega` varchar(45) DEFAULT NULL COMMENT 'Fecha de entrega de la orden',
  `observaciones` longtext DEFAULT NULL COMMENT 'Detalles de la orden',
  `fecha_creacion` date DEFAULT NULL,
  `token` varchar(45) DEFAULT NULL COMMENT 'Token random',
  `pago_descuento` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Descuento sobre le monto de la orden',
  `pago_total` decimal(12, 2) DEFAULT 0.00 COMMENT 'Montototal de la orden',
  `pago_abono` decimal(12, 2) DEFAULT 0.00 COMMENT 'Monto abonado',
  `pago_comision` varchar(9) NOT NULL DEFAULT 'pendiente' COMMENT 'Los valores puedes ser pendiente: cuando aun no se ha pagado el total de la orden al vendedor, pagado, cuando se ha  terminado de pagar la totalidad de comisiones al vendedor, anulado, cuando por algun motivo no se terminará de pagar el vanededor y el administrador decide anular los pagos de esta orden',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creación del registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci;
CREATE TABLE `presupuestos_productos` (
  `_id` int(11) NOT NULL COMMENT 'ID del registro',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_woo` int(11) DEFAULT NULL COMMENT 'ID del producto en woocommerce',
  `id_category` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la catagoria en WooCommerce ',
  `category_name` varchar(20) DEFAULT NULL COMMENT 'NOMBRE de la categoria en woocommerce',
  `name` varchar(240) DEFAULT NULL COMMENT 'Nombre del producto',
  `cantidad` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad del producto',
  `talla` varchar(8) DEFAULT NULL COMMENT 'Talla del producto',
  `corte` varchar(32) DEFAULT NULL COMMENT 'Dama, caballero, niño',
  `id_catalogo_telas` int(11) DEFAULT NULL COMMENT 'IDde el catálogo de telas',
  `tela` varchar(128) DEFAULT NULL COMMENT 'Tela principal seleccionada desde Comercialización',
  `id_products_attributes` int(11) DEFAULT NULL COMMENT 'ID de la variante del producto',
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `id_tela` int(11) DEFAULT NULL COMMENT 'ID de la tela a utilizar del catálogo de telas',
  `precio_unitario` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del producto',
  `precio_woo` decimal(10, 0) DEFAULT NULL COMMENT 'Precio de Woocommerce',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci;
CREATE TABLE `products` (
  `_id` bigint(20) UNSIGNED NOT NULL,
  `product` text DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `fisico` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indica true si es un [rpducto virtual como diseños, patronajes o indica si es un producto fisico, si es falso indica un producto virtual o digital',
  `es_diseno` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el producto pretenece al departamento de diseño',
  `price` decimal(20, 2) DEFAULT NULL,
  `comision` decimal(7, 2) DEFAULT 0.00 COMMENT 'Monto para el calculo de comisión variable',
  `stock_quantity` int(11) DEFAULT 0 COMMENT 'Existencia en inventario\r\n',
  `product_description` text DEFAULT NULL COMMENT 'Descripción para mostrar e el sistema y la teienda',
  `category_ids` varchar(255) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo maestro de productos. Almacena productos con SKU, precio base, comisión, stock y si es producto físico o digital.';
INSERT INTO `products` (
    `_id`,
    `product`,
    `sku`,
    `fisico`,
    `es_diseno`,
    `price`,
    `comision`,
    `stock_quantity`,
    `product_description`,
    `category_ids`,
    `moment`
  )
VALUES (
    1,
    'Producto de pruebas',
    'PRU_01',
    1,
    0,
    10.00,
    0.20,
    12,
    'Producto de pruebas',
    '1',
    '2025-09-25 13:36:26'
  ),
  (
    2,
    'Diseño Gráfico',
    'DIS_01',
    0,
    1,
    15.00,
    10.00,
    12,
    'Diseño Gráfico de pruebas',
    '1',
    '2025-09-25 13:36:26'
  );
CREATE TABLE `products_attributes` (
  `_id` int(11) NOT NULL,
  `attribute_name` varchar(255) NOT NULL COMMENT 'Nombre del atributo',
  `precio` decimal(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del atributo'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de atributos para productos';
INSERT INTO `products_attributes` (`_id`, `attribute_name`, `precio`)
VALUES (1, 'Atributo de pruebas', 5.00);
CREATE TABLE `products_attributes_values` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_product` int(11) NOT NULL COMMENT 'id del prodcuto',
  `id_product_attribute` int(11) NOT NULL COMMENT 'id del atributo del producto',
  `attribute_value` varchar(128) NOT NULL COMMENT 'Descripción del atributo del producto',
  `attribute_price` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del atributo'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Atributos asignados a los productos';
CREATE TABLE `products_comisiones` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `comision` decimal(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Comisión asignada'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Comisiones asignada a los productos por departamento';
INSERT INTO `products_comisiones` (
    `_id`,
    `id_product`,
    `id_departamento`,
    `comision`
  )
VALUES (1, 1, 1, 0.50),
(2, 1, 2, 0.50), 
(3, 1, 3, 0.50),
(4, 1, 4, 0.50),
(5, 1, 6, 0.50),
(6, 1, 7, 0.50);
CREATE TABLE `empleados_salario` (
  `id_empleado` INT(11) UNSIGNED NOT NULL COMMENT 'ID del empleado (Referencia a la tabla principal de empleados)',
  `sueldo_base` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Salario mensual fijo antes de cualquier deducción o bono.',
  `moneda` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT 'Moneda en la que se paga el sueldo (ej: USD, VES).',
  `bonos_fijos` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Monto fijo mensual o bonos regulares que forman parte del ingreso bruto.',
  `fecha_inicio_contrato` DATE NOT NULL COMMENT 'Fecha de inicio del contrato del empleado.',
  `pago_mensual_fijo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si se paga mensualmente, 0 si es quincenal o semanal.',
  `fecha_actualizacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de la última modificación.',
  PRIMARY KEY (`id_empleado`),
  KEY `idx_fecha_inicio` (`fecha_inicio_contrato`),
  KEY `idx_moneda` (`moneda`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Tabla de salarios de empleados';

CREATE TABLE `salario_carga_familiar` (
  `id_carga` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID único del registro de carga familiar.',
  `id_empleado` INT(11) UNSIGNED NOT NULL COMMENT 'ID del empleado asociado a la carga familiar.',
  `nombre_completo` VARCHAR(100) NOT NULL COMMENT 'Nombre completo del familiar.',
  `cedula_o_id` VARCHAR(20) DEFAULT NULL COMMENT 'Cédula o ID del familiar.',
  `tipo_relacion` VARCHAR(50) NOT NULL COMMENT 'Tipo de relación con el empleado (hijo, esposa, padre, etc).',
  `fecha_nacimiento` DATE NOT NULL COMMENT 'Fecha de nacimiento del dependiente para verificar edad límite para deducciones.',
  `es_deducible_impuesto` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si califica como deducción fiscal según las leyes locales.',
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro en el sistema.',
  PRIMARY KEY (`id_carga`),
  KEY `idx_empleado` (`id_empleado`),
  KEY `idx_tipo_relacion` (`tipo_relacion`),
  KEY `idx_fecha_nacimiento` (`fecha_nacimiento`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Tabla de carga familiar para deducciones fiscales de empleados';
CREATE TABLE `pagos_salarios` (
  `_id` INT NULL AUTO_INCREMENT ,
  `id_pago` INT NULL COMMENT 'ID del pago' ,
  `tipo_salario` VARCHAR(9) NULL DEFAULT 'quincenal' COMMENT 'Periodo de pago del salario' ,
  `numero_semana` INT(2) NULL COMMENT 'Número de la semana cancelada' ,
  `monto` DECIMAL(10,2) NULL COMMENT 'Monto del salario cancelado' ,
  `moment` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ,
  PRIMARY KEY (`_id`)
) ENGINE = InnoDB COMMENT = 'Guarda los datos de los salarios cancelados';

CREATE TABLE `products_prices` (
 `_id` int(11) NOT NULL,
 `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto',
 `price` decimal(7, 2) DEFAULT NULL COMMENT 'Precio del producto',
 `descripcion` varchar(128) DEFAULT NULL COMMENT 'Descripción del precio'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Precios de productos';
CREATE TABLE `comisiones_pagados` (
 `_id` int(11) NOT NULL COMMENT 'ID único',
 `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
 `tipo_comision` varchar(12) DEFAULT NULL COMMENT 'Tipo de comisión',
 `numero_semana` int(2) DEFAULT NULL COMMENT 'Número de semana',
 `monto` decimal(10,2) DEFAULT NULL COMMENT 'Monto de la comisión',
 `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de comisiones pagadas a empleados. Almacena pagos de comisiones por semana y tipo.';
CREATE TABLE `pagos_abonos` (
 `_id` INT NOT NULL AUTO_INCREMENT ,
 `id_pago` INT NULL COMMENT 'ID de la tabla pagos' ,
 `monto` DECIMAL(10,2) NULL DEFAULT '0' COMMENT 'monto de la transacción' ,
 `descripcion` VARCHAR(512) NULL DEFAULT NULL COMMENT 'descripción de la transacción' ,
 `moment` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
 PRIMARY KEY (`_id`)
) ENGINE = InnoDB COMMENT = 'Registra los abonos adicionales para en el momento del pago';

CREATE TABLE `pagos_descuentos` (
 `_id` INT NOT NULL AUTO_INCREMENT ,
 `id_pago` INT NULL COMMENT 'ID de la tabla pagos' ,
 `monto` DECIMAL(10,2) NULL DEFAULT '0' COMMENT 'monto de la transacción' ,
 `descripcion` VARCHAR(512) NULL DEFAULT NULL COMMENT 'descripción de la transacción' ,
 `moment` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
 PRIMARY KEY (`_id`)
) ENGINE = InnoDB COMMENT = 'Registra los descuentos para en el momento del pago';

INSERT INTO `products_prices` (`_id`, `id_product`, `price`, `descripcion`) VALUES
(1, 1, 25.00, 'Detal'),
(2, 1, 22.00, 'Mayor'),
(3, 2, 15.00, 'Unitario');
CREATE TABLE `products_sizes_eficiencia` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `id_catalogo_insumos_prodcutos` int(11) DEFAULT NULL COMMENT 'ID de la tabla catalogo_insumos_productos',
  `cantidad` decimal(3, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad de insumo',
  `unidad` varchar(64) DEFAULT NULL COMMENT 'Unidad de medida del insumo'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Eficiencia de insumos por talla. Define la cantidad de material requerido para cada talla de producto.';
CREATE TABLE `products_tiempos_de_produccion` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `tiempo` int(11) NOT NULL DEFAULT 1 COMMENT 'Tiempo de producción en segundos',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Tiempos estándar de producción. Define minutos estimados por producto y departamento para proyección de entregas.';

INSERT INTO `products_tiempos_de_produccion` (`_id`, `id_product`, `id_departamento`, `tiempo`, `moment`) VALUES
(1, 1, 1, 1800, CURRENT_TIMESTAMP),
(2, 1, 2, 2400, CURRENT_TIMESTAMP),
(3, 1, 3, 1200, CURRENT_TIMESTAMP),
(4, 1, 4, 1800, CURRENT_TIMESTAMP);
CREATE TABLE `product_insumos_asignados` (
  `_id` int(11) NOT NULL,
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del prodducto',
  `id_catalogo_insumos_productos` int(11) DEFAULT NULL COMMENT 'ID delc atalogo de insumos de productos',
  `id_departamento` int(11) NOT NULL COMMENT 'ID del departamento',
  `id_talla` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `cantidad` decimal(6, 2) NOT NULL DEFAULT 0.00 COMMENT 'cantidad del insumo',
  `unidad` varchar(64) DEFAULT NULL COMMENT 'Unidad de medida del insumo',
  `tiempo` int(11) NOT NULL DEFAULT 0 COMMENT 'Tiempo estimado de fabricación en segundos',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Insumos requeridos por producto. Define qué materiales y cantidades necesita cada producto por departamento y talla.';

INSERT INTO `product_insumos_asignados` (`_id`, `id_product`, `id_catalogo_insumos_productos`, `id_departamento`, `id_talla`, `cantidad`, `unidad`) VALUES
(1, 1, 1, 1, 1, 1.00, 'Mt'),
(2, 1, 1, 1, 2, 1.00, 'Mt'),
(3, 1, 1, 1, 3, 1.00, 'Mt'),
(4, 1, 1, 1, 4, 1.00, 'Mt'),
(5, 1, 6, 2, 1, 1.00, 'Kg'),
(6, 1, 6, 2, 2, 1.00, 'Kg'),
(7, 1, 6, 2, 3, 1.00, 'Kg'),
(8, 1, 6, 2, 4, 1.00, 'Kg'),
(9, 1, 6, 3, 1, 1.00, 'Kg'),
(10, 1, 6, 3, 2, 1.00, 'Kg'),
(11, 1, 6, 3, 3, 1.00, 'Kg'),
(12, 1, 6, 3, 4, 1.00, 'Kg'),
(13, 1, 3, 4, 1, 6.00, 'Und'),
(14, 1, 3, 4, 2, 6.00, 'Und'),
(15, 1, 3, 4, 3, 6.00, 'Und'),
(16, 1, 3, 4, 4, 6.00, 'Und');
CREATE TABLE `rendimiento` (
  `_id` int(11) NOT NULL,
  `id_empleado_impresion` int(11) DEFAULT NULL,
  `id_empleado_estampado` int(11) DEFAULT NULL,
  `id_empleado_corte` int(11) DEFAULT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_insumo` int(11) DEFAULT NULL COMMENT 'Numero de rollo',
  `metros` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Metros de material utilizado',
  `desperdicio` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'peso en gramos del material sobrante',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Datos para el calculo de el rendimiento del material';
CREATE TABLE `reposiciones` (
  `_id` int(11) NOT NULL COMMENT 'ID unico de la tabla',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento del empleado al que se envía la reposición',
  `id_departamento_solicitante` int(11) DEFAULT NULL COMMENT 'ID del departamento que emitió la reposición',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado asignado',
  `id_empleado_emisor` int(11) DEFAULT NULL COMMENT 'ID del empleado que genera la reposición',
  `id_ordenes_productos` int(11) DEFAULT NULL COMMENT 'ID de ordenes_productos',
  `unidades` int(11) DEFAULT NULL COMMENT 'Número de unidades involucradas en la reposición',
  `detalle` text DEFAULT NULL COMMENT 'Detalles del jefe de producción',
  `detalle_emisor` text DEFAULT NULL COMMENT 'Detalle de el empleado emisor',
  `aprobada` tinyint(1) DEFAULT 0 COMMENT 'Determina si la reposición has sido aprobada es true, si no es false, si en null aún no se ha indicado el estado de la reposicion',
  `terminada` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el empleado al que se le asignó la reposicion ya la terminó',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'moment'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Control de reposiciones durante el proceso de fabricacion';
CREATE TABLE `retiros` (
  `_id` int(11) NOT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `monto` decimal(10, 0) DEFAULT NULL,
  `moneda` varchar(12) DEFAULT NULL COMMENT 'nombre de la moneda que será objeto del retiro',
  `tasa` decimal(10, 0) NOT NULL DEFAULT 0 COMMENT 'TASA DE CONVERSION',
  `metodo_pago` varchar(20) DEFAULT NULL COMMENT 'Metodo de pago ejm pago movil, efectivo etc.',
  `detalle_retiro` text DEFAULT NULL,
  `cierre_caja` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'El registro corresponde a un cierre de caja',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci;
CREATE TABLE `revisiones` (
  `_id` int(11) NOT NULL COMMENT 'id de la tabla',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden a la cual pertenece el diseño y estarevision',
  `id_diseno` int(11) DEFAULT NULL COMMENT 'id en la tabla disenos',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del diseñador que envió la revisión',
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto asociado a la revisión',
  `tipo` varchar(128) DEFAULT NULL COMMENT 'Tipo de diseño asociado a la revisión',
  `revision` int(11) NOT NULL DEFAULT 0 COMMENT 'Numero de revisiones máximo dos',
  `estatus` varchar(19) NOT NULL DEFAULT 'Esperando Respuesta' COMMENT 'Los estados son ''Esperando Respuesta'', ''Rechazado'', ''Aprobado''',
  `url_image` varchar(255) DEFAULT NULL COMMENT 'URL de la imagen de la revisión',
  `detalles` text DEFAULT NULL COMMENT 'Detalles de la revision',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Control de revisiones de diseño. Registra cada iteración de revisión solicitada al diseñador con límite máximo de dos.';
CREATE TABLE `sizes` (
  `_id` int(11) NOT NULL COMMENT 'ID único de la talla',
  `nombre` varchar(100) DEFAULT NULL COMMENT 'Nombre de la talla'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de tallas disponibles. Define las tallas manejadas por la empresa para asignación en productos y órdenes.';
INSERT INTO `sizes` (`_id`, `nombre`) VALUES
(1, 'S'),
(2, 'M'),
(3, 'L'),
(4, 'XL');
CREATE TABLE `tintas` (
  `_id` int(11) NOT NULL,
  `c` decimal(7, 2) DEFAULT NULL COMMENT 'Cyan',
  `m` decimal(7, 2) DEFAULT NULL COMMENT 'Magenta',
  `y` decimal(7, 2) DEFAULT NULL COMMENT 'Yellow',
  `k` decimal(7, 2) DEFAULT NULL COMMENT 'Black',
  `w` decimal(7, 2) DEFAULT NULL COMMENT 'White',
  `id_catalogo_impresoras` int(11) DEFAULT NULL COMMENT 'ID del catálogo de impresoras',
  `id_orden` int(11) DEFAULT NULL COMMENT 'Id de la Orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado que imprimió',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registra el consumo de tintas por orden';
CREATE TABLE `tintas_recargas` (
  `_id` int(11) NOT NULL,
  `id_insumo` int(11) DEFAULT NULL,
  `id_catalogo_impresora` int(11) DEFAULT NULL COMMENT 'ID catalodo de imoresoras',
  `color` varchar(1) DEFAULT NULL COMMENT 'Color de la tinta',
  `cantidad` decimal(7, 2) DEFAULT NULL COMMENT 'Cantidad en ML',
  `fecha_recarga` timestamp NULL DEFAULT NULL COMMENT 'Fecha de la recarga',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Recargas de tinta';
CREATE TABLE `tinta_filtro` (
  `_id` int(11) NOT NULL,
  `id_inventario` int(11) DEFAULT NULL COMMENT 'Id del insumo',
  `color` varchar(1) NOT NULL COMMENT 'Color de la tinta C, M, Y, K, W',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Indica cuales insumos son tintas para filtrar las tintas';

INSERT INTO `tinta_filtro` (`_id`, `id_inventario`, `color`, `moment`) VALUES
(1, 3, 'C', CURRENT_TIMESTAMP),
(2, 4, 'M', CURRENT_TIMESTAMP),
(3, 5, 'Y', CURRENT_TIMESTAMP),
(4, 6, 'K', CURRENT_TIMESTAMP);
ALTER TABLE `abonos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_empleado`),
  ADD KEY `id_empleado` (`id_empleado`);
ALTER TABLE `aprobacion_clientes`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_diseno`);
ALTER TABLE `asistencias`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_empleado` (`id_empleado`);
ALTER TABLE `caja`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_empleado` (`id_empleado`);
ALTER TABLE `caja_cierres`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_empleado` (`id_empleado`);
ALTER TABLE `caja_fondos`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `catalogo_impresoras`
ADD PRIMARY KEY (`_id`),
  ADD UNIQUE KEY `idx_codigo_interno` (`codigo_interno`) COMMENT 'Asegura que cada código sea único.';
ALTER TABLE `catalogo_insumos_productos`
ADD PRIMARY KEY (`_id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `nombre_2` (`nombre`);
ALTER TABLE `catalogo_telas`
ADD PRIMARY KEY (`_id`),
  ADD UNIQUE KEY `_id` (`_id`);
ALTER TABLE `categories`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `check_tareas`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `config`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `customers`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `departamentos`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `disenos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_empleado` (`id_empleado`);
ALTER TABLE `disenos_ajustes_y_personalizaciones`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_diseno`);
ALTER TABLE `empleados_lotes_fabricacion`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `empleados_lotes_fabricacion_items`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `inventario`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `inventario_movimientos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (
    `id_orden`,
    `id_producto`,
    `id_empleado`,
    `id_insumo`
  );
ALTER TABLE `lotes`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`);
ALTER TABLE `lotes_detalles`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_empleado` (`id_empleado`),
  ADD KEY `id_orden` (`id_orden`, `id_ordenes_productos`);
ALTER TABLE `lotes_detalles_empleados_asignados`
ADD PRIMARY KEY (`_id`),
  ADD KEY `idx_id_empleado` (`id_empleado`),
  ADD KEY `idx_id_lotes_detalles` (`id_lotes_detalles`),
  ADD KEY `idx_id_orden` (`id_orden`),
  ADD KEY `idx_id_departamento` (`id_departamento`),
  ADD KEY `idx_empleado_orden_depto` (`id_empleado`, `id_orden`, `id_departamento`);
ALTER TABLE `lotes_detalles_empleados_asignados_pausas`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `lotes_fisicos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`);
ALTER TABLE `lotes_historico_solicitadas`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_lotes_fisicos`);
ALTER TABLE `lotes_movimientos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_lotes_detalles` (`id_lotes_detalles`, `id_orden`);
ALTER TABLE `metodos_de_pago`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`);

ALTER TABLE `ordenes_borrador_empleado`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `ordenes_fila_orden`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `ordenes_fila_orden_cambios`
ADD PRIMARY KEY (`id`);
ALTER TABLE `ordenes_fila_reposiciones`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `ordenes_observaciones`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `ordenes_productos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `rollo`),
  ADD KEY `id_catalogo_telas` (`rollo`);
ALTER TABLE `ordenes_tmp`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `ordenes_vinculadas`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_father` (`id_father`, `id_child`),
  ADD KEY `id_child` (`id_child`);
ALTER TABLE `pagos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (
    `id_orden`,
    `id_metodos_de_pago`,
    `id_lotes_detalles`,
    `id_empleado`
  ),
  ADD KEY `id_metodos_de_pago` (`id_metodos_de_pago`),
  ADD KEY `id_lotes_detalles` (`id_lotes_detalles`),
  ADD KEY `id_empleado` (`id_empleado`);
ALTER TABLE `piezas_cortadas`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (
    `id_orden`,
    `id_inventario`,
    `id_ordenes_productos`,
    `id_empleado`
  );
ALTER TABLE `presupuestos`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `presupuestos_productos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_catalogo_telas`),
  ADD KEY `id_catalogo_telas` (`id_catalogo_telas`);
ALTER TABLE `products`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_attributes`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_attributes_values`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_comisiones`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_prices`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_sizes_eficiencia`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_tiempos_de_produccion`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `product_insumos_asignados`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `rendimiento`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `reposiciones`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (
    `id_orden`,
    `id_empleado`,
    `id_ordenes_productos`
  ),
  ADD KEY `id_empleado_emisor` (`id_empleado_emisor`);
ALTER TABLE `retiros`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_empleado` (`id_empleado`);
ALTER TABLE `revisiones`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_diseno`),
  ADD KEY `id_orden_2` (`id_orden`, `id_diseno`);
ALTER TABLE `sizes`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `tintas`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `tintas_recargas`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `tinta_filtro`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `abonos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID de la talba';
ALTER TABLE `aprobacion_clientes`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `asistencias`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unico del registro';
ALTER TABLE `caja`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `caja_cierres`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `caja_fondos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `catalogo_impresoras`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `catalogo_insumos_productos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `catalogo_telas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico de la tabla',
  AUTO_INCREMENT = 2;
ALTER TABLE `categories`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 2;
ALTER TABLE `check_tareas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unico';
ALTER TABLE `config`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 2;
ALTER TABLE `customers`
MODIFY `_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 2;
ALTER TABLE `departamentos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 8;
ALTER TABLE `disenos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID de la tabla';
ALTER TABLE `disenos_ajustes_y_personalizaciones`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `empleados_lotes_fabricacion`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `empleados_lotes_fabricacion_items`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `inventario`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico';
ALTER TABLE `inventario_movimientos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico';
ALTER TABLE `lotes`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID Autonumérico';
ALTER TABLE `lotes_detalles`
MODIFY `_id` int(10) NOT NULL AUTO_INCREMENT COMMENT 'ID único del registro';
ALTER TABLE `lotes_detalles_empleados_asignados`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lotes_detalles_empleados_asignados_pausas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lotes_fisicos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lotes_historico_solicitadas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lotes_movimientos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `metodos_de_pago`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unico de la tabla';

ALTER TABLE `ordenes_borrador_empleado`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ordenes_fila_orden`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ordenes_fila_orden_cambios`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ordenes_fila_reposiciones`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ordenes_observaciones`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ordenes_productos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID del registro';
ALTER TABLE `ordenes_tmp`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Clave primaria';
ALTER TABLE `ordenes_vinculadas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id de la tabla';
ALTER TABLE `pagos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unico';
ALTER TABLE `piezas_cortadas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unico';
ALTER TABLE `presupuestos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `presupuestos_productos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID del registro';
ALTER TABLE `products`
MODIFY `_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 2;
ALTER TABLE `products_attributes`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 2;
ALTER TABLE `products_attributes_values`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `products_comisiones`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID único',
  AUTO_INCREMENT = 2;
ALTER TABLE `products_prices`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `products_sizes_eficiencia`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `products_tiempos_de_produccion`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `product_insumos_asignados`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `rendimiento`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `reposiciones`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unico de la tabla';
ALTER TABLE `retiros`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `revisiones`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id de la tabla';
ALTER TABLE `sizes`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 2;
ALTER TABLE `tintas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tintas_recargas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tinta_filtro`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
-- =====================================================
-- FOREIGN KEYS - 95 FKs
-- =====================================================

-- abonos
ALTER TABLE `abonos`
ADD CONSTRAINT `abonos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- aprobacion_clientes
ALTER TABLE `aprobacion_clientes`
ADD CONSTRAINT `aprob_cli_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `aprob_cli_ibfk_2` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- caja
ALTER TABLE `caja`
ADD CONSTRAINT `caja_ibfk_1` FOREIGN KEY (`id_caja_cierres`) REFERENCES `caja_cierres` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- caja_fondos
ALTER TABLE `caja_fondos`
ADD CONSTRAINT `caja_fondos_ibfk_1` FOREIGN KEY (`id_caja_cierres`) REFERENCES `caja_cierres` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- check_tareas
ALTER TABLE `check_tareas`
ADD CONSTRAINT `check_tar_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `check_tar_ibfk_2` FOREIGN KEY (`id_lotes_detalles_empleados_asigandos`) REFERENCES `lotes_detalles_empleados_asignados` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `check_tar_ibfk_3` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- disenos
ALTER TABLE `disenos`
ADD CONSTRAINT `disenos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- disenos_ajustes_y_personalizaciones
ALTER TABLE `disenos_ajustes_y_personalizaciones`
ADD CONSTRAINT `dis_ajust_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `dis_ajust_ibfk_2` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- empleados_lotes_fabricacion
ALTER TABLE `empleados_lotes_fabricacion`
ADD CONSTRAINT `emp_lotes_fab_ibfk_1` FOREIGN KEY (`id_departamento_creador`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `emp_lotes_fab_ibfk_2` FOREIGN KEY (`id_departamento_actual`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- empleados_lotes_fabricacion_items
ALTER TABLE `empleados_lotes_fabricacion_items`
ADD CONSTRAINT `emp_lotes_items_ibfk_1` FOREIGN KEY (`id_lote`) REFERENCES `empleados_lotes_fabricacion` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `emp_lotes_items_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- inventario_movimientos
ALTER TABLE `inventario_movimientos`
ADD CONSTRAINT `inv_mov_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `inv_mov_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `inventario` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `inv_mov_ibfk_3` FOREIGN KEY (`id_catalogo_insumos_prodcutos`) REFERENCES `catalogo_insumos_productos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `inv_mov_ibfk_4` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- lotes
ALTER TABLE `lotes`
ADD CONSTRAINT `lotes_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_ibfk_2` FOREIGN KEY (`id_departamento_actual`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- lotes_detalles
ALTER TABLE `lotes_detalles`
ADD CONSTRAINT `lotes_det_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_det_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_det_ibfk_3` FOREIGN KEY (`id_ordenes_productos`) REFERENCES `ordenes_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_det_ibfk_4` FOREIGN KEY (`id_reposicion`) REFERENCES `reposiciones` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- lotes_detalles_empleados_asignados
ALTER TABLE `lotes_detalles_empleados_asignados`
ADD CONSTRAINT `ldea_ibfk_1` FOREIGN KEY (`id_lotes_detalles`) REFERENCES `lotes_detalles` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ldea_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ldea_ibfk_3` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- lotes_detalles_empleados_asignados_pausas
ALTER TABLE `lotes_detalles_empleados_asignados_pausas`
ADD CONSTRAINT `ldea_pausas_ibfk_1` FOREIGN KEY (`id_lotes_detalles_empleados_asignados`) REFERENCES `lotes_detalles_empleados_asignados` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- lotes_fisicos
ALTER TABLE `lotes_fisicos`
ADD CONSTRAINT `lotes_fis_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- lotes_historico_solicitadas
ALTER TABLE `lotes_historico_solicitadas`
ADD CONSTRAINT `lotes_hist_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_hist_ibfk_2` FOREIGN KEY (`id_lotes_fisicos`) REFERENCES `lotes_fisicos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- lotes_movimientos
ALTER TABLE `lotes_movimientos`
ADD CONSTRAINT `lotes_mov_ibfk_1` FOREIGN KEY (`id_lotes_detalles`) REFERENCES `lotes_detalles` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_mov_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- metodos_de_pago
ALTER TABLE `metodos_de_pago`
ADD CONSTRAINT `met_pago_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `met_pago_ibfk_2` FOREIGN KEY (`id_caja_cierres`) REFERENCES `caja_cierres` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ordenes_borrador_empleado
ALTER TABLE `ordenes_borrador_empleado`
ADD CONSTRAINT `ord_borr_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ord_borr_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ordenes_fila_orden
ALTER TABLE `ordenes_fila_orden`
ADD CONSTRAINT `ord_fila_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ordenes_fila_reposiciones
ALTER TABLE `ordenes_fila_reposiciones`
ADD CONSTRAINT `ord_fila_rep_ibfk_1` FOREIGN KEY (`id_reposicion`) REFERENCES `reposiciones` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ordenes_observaciones
ALTER TABLE `ordenes_observaciones`
ADD CONSTRAINT `ord_obs_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ordenes_productos
ALTER TABLE `ordenes_productos`
ADD CONSTRAINT `ord_prod_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ord_prod_ibfk_2` FOREIGN KEY (`id_tela`) REFERENCES `catalogo_telas` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `ord_prod_ibfk_3` FOREIGN KEY (`id_size`) REFERENCES `sizes` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ordenes_vinculadas
ALTER TABLE `ordenes_vinculadas`
ADD CONSTRAINT `ord_vinc_ibfk_1` FOREIGN KEY (`id_father`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ord_vinc_ibfk_2` FOREIGN KEY (`id_child`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- pagos
ALTER TABLE `pagos`
ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`id_reposicion`) REFERENCES `reposiciones` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `pagos_ibfk_3` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `pagos_ibfk_4` FOREIGN KEY (`id_metodos_de_pago`) REFERENCES `metodos_de_pago` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `pagos_ibfk_5` FOREIGN KEY (`id_lotes_detalles`) REFERENCES `lotes_detalles` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- pagos_abonos
ALTER TABLE `pagos_abonos`
ADD CONSTRAINT `pagos_ab_ibfk_1` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- pagos_descuentos
ALTER TABLE `pagos_descuentos`
ADD CONSTRAINT `pagos_desc_ibfk_1` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- pagos_salarios
ALTER TABLE `pagos_salarios`
ADD CONSTRAINT `pagos_sal_ibfk_1` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- piezas_cortadas
ALTER TABLE `piezas_cortadas`
ADD CONSTRAINT `piez_cort_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `piez_cort_ibfk_2` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `piez_cort_ibfk_3` FOREIGN KEY (`id_ordenes_productos`) REFERENCES `ordenes_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- presupuestos
ALTER TABLE `presupuestos`
ADD CONSTRAINT `presup_ibfk_1` FOREIGN KEY (`id_wp`) REFERENCES `customers` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- presupuestos_productos
ALTER TABLE `presupuestos_productos`
ADD CONSTRAINT `presup_prod_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `presupuestos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `presup_prod_ibfk_2` FOREIGN KEY (`id_catalogo_telas`) REFERENCES `catalogo_telas` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- products_attributes_values
ALTER TABLE `products_attributes_values`
ADD CONSTRAINT `prod_attr_val_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_attr_val_ibfk_2` FOREIGN KEY (`id_product_attribute`) REFERENCES `products_attributes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- products_comisiones
ALTER TABLE `products_comisiones`
ADD CONSTRAINT `prod_com_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- products_sizes_eficiencia
ALTER TABLE `products_sizes_eficiencia`
ADD CONSTRAINT `prod_size_ef_ibfk_1` FOREIGN KEY (`id_size`) REFERENCES `sizes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_size_ef_ibfk_2` FOREIGN KEY (`id_catalogo_insumos_prodcutos`) REFERENCES `catalogo_insumos_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- products_tiempos_de_produccion
ALTER TABLE `products_tiempos_de_produccion`
ADD CONSTRAINT `prod_tiempo_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- product_insumos_asignados
ALTER TABLE `product_insumos_asignados`
ADD CONSTRAINT `prod_ins_asig_ibfk_1` FOREIGN KEY (`id_catalogo_insumos_productos`) REFERENCES `catalogo_insumos_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_ins_asig_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_ins_asig_ibfk_3` FOREIGN KEY (`id_talla`) REFERENCES `sizes` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- rendimiento
ALTER TABLE `rendimiento`
ADD CONSTRAINT `rendim_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `rendim_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `inventario` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- reposiciones
ALTER TABLE `reposiciones`
ADD CONSTRAINT `repos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `repos_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `repos_ibfk_3` FOREIGN KEY (`id_departamento_solicitante`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `repos_ibfk_4` FOREIGN KEY (`id_ordenes_productos`) REFERENCES `ordenes_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- revisiones
ALTER TABLE `revisiones`
ADD CONSTRAINT `revisiones_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `revisiones_ibfk_2` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- tintas
ALTER TABLE `tintas`
ADD CONSTRAINT `tintas_ibfk_1` FOREIGN KEY (`id_catalogo_impresoras`) REFERENCES `catalogo_impresoras` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `tintas_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- tintas_recargas
ALTER TABLE `tintas_recargas`
ADD CONSTRAINT `tintas_rec_ibfk_1` FOREIGN KEY (`id_insumo`) REFERENCES `inventario` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `tintas_rec_ibfk_2` FOREIGN KEY (`id_catalogo_impresora`) REFERENCES `catalogo_impresoras` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- tinta_filtro
ALTER TABLE `tinta_filtro`
ADD CONSTRAINT `tinta_filtro_ibfk_1` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;
