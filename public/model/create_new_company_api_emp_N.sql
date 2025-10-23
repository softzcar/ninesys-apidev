SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */; 
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


--
-- Estructura de tabla para `abonos`
--

CREATE TABLE `abonos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID de la talba', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `abono` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'monto del abono',
  `descuento` decimal(10,2) DEFAULT 0.00 COMMENT 'Descuento del abono',
  `detalle` varchar(60) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'fecha del abono'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `abonos`
--
TRUNCATE TABLE `abonos`;

--
-- Estructura de tabla para `aprobacion_clientes`
--

CREATE TABLE `aprobacion_clientes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL,
  `id_diseno` int(11) DEFAULT NULL,
  `check` tinyint(1) NOT NULL DEFAULT 1,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Registro para notificación de aprobación de diseño';

--
-- Vaciar tabla `aprobacion_clientes`
--
TRUNCATE TABLE `aprobacion_clientes`;

--
-- Estructura de tabla para `asistencias`
--

CREATE TABLE `asistencias` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unico del registro', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `registro` varchar(14) DEFAULT NULL COMMENT 'Entrada Mañana, Salida Mañana, Entrada Tarde, Salida Tarde',
  `detalle` mediumtext DEFAULT NULL COMMENT 'Detalle de el registro si se requiere',
  `moment` datetime DEFAULT current_timestamp() COMMENT 'Momento de la acción'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `asistencias`
--
TRUNCATE TABLE `asistencias`;

--
-- Estructura de tabla para `caja`
--

CREATE TABLE `caja` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_caja_cierres` int(11) DEFAULT NULL COMMENT 'ID del cierre de la caja',
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'monto de la moneda',
  `moneda` varchar(10) NOT NULL DEFAULT '0' COMMENT 'dolares, pesos, bolivares',
  `tasa` decimal(12,2) NOT NULL DEFAULT 1.00 COMMENT 'tasa de conversion para el dia', -- Corregido: decimal(12,2)
  `detalle` text DEFAULT NULL,
  `tipo` varchar(20) DEFAULT NULL COMMENT 'orden_nueva, orden_abono, otro_abono, retiro, cierre_de_caja, ajuste',
  `id_empleado` int(11) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Registros de los movimientos del efectivo en la caja antes del cierre, luego se reinicia, el histroico de ingresos queda en la tabla metodos_de_pago';

--
-- Vaciar tabla `caja`
--
TRUNCATE TABLE `caja`;

--
-- Estructura de tabla para `caja_cierres`
--

CREATE TABLE `caja_cierres` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `dolares` decimal(10,2) NOT NULL DEFAULT 0.00, -- Corregido: decimal(10,2)
  `pesos` decimal(10,2) NOT NULL DEFAULT 0.00,   -- Corregido: decimal(10,2)
  `bolivares` decimal(10,2) NOT NULL DEFAULT 0.00, -- Corregido: decimal(10,2)
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_empleado` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Registro de cierres de caja';

--
-- Vaciar tabla `caja_cierres`
--
TRUNCATE TABLE `caja_cierres`;

--
-- Estructura de tabla para `caja_fondos`
--

CREATE TABLE `caja_fondos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_caja_cierres` int(11) DEFAULT NULL COMMENT 'ID del cierre de la caja',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del Vendedor',
  `dolares` decimal(12,2) NOT NULL DEFAULT 0.00, -- Corregido: decimal(12,2)
  `pesos` decimal(12,2) NOT NULL DEFAULT 0.00,   -- Corregido: decimal(12,2)
  `bolivares` decimal(12,2) NOT NULL DEFAULT 0.00, -- Corregido: decimal(12,2)
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Fondo en efectivo que queda en caja';

--
-- Vaciar tabla `caja_fondos`
--
TRUNCATE TABLE `caja_fondos`;

--
-- Estructura de tabla para `catalogo_impresoras`
--

CREATE TABLE `catalogo_impresoras` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `codigo_interno` varchar(50) NOT NULL UNIQUE COMMENT 'Identificador único y fácil de leer para el empleado. Ej: SUBLIMACION-01, EPSON-F570-A', -- Corregido: UNIQUE para el índice
  `marca` varchar(50) DEFAULT NULL COMMENT 'Marca del fabricante. Ej: Epson, Roland',
  `modelo` varchar(100) DEFAULT NULL COMMENT 'Nombre comercial del modelo. Ej: SureColor F570',
  `capacidad_contenedor` decimal(7,2) DEFAULT NULL COMMENT 'Capacidad del contenedor de la tinta',
  `ubicacion` varchar(100) DEFAULT NULL COMMENT 'Ubicación física para ayudar al empleado a identificarla. Ej: Taller de Estampado',
  `tipo_tecnologia` varchar(50) DEFAULT NULL COMMENT 'Tecnología para agrupar o filtrar. Ej: Sublimación, DTG, DTF',
  `estado` varchar(20) NOT NULL DEFAULT 'activa' COMMENT 'Estado actual. Ej: activa, inactiva, en_mantenimiento',
  `notas` text DEFAULT NULL COMMENT 'Cualquier información adicional relevante.',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Catálogo de las impresoras físicas de la empresa.';

--
-- Vaciar tabla `catalogo_impresoras`
--
TRUNCATE TABLE `catalogo_impresoras`;

--
-- Volcado de datos para la tabla `catalogo_impresoras`
--
INSERT INTO catalogo_impresoras (codigo_interno, marca, modelo, capacidad_contenedor, ubicacion, tipo_tecnologia, estado, notas) VALUES
('Impresora de CMYK','EPSON','PRINTER_0001',0.00,'Fábrica','CMYK','activa','Notas de la impresora EPSON CMYK'),
('Impresora de CMYKW','Mimaki','PRINTER_0002',0.00,'Fábrica','CMYKW','activa','Notas de la impresora Mimaki CMYKW');

--
-- Estructura de tabla para `catalogo_insumos_productos`
--

CREATE TABLE `catalogo_insumos_productos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `nombre` varchar(128) NOT NULL UNIQUE, -- Corregido: UNIQUE
  `id_product` int(11) NOT NULL COMMENT 'ID del producto',
  `id_departamento` int(11) NOT NULL COMMENT 'ID del departamento',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `catalogo_insumos_productos`
--
TRUNCATE TABLE `catalogo_insumos_productos`;

--
-- Volcado de datos para la tabla `catalogo_insumos_productos`
--
INSERT INTO `catalogo_insumos_productos`(nombre, id_product, id_departamento) VALUES
('Papel',1,1),
('Tinta',1,3),
('Tela',2,3);

--
-- Estructura de tabla para `catalogo_telas`
--

CREATE TABLE `catalogo_telas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador unico de la tabla', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `tela` varchar(45) DEFAULT NULL COMMENT 'Nombre de la tela',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `catalogo_telas`
--
TRUNCATE TABLE `catalogo_telas`;

--
-- Volcado de datos para la tabla `catalogo_telas`
--
INSERT INTO `catalogo_telas` (`_id`, `tela`) VALUES
(1, 'Algodón'),
(2, 'Poliester'),
(3, 'Lycra');

--
-- Estructura de tabla para `categories`
--

CREATE TABLE `categories` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `nombre` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `categories`
--
TRUNCATE TABLE `categories`;

--
-- Volcado de datos para la tabla `categories`
--
INSERT INTO `categories` (`_id`, `nombre`) VALUES
(1, 'Categoría de Pruebas');

--
-- Estructura de tabla para `check_tareas`
--

CREATE TABLE `check_tareas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unico', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL,
  `id_lotes_detalles_empleados_asigandos` int(11) DEFAULT NULL,
  `id_ordenes_productos` int(11) DEFAULT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `id_departamento` int(11) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fin de tarea'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Control de el check de tareas para empleados';

--
-- Vaciar tabla `check_tareas`
--
TRUNCATE TABLE `check_tareas`;

--
-- Estructura de tabla para `config`
--

CREATE TABLE `config` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `app_key` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indica si el cliente tiene acceso a el sitema o está suspendido',
  `nombre_empresa` varchar(45) DEFAULT NULL,
  `identificador_fiscal` varchar(60) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL COMMENT 'Dirección de la empresa',
  `telefonos` varchar(60) DEFAULT NULL COMMENT 'Teléfonos de la empresa', -- Corregido: varchar
  `email` varchar(255) DEFAULT NULL COMMENT 'Email de la empresa', -- Corregido: varchar
  `msg_welcome` text DEFAULT NULL COMMENT 'Mensaje de bienvenida a el cliente',
  `msg_bye` text DEFAULT NULL COMMENT 'Mensajde de despedida al cliente',
  `msg_saldo` text DEFAULT NULL COMMENT 'Mensaje de saldo pendiente del cliente',
  `sys_mostrar_detalle_terminar_indicidual` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si se muestra el formaulario de ingresar detalle de la terminación del item indicidual en el módulo de empleados al momento de terminar una tarea individual',
  `sys_mostrar_rollo_en_empleado_corte` tinyint(1) DEFAULT 0 COMMENT 'Muestra la opción sedeleccionar rollo en el módulo de empleados depatament de Corte',
  `sys_mostrar_rollo_en_empleado_estampado` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Mostrar el rollo de tela al emplado de Estampado',
  `sys_mostrar_insumo_en_empleado_costura` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Mostrar select de insumos en modulo de empleados',
  `sys_mostrar_insumo_en_empleado_limpieza` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'empleados',
  `sys_mostrar_insumo_en_empleado_revision` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'empleados',
  `sys_comision_de_costura` varchar(8) NOT NULL DEFAULT 'producto' COMMENT 'Define si a costura se le calclua comision por el porcentaje en la tabla empleados o el porcentaje ne la tabla productos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `config`
--
TRUNCATE TABLE `config`;

--
-- Volcado de datos para la tabla `config`
--
INSERT INTO `config` (`_id`, `app_key`, `activo`, `nombre_empresa`, `identificador_fiscal`, `direccion`, `telefonos`, `email`, `msg_welcome`, `msg_bye`, `msg_saldo`, `sys_mostrar_detalle_terminar_indicidual`, `sys_mostrar_rollo_en_empleado_corte`, `sys_mostrar_rollo_en_empleado_estampado`, `sys_mostrar_insumo_en_empleado_costura`, `sys_mostrar_insumo_en_empleado_limpieza`, `sys_mostrar_insumo_en_empleado_revision`, `sys_comision_de_costura`) VALUES
(1, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 'producto');

--
-- Estructura de tabla para `customers`
--

CREATE TABLE `customers` (
  `_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `first_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) DEFAULT NULL,
  `username` varchar(60) DEFAULT NULL,
  `cedula` varchar(12) DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  `billing_city` varchar(60) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `customers`
--
TRUNCATE TABLE `customers`;

--
-- Volcado de datos para la tabla `customers`
--
INSERT INTO `customers` (`_id`, `first_name`, `last_name`, `username`, `cedula`, `address`, `billing_city`, `phone`, `email`) VALUES
(1, 'Cliente', 'de Pruebas', 'Cliente Prueba', 'V12345678', 'Dirección de prueba', 'Caracas', '58424000000', 'clientepruebas@email.com');

--
-- Estructura de tabla para `departamentos`
--

CREATE TABLE `departamentos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_modulo` int(11) DEFAULT NULL COMMENT 'ID del módulo asignado al departamento',
  `orden_proceso` int(11) NOT NULL DEFAULT 0 COMMENT 'indica el orden del proceso de fabricación',
  `departamento` varchar(256) DEFAULT NULL COMMENT 'Nombre del departamento',
  `asignar_numero_de_paso` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Interviene en proceso Es un paso de proceso de fabricación',
  `enviar_mensaje` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enviar mensaje al cliente al iniciar el paso',
  `mensaje` text DEFAULT NULL COMMENT 'Mensaje para el cliente máximo 255 caracters',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `departamentos`
--
TRUNCATE TABLE `departamentos`;

--
-- Volcado de datos para la tabla `departamentos`
--
INSERT INTO `departamentos` (`_id`, `id_modulo`, `orden_proceso`, `departamento`, `asignar_numero_de_paso`, `enviar_mensaje`, `mensaje`) VALUES
(1, 4, 1, 'Impresión', 1, 1, NULL),
(2, 4, 2, 'Estampado', 1, 1, NULL),
(3, 4, 3, 'Corte', 1, 1, NULL),
(4, 4, 4, 'Costura', 1, 1, NULL),
(5, 1, 0, 'Administración', 0, 0, NULL),
(6, 2, 0, 'Comecialización', 0, 0, NULL),
(7, 3, 0, 'Diseño', 0, 0, NULL);

--
-- Estructura de tabla para `disenos`
--

CREATE TABLE `disenos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID de la tabla', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'IDn de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del diseÑador tabla empleados',
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto asociado al diseño',
  `origen` varchar(25) NOT NULL DEFAULT 'orden_inicial' COMMENT 'Identifica el Origen del registro, puede ser ''origen_inicial'' si se crea al momento de la facturación o ''agregado_posterior'' si proviene de la creación de una revisión',
  `codigo_diseno` varchar(6) DEFAULT NULL COMMENT 'Codigo de diseño de uso interno de 6 digitos formato XX-XXX',
  `tipo` varchar(128) DEFAULT NULL COMMENT 'Tipo de diseÑo modas ó gráfico',
  `terminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el diseño ya ha sido terminado',
  `linkdrive` text DEFAULT NULL COMMENT 'Link a google drive',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `disenos`
--
TRUNCATE TABLE `disenos`;

--
-- Estructura de tabla para `disenos_ajustes_y_personalizaciones`
--

CREATE TABLE `disenos_ajustes_y_personalizaciones` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_diseno` int(11) DEFAULT NULL COMMENT 'ID de la tabla disenos',
  `tipo` varchar(15) DEFAULT NULL COMMENT 'Si es ajuste o personalizacion',
  `cantidad` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad de piezas trabajadas',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Guarda Datos ajustes y las personalizaciones';

--
-- Vaciar tabla `disenos_ajustes_y_personalizaciones`
--
TRUNCATE TABLE `disenos_ajustes_y_personalizaciones`;

--
-- Estructura de tabla para `empleados_lotes_fabricacion`
--

CREATE TABLE `empleados_lotes_fabricacion` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID emleado que ejecuta la tarea',
  `id_departamento_creador` int(11) DEFAULT NULL,
  `id_departamento_actual` int(11) DEFAULT NULL,
  `estado` varchar(11) DEFAULT 'pendiente' COMMENT 'pendiente, en_curso, terminado',
  `fecha_inicio` timestamp NULL DEFAULT NULL COMMENT 'Fecha de inicio del pprocesamiento en lotes',
  `fecha_fin` timestamp NULL DEFAULT NULL COMMENT 'FEcha de finlización del porcesamienteo en lotes',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'FEcha de creación del registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='ordenes que se procesan el lotes en el modulo de Empleados';

--
-- Vaciar tabla `empleados_lotes_fabricacion`
--
TRUNCATE TABLE `empleados_lotes_fabricacion`;

--
-- Estructura de tabla para `empleados_lotes_fabricacion_items`
--

CREATE TABLE `empleados_lotes_fabricacion_items` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_lote` int(11) DEFAULT NULL,
  `id_orden` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Informacion general de los lotes de fabricación';

--
-- Vaciar tabla `empleados_lotes_fabricacion_items`
--
TRUNCATE TABLE `empleados_lotes_fabricacion_items`;

--
-- Estructura de tabla para `inventario`
--

CREATE TABLE `inventario` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador unico', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `sku` varchar(128) DEFAULT NULL COMMENT 'SKU del Item de inventario',
  `id_catalogo` int(11) DEFAULT NULL COMMENT 'ID de catalogo_insumos_productos',
  `insumo` varchar(45) DEFAULT NULL COMMENT 'Nombre del insumo',
  `unidad` varchar(6) DEFAULT NULL COMMENT 'Unidd de medida del articulo CD, LTS, ML UND',
  `costo` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio de costo del insumo',
  `rendimiento` decimal(3,1) DEFAULT NULL,
  `cantidad` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de la unidad e medida',
  `color` varchar(64) DEFAULT NULL COMMENT 'Color del insumo',
  `ancho` decimal(7,2) DEFAULT 0.00 COMMENT 'ancho del insumo',
  `elongacion` varchar(32) DEFAULT NULL COMMENT 'Elongación del material',
  `detalles` text DEFAULT NULL COMMENT 'Detalles del insumo',
  `departamento` varchar(14) DEFAULT NULL COMMENT 'Departamento al que pertence el insumo',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `inventario`
--
TRUNCATE TABLE `inventario`;

--
-- Volcado de datos para la tabla `inventario`
--
INSERT INTO `inventario` (`_id`, `sku`, `id_catalogo`, `insumo`, `unidad`, `costo`, `rendimiento`, `cantidad`, `color`, `ancho`, `elongacion`, `detalles`, `departamento`) VALUES
(1, 'PAP_001', 1, 'Papel de pruebas', 'Mts', 20.00, 8.0, 6.35, 'BLANCO', 0.90, NULL, NULL, 'Impresión'),
(2, 'TEL_001', 2, 'Tela de pruebas', 'Kg', 80.00, 3.0, 11.41, 'BLANCO', 1.50, 'HORIZONTAL', NULL, 'Telas'),
(3, 'INK_001', 2, 'Tinta de Pruebas', 'Und', 50.00, 1.0, 750.00, NULL, 0.00, NULL, NULL, 'Impresión'),
(4, 'INK_002', 2, 'Tinta de Pruebas', 'Und', 50.00, 1.0, 750.00, NULL, 0.00, NULL, NULL, 'Impresión'),
(5, 'INK_003', 2, 'Tinta de Pruebas', 'Und', 50.00, 1.0, 750.00, NULL, 0.00, NULL, NULL, 'Impresión');

--
-- Estructura de tabla para `inventario_movimientos`
--

CREATE TABLE `inventario_movimientos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador unico', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la  orden - lote',
  `id_producto` int(11) DEFAULT NULL COMMENT 'ID del catálogo de productos',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `id_insumo` int(11) DEFAULT NULL COMMENT 'Id del insumoa signado',
  `id_catalogo_insumos_prodcutos` int(11) DEFAULT NULL COMMENT 'ID del catálogo seleccionado por el empleado al momento de usar el insumo',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'Id del departamento del empleado',
  `departamento` varchar(20) DEFAULT NULL COMMENT 'Nombre del departamento',
  `valor_inicial` decimal(7,2) DEFAULT NULL COMMENT 'Valor inicial del insumo',
  `valor_final` decimal(7,2) DEFAULT NULL COMMENT 'Valor Final del insumo ',
  `fecha` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'fecha del registro',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `inventario_movimientos`
--
TRUNCATE TABLE `inventario_movimientos`;

--
-- Estructura de tabla para `lotes`
--

CREATE TABLE `lotes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID Autonumérico', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `lote` mediumtext DEFAULT NULL COMMENT 'Código del Lote',
  `fecha` date DEFAULT NULL COMMENT 'Fecha de creación del lote',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_departamento_actual` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `prioridad` int(1) NOT NULL DEFAULT 0 COMMENT '0 NORMAL, 1 URGENTE',
  `piezas_actuales` int(11) DEFAULT NULL COMMENT 'Cantidad de piezasa ctuales',
  `paso` varchar(128) DEFAULT 'responsable' COMMENT 'Paso actual del proceso, Corte, estampado, impresion, etc.',
  `detalles` mediumtext DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Controla el proceso de fabricacion';

--
-- Vaciar tabla `lotes`
--
TRUNCATE TABLE `lotes`;

--
-- Estructura de tabla para `lotes_detalles`
--

CREATE TABLE `lotes_detalles` (
  `_id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único del registro', -- Corregido: AUTO_INCREMENT PRIMARY KEY
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
  `comision` decimal(8,2) DEFAULT 0.00 COMMENT 'Porcentaje para el cálculo de la comisión',
  `detalles` varchar(255) DEFAULT NULL COMMENT 'Información adicional del producto',
  `fecha_inicio` timestamp NULL DEFAULT NULL COMMENT 'Momento en que el primer empleado asignado ha iniciado el trabajo	',
  `fecha_terminado` timestamp NULL DEFAULT NULL COMMENT 'Momento en que el último empleado afirma haber terminado el trabajo',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Controla el proceso de fabciacion x producto y empleado';

--
-- Vaciar tabla `lotes_detalles`
--
TRUNCATE TABLE `lotes_detalles`;

--
-- Estructura de tabla para `lotes_detalles_empleados_asignados`
--

CREATE TABLE `lotes_detalles_empleados_asignados` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_lotes_detalles` int(11) DEFAULT NULL COMMENT 'ID de lotes_detalles',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID empleado',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `progreso` varchar(11) DEFAULT 'por iniciar' COMMENT 'Nos indica el estado de desarrollo de la tarea: por iniciar, en curso, terminada por cada empleado para el control de su proceso en el modulo de empleados',
  `procentaje_comision` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje para el cálculo de la comisión',
  `terminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si la tarea se ha terminado para la lista de verificación en el módulo de empleados',
  `fecha_inicio` timestamp NULL DEFAULT NULL COMMENT 'Indica el momento en que el empleado indica que iniciado',
  `fecha_terminado` timestamp NULL DEFAULT NULL COMMENT 'Indica el momento en que el empleado indica que ha terminado la tarea'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Empleados asignados a tareas de producción con su porcentaje';

--
-- Vaciar tabla `lotes_detalles_empleados_asignados`
--
TRUNCATE TABLE `lotes_detalles_empleados_asignados`;

--
-- Estructura de tabla para `lotes_detalles_empleados_asignados_pausas`
--

CREATE TABLE `lotes_detalles_empleados_asignados_pausas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_lotes_detalles_empleados_asignados` int(11) DEFAULT NULL COMMENT 'ID de la tabla madre',
  `pausa_inicio` timestamp NULL DEFAULT NULL COMMENT 'Inicio de la pausa',
  `pausa_fin` timestamp NULL DEFAULT NULL COMMENT 'Fin de la pausa',
  `motivo` mediumtext NOT NULL COMMENT 'MOtivo de la pausa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `lotes_detalles_empleados_asignados_pausas`
--
TRUNCATE TABLE `lotes_detalles_empleados_asignados_pausas`;

--
-- Estructura de tabla para `lotes_fisicos`
--

CREATE TABLE `lotes_fisicos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'id de la orden',
  `id_woo` int(11) DEFAULT NULL COMMENT 'id del producto en woocommerce',
  `piezas_actuales` int(11) DEFAULT NULL COMMENT 'Cantidad de unidades en el lote',
  `tela` varchar(120) DEFAULT NULL COMMENT 'Tela del corte',
  `talla` varchar(5) DEFAULT NULL COMMENT 'Nombre de la talla',
  `corte` varchar(24) DEFAULT NULL COMMENT 'Tipo de corte, dama caballeto etc',
  `categoria` int(11) DEFAULT NULL COMMENT 'ID de la categoría en woocommerce',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Controla la cantidad de piezas cortadas existentes';

--
-- Vaciar tabla `lotes_fisicos`
--
TRUNCATE TABLE `lotes_fisicos`;

--
-- Estructura de tabla para `lotes_historico_solicitadas`
--

CREATE TABLE `lotes_historico_solicitadas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden que solicitó el corte del lote',
  `id_lotes_fisicos` int(11) DEFAULT NULL,
  `unidades_produccion` int(11) DEFAULT NULL COMMENT 'Unidades que se solicitan en produccion',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Histórico de unidades solicitadas';

--
-- Vaciar tabla `lotes_historico_solicitadas`
--
TRUNCATE TABLE `lotes_historico_solicitadas`;

--
-- Estructura de tabla para `lotes_movimientos`
--

CREATE TABLE `lotes_movimientos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_lotes_detalles` int(11) DEFAULT NULL COMMENT 'ID del detalle del lote',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `unidades_existentes` int(11) DEFAULT NULL COMMENT 'unidades existentes en elote al momento de el registro',
  `unidades_solicitadas_corte` int(11) DEFAULT NULL COMMENT 'Unidades solicitadas para cortar',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Registra los movimientos que se efectúan sobre los lotes';

--
-- Vaciar tabla `lotes_movimientos`
--
TRUNCATE TABLE `lotes_movimientos`;

--
-- Estructura de tabla para `metodos_de_pago`
--

CREATE TABLE `metodos_de_pago` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unico de la tabla', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la orden',
  `id_caja_cierres` int(11) DEFAULT NULL COMMENT 'ID del cierre de caja, nos indica si el pago ya ha sido retirado ',
  `moneda` varchar(10) DEFAULT NULL COMMENT 'tipo de moneda',
  `metodo_pago` varchar(20) DEFAULT NULL COMMENT 'Método de pago',
  `detalle` varchar(140) DEFAULT NULL COMMENT 'Detalle en caso de que el tipo de pago sea abonos u otros',
  `tipo_de_pago` varchar(13) NOT NULL DEFAULT 'Orden nueva' COMMENT 'Procedencia del pago para identificar el tipo de ingreso',
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto cancelado en cada metodo de pago',
  `tasa` decimal(12,2) DEFAULT NULL COMMENT 'Tasa de conversion con relacion al dolar', -- Corregido: decimal(12,2)
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `metodos_de_pago`
--
TRUNCATE TABLE `metodos_de_pago`;

--
-- Estructura de tabla para `ordenes`
--

CREATE TABLE `ordenes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_wp` int(11) DEFAULT NULL COMMENT 'ID del cliente de Woocommerce',
  `id_wp_order` int(11) DEFAULT NULL COMMENT 'ID de la orden generada en Wocommerce',
  `status` varchar(45) DEFAULT NULL COMMENT 'Status de la orden: activa, pausada, cancelada, terminada, entregada',
  `tipo` varchar(6) NOT NULL DEFAULT 'custom' COMMENT 'Identificar si la orden pertence a custom o a sport',
  `responsable` int(11) DEFAULT NULL COMMENT 'ID del Vendedor',
  `cliente_nombre` varchar(256) DEFAULT NULL COMMENT 'Nombre del cliente',
  `cliente_cedula` varchar(45) DEFAULT NULL COMMENT 'Cedula del cliente',
  `lote_id` varchar(33) DEFAULT NULL COMMENT 'ID del Lote',
  `fecha_inicio` varchar(45) DEFAULT NULL COMMENT 'Fecha de inicio de la orden',
  `fecha_entrega` varchar(45) DEFAULT NULL COMMENT 'Fecha de entrega de la orden',
  `fecha_creacion` date DEFAULT NULL,
  `token` varchar(45) DEFAULT NULL COMMENT 'Token random',
  `pago_descuento` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Descuento sobre le monto de la orden',
  `pago_total` decimal(12,2) DEFAULT 0.00 COMMENT 'Montototal de la orden',
  `pago_abono` decimal(12,2) DEFAULT 0.00 COMMENT 'Monto abonado',
  `pago_comision` varchar(9) NOT NULL DEFAULT 'pendiente' COMMENT 'Los valores puedes ser pendiente: cuando aun no se ha pagado el total de la orden al vendedor, pagado, cuando se ha  terminado de pagar la totalidad de comisiones al vendedor, anulado, cuando por algun motivo no se terminará de pagar el vanededor y el administrador decide anular los pagos de esta orden',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creación del registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `ordenes`
--
TRUNCATE TABLE `ordenes`;

--
-- Estructura de tabla para `ordenes_borrador_empleado`
--

CREATE TABLE `ordenes_borrador_empleado` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `id_departamento` int(11) NOT NULL COMMENT 'ID del departamento',
  `borrador` mediumtext DEFAULT NULL COMMENT 'El detalle de la orden editado por el empleado',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `ordenes_borrador_empleado`
--
TRUNCATE TABLE `ordenes_borrador_empleado`;

--
-- Estructura de tabla para `ordenes_fila_orden`
--

CREATE TABLE `ordenes_fila_orden` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `orden_fila` int(6) DEFAULT NULL COMMENT 'Orden en la fila de producción'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `ordenes_fila_orden`
--
TRUNCATE TABLE `ordenes_fila_orden`;

--
-- Delimitadores para triggers
--
DELIMITER $$

--
-- Triggers `ordenes_fila_orden_cambios_trigger_delete`
--
CREATE TRIGGER `ordenes_fila_orden_cambios_trigger_delete` AFTER DELETE ON `ordenes_fila_orden` FOR EACH ROW BEGIN
    -- Obtiene todos los registros de ordenes_fila_orden ordenados por orden_fila
    SET @cambio = (SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
        '_id', _id,
        'id_orden', id_orden,
        'orden_fila', orden_fila       
    )), ']') FROM ordenes_fila_orden ORDER BY orden_fila ASC);

    -- Inserta el cambio en la tabla ordenes_fila_orden_cambios
    INSERT INTO ordenes_fila_orden_cambios (cambio) VALUES (@cambio);
END
$$

--
-- Triggers `ordenes_fila_orden_cambios_trigger_insert`
--
CREATE TRIGGER `ordenes_fila_orden_cambios_trigger_insert` AFTER INSERT ON `ordenes_fila_orden` FOR EACH ROW BEGIN
    -- Obtiene todos los registros de ordenes_fila_orden ordenados por orden_fila
    SET @cambio = (SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
        '_id', _id,
        'id_orden', id_orden,
        'orden_fila', orden_fila      
    )), ']') FROM ordenes_fila_orden ORDER BY orden_fila ASC);

    -- Inserta el cambio en la tabla ordenes_fila_orden_cambios
    INSERT INTO ordenes_fila_orden_cambios (cambio) VALUES (@cambio);
END
$$

--
-- Triggers `ordenes_fila_orden_cambios_trigger_update`
--
CREATE TRIGGER `ordenes_fila_orden_cambios_trigger_update` AFTER UPDATE ON `ordenes_fila_orden` FOR EACH ROW BEGIN
    -- Obtiene todos los registros de ordenes_fila_orden ordenados por orden_fila
    SET @cambio = (SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
        '_id', _id,
        'id_orden', id_orden,
        'orden_fila', orden_fila        
    )), ']') FROM ordenes_fila_orden ORDER BY orden_fila ASC);

    -- Inserta el cambio en la tabla ordenes_fila_orden_cambios
    INSERT INTO ordenes_fila_orden_cambios (cambio) VALUES (@cambio);
END
$$
DELIMITER ;

--
-- Estructura de tabla para `ordenes_fila_orden_cambios`
--

CREATE TABLE `ordenes_fila_orden_cambios` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `cambio` mediumtext NOT NULL,
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `ordenes_fila_orden_cambios`
--
TRUNCATE TABLE `ordenes_fila_orden_cambios`;

--
-- Estructura de tabla para `ordenes_fila_reposiciones`
--

CREATE TABLE `ordenes_fila_reposiciones` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `orden_fila` smallint(6) DEFAULT NULL COMMENT 'Orden en la fila de producción'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `ordenes_fila_reposiciones`
--
TRUNCATE TABLE `ordenes_fila_reposiciones`;

--
-- Estructura de tabla para `ordenes_observaciones`
--

CREATE TABLE `ordenes_observaciones` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) NOT NULL,
  `observaciones` longtext DEFAULT NULL COMMENT 'Observaciones de la orden desde QuillEditor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Observaciones de las ordenes en html e imágenes incrustadas';

--
-- Vaciar tabla `ordenes_observaciones`
--
TRUNCATE TABLE `ordenes_observaciones`;

--
-- Estructura de tabla para `ordenes_productos`
--

CREATE TABLE `ordenes_productos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID del registro', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_woo` int(11) DEFAULT NULL COMMENT 'ID del producto en woocommerce',
  `id_tela` int(11) DEFAULT NULL COMMENT 'ID de la tela a utilizar del catálogo de telas',
  `id_category` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la catagoria en WooCommerce ',
  `id_products_attributes` int(11) DEFAULT NULL COMMENT 'ID de la variante del producto',
  `category_name` varchar(20) DEFAULT NULL COMMENT 'NOMBRE de la categoria en woocommerce',
  `name` varchar(240) DEFAULT NULL COMMENT 'Nombre del producto',
  `cantidad` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad del producto',
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `talla` varchar(128) DEFAULT NULL COMMENT 'Talla del producto',
  `corte` varchar(32) DEFAULT NULL COMMENT 'Dama, caballero, niño',
  `metros` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Metros de material utilizado',
  `desperdicio` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Restos del material',
  `rollo` int(11) DEFAULT NULL COMMENT 'ID de el catálogo de telas',
  `tela` varchar(128) DEFAULT NULL COMMENT 'Tela principal seleccionada desde Comercialización',
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del producto',
  `precio_woo` decimal(10,2) DEFAULT NULL COMMENT 'Precio de Woocommerce', -- Corregido: decimal(10,2)
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `ordenes_productos`
--
TRUNCATE TABLE `ordenes_productos`;

--
-- Estructura de tabla para `ordenes_tmp`
--

CREATE TABLE `ordenes_tmp` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Clave primaria', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `form` longtext DEFAULT NULL COMMENT 'Datos del formulario',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del vendedor',
  `tipo` varchar(11) NOT NULL DEFAULT 'Orden' COMMENT 'Orden o Presupuesto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Ordenes guardadas pendientes por terminar';

--
-- Vaciar tabla `ordenes_tmp`
--
TRUNCATE TABLE `ordenes_tmp`;

--
-- Estructura de tabla para `ordenes_vinculadas`
--

CREATE TABLE `ordenes_vinculadas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'id de la tabla', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_father` int(11) DEFAULT NULL COMMENT 'ID de la orden principal',
  `id_child` int(11) DEFAULT NULL COMMENT 'ID de la orden secundaria',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `ordenes_vinculadas`
--
TRUNCATE TABLE `ordenes_vinculadas`;

--
-- Estructura de tabla para `pagos`
--

CREATE TABLE `pagos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unico', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de la reposición se usa para identificar la reposición en los pagos y filtrar las reposiciones terminadas en el modulo de empleados',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento del empleado, lo utilizamos para identificar si es reposición a cual departamento de los que tenga asignados el empleado pertenece el pago. ',
  `id_metodos_de_pago` int(11) DEFAULT NULL COMMENT 'ID de la tabla metodos_de_pago',
  `id_lotes_detalles` int(11) DEFAULT NULL COMMENT 'ID del registro asociado al pago',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `cantidad` int(11) DEFAULT NULL COMMENT 'Cantidad de items a calcular',
  `monto_pago` decimal(12,2) DEFAULT NULL COMMENT 'Monto a pagar',
  `comision` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Comision usada para el calculo del pago',
  `comision_tipo` varchar(12) DEFAULT NULL COMMENT 'Tipo de comision: fija, variable, porcentaje',
  `detalle` varchar(64) DEFAULT NULL COMMENT 'Detalle de el pago, en el caso de diseño pra diferenciar si el pago es por ajuste, personalizacion etc, en el caso de los empleados no es relevante pues es un pago unico por item trabajado registrado en la tabla id_lotes_detalles',
  `estatus` varchar(9) DEFAULT NULL COMMENT '`aprobado` es el estado por defecto, se crea al terminar la tarea desde el modulo del empleado y `rechazado` se asigna cuando hay una revision y se vuelve a asignar cuando el empleado repite la tarea',
  `fecha_pago` timestamp NULL DEFAULT NULL COMMENT 'Fecha en que se raliza el pago si es NULL no se ha realizado el pago',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='id_lotes_detalles se usa para pagos de empleados y id_orden para vendedores y disenadores';

--
-- Vaciar tabla `pagos`
--
TRUNCATE TABLE `pagos`;

--
-- Estructura de tabla para `piezas_cortadas`
--

CREATE TABLE `piezas_cortadas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unico', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_inventario` int(11) DEFAULT NULL COMMENT 'ID del insumo',
  `id_ordenes_productos` int(11) DEFAULT NULL COMMENT 'ID de los detalles de el producto cortado',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado que hizo el corte',
  `peso` decimal(5,2) DEFAULT NULL COMMENT 'Peso en Gramos de los cortes',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Detalles de las piezas cortadas';

--
-- Vaciar tabla `piezas_cortadas`
--
TRUNCATE TABLE `piezas_cortadas`;

--
-- Estructura de tabla para `presupuestos`
--

CREATE TABLE `presupuestos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_wp` int(11) DEFAULT NULL COMMENT 'ID del cliente de Woocommerce',
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
  `pago_descuento` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Descuento sobre le monto de la orden',
  `pago_total` decimal(12,2) DEFAULT 0.00 COMMENT 'Montototal de la orden',
  `pago_abono` decimal(12,2) DEFAULT 0.00 COMMENT 'Monto abonado',
  `pago_comision` varchar(9) NOT NULL DEFAULT 'pendiente' COMMENT 'Los valores puedes ser pendiente: cuando aun no se ha pagado el total de la orden al vendedor, pagado, cuando se ha  terminado de pagar la totalidad de comisiones al vendedor, anulado, cuando por algun motivo no se terminará de pagar el vanededor y el administrador decide anular los pagos de esta orden',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creación del registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `presupuestos`
--
TRUNCATE TABLE `presupuestos`;

--
-- Estructura de tabla para `presupuestos_productos`
--

CREATE TABLE `presupuestos_productos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID del registro', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_woo` int(11) DEFAULT NULL COMMENT 'ID del producto en woocommerce',
  `id_category` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la catagoria en WooCommerce ',
  `category_name` varchar(20) DEFAULT NULL COMMENT 'NOMBRE de la categoria en woocommerce',
  `name` varchar(240) DEFAULT NULL COMMENT 'Nombre del producto',
  `cantidad` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad del producto',
  `talla` varchar(8) DEFAULT NULL COMMENT 'Talla del producto',
  `corte` varchar(32) DEFAULT NULL COMMENT 'Dama, caballero, niño',
  `id_catalogo_telas` int(11) DEFAULT NULL COMMENT 'ID de el catálogo de telas',
  `tela` varchar(128) DEFAULT NULL COMMENT 'Tela principal seleccionada desde Comercialización',
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del producto',
  `precio_woo` decimal(10,2) DEFAULT NULL COMMENT 'Precio de Woocommerce', -- Corregido: decimal(10,2)
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `presupuestos_productos`
--
TRUNCATE TABLE `presupuestos_productos`;

--
-- Estructura de tabla para `products`
--

CREATE TABLE `products` (
  `_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `product` text DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `fisico` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indica true si es un [rpducto virtual como diseños, patronajes o indica si es un producto fisico, si es falso indica un producto virtual o digital',
  `es_diseno` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el producto es un diseño (gráfico, logotipo, etc.)',
  `price` decimal(20,2) NOT NULL DEFAULT 0.00, -- Corregido: NOT NULL DEFAULT 0.00
  `comision` decimal(7,2) DEFAULT 0.00 COMMENT 'Monto para el calculo de comisión variable',
  `stock_quantity` int(11) DEFAULT 0 COMMENT 'Existencia en inventario\r\n',
  `product_description` text DEFAULT NULL COMMENT 'Descripción para mostrar e el sistema y la teienda',
  `category_ids` varchar(255) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `products`
--
TRUNCATE TABLE `products`;

--
-- Volcado de datos para la tabla `products`
--
INSERT INTO `products` (`_id`, `product`, `sku`, `fisico`, `price`, `comision`, `stock_quantity`, `product_description`, `category_ids`) VALUES
(1, 'Producto de pruebas', 'PRU_01', 1, 10.00, 0.20, 12, 'Producto de pruebas', '1'),
(2, 'Diseño Gráfico', 'DIS_01', 0, 15.00, 5.00, 0, 'Diseño Gráfico', '1');

--
-- Estructura de tabla para `products_attributes`
--

CREATE TABLE `products_attributes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `attribute_name` varchar(255) NOT NULL COMMENT 'Nombre del atributo',
  `precio` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del atributo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Catálogo de atributos para productos';

--
-- Vaciar tabla `products_attributes`
--
TRUNCATE TABLE `products_attributes`;

--
-- Volcado de datos para la tabla `products_attributes`
--
INSERT INTO `products_attributes` (`_id`, `attribute_name`, `precio`) VALUES
(1, 'Atributo de Prueba', 1.00);

--
-- Estructura de tabla para `products_attributes_values`
--

CREATE TABLE `products_attributes_values` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_product` int(11) NOT NULL COMMENT 'id del prodcuto',
  `id_product_attribute` int(11) NOT NULL COMMENT 'id del atributo del producto',
  `attribute_value` varchar(128) NOT NULL COMMENT 'Descripción del atributo del producto',
  `attribute_price` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del atributo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Atributos asignados a los productos';

--
-- Vaciar tabla `products_attributes_values`
--
TRUNCATE TABLE `products_attributes_values`; -- Esta tabla quedará vacía al final

--
-- Estructura de tabla para `products_comisiones`
--

CREATE TABLE `products_comisiones` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `comision` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Comisión asignada',
  UNIQUE KEY `unique_product_dept` (`id_product`,`id_departamento`) -- Descomentado/Añadido si se quiere esta unicidad
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Comisiones asignada a los productos por departamento';

--
-- Vaciar tabla `products_comisiones`
--
TRUNCATE TABLE `products_comisiones`;

--
-- Volcado de datos para la tabla `products_comisiones`
--
INSERT INTO `products_comisiones` (`_id`, `id_product`, `id_departamento`, `comision`) VALUES
(1, 1, 1, 0.09),
(2, 1, 2, 0.07),
(3, 1, 3, 0.05),
(4, 1, 4, 0.04),
(5, 2, 7, 10.00);

--
-- Estructura de tabla para `products_prices`
--

CREATE TABLE `products_prices` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto',
  `price` decimal(7,2) DEFAULT NULL COMMENT 'Precio del producto',
  `descripcion` varchar(128) DEFAULT NULL COMMENT 'Descripción del precio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Precios de productos';

--
-- Vaciar tabla `products_prices`
--
TRUNCATE TABLE `products_prices`;

--
-- Volcado de datos para la tabla `products_prices`
--
INSERT INTO `products_prices` (`_id`, `id_product`, `price`, `descripcion`) VALUES
(1, 1, 20.00, 'Detal'),
(2, 1, 15.00, 'Mayor'),
(3, 2, 15.00, 'Único');

--
-- Estructura de tabla para `products_sizes_eficiencia`
--

CREATE TABLE `products_sizes_eficiencia` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `id_catalogo_insumos_prodcutos` int(11) DEFAULT NULL COMMENT 'ID de la tabla catalogo_ibsumos_productos',
  `cantidad` decimal(3,2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad de insumo',
  `unidad` varchar(64) DEFAULT NULL COMMENT 'Unidad de medida del insumo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Valores de la eficiencia de productos por tallas';

--
-- Vaciar tabla `products_sizes_eficiencia`
--
TRUNCATE TABLE `products_sizes_eficiencia`;

--
-- Estructura de tabla para `products_tiempos_de_produccion`
--

CREATE TABLE `products_tiempos_de_produccion` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `tiempo` int(11) NOT NULL DEFAULT 1 COMMENT 'Tiempo de producción e segundos',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `products_tiempos_de_produccion`
--
TRUNCATE TABLE `products_tiempos_de_produccion`;

--
-- Volcado de datos para la tabla `products_tiempos_de_produccion`
--
INSERT INTO `products_tiempos_de_produccion` (`_id`, `id_product`, `id_departamento`, `tiempo`) VALUES
(1, 1, 1, 60),
(2, 1, 2, 108),
(3, 1, 3, 228),
(4, 1, 4, 330);

--
-- Estructura de tabla para `product_insumos_asignados`
--

CREATE TABLE `product_insumos_asignados` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del prodducto',
  `id_catalogo_insumos_productos` int(11) DEFAULT NULL COMMENT 'ID delc atalogo de insumos de productos',
  `id_departamento` int(11) NOT NULL COMMENT 'ID del departamento',
  `id_talla` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `cantidad` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'cantidad del insumo',
  `unidad` varchar(64) DEFAULT NULL COMMENT 'Unidad de medida del insumo',
  `tiempo` int(11) NOT NULL DEFAULT 0 COMMENT 'tiempo estimadop de fabricación en segundos',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `product_insumos_asignados`
--
TRUNCATE TABLE `product_insumos_asignados`;

--
-- Estructura de tabla para `rendimiento`
--

CREATE TABLE `rendimiento` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_empleado_impresion` int(11) DEFAULT NULL,
  `id_empleado_estampado` int(11) DEFAULT NULL,
  `id_empleado_corte` int(11) DEFAULT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_insumo` int(11) DEFAULT NULL COMMENT 'Numero de rollo',
  `metros` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Metros de material utilizado',
  `desperdicio` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'peso en gramos del material sobrante',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Datos para el calculo de el rendimiento del material';

--
-- Vaciar tabla `rendimiento`
--
TRUNCATE TABLE `rendimiento`;

--
-- Estructura de tabla para `reposiciones`
--

CREATE TABLE `reposiciones` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unico de la tabla', -- Corregido: AUTO_INCREMENT PRIMARY KEY
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Control de reposiciones durante el proceso de fabricacion';

--
-- Vaciar tabla `reposiciones`
--
TRUNCATE TABLE `reposiciones`;

--
-- Estructura de tabla para `retiros`
--

CREATE TABLE `retiros` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_empleado` int(11) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL, -- Corregido: decimal(10,2)
  `moneda` varchar(12) DEFAULT NULL COMMENT 'nombre de la moneda que será objeto del retiro',
  `tasa` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'TASA DE CONVERSION', -- Corregido: decimal(10,2)
  `metodo_pago` varchar(20) DEFAULT NULL COMMENT 'Metodo de pago ejm pago movil, efectivo etc.',
  `detalle_retiro` text DEFAULT NULL,
  `cierre_caja` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'El registro corresponde a un cierre de caja',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `retiros`
--
TRUNCATE TABLE `retiros`;

--
-- Estructura de tabla para `revisiones`
--

CREATE TABLE `revisiones` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'id de la tabla', -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden a la cual pertenece el diseño y estarevision',
  `id_diseno` int(11) DEFAULT NULL COMMENT 'id en la tabla disenos',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del diseñador que envió la revisión',
  `id_product` int(11) DEFAULT NULL COMMENT 'ID del producto asociado a la revisión',
  `tipo` varchar(128) DEFAULT NULL COMMENT 'Tipo de diseño asociado a la revisión',
  `revision` int(11) NOT NULL DEFAULT 0 COMMENT 'Numero de revisiones máximo dos',
  `estatus` varchar(19) NOT NULL DEFAULT 'Esperando Respuesta' COMMENT 'Los estados son ''Esperando Respuesta'', ''Rechazado'', ''Aprobado''',
  `url_image` varchar(255) DEFAULT NULL COMMENT 'URL de la imagen de la revisión',
  `detalles` text DEFAULT NULL COMMENT 'Detalles de la revision',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `revisiones`
--
TRUNCATE TABLE `revisiones`;

--
-- Estructura de tabla para `sizes`
--

CREATE TABLE `sizes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `nombre` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Vaciar tabla `sizes`
--
TRUNCATE TABLE `sizes`;

--
-- Volcado de datos para la tabla `sizes`
--
INSERT INTO `sizes` (`_id`, `nombre`) VALUES
(1, 'S'),
(2, 'M'),
(3, 'L'),
(4, 'XL');

--
-- Estructura de tabla para `tintas`
--

CREATE TABLE `tintas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `c` decimal(7,2) DEFAULT NULL COMMENT 'Cyan',
  `m` decimal(7,2) DEFAULT NULL COMMENT 'Magenta',
  `y` decimal(7,2) DEFAULT NULL COMMENT 'Yellow',
  `k` decimal(7,2) DEFAULT NULL COMMENT 'Black',
  `w` decimal(7,2) DEFAULT NULL COMMENT 'White',
  `id_catalogo_impresoras` int(11) DEFAULT NULL COMMENT 'ID del catálogo de impresoras',
  `id_orden` int(11) DEFAULT NULL COMMENT 'Id de la Orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado que imprimió',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Registra el consumo de tintas por orden';

--
-- Vaciar tabla `tintas`
--
TRUNCATE TABLE `tintas`;

--
-- Estructura de tabla para `tintas_recargas`
--

CREATE TABLE `tintas_recargas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_insumo` int(11) DEFAULT NULL,
  `id_catalogo_impresora` int(11) DEFAULT NULL COMMENT 'ID catalodo de imoresoras',
  `color` varchar(1) DEFAULT NULL COMMENT 'Color de la tinta',
  `cantidad` decimal(7,2) DEFAULT NULL COMMENT 'Cantidad en ML',
  `fecha_recarga` timestamp NULL DEFAULT NULL COMMENT 'Fecha de la recarga',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Recargas de tinta';

--
-- Vaciar tabla `tintas_recargas`
--
TRUNCATE TABLE `tintas_recargas`;

--
-- Estructura de tabla para `tinta_filtro`
--

CREATE TABLE `tinta_filtro` (
  `_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Corregido: AUTO_INCREMENT PRIMARY KEY
  `id_inventario` int(11) DEFAULT NULL COMMENT 'Id del insumo',
  `color` varchar(1) NOT NULL COMMENT 'Color de la tinta C, M, Y, K, W',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Indica cuales insumos son tintas para filtrar las tintas';

--
-- Vaciar tabla `tinta_filtro`
--
TRUNCATE TABLE `tinta_filtro`;

--
-- Volcado de datos para la tabla `tinta_filtro`
--
INSERT INTO `tinta_filtro` (`_id`, `id_inventario`, `color`) VALUES
(1, 2, 'C'),
(2, 3, 'M'),
(3, 4, 'Y'),
(4, 5, 'K');


-- Tabla: salario_carga_familiar
CREATE TABLE salario_carga_familiar (
    id_carga INT NOT NULL AUTO_INCREMENT COMMENT 'Clave primaria, identificador único del familiar o dependiente.',
    id_usuario INT DEFAULT NULL COMMENT 'Clave foránea a la tabla de empleados (empresas_usuarios), indica a quién pertenece la carga.',
    nombre_completo VARCHAR(255) DEFAULT NULL COMMENT 'Nombre completo del dependiente.',
    cedula_o_id VARCHAR(50) DEFAULT NULL COMMENT 'Documento de identidad del dependiente, si aplica.',
    parentesco VARCHAR(50) DEFAULT NULL COMMENT 'Relación con el empleado (ej: Hijo, Cónyuge, Padre).',
    fecha_nacimiento DATE DEFAULT NULL COMMENT 'Fecha de nacimiento del familiar (útil para beneficios por edad).',
    es_deducible BOOLEAN DEFAULT NULL COMMENT 'Indica si aplica como deducción fiscal o beneficio de ley (TRUE/FALSE).',
    PRIMARY KEY (id_carga)
) COMMENT = 'Registra los dependientes o familiares del empleado, relevante para beneficios y deducciones fiscales.';

-- TABLA 1: salario_periodo_nomina
CREATE TABLE salario_periodo_nomina (
    id_periodo INT NOT NULL AUTO_INCREMENT COMMENT 'Clave primaria, identificador único del período de pago.',
    id_empresa INT DEFAULT NULL COMMENT 'Clave foránea a la tabla empresas.',
    fecha_inicio DATE DEFAULT NULL COMMENT 'Día inicial del período de cálculo de nómina.',
    fecha_fin DATE DEFAULT NULL COMMENT 'Día final del período de cálculo de nómina.',
    frecuencia_pago VARCHAR(20) DEFAULT NULL COMMENT 'Frecuencia (ej: Quincenal, Mensual).',
    fecha_pago DATE DEFAULT NULL COMMENT 'Día en que se realiza el pago a los empleados.',
    estado VARCHAR(50) DEFAULT NULL COMMENT 'Estado del proceso (ej: Abierto, Procesado, Pagado, Anulado).',
    PRIMARY KEY (id_periodo),
    FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa)
) COMMENT = 'Define los períodos de tiempo en los que se procesa la nómina.';


-- TABLA 2: salario_incidencias_x_empleado
CREATE TABLE salario_incidencias_x_empleado (
    id_incidencia INT NOT NULL AUTO_INCREMENT COMMENT 'Clave primaria, identificador único de la incidencia en el período.',
    id_periodo INT DEFAULT NULL COMMENT 'Clave foránea a salario_periodo_nomina.',
    id_usuario INT DEFAULT NULL COMMENT 'Clave foránea a la tabla de empleados (empresas_usuarios).',
    horas_extra_diurnas DECIMAL(5, 2) DEFAULT NULL COMMENT 'Cantidad de horas extra diurnas trabajadas.',
    horas_extra_nocturnas DECIMAL(5, 2) DEFAULT NULL COMMENT 'Cantidad de horas extra nocturnas trabajadas.',
    dias_feriados_trabajados DECIMAL(4, 2) DEFAULT NULL COMMENT 'Días feriados o de descanso obligatorio trabajados.',
    dias_vacaciones_tomados DECIMAL(4, 2) DEFAULT NULL COMMENT 'Días de vacaciones que el empleado tomó en el período.',
    monto_comision DECIMAL(15, 2) DEFAULT NULL COMMENT 'Monto de comisiones devengadas en el período.',
    monto_bono_alimenticio DECIMAL(15, 2) DEFAULT NULL COMMENT 'Monto del Cestaticket o bono alimenticio del período.',
    PRIMARY KEY (id_incidencia),
    FOREIGN KEY (id_periodo) REFERENCES salario_periodo_nomina(id_periodo)
) COMMENT = 'Registra todas las variables que impactan el salario del empleado en un período específico.';


-- TABLA 3: salario_resultados_nomina
CREATE TABLE salario_resultados_nomina (
    id_resultado INT NOT NULL AUTO_INCREMENT COMMENT 'Clave primaria, identificador único de la línea de resultado de nómina.',
    id_periodo INT DEFAULT NULL COMMENT 'Clave foránea a salario_periodo_nomina.',
    id_usuario INT DEFAULT NULL COMMENT 'Clave foránea a la tabla de empleados (empresas_usuarios).',
    id_concepto INT DEFAULT NULL COMMENT 'Clave foránea que referencia el concepto legal (salario_conceptos_nomina, en la BD Central).',
    monto DECIMAL(15, 2) DEFAULT NULL COMMENT 'Monto calculado para este concepto (positivo para Devengo, negativo para Deducción).',
    es_deduccion BOOLEAN DEFAULT NULL COMMENT 'Indica si el monto final fue una deducción (TRUE) o una percepción (FALSE).',
    detalle_calculo TEXT DEFAULT NULL COMMENT 'Descripción opcional del cálculo (ej: Base 100 * 1.50 recargo).',
    PRIMARY KEY (id_resultado),
    FOREIGN KEY (id_periodo) REFERENCES salario_periodo_nomina(id_periodo)
    -- NOTA: La FK a salario_conceptos_nomina es lógica/aplicación, no física, por estar en otra BD.
) COMMENT = 'Almacena el detalle final de cada concepto de pago o deducción para generar el recibo de nómina.';


--
-- Índices para tablas volcadas
-- Las PRIMARY KEY y UNIQUE KEY ya se definieron en el CREATE TABLE
-- Solo se añaden los KEY (índices no únicos)
-- salario más comision

ALTER TABLE `abonos`
  ADD KEY `id_orden` (`id_orden`,`id_empleado`),
  ADD KEY `id_empleado` (`id_empleado`);

ALTER TABLE `aprobacion_clientes`
  ADD KEY `id_orden` (`id_orden`,`id_diseno`);

ALTER TABLE `asistencias`
  ADD KEY `id_empleado` (`id_empleado`);

ALTER TABLE `caja`
  ADD KEY `id_empleado` (`id_empleado`);

ALTER TABLE `caja_cierres`
  ADD KEY `id_empleado` (`id_empleado`);

-- ALTER TABLE `catalogo_impresoras` -- Ya se añadió UNIQUE KEY en el CREATE TABLE
--   ADD UNIQUE KEY `idx_codigo_interno` (`codigo_interno`) COMMENT 'Asegura que cada código sea único.';

-- ALTER TABLE `catalogo_insumos_productos` -- Ya se añadió PRIMARY KEY y UNIQUE KEY en el CREATE TABLE
--   ADD UNIQUE KEY `nombre` (`nombre`), -- Ya se añadió
--   ADD UNIQUE KEY `nombre_2` (`nombre`); -- Redundante, eliminado

-- ALTER TABLE `catalogo_telas` -- Ya se añadió PRIMARY KEY en CREATE TABLE
--   ADD UNIQUE KEY `_id` (`_id`); -- Redundante, eliminado

ALTER TABLE `disenos`
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_empleado` (`id_empleado`);

ALTER TABLE `disenos_ajustes_y_personalizaciones`
  ADD KEY `id_orden` (`id_orden`,`id_diseno`);

ALTER TABLE `inventario_movimientos`
  ADD KEY `id_orden` (`id_orden`,`id_producto`,`id_empleado`,`id_insumo`);

ALTER TABLE `lotes`
  ADD KEY `id_orden` (`id_orden`);

ALTER TABLE `lotes_detalles`
  ADD KEY `id_empleado` (`id_empleado`),
  ADD KEY `id_orden` (`id_orden`,`id_ordenes_productos`);

ALTER TABLE `lotes_fisicos`
  ADD KEY `id_orden` (`id_orden`);

ALTER TABLE `lotes_historico_solicitadas`
  ADD KEY `id_orden` (`id_orden`,`id_lotes_fisicos`);

ALTER TABLE `lotes_movimientos`
  ADD KEY `id_lotes_detalles` (`id_lotes_detalles`,`id_orden`);

ALTER TABLE `metodos_de_pago`
  ADD KEY `id_orden` (`id_orden`);

ALTER TABLE `ordenes_productos`
  ADD KEY `id_orden` (`id_orden`,`rollo`),
  ADD KEY `id_catalogo_telas` (`rollo`);

ALTER TABLE `ordenes_vinculadas`
  ADD KEY `id_father` (`id_father`,`id_child`),
  ADD KEY `id_child` (`id_child`);

ALTER TABLE `pagos`
  ADD KEY `id_orden` (`id_orden`,`id_metodos_de_pago`,`id_lotes_detalles`,`id_empleado`),
  ADD KEY `id_metodos_de_pago` (`id_metodos_de_pago`),
  ADD KEY `id_lotes_detalles` (`id_lotes_detalles`),
  ADD KEY `id_empleado` (`id_empleado`);

ALTER TABLE `piezas_cortadas`
  ADD KEY `id_orden` (`id_orden`,`id_inventario`,`id_ordenes_productos`,`id_empleado`);

ALTER TABLE `presupuestos_productos`
  ADD KEY `id_orden` (`id_orden`,`id_catalogo_telas`),
  ADD KEY `id_catalogo_telas` (`id_catalogo_telas`);

-- ALTER TABLE `products_comisiones` -- UNIQUE KEY ya se definió en el CREATE TABLE
--   ADD PRIMARY KEY (`_id`); -- Ya se definió

ALTER TABLE `reposiciones`
  ADD KEY `id_orden` (`id_orden`,`id_empleado`,`id_ordenes_productos`),
  ADD KEY `id_empleado_emisor` (`id_empleado_emisor`);

ALTER TABLE `retiros`
  ADD KEY `id_empleado` (`id_empleado`); 

ALTER TABLE `revisiones`
  ADD KEY `id_orden` (`id_orden`,`id_diseno`); -- Eliminado id_orden_2 redundante


-- No se necesitan ALTER TABLE ... MODIFY `_id` ... AUTO_INCREMENT porque ya están en el CREATE TABLE
-- Si quisieras cambiar el AUTO_INCREMENT inicial, lo harías así:
-- ALTER TABLE `abonos` AUTO_INCREMENT = 1;
-- etc.


--
-- Restricciones para tablas volcadas
--

ALTER TABLE `abonos`
  ADD CONSTRAINT `abonos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `disenos`
  ADD CONSTRAINT `disenos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`);

ALTER TABLE `lotes`
  ADD CONSTRAINT `lotes_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `lotes_detalles`
  ADD CONSTRAINT `lotes_detalles_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`);

ALTER TABLE `ordenes_productos`
  ADD CONSTRAINT `ordenes_productos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `ordenes_vinculadas`
  ADD CONSTRAINT `ordenes_vinculadas_ibfk_1` FOREIGN KEY (`id_father`) REFERENCES `ordenes` (`_id`),
  ADD CONSTRAINT `ordenes_vinculadas_ibfk_2` FOREIGN KEY (`id_child`) REFERENCES `ordenes` (`_id`);
SET FOREIGN_KEY_CHECKS=1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;