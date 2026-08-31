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
  `abono` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'monto del abono',
  `descuento` decimal(12, 2) DEFAULT 0.00 COMMENT 'Descuento del abono',
  `nota_credito` decimal(12, 2) DEFAULT 0.00 COMMENT 'Nota de crédito (devolución al cliente)',
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
  `id_catalogo_tintas` int(11) DEFAULT NULL COMMENT 'ID de la tecnología de tinta en catalogo_tintas',
  `estado` varchar(20) NOT NULL DEFAULT 'activa' COMMENT 'Estado actual. Ej: activa, inactiva, en_mantenimiento',
  `notas` text DEFAULT NULL COMMENT 'Cualquier información adicional relevante.',
  `ml_tinta_por_metro` decimal(6, 2) NOT NULL DEFAULT 12.00 COMMENT 'Estimado de ml de tinta por metro de material (full print, sin considerar saturación). Solo referencia visual en el modal de Impresión, no autocompleta ni valida.',
  `ingresar_tinta_manual` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Si el empleado debe ingresar manualmente los ml de tinta por color al finalizar. Si es 0, el formulario de captura no se muestra para esta impresora (modo automático).',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de impresoras de la empresa. Almacena información de equipos de impresión para asignación de trabajos y control de producción.';
INSERT INTO `catalogo_impresoras` (`_id`, `codigo_interno`, `marca`, `modelo`, `capacidad_contenedor`, `ubicacion`, `tipo_tecnologia`, `id_catalogo_tintas`, `estado`, `notas`, `moment`) VALUES
(1, 'IMPRESORA PRINCIPAL', 'EPSON', 'EPS_9902', 1000.00, 'PISO 1', 'CMYK', 1, 'activa', 'Impresora con cabezales originales', CURRENT_TIMESTAMP),
(2, 'IMPRESORA SECUNDARIA', 'Mimaki', 'MK_09890', 750.00, 'PISO 2', 'CMYKW', 2, 'activa', 'Impresora para usar solo con tintas originales', CURRENT_TIMESTAMP);

CREATE TABLE `catalogo_colores_tintas` (
  `_id` int(11) NOT NULL COMMENT 'ID único del color de tinta',
  `codigo` varchar(16) NOT NULL COMMENT 'Código corto del color (ej. C, M, Y, K, W, V)',
  `nombre` varchar(64) NOT NULL COMMENT 'Nombre completo del color/insumo',
  `color_hex` varchar(7) DEFAULT '#808080' COMMENT 'Color hexadecimal para representación visual',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo maestro de colores y canales de tintas.';

INSERT INTO `catalogo_colores_tintas` (`_id`, `codigo`, `nombre`, `color_hex`) VALUES
(1,  'C',    'Cyan',          '#00FFFF'),
(2,  'M',    'Magenta',       '#FF00FF'),
(3,  'Y',    'Yellow',        '#FFFF00'),
(4,  'K',    'Black',         '#343A40'),
(5,  'W',    'White',         '#FFFFFF'),
(6,  'BRNZ', 'Barniz',        '#E0F7FA'),
(7,  'LC',   'Light Cyan',    '#80FFFF'),
(8,  'LM',   'Light Magenta', '#FF80FF'),
(9,  'OR',   'Orange',        '#FFA500'),
(10, 'GR',   'Green',         '#008000'),
(11, 'RD',   'Red',           '#FF0000'),
(12, 'BL',   'Blue',          '#0000FF');

CREATE TABLE `impresoras_colores` (
  `id_catalogo_impresora` int(11) NOT NULL COMMENT 'ID de la impresora',
  `id_color_tinta` int(11) NOT NULL COMMENT 'ID del color asignado'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Tabla de relación entre impresoras y canales de color.';

INSERT INTO `impresoras_colores` (`id_catalogo_impresora`, `id_color_tinta`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5);
CREATE TABLE `catalogo_insumos_productos` (
  `_id` int(11) NOT NULL COMMENT 'ID único del catálogo',
  `nombre` varchar(128) NOT NULL COMMENT 'Nombre del tipo de insumo',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo maestro de tipos de insumos para producción. Define categorías de materiales (telas, tintas, botones, etc.) reutilizables entre productos; la relación real producto->insumo vive en product_insumos_asignados.';

INSERT INTO `catalogo_insumos_productos` (`_id`, `nombre`) VALUES
(1, 'Papel para sublimación'),
(3, 'Botones'),
(4, 'Tinta'),
(6, 'Tela');

CREATE TABLE `catalogo_telas` (
  `_id` int(11) NOT NULL COMMENT 'Identificador unico de la tabla',
  `tela` varchar(45) DEFAULT NULL COMMENT 'Nombre de la tela',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de telas disponibles. Almacena tipos de tela con características para selección en órdenes de producción.';
INSERT INTO `catalogo_telas` (`_id`, `tela`, `moment`)
VALUES (1, 'Tela de Prueba', CURRENT_TIMESTAMP);
CREATE TABLE `catalogo_tintas` (
  `_id` int(11) NOT NULL COMMENT 'ID único del catálogo de tintas',
  `nombre` varchar(128) NOT NULL COMMENT 'Nombre del tipo de tinta',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo maestro de tipos de tintas.';

INSERT INTO `catalogo_tintas` (`_id`, `nombre`, `moment`) VALUES
(1, 'Tinta de Sublimación', CURRENT_TIMESTAMP),
(2, 'Tinta DTF (Direct to Film)', CURRENT_TIMESTAMP),
(3, 'Tinta DTG (Direct to Garment)', CURRENT_TIMESTAMP),
(4, 'Tinta Ácida', CURRENT_TIMESTAMP),
(5, 'Tinta Reactiva', CURRENT_TIMESTAMP),
(6, 'Tinta de Pigmento Textil (Directo a Tela)', CURRENT_TIMESTAMP),
(7, 'Tinta UV Textil / Eco-Solvente', CURRENT_TIMESTAMP),
(8, 'Plastisol', CURRENT_TIMESTAMP),
(9, 'Tinta de Base Agua', CURRENT_TIMESTAMP),
(10, 'Tintas de Descarga', CURRENT_TIMESTAMP),
(11, 'Tintas de Silicona', CURRENT_TIMESTAMP),
(12, 'Pastas de Pigmento', CURRENT_TIMESTAMP),
(13, 'Pastas Reactivas y Dispersas', CURRENT_TIMESTAMP),
(14, 'Tintas Metalizadas / Escarchadas (Glitter)', CURRENT_TIMESTAMP),
(15, 'Tintas Fotocromáticas', CURRENT_TIMESTAMP),
(16, 'Tintas Fluorescentes / Neón', CURRENT_TIMESTAMP),
(17, 'Tintas Fosforescentes (Glow in the dark)', CURRENT_TIMESTAMP),
(18, 'Tintas Foil / Adhesivos', CURRENT_TIMESTAMP),
(19, 'Tintas de Alto Relieve (Puff / Espumantes)', CURRENT_TIMESTAMP),
(20, 'Tintas Reflectivas', CURRENT_TIMESTAMP);
CREATE TABLE `categories` (
  `_id` int(11) NOT NULL COMMENT 'ID único de la categoría',
  `nombre` varchar(100) DEFAULT NULL COMMENT 'Nombre de la categoría',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto'
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
CREATE TABLE `servicios_maquinas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_maquina` int(11) DEFAULT NULL,
  `maquina_tipo` varchar(50) DEFAULT 'impresora',
  `tipo_servicio` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tecnico` varchar(255) DEFAULT NULL,
  `costo` decimal(10, 2) DEFAULT 0.00,
  `estado` enum('pendiente','en_proceso','completado','cancelado') DEFAULT 'pendiente',
  `fecha_servicio` datetime DEFAULT NULL,
  `proxima_fecha` datetime DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `idx_maquina` (`id_maquina`, `maquina_tipo`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
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
  `multiplicador_precio` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Multiplicador de precio predeterminado para conversión USD a VES',
  `wizard_operativo_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Datos del Administrador revisado por el cliente',
  `wizard_operativo_empresa` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Datos de la Empresa revisado por el cliente',
  `wizard_operativo_monedas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Monedas revisado por el cliente',
  `wizard_operativo_horario` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Horario Laboral revisado por el cliente',
  `wizard_operativo_personalizacion` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Personalización revisado por el cliente',
  `wizard_operativo_gastos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Gastos Fijos revisado por el cliente',
  `wizard_operativo_departamentos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Departamentos revisado por el cliente',
  `wizard_operativo_empleados` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Empleados revisado por el cliente',
  `wizard_operativo_categorias` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Categorías de productos revisado por el cliente',
  `wizard_operativo_productos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Productos revisado por el cliente',
  `wizard_operativo_insumos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Insumos de productos revisado por el cliente',
  `wizard_operativo_comisiones` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Comisiones de productos revisado por el cliente',
  `wizard_operativo_impresoras` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Impresoras revisado por el cliente (o no aplica)',
  `wizard_operativo_inventario_tintas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Inventario de Tintas revisado por el cliente (o no aplica)',
  `wizard_operativo_tintas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Recarga de tintas revisado por el cliente (o no aplica)',
  `wizard_operativo_tallas_telas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso Tallas y telas revisado por el cliente',
  `wizard_operativo_whatsapp` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: paso WhatsApp revisado por el cliente',
  `wizard_operativo_completo` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wizard operativo: derivado, true cuando todos los pasos obligatorios están revisados o no aplican',
  `wizard_operativo_omitido_en` timestamp NULL DEFAULT NULL COMMENT 'Wizard operativo: fecha en que el cliente eligió Continuar más tarde, si aplica'
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
CREATE TABLE `crm_campanas` (
  `_id` int(11) NOT NULL,
  `nombre` varchar(128) NOT NULL,
  `mensaje_plantilla` text NOT NULL,
  `filtro_productos` text DEFAULT NULL COMMENT 'Arreglo JSON de IDs de productos de la segmentacion',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Campañas de marketing masivas enviadas por WhatsApp';
CREATE TABLE `crm_campanas_envios` (
  `_id` int(11) NOT NULL,
  `id_campana` int(11) NOT NULL,
  `id_customer` int(10) unsigned NOT NULL,
  `estado_envio` varchar(20) NOT NULL DEFAULT 'enviado' COMMENT 'enviado, fallido',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Bitácora de envíos individuales realizados en una campaña de marketing';
CREATE TABLE `crm_notas` (
  `_id` int(11) NOT NULL,
  `id_customer` int(10) unsigned NOT NULL,
  `id_oportunidad` int(11) DEFAULT NULL,
  `id_usuario_creador` int(11) NOT NULL COMMENT 'ID del empleado/usuario que redacta la nota',
  `nota` text NOT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Bitácora de notas de seguimiento de clientes y leads';
CREATE TABLE `crm_oportunidades` (
  `_id` int(11) NOT NULL,
  `id_customer` int(10) unsigned DEFAULT NULL,
  `titulo` varchar(128) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `monto_estimado` decimal(12, 2) NOT NULL DEFAULT 0.00,
  `estado` varchar(32) NOT NULL DEFAULT 'nuevo_lead' COMMENT 'nuevo_lead, en_negociacion, propuesta_enviada, cliente_ganado, cliente_perdido',
  `motivo_perdida` varchar(255) DEFAULT NULL,
  `id_campana` int(11) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Oportunidades de venta y leads en el embudo comercial';
CREATE TABLE `crm_oportunidades_vendedores` (
  `_id` int(11) NOT NULL,
  `id_oportunidad` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL COMMENT 'ID del vendedor de empresas_usuarios',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Vendedores asignados a las oportunidades del embudo comercial';
CREATE TABLE `crm_soporte` (
  `_id` int(11) NOT NULL,
  `id_customer` int(10) unsigned NOT NULL,
  `titulo` varchar(128) NOT NULL,
  `descripcion` text NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'abierto' COMMENT 'abierto, resuelto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Bitácora de incidencias simples de soporte y postventa';
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
  `recibir_notificaciones` tinyint(1) NOT NULL DEFAULT 1,
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico (soft delete): 1 = cliente eliminado/oculto de listados, conserva historial',
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
    'Producción',
    'Interna',
    'interno_sistema',
    'INTERNO-001',
    'N/A',
    'N/A',
    '0000000000',
    'interno@sistema.local',
    NOW()
  );
CREATE TABLE `departamentos` (
  `_id` int(11) NOT NULL,
  `id_modulo` int(11) DEFAULT NULL COMMENT 'ID del módulo asignado al departamento',
  `orden_proceso` int(11) NOT NULL DEFAULT 0 COMMENT 'indica el orden del proceso de fabricación',
  `departamento` varchar(256) DEFAULT NULL COMMENT 'Nombre del departamento',
  `asignar_numero_de_paso` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Interviene en proceso Es un paso de proceso de fabricación',
  `enviar_mensaje` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enviar mensaje al cliente al iniciar el paso',
  `mensaje` text DEFAULT NULL COMMENT 'Mensaje para el cliente máximo 255 caracters',
  `tipo` varchar(50) NOT NULL DEFAULT 'general' COMMENT 'Tipo de comportamiento del departamento (general, corte, impresion, estampado, costura)',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto',
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
    `tipo`,
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
    'impresion',
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
    'estampado',
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
    'corte',
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
    'costura',
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
    'general',
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
    'general',
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
    'general',
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
    'general',
    '2025-09-24 19:50:20'
  );
CREATE TABLE `disenos` (
  `_id` int(11) NOT NULL COMMENT 'ID de la tabla',
  `id_orden` int(11) DEFAULT NULL COMMENT 'IDn de la orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del diseÑador tabla empleados',
  `id_product` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto asociado al diseño',
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
  `tipo_insumo` enum('general','tela','tinta','papel','repuesto','bisutería') NOT NULL DEFAULT 'general' COMMENT 'Categoría de insumo para lógica de consumo',
  `id_color_tinta` int(11) DEFAULT NULL COMMENT 'ID del color de tinta si aplica',
  `id_catalogo_tintas` int(11) DEFAULT NULL COMMENT 'ID de la tecnología de tinta si aplica',
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
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de insumos disponibles en inventario. Almacena materiales de producción con SKU, cantidad actual e inicial, costo, unidad de medida y departamento asignado.';

INSERT INTO `inventario` (`_id`, `sku`, `id_catalogo`, `tipo_insumo`, `id_color_tinta`, `id_catalogo_tintas`, `insumo`, `unidad`, `costo`, `rendimiento`, `cantidad`, `cantidad_inicial`, `color`, `ancho`, `elongacion`, `detalles`, `departamento`, `moment`) VALUES
(1, 'PAP_001', 1, 'general', NULL, NULL, 'Papel de pruebas', 'Mts', 20.00, 1.0, 250.00, 250.00, 'BLANCO', 0.90, NULL, 'Papel para pruebas de impresión', 'Impresión', CURRENT_TIMESTAMP),
(2, 'TEL_001', 6, 'tela', NULL, NULL, 'Tela de pruebas', 'Kg', 80.00, 3.96, 24.00, 24.00, 'BLANCO', 1.50, 'HORIZONTAL', 'Tela para pruebas de estampado', 'Estampado', CURRENT_TIMESTAMP),
(3, 'TIN_C_001', 4, 'tinta', 1, 1, 'Tinta Cyan', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'CYAN', NULL, NULL, 'Tinta cyan para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(4, 'TIN_M_001', 4, 'tinta', 2, 1, 'Tinta Magenta', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'MAGENTA', NULL, NULL, 'Tinta magenta para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(5, 'TIN_Y_001', 4, 'tinta', 3, 1, 'Tinta Yellow', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'YELLOW', NULL, NULL, 'Tinta yellow para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(6, 'TIN_K_001', 4, 'tinta', 4, 1, 'Tinta Black', 'ML', 15.00, 1.0, 1000.00, 1000.00, 'BLACK', NULL, NULL, 'Tinta negra para impresoras', 'Impresión', CURRENT_TIMESTAMP),
(7, 'BOT_001', 3, 'general', NULL, NULL, 'Botones blancos', 'Und', 0.50, 1.0, 1000.00, 1000.00, 'BLANCO', NULL, NULL, 'Botones blancos para prendas', 'Costura', CURRENT_TIMESTAMP);
CREATE TABLE `inventario_movimientos` (
  `_id` int(11) NOT NULL COMMENT 'Identificador unico',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la  orden - lote',
  `id_producto` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del catálogo de productos',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado',
  `id_insumo` int(11) DEFAULT NULL COMMENT 'Id del insumoa signado',
  `id_catalogo_insumos_prodcutos` int(11) DEFAULT NULL COMMENT 'ID del catálogo seleccionado por el empleado al momento de usar el insumo',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'Id del departamento del empleado',
  `departamento` varchar(20) DEFAULT NULL COMMENT 'Nombre del departamento',
  `valor_inicial` decimal(7, 2) DEFAULT NULL COMMENT 'Valor inicial del insumo',
  `valor_final` decimal(7, 2) DEFAULT NULL COMMENT 'Valor Final del insumo ',
  `fecha` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'fecha del registro',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro',
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de la reposición'
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Historial de cambios en inventario_movimientos. Registra modificaciones de material_consumido para auditoría y trazabilidad.';
CREATE TABLE `inventario_remanentes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_insumo` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `motivo` varchar(255) NOT NULL DEFAULT 'Terminación',
  `observacion` text,
  `id_empleado` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`_id`),
  KEY `id_insumo` (`id_insumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT = 'Historial de remanentes (retazos/sobrantes) de insumos al ser terminados.';

CREATE TABLE `gastos` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'USD',
  `periodicidad` enum('mensual','trimestral','semestral','anual','único') NOT NULL DEFAULT 'mensual',
  `tipo` enum('fijo','variable') NOT NULL DEFAULT 'fijo',
  `estatus` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Plantillas de gastos recurrentes (fijos y variables)';

CREATE TABLE `gastos_registros` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_gasto_plantilla` int(11) DEFAULT NULL,
  `tipo` enum('fijo','variable','adicional') NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `moneda` varchar(10) NOT NULL DEFAULT 'USD',
  `fecha_de_gasto` date NOT NULL,
  `periodo` varchar(7) DEFAULT NULL COMMENT 'Formato YYYY-MM',
  `id_usuario` int(11) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `idx_fecha` (`fecha_de_gasto`),
  KEY `idx_periodo` (`periodo`),
  CONSTRAINT `fk_gastos_registros_plantilla` FOREIGN KEY (`id_gasto_plantilla`) REFERENCES `gastos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Registro de pagos realizados de todos los tipos de gastos';

CREATE TABLE `gastos_auditoria` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_registro` int(11) NOT NULL,
  `accion` enum('editado','eliminado') NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_usuario` varchar(255) NOT NULL,
  `monto_anterior` decimal(12,2) DEFAULT NULL,
  `monto_nuevo` decimal(12,2) DEFAULT NULL,
  `detalle` text NOT NULL,
  `fecha_accion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Auditoría de cambios en los registros de gastos';
CREATE TABLE `inventario_corte` (
  `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID único del registro',
  `id_orden` int(11) NOT NULL COMMENT 'ID de la orden',
  `id_ordenes_productos` int(11) NOT NULL COMMENT 'ID del producto en ordenes_productos',
  `id_empleado_corte` int(11) DEFAULT NULL COMMENT 'ID del empleado que realizó el corte',
  `cantidad` decimal(8, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad de piezas cortadas físicamente',
  `talla` varchar(10) DEFAULT NULL COMMENT 'Talla de la pieza cortada',
  `tela` varchar(128) DEFAULT NULL COMMENT 'Tela de la pieza cortada',
  `corte` varchar(32) DEFAULT NULL COMMENT 'Tipo de corte',
  `fecha_corte` timestamp NULL DEFAULT NULL COMMENT 'Fecha y hora real del corte',
  `estado` enum('por_cortar', 'cortada', 'disponible', 'procesado') NOT NULL DEFAULT 'por_cortar' COMMENT 'Estado de las piezas (por_cortar: asignada/ajustada, cortada: piezas listas, disponible: en inventario, procesado: usada)',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro',
  PRIMARY KEY (`_id`),
  KEY `id_orden` (`id_orden`),
  KEY `estado` (`estado`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Inventario de piezas cortadas listas para el siguiente departamento.';
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
CREATE TABLE `lotes_corte_ajustes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID único del registro',
  `id_orden` int(11) NOT NULL COMMENT 'ID de la orden',
  `id_lote` int(11) DEFAULT NULL COMMENT 'ID del lote asociado (opcional)',
  `id_ordenes_productos` int(11) NOT NULL COMMENT 'ID del producto en ordenes_productos',
  `cantidad_solicitada` decimal(8, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad original solicitada en la orden',
  `cantidad_ajustada` decimal(8, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad nueva definida por producción',
  `id_empleado_ajuste` int(11) DEFAULT NULL COMMENT 'ID del empleado que realizó el ajuste',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha del ajuste',
  PRIMARY KEY (`_id`),
  KEY `id_orden` (`id_orden`),
  KEY `id_ordenes_productos` (`id_ordenes_productos`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registra los ajustes de cantidad a cortar definidos por producción.';
CREATE TABLE `lotes_detalles` (
  `_id` int(10) NOT NULL COMMENT 'ID único del registro',
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden de trabajo',
  `id_woo` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto en Woocommerce',
  `progreso` varchar(11) NOT NULL DEFAULT 'por iniciar' COMMENT 'Nos indica el estado de desarrollo de la tarea: por niciar, en curso, terminada',
  `id_ordenes_productos` int(11) NOT NULL DEFAULT 0 COMMENT 'ID del producto ordenes_productos',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'id del empleado responsable de la producción',
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de en caso de ser una reposción',
  `terminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si la tarea se ha terminado para la lista de verificación en el módulo de empleados',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `departamento` varchar(256) DEFAULT NULL COMMENT 'Departamento al cual pertenecen las unidades, se guarda como histórico del registro en caso que el nombre del departamento sea editado posteriormente',
  `unidades_solicitadas` decimal(10,1) DEFAULT 0 COMMENT 'Unidades para el calculo de pago (admite decimales: productos por metro)',
  `comision` decimal(8, 2) DEFAULT 0.00 COMMENT 'Porcentaje para el cálculo de la comisión',
  `detalles` varchar(255) DEFAULT NULL COMMENT 'Información adicional del producto',
  `fecha_inicio` timestamp NULL DEFAULT NULL COMMENT 'Momento en que el primer empleado asignado ha iniciado el trabajo	',
  `fecha_terminado` timestamp NULL DEFAULT NULL COMMENT 'Momento en que el último empleado afirma haber terminado el trabajo',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Controla el proceso de fabciacion x producto y empleado';
CREATE TABLE `lotes_detalles_empleados_asignados` (
  `_id` int(11) NOT NULL,
  `id_lotes_detalles` int(11) DEFAULT NULL COMMENT 'ID de lotes_detalles',
  `id_reposicion` int(11) DEFAULT NULL COMMENT 'ID de la reposición',
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
CREATE TABLE `lotes_detalles_empleados_productos` (
  `_id` int(11) NOT NULL,
  `id_lotes_detalles_empleados_asignados` int(11) NOT NULL COMMENT 'FK a lotes_detalles_empleados_asignados',
  `id_ordenes_productos` int(11) NOT NULL COMMENT 'FK a ordenes_productos: línea de producto asignada',
  `cantidad_asignada` decimal(6,1) NOT NULL COMMENT 'Unidades de esa línea asignadas a este empleado (admite decimales: productos por metro)',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Asignación granular de productos/cantidades por empleado dentro de una tarea de lote';
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
CREATE TABLE `metodos_de_pago` (
  `_id` int(11) NOT NULL COMMENT 'ID unico de la tabla',
  `id_orden` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la orden',
  `id_caja_cierres` int(11) DEFAULT NULL COMMENT 'ID del cierre de caja, nos indica si el pago ya ha sido retirado ',
  `moneda` varchar(10) DEFAULT NULL COMMENT 'tipo de moneda',
  `metodo_pago` varchar(20) DEFAULT NULL COMMENT 'Método de pago',
  `detalle` varchar(140) DEFAULT NULL COMMENT 'Detalle en caso de que el tipo de pago sea abonos u otros',
  `tipo_de_pago` varchar(13) NOT NULL DEFAULT 'Orden nueva' COMMENT 'Procedencia del pago para identificar el tipo de ingreso',
  `monto` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Monto cancelado en cada metodo de pago',
  `tasa` decimal(12, 2) DEFAULT NULL COMMENT 'Tasa de conversion con relacion al dolar',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registro de transacciones de pago asociadas a órdenes. Almacena método, moneda, monto, tasa de conversión y referencia al cierre de caja.';
CREATE TABLE `ordenes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_wp` int(10) UNSIGNED DEFAULT NULL,
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
  `pago_nota_credito` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total acumulado de notas de crédito',
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
  `id_woo` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto en woocommerce',
  `id_tela` int(11) DEFAULT NULL COMMENT 'ID de la tela a utilizar del catálogo de telas',
  `id_category` int(11) DEFAULT NULL COMMENT 'ID de la categoria en WooCommerce',
  `id_products_attributes` int(11) DEFAULT NULL COMMENT 'ID de la variante del producto',
  `category_name` varchar(20) DEFAULT NULL COMMENT 'NOMBRE de la categoria en woocommerce',
  `name` varchar(240) DEFAULT NULL COMMENT 'Nombre del producto',
  `cantidad` DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT 'Cantidad del producto',
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `talla` varchar(32) DEFAULT NULL COMMENT 'Talla del producto',
  `corte` varchar(32) DEFAULT NULL COMMENT 'Dama, caballero, niño',
  `metros` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Metros de material utilizado',
  `desperdicio` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Restos del material',
  `rollo` int(11) DEFAULT NULL COMMENT 'ID de el catálogo de telas',
  `tela` varchar(128) DEFAULT NULL COMMENT 'Tela principal seleccionada desde Comercialización',
  `precio_unitario` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del producto',
  `precio_woo` decimal(10, 2) DEFAULT NULL COMMENT 'Precio de Woocommerce',
  `multiplicador_porcentaje` decimal(5, 2) DEFAULT NULL COMMENT 'Porcentaje del multiplicador de empresa aplicado a este ítem al momento de la venta; NULL = no aplicado',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Productos incluidos en cada orden. Detalla cada ítem con cantidad, talla, corte, tela, precio unitario y categoría.';
CREATE TABLE `ordenes_auditoria` (
  `_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único del registro',
  `id_orden` INT NOT NULL COMMENT 'ID de la orden',
  `accion` ENUM('cancelada', 'terminada', 'reactivada', 'reversión de entrega') NOT NULL COMMENT 'Tipo de acción manual',
  `id_admin` INT NOT NULL COMMENT 'ID del administrador',
  `nombre_admin` VARCHAR(255) NOT NULL COMMENT 'Nombre del administrador',
  `motivo` TEXT NOT NULL COMMENT 'Motivo detallado',
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de la acción',
  INDEX `idx_orden` (`id_orden`),
  INDEX `idx_admin` (`id_admin`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Auditoría de cancelaciones y terminaciones manuales. Registra administrador y motivo cuando se interrumpe el proceso normal de producción.';
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
  `status` varchar(45) DEFAULT NULL COMMENT 'Status de la orden: activa, pausada, cancelada, terminada, entregada',
  `tipo` varchar(6) NOT NULL DEFAULT 'custom' COMMENT 'Identificar si la orden pertence a custom o a sport',
  `responsable` int(11) DEFAULT NULL COMMENT 'ID del Vendedor',
  `cliente_nombre` varchar(40) DEFAULT NULL COMMENT 'Nombre del cliente',
  `cliente_cedula` varchar(45) DEFAULT NULL COMMENT 'Cedula del cliente',
  `cliente_direccion` varchar(250) DEFAULT NULL COMMENT 'Dirección del cliente al momento de cotizar (independiente de la dirección actual en customers)',
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
  `id_woo` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto en woocommerce',
  `id_category` int(11) DEFAULT NULL COMMENT 'ID de la categoria en WooCommerce',
  `category_name` varchar(20) DEFAULT NULL COMMENT 'NOMBRE de la categoria en woocommerce',
  `name` varchar(240) DEFAULT NULL COMMENT 'Nombre del producto',
  `cantidad` DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT 'Cantidad del producto (admite decimales -- ej. metros de impresión/sublimación -- igual precisión que ordenes_productos.cantidad)',
  `talla` varchar(32) DEFAULT NULL COMMENT 'Talla del producto',
  `corte` varchar(32) DEFAULT NULL COMMENT 'Dama, caballero, niño',
  `tela` varchar(128) DEFAULT NULL COMMENT 'Tela principal seleccionada desde Comercialización',
  `precio_unitario` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del producto',
  `precio_woo` decimal(10, 0) DEFAULT NULL COMMENT 'Precio de Woocommerce',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_products_attributes` int(11) DEFAULT NULL COMMENT 'ID de la variante del producto',
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `id_tela` int(11) DEFAULT NULL COMMENT 'ID de la tela a utilizar del catálogo de telas',
  `multiplicador_porcentaje` decimal(5, 2) DEFAULT NULL COMMENT 'Reservado por consistencia de esquema con ordenes_productos; no usado en presupuestos'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci;
CREATE TABLE `products` (
  `_id` bigint(20) UNSIGNED NOT NULL,
  `product` text DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `fisico` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indica true si es un [rpducto virtual como diseños, patronajes o indica si es un producto fisico, si es falso indica un producto virtual o digital',
  `es_diseno` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el producto pretenece al departamento de diseño',
  `requiere_talla_corte_tela` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Si es 0, el producto es físico pero no se confecciona como prenda (ej. impresión DTF standalone) -- no exige talla/corte/tela al crear una orden.',
  `es_servicio_de_impresion` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Producto ofrecido como servicio de impresión/sublimación por metraje (ej. catálogo de 19print) -- independiente de requiere_talla_corte_tela, hay productos que no requieren talla/corte/tela pero tampoco son servicios de impresión.',
  `price` decimal(20, 2) DEFAULT NULL,
  `comision` decimal(7, 2) DEFAULT 0.00 COMMENT 'Monto para el calculo de comisión variable',
  `stock_quantity` int(11) DEFAULT 0 COMMENT 'Existencia en inventario\r\n',
  `product_description` text DEFAULT NULL COMMENT 'Descripción para mostrar e el sistema y la teienda',
  `category_ids` varchar(255) DEFAULT NULL,
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto',
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
  ),
  (
    3,
    'Franela Sublimada',
    'FRA_SUB_001',
    1,
    0,
    NULL,
    0.00,
    0,
    NULL,
    '1',
    CURRENT_TIMESTAMP
  );
CREATE TABLE `products_attributes` (
  `_id` int(11) NOT NULL,
  `attribute_name` varchar(255) NOT NULL COMMENT 'Nombre del atributo',
  `precio` decimal(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del atributo',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de atributos para productos';
INSERT INTO `products_attributes` (`_id`, `attribute_name`, `precio`)
VALUES (1, 'Atributo de pruebas', 5.00);
CREATE TABLE `products_attributes_values` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL COMMENT 'ID de la orden',
  `id_product` bigint(20) UNSIGNED NOT NULL COMMENT 'id del producto',
  `id_product_attribute` int(11) NOT NULL COMMENT 'id del atributo del producto',
  `attribute_value` varchar(128) NOT NULL COMMENT 'Descripción del atributo del producto',
  `attribute_price` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del atributo'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Atributos asignados a los productos';
CREATE TABLE `products_comisiones` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_product` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto',
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
(6, 1, 7, 0.50),
(7, 3, 1, 0.60),
(8, 3, 2, 0.50),
(9, 3, 3, 0.25),
(10, 3, 4, 0.30);
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
 `id_product` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto',
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
(3, 2, 15.00, 'Unitario'),
(4, 3, 20.00, 'Detal');
CREATE TABLE `products_sizes_eficiencia` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_size` int(11) DEFAULT NULL COMMENT 'ID de la talla',
  `id_catalogo_insumos_prodcutos` int(11) DEFAULT NULL COMMENT 'ID de la tabla catalogo_insumos_productos',
  `cantidad` decimal(3, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad de insumo',
  `unidad` varchar(64) DEFAULT NULL COMMENT 'Unidad de medida del insumo'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Eficiencia de insumos por talla. Define la cantidad de material requerido para cada talla de producto.';
CREATE TABLE `products_tiempos_de_produccion` (
  `_id` int(11) NOT NULL COMMENT 'ID único',
  `id_product` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto',
  `id_departamento` int(11) DEFAULT NULL COMMENT 'ID del departamento',
  `tiempo` int(11) NOT NULL DEFAULT 1 COMMENT 'Tiempo de producción en segundos',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro',
  `usa_desperdicio` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el producto solicita desperdicio en este departamento'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Tiempos estándar de producción. Define minutos estimados por producto y departamento para proyección de entregas.';

INSERT INTO `products_tiempos_de_produccion` (`_id`, `id_product`, `id_departamento`, `tiempo`, `moment`) VALUES
(1, 1, 1, 60, CURRENT_TIMESTAMP),
(2, 1, 2, 60, CURRENT_TIMESTAMP),
(3, 1, 3, 60, CURRENT_TIMESTAMP),
(4, 1, 4, 60, CURRENT_TIMESTAMP),
(5, 3, 3, 60, CURRENT_TIMESTAMP),
(6, 3, 1, 60, CURRENT_TIMESTAMP),
(7, 3, 2, 60, CURRENT_TIMESTAMP),
(8, 3, 4, 60, CURRENT_TIMESTAMP);
CREATE TABLE `product_insumos_asignados` (
  `_id` int(11) NOT NULL,
  `id_product` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto',
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
(16, 1, 3, 4, 4, 6.00, 'Und'),
-- Franela Sublimada: Impresión — Papel para sublimación (cat 1)
(17, 3, 1, 1, 1, 1.00, 'Mt'),
(18, 3, 1, 1, 2, 1.00, 'Mt'),
(19, 3, 1, 1, 3, 1.00, 'Mt'),
(20, 3, 1, 1, 4, 1.00, 'Mt'),
-- Franela Sublimada: Estampado — Tela (cat 6)
(21, 3, 6, 2, 1, 1.00, 'Mt'),
(22, 3, 6, 2, 2, 1.00, 'Mt'),
(23, 3, 6, 2, 3, 1.00, 'Mt'),
(24, 3, 6, 2, 4, 1.00, 'Mt');
CREATE TABLE `rendimiento` (
  `_id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_insumo` int(11) DEFAULT NULL COMMENT 'Numero de rollo',
  `cantidad` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad de material utilizado',
  `desperdicio` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'peso en gramos del material sobrante',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_empleado` int(11) DEFAULT NULL,
  `id_departamento` int(11) DEFAULT NULL
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
  `eliminada` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si la reposición ha sido eliminada de forma lógica',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'moment'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Control de reposiciones durante el proceso de fabricacion';
CREATE TABLE `reposiciones_departamentos_excluidos` (
  `_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único',
  `id_reposicion` INT NOT NULL COMMENT 'ID de la reposición',
  `id_departamento` INT NOT NULL COMMENT 'ID del departamento excluido de la cola',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
  UNIQUE KEY `uk_repo_depto` (`id_reposicion`, `id_departamento`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Departamentos excluidos de la cola de una reposición. Permite al supervisor omitir pasos intermedios en el encadenamiento automático de departamentos.';
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
  `id_product` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del producto asociado a la revisión',
  `tipo` varchar(128) DEFAULT NULL COMMENT 'Tipo de diseño asociado a la revisión',
  `revision` int(11) NOT NULL DEFAULT 0 COMMENT 'Numero de revisiones máximo dos',
  `estatus` varchar(19) NOT NULL DEFAULT 'Esperando Respuesta' COMMENT 'Los estados son ''Esperando Respuesta'', ''Rechazado'', ''Aprobado''',
  `url_image` varchar(255) DEFAULT NULL COMMENT 'URL de la imagen de la revisión',
  `detalles` text DEFAULT NULL COMMENT 'Detalles de la revision',
  `moment` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Control de revisiones de diseño. Registra cada iteración de revisión solicitada al diseñador con límite máximo de dos.';
CREATE TABLE `sizes` (
  `_id` int(11) NOT NULL COMMENT 'ID único de la talla',
  `nombre` varchar(100) DEFAULT NULL COMMENT 'Nombre de la talla',
  `variation_percentage` decimal(5,2) DEFAULT 0.00 COMMENT 'Porcentaje de variación para cálculo de insumos',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borrado lógico: 0 = activo, 1 = eliminado/oculto'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Catálogo de tallas disponibles. Define las tallas manejadas por la empresa para asignación en productos y órdenes.';
INSERT INTO `sizes` (`_id`, `nombre`) VALUES
(1, 'S'),
(2, 'M'),
(3, 'L'),
(4, 'XL');
CREATE TABLE `tintas` (
  `_id` int(11) NOT NULL,
  `id_catalogo_impresoras` int(11) DEFAULT NULL COMMENT 'ID del catálogo de impresoras',
  `id_orden` int(11) DEFAULT NULL COMMENT 'Id de la Orden',
  `id_empleado` int(11) DEFAULT NULL COMMENT 'ID del empleado que imprimió',
  `id_color_tinta` int(11) NOT NULL COMMENT 'ID del color de la tinta consumida',
  `cantidad` decimal(7, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad consumida en ml/g',
  `es_estimado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si es 1, el valor fue calculado automáticamente (impresora en modo automático), no capturado a mano por el empleado.',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Registra el consumo de tintas por orden';
CREATE TABLE `tintas_calibracion_colores` (
  `_id` int(11) NOT NULL,
  `id_catalogo_impresora` int(11) NOT NULL,
  `id_color_tinta` int(11) NOT NULL,
  `ml_por_metro` decimal(6, 2) NOT NULL COMMENT 'Ratio calibrado automáticamente por color, a partir del consumo real medido en niveles de tanque entre 2 recargas y los metros reales impresos en ese mismo período.',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `uk_tintas_calib_impresora_color` (`id_catalogo_impresora`, `id_color_tinta`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Calibración automática de ml de tinta por metro de material, por impresora y color.';
CREATE TABLE `tintas_recargas` (
  `_id` int(11) NOT NULL,
  `id_insumo` int(11) DEFAULT NULL,
  `id_catalogo_impresora` int(11) DEFAULT NULL COMMENT 'ID catalodo de imoresoras',
  `id_color_tinta` int(11) DEFAULT NULL COMMENT 'ID del color de la tinta recargado',
  `cantidad` decimal(7, 2) DEFAULT NULL COMMENT 'Cantidad en ML',
  `nivel_tanque_previo` decimal(10, 2) DEFAULT NULL,
  `fecha_recarga` timestamp NULL DEFAULT NULL COMMENT 'Fecha de la recarga',
  `moment` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Recargas de tinta';
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
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_ordenes_productos` (`id_ordenes_productos`);
ALTER TABLE `config`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `crm_campanas`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `crm_campanas_envios`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_campana` (`id_campana`),
  ADD KEY `id_customer` (`id_customer`);
ALTER TABLE `crm_notas`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_customer` (`id_customer`),
  ADD KEY `id_oportunidad` (`id_oportunidad`);
ALTER TABLE `crm_oportunidades`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_customer` (`id_customer`),
  ADD KEY `id_campana` (`id_campana`);
ALTER TABLE `crm_oportunidades_vendedores`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_oportunidad` (`id_oportunidad`),
  ADD KEY `id_vendedor` (`id_vendedor`);
ALTER TABLE `crm_soporte`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_customer` (`id_customer`);
ALTER TABLE `customers`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `departamentos`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `disenos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_empleado` (`id_empleado`),
  ADD KEY `id_product` (`id_product`);
ALTER TABLE `disenos_ajustes_y_personalizaciones`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_diseno`);
ALTER TABLE `empleados_lotes_fabricacion`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `empleados_lotes_fabricacion_items`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `inventario`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_color_tinta` (`id_color_tinta`),
  ADD KEY `id_catalogo_tintas` (`id_catalogo_tintas`);
ALTER TABLE `inventario_movimientos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_insumo` (`id_insumo`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `idx_composite` (
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
  ADD KEY `id_orden` (`id_orden`, `id_ordenes_productos`),
  ADD KEY `id_woo` (`id_woo`);
ALTER TABLE `lotes_detalles_empleados_asignados`
ADD PRIMARY KEY (`_id`),
  ADD KEY `idx_id_empleado` (`id_empleado`),
  ADD KEY `idx_id_lotes_detalles` (`id_lotes_detalles`),
  ADD KEY `idx_id_orden` (`id_orden`),
  ADD KEY `idx_id_departamento` (`id_departamento`),
  ADD KEY `idx_empleado_orden_depto` (`id_empleado`, `id_orden`, `id_departamento`);
ALTER TABLE `lotes_detalles_empleados_productos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `idx_id_ldea` (`id_lotes_detalles_empleados_asignados`),
  ADD KEY `idx_id_ordenes_productos` (`id_ordenes_productos`);
ALTER TABLE `lotes_detalles_empleados_asignados_pausas`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `lotes_fisicos`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`);
ALTER TABLE `lotes_historico_solicitadas`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`, `id_lotes_fisicos`);
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
  ADD KEY `id_catalogo_telas` (`rollo`),
  ADD KEY `id_woo` (`id_woo`);
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
  ADD KEY `id_empleado` (`id_empleado`),
  ADD KEY `idx_empleado_moment` (`id_empleado`, `moment`),
  ADD KEY `idx_fecha_pago` (`fecha_pago`),
  ADD KEY `idx_comision_tipo_fecha` (`comision_tipo`, `fecha_pago`);
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
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_tela` (`id_tela`);
ALTER TABLE `products`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_attributes`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_attributes_values`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_product` (`id_product`);
ALTER TABLE `products_comisiones`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_product` (`id_product`);
ALTER TABLE `products_prices`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_product` (`id_product`);
ALTER TABLE `products_sizes_eficiencia`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `products_tiempos_de_produccion`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `product_insumos_asignados`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_product` (`id_product`);
ALTER TABLE `rendimiento`
ADD PRIMARY KEY (`_id`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_departamento` (`id_departamento`);
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
  ADD KEY `id_orden_2` (`id_orden`, `id_diseno`),
  ADD KEY `id_product` (`id_product`);
ALTER TABLE `sizes`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `catalogo_colores_tintas`
ADD PRIMARY KEY (`_id`),
  ADD UNIQUE KEY `uk_codigo_color` (`codigo`);
ALTER TABLE `impresoras_colores`
ADD PRIMARY KEY (`id_catalogo_impresora`, `id_color_tinta`),
  ADD KEY `id_color_tinta` (`id_color_tinta`);
ALTER TABLE `tintas`
ADD PRIMARY KEY (`_id`),
  ADD KEY `idx_tintas_color` (`id_color_tinta`),
  ADD KEY `idx_tintas_impresora` (`id_catalogo_impresoras`);
ALTER TABLE `tintas_calibracion_colores`
ADD PRIMARY KEY (`_id`);
ALTER TABLE `tintas_recargas`
ADD PRIMARY KEY (`_id`),
  ADD KEY `idx_recargas_color` (`id_color_tinta`),
  ADD KEY `id_insumo` (`id_insumo`),
  ADD KEY `id_catalogo_impresora` (`id_catalogo_impresora`);
ALTER TABLE `catalogo_tintas`
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
ALTER TABLE `crm_campanas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `crm_campanas_envios`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `crm_notas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `crm_oportunidades`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `crm_oportunidades_vendedores`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `crm_soporte`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
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
ALTER TABLE `lotes_detalles_empleados_productos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lotes_fisicos`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lotes_historico_solicitadas`
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
ALTER TABLE `catalogo_colores_tintas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID único del color de tinta',
  AUTO_INCREMENT = 13;
ALTER TABLE `tintas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tintas_calibracion_colores`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tintas_recargas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `catalogo_tintas`
MODIFY `_id` int(11) NOT NULL AUTO_INCREMENT;
-- =====================================================
-- FOREIGN KEYS - 123 FKs
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
ADD CONSTRAINT `check_tar_ibfk_3` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `check_tar_ibfk_4` FOREIGN KEY (`id_ordenes_productos`) REFERENCES `ordenes_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- crm_campanas_envios
ALTER TABLE `crm_campanas_envios`
  ADD CONSTRAINT `crm_camp_env_ibfk_1` FOREIGN KEY (`id_campana`) REFERENCES `crm_campanas` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `crm_camp_env_ibfk_2` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- crm_notas
ALTER TABLE `crm_notas`
  ADD CONSTRAINT `crm_notas_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `crm_notas_ibfk_2` FOREIGN KEY (`id_oportunidad`) REFERENCES `crm_oportunidades` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- crm_oportunidades
ALTER TABLE `crm_oportunidades`
  ADD CONSTRAINT `crm_oport_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `crm_oport_ibfk_2` FOREIGN KEY (`id_campana`) REFERENCES `crm_campanas` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- crm_oportunidades_vendedores
ALTER TABLE `crm_oportunidades_vendedores`
  ADD CONSTRAINT `crm_oport_vend_ibfk_1` FOREIGN KEY (`id_oportunidad`) REFERENCES `crm_oportunidades` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- crm_soporte
ALTER TABLE `crm_soporte`
  ADD CONSTRAINT `crm_soporte_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- disenos
ALTER TABLE `disenos`
ADD CONSTRAINT `disenos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `disenos_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- disenos_ajustes_y_personalizaciones
ALTER TABLE `disenos_ajustes_y_personalizaciones`
ADD CONSTRAINT `dis_ajust_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `dis_ajust_ibfk_2` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- empleados_lotes_fabricacion
ALTER TABLE `empleados_lotes_fabricacion`
ADD CONSTRAINT `emp_lotes_fab_ibfk_1` FOREIGN KEY (`id_departamento_creador`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `emp_lotes_fab_ibfk_2` FOREIGN KEY (`id_departamento_actual`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- empleados_lotes_fabricacion_items
ALTER TABLE `empleados_lotes_fabricacion_items`
ADD CONSTRAINT `emp_lotes_items_ibfk_1` FOREIGN KEY (`id_lote`) REFERENCES `empleados_lotes_fabricacion` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `emp_lotes_items_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- inventario_movimientos
ALTER TABLE `inventario_movimientos`
ADD CONSTRAINT `inv_mov_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `inv_mov_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `inventario` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `inv_mov_ibfk_3` FOREIGN KEY (`id_catalogo_insumos_prodcutos`) REFERENCES `catalogo_insumos_productos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `inv_mov_ibfk_4` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `inv_mov_ibfk_5` FOREIGN KEY (`id_producto`) REFERENCES `products` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- inventario_remanentes
ALTER TABLE `inventario_remanentes`
ADD CONSTRAINT `inv_rem_ibfk_1` FOREIGN KEY (`id_insumo`) REFERENCES `inventario` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- inventario_movimientos_historial
ALTER TABLE `inventario_movimientos_historial`
ADD CONSTRAINT `inv_mov_hist_ibfk_1` FOREIGN KEY (`id_movimiento`) REFERENCES `inventario_movimientos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- lotes
ALTER TABLE `lotes`
ADD CONSTRAINT `lotes_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_ibfk_2` FOREIGN KEY (`id_departamento_actual`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- lotes_detalles
ALTER TABLE `lotes_detalles`
ADD CONSTRAINT `lotes_det_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_det_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_det_ibfk_3` FOREIGN KEY (`id_ordenes_productos`) REFERENCES `ordenes_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_det_ibfk_4` FOREIGN KEY (`id_reposicion`) REFERENCES `reposiciones` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_det_ibfk_5` FOREIGN KEY (`id_woo`) REFERENCES `products` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- lotes_detalles_empleados_asignados
ALTER TABLE `lotes_detalles_empleados_asignados`
ADD CONSTRAINT `ldea_ibfk_1` FOREIGN KEY (`id_lotes_detalles`) REFERENCES `lotes_detalles` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ldea_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ldea_ibfk_3` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- lotes_detalles_empleados_asignados_pausas
ALTER TABLE `lotes_detalles_empleados_asignados_pausas`
ADD CONSTRAINT `ldea_pausas_ibfk_1` FOREIGN KEY (`id_lotes_detalles_empleados_asignados`) REFERENCES `lotes_detalles_empleados_asignados` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- lotes_detalles_empleados_productos
ALTER TABLE `lotes_detalles_empleados_productos`
ADD CONSTRAINT `ldep_ibfk_1` FOREIGN KEY (`id_lotes_detalles_empleados_asignados`) REFERENCES `lotes_detalles_empleados_asignados` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ldep_ibfk_2` FOREIGN KEY (`id_ordenes_productos`) REFERENCES `ordenes_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- lotes_fisicos
ALTER TABLE `lotes_fisicos`
ADD CONSTRAINT `lotes_fis_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- lotes_historico_solicitadas
ALTER TABLE `lotes_historico_solicitadas`
ADD CONSTRAINT `lotes_hist_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `lotes_hist_ibfk_2` FOREIGN KEY (`id_lotes_fisicos`) REFERENCES `lotes_fisicos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- metodos_de_pago
ALTER TABLE `metodos_de_pago`
ADD CONSTRAINT `met_pago_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `met_pago_ibfk_2` FOREIGN KEY (`id_caja_cierres`) REFERENCES `caja_cierres` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ordenes
ALTER TABLE `ordenes`
ADD CONSTRAINT `ordenes_ibfk_1` FOREIGN KEY (`id_wp`) REFERENCES `customers` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ordenes_auditoria
ALTER TABLE `ordenes_auditoria`
ADD CONSTRAINT `ord_audit_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ordenes_borrador_empleado
ALTER TABLE `ordenes_borrador_empleado`
ADD CONSTRAINT `ord_borr_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `ord_borr_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

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
ADD CONSTRAINT `ord_prod_ibfk_3` FOREIGN KEY (`id_size`) REFERENCES `sizes` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `ord_prod_ibfk_4` FOREIGN KEY (`id_woo`) REFERENCES `products` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `ord_prod_ibfk_5` FOREIGN KEY (`id_category`) REFERENCES `categories` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
ADD CONSTRAINT `pagos_ibfk_5` FOREIGN KEY (`id_lotes_detalles`) REFERENCES `lotes_detalles_empleados_asignados` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- pagos_abonos
ALTER TABLE `pagos_abonos`
ADD CONSTRAINT `pagos_ab_ibfk_1` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- pagos_descuentos
ALTER TABLE `pagos_descuentos`
ADD CONSTRAINT `pagos_desc_ibfk_1` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- pagos_salarios
ALTER TABLE `pagos_salarios`
  ADD KEY `id_pago` (`id_pago`),
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
ADD CONSTRAINT `presup_prod_ibfk_2` FOREIGN KEY (`id_tela`) REFERENCES `catalogo_telas` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- products_attributes_values
ALTER TABLE `products_attributes_values`
ADD CONSTRAINT `prod_attr_val_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_attr_val_ibfk_2` FOREIGN KEY (`id_product_attribute`) REFERENCES `products_attributes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_attr_val_ibfk_3` FOREIGN KEY (`id_product`) REFERENCES `products` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- products_comisiones
ALTER TABLE `products_comisiones`
ADD CONSTRAINT `prod_com_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_com_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- products_prices
ALTER TABLE `products_prices`
ADD CONSTRAINT `prod_prices_ibfk_1` FOREIGN KEY (`id_product`) REFERENCES `products` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- products_sizes_eficiencia
ALTER TABLE `products_sizes_eficiencia`
ADD CONSTRAINT `prod_size_ef_ibfk_1` FOREIGN KEY (`id_size`) REFERENCES `sizes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_size_ef_ibfk_2` FOREIGN KEY (`id_catalogo_insumos_prodcutos`) REFERENCES `catalogo_insumos_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- products_tiempos_de_produccion
ALTER TABLE `products_tiempos_de_produccion`
ADD CONSTRAINT `prod_tiempo_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_tiempo_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- product_insumos_asignados
ALTER TABLE `product_insumos_asignados`
ADD CONSTRAINT `prod_ins_asig_ibfk_1` FOREIGN KEY (`id_catalogo_insumos_productos`) REFERENCES `catalogo_insumos_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_ins_asig_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `prod_ins_asig_ibfk_3` FOREIGN KEY (`id_talla`) REFERENCES `sizes` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `prod_ins_asig_ibfk_4` FOREIGN KEY (`id_product`) REFERENCES `products` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- rendimiento
ALTER TABLE `rendimiento`
ADD CONSTRAINT `rendim_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `rendim_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `inventario` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `rendim_ibfk_3` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- reposiciones
ALTER TABLE `reposiciones`
ADD CONSTRAINT `repos_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `repos_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `repos_ibfk_3` FOREIGN KEY (`id_departamento_solicitante`) REFERENCES `departamentos` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `repos_ibfk_4` FOREIGN KEY (`id_ordenes_productos`) REFERENCES `ordenes_productos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- reposiciones_departamentos_excluidos
ALTER TABLE `reposiciones_departamentos_excluidos`
ADD CONSTRAINT `rde_ibfk_1` FOREIGN KEY (`id_reposicion`) REFERENCES `reposiciones` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `rde_ibfk_2` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- revisiones
ALTER TABLE `revisiones`
ADD CONSTRAINT `revisiones_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `revisiones_ibfk_2` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `revisiones_ibfk_3` FOREIGN KEY (`id_product`) REFERENCES `products` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- catalogo_impresoras
ALTER TABLE `catalogo_impresoras`
  ADD CONSTRAINT `fk_impresoras_cat_tintas` FOREIGN KEY (`id_catalogo_tintas`) REFERENCES `catalogo_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- impresoras_colores
ALTER TABLE `impresoras_colores`
  ADD CONSTRAINT `fk_imp_col_impresora` FOREIGN KEY (`id_catalogo_impresora`) REFERENCES `catalogo_impresoras` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_imp_col_color` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- inventario
ALTER TABLE `inventario`
  ADD CONSTRAINT `fk_inventario_color` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventario_catalogo_tintas` FOREIGN KEY (`id_catalogo_tintas`) REFERENCES `catalogo_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- tintas
ALTER TABLE `tintas`
  ADD CONSTRAINT `tintas_ibfk_1` FOREIGN KEY (`id_catalogo_impresoras`) REFERENCES `catalogo_impresoras` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tintas_ibfk_2` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tintas_color` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- tintas_calibracion_colores
ALTER TABLE `tintas_calibracion_colores`
  ADD CONSTRAINT `tintas_calib_ibfk_1` FOREIGN KEY (`id_catalogo_impresora`) REFERENCES `catalogo_impresoras` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tintas_calib_ibfk_2` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- tintas_recargas
ALTER TABLE `tintas_recargas`
  ADD CONSTRAINT `tintas_rec_ibfk_1` FOREIGN KEY (`id_insumo`) REFERENCES `inventario` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tintas_rec_ibfk_2` FOREIGN KEY (`id_catalogo_impresora`) REFERENCES `catalogo_impresoras` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recargas_color` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- =====================================================
-- INDICES ADICIONALES PARA OPTIMIZACION
-- =====================================================
ALTER TABLE `lotes_detalles_empleados_asignados`
  ADD INDEX `idx_orden_depto` (`id_orden`, `id_departamento`);

ALTER TABLE `ordenes`
  ADD INDEX `idx_status` (`status`),
  ADD KEY `id_wp` (`id_wp`);

ALTER TABLE `ordenes_fila_orden`
  ADD INDEX `idx_id_orden` (`id_orden`);

ALTER TABLE `ordenes_productos`
  ADD INDEX `idx_orden_woo` (`id_orden`, `id_woo`),
  ADD KEY `id_category` (`id_category`);

ALTER TABLE `products_tiempos_de_produccion`
  ADD INDEX `idx_prod_depto` (`id_product`, `id_departamento`);

-- =====================================================
-- CATÁLOGO GEOGRÁFICO (País / Estado / Ciudad)
-- Usado por los selects encadenados de dirección de clientes.
-- Sincronizado con: ninesys-api/app/routes/geografia.php
-- =====================================================
CREATE TABLE IF NOT EXISTS `catalogo_paises` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo_iso2` varchar(2) NOT NULL,
  `codigo_telefonico` varchar(10) DEFAULT NULL,
  `formato_postal` varchar(20) DEFAULT NULL,
  `codigo_postal_ejemplo` varchar(20) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  UNIQUE KEY `uk_codigo_iso2` (`codigo_iso2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Catálogo maestro de países (ISO 3166-1 alpha-2).';

INSERT INTO `catalogo_paises` (`_id`, `nombre`, `codigo_iso2`, `codigo_telefonico`, `formato_postal`, `codigo_postal_ejemplo`) VALUES
  (1, 'Afganistán', 'AF', '+93', 'NNNN', '1001'),
  (2, 'Albania', 'AL', '+355', 'NNNN', '1001'),
  (3, 'Alemania', 'DE', '+49', 'NNNNN', '10115'),
  (4, 'Andorra', 'AD', '+376', 'AANNNN', 'AD100'),
  (5, 'Angola', 'AO', '+244', NULL, NULL),
  (6, 'Antigua y Barbuda', 'AG', '+1-268', NULL, NULL),
  (7, 'Arabia Saudita', 'SA', '+966', 'NNNNN', '11564'),
  (8, 'Argelia', 'DZ', '+213', 'NNNNN', '16000'),
  (9, 'Argentina', 'AR', '+54', 'ANNNNAAA', 'C1002'),
  (10, 'Armenia', 'AM', '+374', 'NNNN', '0010'),
  (11, 'Australia', 'AU', '+61', 'NNNN', '2000'),
  (12, 'Austria', 'AT', '+43', 'NNNN', '1010'),
  (13, 'Azerbaiyán', 'AZ', '+994', 'AANNNNNN', 'AZ1000'),
  (14, 'Bahamas', 'BS', '+1-242', NULL, NULL),
  (15, 'Bahrein', 'BH', '+973', 'NNN o NNNN', '317'),
  (16, 'Bangladesh', 'BD', '+880', 'NNNN', '1000'),
  (17, 'Barbados', 'BB', '+1-246', 'AANNNNNN', 'BB11000'),
  (18, 'Belice', 'BZ', '+501', NULL, NULL),
  (19, 'Benín', 'BJ', '+229', NULL, NULL),
  (20, 'Bielorrusia', 'BY', '+375', 'NNNNNN', '220000'),
  (21, 'Bolivia', 'BO', '+591', NULL, NULL),
  (22, 'Bosnia y Herzegovina', 'BA', '+387', 'NNNNN', '71000'),
  (23, 'Botsuana', 'BW', '+267', NULL, NULL),
  (24, 'Brasil', 'BR', '+55', 'NNNNN-NNN', '01310-100'),
  (25, 'Brunéi', 'BN', '+673', 'AANNNN', 'BS8311'),
  (26, 'Bulgaria', 'BG', '+359', 'NNNN', '1000'),
  (27, 'Burkina Faso', 'BF', '+226', NULL, NULL),
  (28, 'Burundi', 'BI', '+257', NULL, NULL),
  (29, 'Bután', 'BT', '+975', 'NNNNN', '11001'),
  (30, 'Bélgica', 'BE', '+32', 'NNNN', '1000'),
  (31, 'Cabo Verde', 'CV', '+238', 'NNNN', '7600'),
  (32, 'Camboya', 'KH', '+855', 'NNNNN', '12000'),
  (33, 'Camerún', 'CM', '+237', NULL, NULL),
  (34, 'Canadá', 'CA', '+1', 'ANA NAN', 'K1A 0A6'),
  (35, 'Catar', 'QA', '+974', NULL, NULL),
  (36, 'Chad', 'TD', '+235', NULL, NULL),
  (37, 'Chile', 'CL', '+56', 'NNNNNNN', '8320000'),
  (38, 'China', 'CN', '+86', 'NNNNNN', '100000'),
  (39, 'Chipre', 'CY', '+357', 'NNNN', '1010'),
  (40, 'Colombia', 'CO', '+57', 'NNNNNN', '110111'),
  (41, 'Comoras', 'KM', '+269', NULL, NULL),
  (42, 'Congo (Rep. Dem.)', 'CD', '+243', NULL, NULL),
  (43, 'Congo (Rep.)', 'CG', '+242', NULL, NULL),
  (44, 'Corea del Norte', 'KP', '+850', NULL, NULL),
  (45, 'Corea del Sur', 'KR', '+82', 'NNNNN', '03000'),
  (46, 'Costa Rica', 'CR', '+506', 'NNNNN', '10101'),
  (47, 'Costa de Marfil', 'CI', '+225', NULL, NULL),
  (48, 'Croacia', 'HR', '+385', 'NNNNN', '10000'),
  (49, 'Cuba', 'CU', '+53', 'NNNNN', '10400'),
  (50, 'Dinamarca', 'DK', '+45', 'NNNN', '1000'),
  (51, 'Djibouti', 'DJ', '+253', NULL, NULL),
  (52, 'Dominica', 'DM', '+1-767', NULL, NULL),
  (53, 'Ecuador', 'EC', '+593', 'NNNNNN', '170150'),
  (54, 'Egipto', 'EG', '+20', 'NNNNN', '11511'),
  (55, 'El Salvador', 'SV', '+503', 'NNNNN', '01101'),
  (56, 'Emiratos Árabes Unidos', 'AE', '+971', NULL, NULL),
  (57, 'Eritrea', 'ER', '+291', NULL, NULL),
  (58, 'Eslovaquia', 'SK', '+421', 'NNN NN', '811 01'),
  (59, 'Eslovenia', 'SI', '+386', 'NNNN', '1000'),
  (60, 'España', 'ES', '+34', 'NNNNN', '28001'),
  (61, 'Estados Unidos', 'US', '+1', 'NNNNN', '10001'),
  (62, 'Estonia', 'EE', '+372', 'NNNNN', '10111'),
  (63, 'Etiopía', 'ET', '+251', 'NNNN', '1000'),
  (64, 'Filipinas', 'PH', '+63', 'NNNN', '1000'),
  (65, 'Finlandia', 'FI', '+358', 'NNNNN', '00100'),
  (66, 'Fiyi', 'FJ', '+679', NULL, NULL),
  (67, 'Francia', 'FR', '+33', 'NNNNN', '75001'),
  (68, 'Gabón', 'GA', '+241', NULL, NULL),
  (69, 'Gambia', 'GM', '+220', NULL, NULL),
  (70, 'Georgia', 'GE', '+995', 'NNNN', '0100'),
  (71, 'Ghana', 'GH', '+233', NULL, NULL),
  (72, 'Granada', 'GD', '+1-473', NULL, NULL),
  (73, 'Grecia', 'GR', '+30', 'NNN NN', '10431'),
  (74, 'Guatemala', 'GT', '+502', 'NNNNN', '01001'),
  (75, 'Guinea', 'GN', '+224', NULL, NULL),
  (76, 'Guinea Ecuatorial', 'GQ', '+240', NULL, NULL),
  (77, 'Guinea-Bisáu', 'GW', '+245', 'NNNN', '1000'),
  (78, 'Guyana', 'GY', '+592', NULL, NULL),
  (79, 'Haití', 'HT', '+509', 'AANNNN', 'HT6120'),
  (80, 'Honduras', 'HN', '+504', 'NNNNN', '11101'),
  (81, 'Hungría', 'HU', '+36', 'NNNN', '1011'),
  (82, 'India', 'IN', '+91', 'NNNNNN', '110001'),
  (83, 'Indonesia', 'ID', '+62', 'NNNNN', '10110'),
  (84, 'Irak', 'IQ', '+964', 'NNNNN', '10001'),
  (85, 'Irlanda', 'IE', '+353', 'ANN AAAA', 'D01 F5P2'),
  (86, 'Irán', 'IR', '+98', 'NNNNNNNNNN', '1111111111'),
  (87, 'Islandia', 'IS', '+354', 'NNN', '101'),
  (88, 'Islas Marshall', 'MH', '+692', 'NNNNN', '96960'),
  (89, 'Islas Salomón', 'SB', '+677', NULL, NULL),
  (90, 'Israel', 'IL', '+972', 'NNNNNNN', '9100000'),
  (91, 'Italia', 'IT', '+39', 'NNNNN', '00100'),
  (92, 'Jamaica', 'JM', '+1-876', 'AAAANNNN', 'JMAAW19'),
  (93, 'Japón', 'JP', '+81', 'NNN-NNNN', '100-0001'),
  (94, 'Jordania', 'JO', '+962', 'NNNNN', '11110'),
  (95, 'Kazajistán', 'KZ', '+7', 'NNNNNN', '010000'),
  (96, 'Kenia', 'KE', '+254', 'NNNNN', '00100'),
  (97, 'Kirguistán', 'KG', '+996', 'NNNNNN', '720001'),
  (98, 'Kiribati', 'KI', '+686', NULL, NULL),
  (99, 'Kuwait', 'KW', '+965', 'NNNNN', '13001'),
  (100, 'Laos', 'LA', '+856', 'NNNNN', '01000'),
  (101, 'Lesoto', 'LS', '+266', 'NNN', '100'),
  (102, 'Letonia', 'LV', '+371', 'AANNNN', 'LV-1010'),
  (103, 'Liberia', 'LR', '+231', 'NNNN', '1000'),
  (104, 'Libia', 'LY', '+218', NULL, NULL),
  (105, 'Liechtenstein', 'LI', '+423', 'NNNN', '9490'),
  (106, 'Lituania', 'LT', '+370', 'AANNNNNN', 'LT-01001'),
  (107, 'Luxemburgo', 'LU', '+352', 'ANNNN', 'L-1111'),
  (108, 'Líbano', 'LB', '+961', 'NNNN NNNN', '2038 3054'),
  (109, 'Macedonia del Norte', 'MK', '+389', 'NNNN', '1000'),
  (110, 'Madagascar', 'MG', '+261', 'NNN', '101'),
  (111, 'Malasia', 'MY', '+60', 'NNNNN', '50000'),
  (112, 'Malaui', 'MW', '+265', NULL, NULL),
  (113, 'Maldivas', 'MV', '+960', 'NNNNN', '20026'),
  (114, 'Malta', 'MT', '+356', 'AAA NNNN', 'VLT 1117'),
  (115, 'Malí', 'ML', '+223', NULL, NULL),
  (116, 'Marruecos', 'MA', '+212', 'NNNNN', '10000'),
  (117, 'Mauricio', 'MU', '+230', 'NNNNN', '42101'),
  (118, 'Mauritania', 'MR', '+222', NULL, NULL),
  (119, 'Micronesia', 'FM', '+691', 'NNNNN', '96941'),
  (120, 'Moldavia', 'MD', '+373', 'AANNNNNN', 'MD-2001'),
  (121, 'Mongolia', 'MN', '+976', 'NNNNN', '14200'),
  (122, 'Montenegro', 'ME', '+382', 'NNNNN', '81000'),
  (123, 'Mozambique', 'MZ', '+258', 'NNNN', '1100'),
  (124, 'Myanmar', 'MM', '+95', 'NNNNN', '11181'),
  (125, 'México', 'MX', '+52', 'NNNNN', '06600'),
  (126, 'Mónaco', 'MC', '+377', 'NNNNN', '98000'),
  (127, 'Namibia', 'NA', '+264', 'NNNN', '9000'),
  (128, 'Nauru', 'NR', '+674', NULL, NULL),
  (129, 'Nepal', 'NP', '+977', 'NNNNN', '44600'),
  (130, 'Nicaragua', 'NI', '+505', 'NNNNN', '11001'),
  (131, 'Nigeria', 'NG', '+234', 'NNNNNN', '100001'),
  (132, 'Noruega', 'NO', '+47', 'NNNN', '0010'),
  (133, 'Nueva Zelanda', 'NZ', '+64', 'NNNN', '6011'),
  (134, 'Níger', 'NE', '+227', 'NNNN', '8001'),
  (135, 'Omán', 'OM', '+968', 'NNN', '100'),
  (136, 'Pakistán', 'PK', '+92', 'NNNNN', '44000'),
  (137, 'Palaos', 'PW', '+680', 'NNNNN', '96940'),
  (138, 'Panamá', 'PA', '+507', 'NNNN', '0801'),
  (139, 'Papúa Nueva Guinea', 'PG', '+675', 'NNN', '111'),
  (140, 'Paraguay', 'PY', '+595', 'NNNN', '1001'),
  (141, 'Países Bajos', 'NL', '+31', 'NNNN AA', '1011 AB'),
  (142, 'Perú', 'PE', '+51', 'NNNNN', '15001'),
  (143, 'Polonia', 'PL', '+48', 'NN-NNN', '00-001'),
  (144, 'Portugal', 'PT', '+351', 'NNNN-NNN', '1000-001'),
  (145, 'Reino Unido', 'GB', '+44', 'AANN NAA', 'SW1A 1AA'),
  (146, 'República Centroafricana', 'CF', '+236', NULL, NULL),
  (147, 'República Checa', 'CZ', '+420', 'NNN NN', '110 00'),
  (148, 'República Dominicana', 'DO', '+1-809', 'NNNNN', '10101'),
  (149, 'Ruanda', 'RW', '+250', NULL, NULL),
  (150, 'Rumania', 'RO', '+40', 'NNNNNN', '010011'),
  (151, 'Rusia', 'RU', '+7', 'NNNNNN', '101000'),
  (152, 'Samoa', 'WS', '+685', NULL, NULL),
  (153, 'San Cristóbal y Nieves', 'KN', '+1-869', NULL, NULL),
  (154, 'San Marino', 'SM', '+378', 'NNNNN', '47890'),
  (155, 'San Vicente y las Granadinas', 'VC', '+1-784', 'AANNNN', 'VC0100'),
  (156, 'Santa Lucía', 'LC', '+1-758', 'AANN NNN', 'LC01 101'),
  (157, 'Santo Tomé y Príncipe', 'ST', '+239', NULL, NULL),
  (158, 'Senegal', 'SN', '+221', 'NNNNN', '10700'),
  (159, 'Serbia', 'RS', '+381', 'NNNNN', '11000'),
  (160, 'Seychelles', 'SC', '+248', NULL, NULL),
  (161, 'Sierra Leona', 'SL', '+232', NULL, NULL),
  (162, 'Singapur', 'SG', '+65', 'NNNNNN', '018956'),
  (163, 'Siria', 'SY', '+963', NULL, NULL),
  (164, 'Somalia', 'SO', '+252', 'AA NNNNN', 'JH 09010'),
  (165, 'Sri Lanka', 'LK', '+94', 'NNNNN', '10350'),
  (166, 'Suazilandia', 'SZ', '+268', 'ANNN', 'H100'),
  (167, 'Sudáfrica', 'ZA', '+27', 'NNNN', '0001'),
  (168, 'Sudán', 'SD', '+249', 'NNNNN', '11111'),
  (169, 'Sudán del Sur', 'SS', '+211', NULL, NULL),
  (170, 'Suecia', 'SE', '+46', 'NNN NN', '113 51'),
  (171, 'Suiza', 'CH', '+41', 'NNNN', '8001'),
  (172, 'Surinam', 'SR', '+597', NULL, NULL),
  (173, 'Tailandia', 'TH', '+66', 'NNNNN', '10200'),
  (174, 'Tanzania', 'TZ', '+255', NULL, NULL),
  (175, 'Tayikistán', 'TJ', '+992', 'NNNNNN', '734000'),
  (176, 'Timor Oriental', 'TL', '+670', NULL, NULL),
  (177, 'Togo', 'TG', '+228', NULL, NULL),
  (178, 'Tonga', 'TO', '+676', NULL, NULL),
  (179, 'Trinidad y Tobago', 'TT', '+1-868', 'NNNNNN', '100100'),
  (180, 'Turkmenistán', 'TM', '+993', 'NNNNNN', '744000'),
  (181, 'Turquía', 'TR', '+90', 'NNNNN', '06010'),
  (182, 'Tuvalu', 'TV', '+688', NULL, NULL),
  (183, 'Túnez', 'TN', '+216', 'NNNN', '1000'),
  (184, 'Ucrania', 'UA', '+380', 'NNNNN', '01001'),
  (185, 'Uganda', 'UG', '+256', NULL, NULL),
  (186, 'Uruguay', 'UY', '+598', 'NNNNN', '11000'),
  (187, 'Uzbekistán', 'UZ', '+998', 'NNNNNN', '100000'),
  (188, 'Vanuatu', 'VU', '+678', NULL, NULL),
  (189, 'Venezuela', 'VE', '+58', 'NNNN', '1010'),
  (190, 'Vietnam', 'VN', '+84', 'NNNNNN', '100000'),
  (191, 'Yemen', 'YE', '+967', NULL, NULL),
  (192, 'Zambia', 'ZM', '+260', 'NNNNN', '10101'),
  (193, 'Zimbabue', 'ZW', '+263', NULL, NULL);

CREATE TABLE IF NOT EXISTS `catalogo_estados` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pais` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  UNIQUE KEY `uk_pais_estado` (`id_pais`, `nombre`),
  CONSTRAINT `fk_estado_pais` FOREIGN KEY (`id_pais`) REFERENCES `catalogo_paises` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Estados/provincias por país (24 de Venezuela precargados).';

INSERT INTO `catalogo_estados` (`_id`, `id_pais`, `nombre`) VALUES
  (1, 189, 'Amazonas'),
  (2, 189, 'Anzoátegui'),
  (3, 189, 'Apure'),
  (4, 189, 'Aragua'),
  (5, 189, 'Barinas'),
  (6, 189, 'Bolívar'),
  (7, 189, 'Carabobo'),
  (8, 189, 'Cojedes'),
  (9, 189, 'Delta Amacuro'),
  (10, 189, 'Distrito Capital'),
  (11, 189, 'Falcón'),
  (12, 189, 'Guárico'),
  (13, 189, 'Lara'),
  (14, 189, 'Miranda'),
  (15, 189, 'Monagas'),
  (16, 189, 'Mérida'),
  (17, 189, 'Nueva Esparta'),
  (18, 189, 'Portuguesa'),
  (19, 189, 'Sucre'),
  (20, 189, 'Trujillo'),
  (21, 189, 'Táchira'),
  (22, 189, 'Vargas'),
  (23, 189, 'Yaracuy'),
  (24, 189, 'Zulia');

CREATE TABLE IF NOT EXISTS `catalogo_ciudades` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_estado` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `idx_ciudad_estado` (`id_estado`),
  UNIQUE KEY `uk_estado_ciudad` (`id_estado`, `nombre`),
  CONSTRAINT `fk_ciudad_estado` FOREIGN KEY (`id_estado`) REFERENCES `catalogo_estados` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Ciudades/municipios por estado (274 de Venezuela precargadas).';

INSERT INTO `catalogo_ciudades` (`_id`, `id_estado`, `nombre`, `codigo_postal`) VALUES
  (1, 1, 'Isla Ratón', '7281'),
  (2, 1, 'La Esmeralda', '7271'),
  (3, 1, 'Maroa', '7251'),
  (4, 1, 'Puerto Ayacucho', '7101'),
  (5, 1, 'San Carlos de Río Negro', '7261'),
  (6, 1, 'San Fernando de Atabapo', '7201'),
  (7, 1, 'San Juan de Manapiare', '7211'),
  (8, 2, 'Anaco', '6303'),
  (9, 2, 'Aragua de Barcelona', '6021'),
  (10, 2, 'Barcelona', '6001'),
  (11, 2, 'Boca de Uchire', '6016'),
  (12, 2, 'Cantaura', '6060'),
  (13, 2, 'Clarines', '6013'),
  (14, 2, 'El Chaparro', '6017'),
  (15, 2, 'El Tigre', '6030'),
  (16, 2, 'El Tigrito', '6031'),
  (17, 2, 'Guanta', '6022'),
  (18, 2, 'Lecherías', '6023'),
  (19, 2, 'Onoto', '6040'),
  (20, 2, 'Puerto La Cruz', '6023'),
  (21, 2, 'Puerto Píritu', '6012'),
  (22, 2, 'Píritu', '6011'),
  (23, 2, 'San José de Guanipa', '6050'),
  (24, 2, 'San Mateo', '6070'),
  (25, 2, 'Santa Ana', '6080'),
  (26, 2, 'Soledad', '6302'),
  (27, 2, 'Valle de Guanape', '6041'),
  (28, 3, 'Achaguas', '7101'),
  (29, 3, 'Biruaca', '7002'),
  (30, 3, 'Bruzual', '7401'),
  (31, 3, 'El Amparo', '7202'),
  (32, 3, 'Elorza', '7211'),
  (33, 3, 'Guasdualito', '7201'),
  (34, 3, 'Puerto Páez', '7301'),
  (35, 3, 'San Fernando de Apure', '7001'),
  (36, 4, 'Cagua', '2122'),
  (37, 4, 'Camatagua', '2302'),
  (38, 4, 'Colonia Tovar', '2113'),
  (39, 4, 'El Consejo', '2205'),
  (40, 4, 'El Limón', '2107'),
  (41, 4, 'La Victoria', '2201'),
  (42, 4, 'Las Tejerías', '2204'),
  (43, 4, 'Magdaleno', '2312'),
  (44, 4, 'Maracay', '2101'),
  (45, 4, 'Ocumare de la Costa', '2401'),
  (46, 4, 'Palo Negro', '2103'),
  (47, 4, 'San Casimiro', '2303'),
  (48, 4, 'San Mateo', '2155'),
  (49, 4, 'Santa Cruz de Aragua', '2120'),
  (50, 4, 'Turmero', '2115'),
  (51, 4, 'Villa de Cura', '2301'),
  (52, 5, 'Arismendi', '5221'),
  (53, 5, 'Barinas', '5201'),
  (54, 5, 'Barinitas', '5211'),
  (55, 5, 'Ciudad Bolivia', '5251'),
  (56, 5, 'Nutrias', '5271'),
  (57, 5, 'Obispos', '5213'),
  (58, 5, 'Pueblo Nuevo del Sur', '5281'),
  (59, 5, 'Sabaneta', '5261'),
  (60, 5, 'Santa Bárbara', '5241'),
  (61, 5, 'Socopó', '5231'),
  (62, 6, 'Caicara del Orinoco', '8201'),
  (63, 6, 'Ciudad Bolívar', '8001'),
  (64, 6, 'Ciudad Piar', '8301'),
  (65, 6, 'El Callao', '8151'),
  (66, 6, 'Guasipati', '8151'),
  (67, 6, 'Los Pijiguaos', '8201'),
  (68, 6, 'Maripa', '8501'),
  (69, 6, 'Matanzas', '8051'),
  (70, 6, 'Puerto Ordaz', '8050'),
  (71, 6, 'San Félix', '8070'),
  (72, 6, 'Santa Elena de Uairén', '8601'),
  (73, 6, 'Soledad', '8350'),
  (74, 6, 'Tumeremo', '8401'),
  (75, 6, 'Upata', '8101'),
  (76, 7, 'Bejuma', '2041'),
  (77, 7, 'Guacara', '2015'),
  (78, 7, 'Güigüe', '2031'),
  (79, 7, 'Los Guayos', '2016'),
  (80, 7, 'Mariara', '2021'),
  (81, 7, 'Miranda', '2042'),
  (82, 7, 'Montalbán', '2051'),
  (83, 7, 'Morón', '2110'),
  (84, 7, 'Naguanagua', '2005'),
  (85, 7, 'Puerto Cabello', '2101'),
  (86, 7, 'San Diego', '2006'),
  (87, 7, 'San Joaquín', '2014'),
  (88, 7, 'Tocuyito', '2017'),
  (89, 7, 'Valencia', '2001'),
  (90, 8, 'El Baúl', '2301'),
  (91, 8, 'La Aguadita', '2202'),
  (92, 8, 'Las Vegas', '2241'),
  (93, 8, 'Libertad', '2231'),
  (94, 8, 'Macapo', '2251'),
  (95, 8, 'San Carlos', '2201'),
  (96, 8, 'Tinaco', '2211'),
  (97, 8, 'Tinaquillo', '2212'),
  (98, 9, 'Curiapo', '6301'),
  (99, 9, 'Pedernales', '6201'),
  (100, 9, 'Sierra Imataca', '6401'),
  (101, 9, 'Tucupita', '6101'),
  (102, 10, '23 de Enero', '1010'),
  (103, 10, 'Altagracia', '1010'),
  (104, 10, 'Antímano', '1020'),
  (105, 10, 'Caracas (Libertador)', '1010'),
  (106, 10, 'Caricuao', '1050'),
  (107, 10, 'Coche', '1070'),
  (108, 10, 'El Paraíso', '1030'),
  (109, 10, 'El Valle', '1040'),
  (110, 10, 'La Vega', '1060'),
  (111, 10, 'Macarao', '1080'),
  (112, 11, 'Chichiriviche', '4162'),
  (113, 11, 'Coro', '4101'),
  (114, 11, 'Cumarebo', '4171'),
  (115, 11, 'Dabajuro', '4109'),
  (116, 11, 'Guaibacoa', '4121'),
  (117, 11, 'La Vela de Coro', '4101'),
  (118, 11, 'Mene de Mauroa', '4118'),
  (119, 11, 'Mirimire', '4131'),
  (120, 11, 'Palmasola', '4119'),
  (121, 11, 'Punto Fijo', '4102'),
  (122, 11, 'Santa Cruz de Bucaral', '4111'),
  (123, 11, 'Tucacas', '4161'),
  (124, 12, 'Altagracia de Orituco', '2331'),
  (125, 12, 'Calabozo', '2312'),
  (126, 12, 'Chaguaramas', '2302'),
  (127, 12, 'El Sombrero', '2341'),
  (128, 12, 'Las Mercedes', '2323'),
  (129, 12, 'San José de Guaribe', '2332'),
  (130, 12, 'San Juan de los Morros', '2301'),
  (131, 12, 'Tucupido', '2351'),
  (132, 12, 'Valle de la Pascua', '2321'),
  (133, 12, 'Zaraza', '2350'),
  (134, 13, 'Barquisimeto', '3001'),
  (135, 13, 'Cabudare', '3023'),
  (136, 13, 'Carora', '3201'),
  (137, 13, 'Duaca', '3061'),
  (138, 13, 'El Tocuyo', '3101'),
  (139, 13, 'Guarico', '3071'),
  (140, 13, 'Quíbor', '3051'),
  (141, 13, 'Sanare', '3021'),
  (142, 13, 'Sarare', '3151'),
  (143, 13, 'Siquisique', '4201'),
  (144, 13, 'Yaritagua', '3031'),
  (145, 14, 'Baruta', '1080'),
  (146, 14, 'Caucagua', '1231'),
  (147, 14, 'Chacao', '1060'),
  (148, 14, 'Charallave', '1216'),
  (149, 14, 'Cúa', '1214'),
  (150, 14, 'Cúpira', '1238'),
  (151, 14, 'El Hatillo', '1083'),
  (152, 14, 'Guarenas', '1220'),
  (153, 14, 'Guatire', '1221'),
  (154, 14, 'Higuerote', '1237'),
  (155, 14, 'Los Teques', '1201'),
  (156, 14, 'Ocumare del Tuy', '1212'),
  (157, 14, 'Petare', '1070'),
  (158, 14, 'Río Chico', '1236'),
  (159, 14, 'San Francisco de Yare', '1213'),
  (160, 14, 'Santa Teresa del Tuy', '1215'),
  (161, 15, 'Barrancas', '6401'),
  (162, 15, 'Caripe', '6211'),
  (163, 15, 'Caripito', '6241'),
  (164, 15, 'Maturín', '6201'),
  (165, 15, 'Maturín Este', '6210'),
  (166, 15, 'Punta de Mata', '6301'),
  (167, 15, 'Quiriquire', '6251'),
  (168, 15, 'Temblador', '6351'),
  (169, 15, 'Uracoa', '6461'),
  (170, 16, 'Aricagua', '5221'),
  (171, 16, 'Arzobispo Chacón', '5271'),
  (172, 16, 'Bailadores', '5231'),
  (173, 16, 'Canaguá', '5261'),
  (174, 16, 'Ejido', '5111'),
  (175, 16, 'El Vigía', '5311'),
  (176, 16, 'Guaraque', '5241'),
  (177, 16, 'La Azulita', '5301'),
  (178, 16, 'Lagunillas', '5141'),
  (179, 16, 'Mucuchíes', '5131'),
  (180, 16, 'Mérida', '5101'),
  (181, 16, 'Santa Bárbara', '5181'),
  (182, 16, 'Santa Cruz de Mora', '5321'),
  (183, 16, 'Tabay', '5121'),
  (184, 16, 'Timotes', '5161'),
  (185, 16, 'Tovar', '5211'),
  (186, 16, 'Zea', '5251'),
  (187, 17, 'El Valle del Espíritu Santo', '6313'),
  (188, 17, 'Juan Griego', '6311'),
  (189, 17, 'La Asunción', '6301'),
  (190, 17, 'Pampatar', '6303'),
  (191, 17, 'Porlamar', '6302'),
  (192, 17, 'Punta de Piedras', '6322'),
  (193, 17, 'San Juan Bautista', '6321'),
  (194, 18, 'Acarigua', '3301'),
  (195, 18, 'Araure', '3303'),
  (196, 18, 'Biscucuy', '3311'),
  (197, 18, 'Guanare', '3301'),
  (198, 18, 'Guanarito', '3321'),
  (199, 18, 'Ospino', '3331'),
  (200, 18, 'Papelón', '3351'),
  (201, 18, 'Píritu', '3341'),
  (202, 18, 'San Rafael de Onoto', '3361'),
  (203, 18, 'Turen', '3371'),
  (204, 19, 'Araya', '6161'),
  (205, 19, 'Carúpano', '6201'),
  (206, 19, 'Casanay', '6141'),
  (207, 19, 'Cumanacoa', '6121'),
  (208, 19, 'Cumaná', '6101'),
  (209, 19, 'Güiria', '6301'),
  (210, 19, 'Irapa', '6311'),
  (211, 19, 'Río Caribe', '6222'),
  (212, 19, 'San Antonio del Golfo', '6131'),
  (213, 19, 'Yaguaraparo', '6321'),
  (214, 20, 'Betijoque', '3161'),
  (215, 20, 'Boconó', '3251'),
  (216, 20, 'El Paradero', '3141'),
  (217, 20, 'Escuque', '3131'),
  (218, 20, 'La Ceiba', '3191'),
  (219, 20, 'Monte Carmelo', '3201'),
  (220, 20, 'Motatán', '3121'),
  (221, 20, 'Pampanito', '3181'),
  (222, 20, 'Pampán', '3171'),
  (223, 20, 'Sabana de Mendoza', '3111'),
  (224, 20, 'Trujillo', '3151'),
  (225, 20, 'Valera', '3101'),
  (226, 21, 'Abejales', '5171'),
  (227, 21, 'Capacho', '5022'),
  (228, 21, 'Colón', '5131'),
  (229, 21, 'La Fría', '5121'),
  (230, 21, 'La Grita', '5201'),
  (231, 21, 'Lobatera', '5011'),
  (232, 21, 'Michelena', '5031'),
  (233, 21, 'Palmira', '5012'),
  (234, 21, 'Rubio', '5021'),
  (235, 21, 'San Antonio del Táchira', '5101'),
  (236, 21, 'San Cristóbal', '5001'),
  (237, 21, 'Seboruco', '5141'),
  (238, 21, 'Táriba', '5021'),
  (239, 21, 'Ureña', '5111'),
  (240, 22, 'Caraballeda', '1162'),
  (241, 22, 'Catia La Mar', '1163'),
  (242, 22, 'El Junko', '1166'),
  (243, 22, 'La Guaira', '1160'),
  (244, 22, 'Macuto', '1161'),
  (245, 22, 'Maiquetía', '1164'),
  (246, 22, 'Naiguatá', '1165'),
  (247, 22, 'Tanaguarena', '1167'),
  (248, 23, 'Aroa', '2251'),
  (249, 23, 'Chivacoa', '2211'),
  (250, 23, 'Cocorote', '2231'),
  (251, 23, 'Independencia', '2271'),
  (252, 23, 'La Unión', '2241'),
  (253, 23, 'Nirgua', '2221'),
  (254, 23, 'San Felipe', '2201'),
  (255, 23, 'Urachiche', '2261'),
  (256, 23, 'Veroes', '2281'),
  (257, 23, 'Yaritagua', '3031'),
  (258, 24, 'Bobures', '4171'),
  (259, 24, 'Cabimas', '4013'),
  (260, 24, 'Ciudad Ojeda', '4028'),
  (261, 24, 'Colón', '4131'),
  (262, 24, 'El Vigía (Zulia)', '4141'),
  (263, 24, 'Encontrados', '4121'),
  (264, 24, 'Jesús Enrique Lossada', '4003'),
  (265, 24, 'La Cañada de Urdaneta', '4051'),
  (266, 24, 'La Villa del Rosario', '4071'),
  (267, 24, 'Lagunillas', '4028'),
  (268, 24, 'Los Puertos de Altagracia', '4011'),
  (269, 24, 'Machiques', '4101'),
  (270, 24, 'Maracaibo', '4001'),
  (271, 24, 'Moporo', '4081'),
  (272, 24, 'San Francisco', '4001'),
  (273, 24, 'Santa Bárbara del Zulia', '4151'),
  (274, 24, 'Totumos', '4061');

-- Columnas de dirección estructurada en customers (país/estado/ciudad seleccionados)
ALTER TABLE `customers`
  ADD COLUMN `id_catalogo_pais` int(11) DEFAULT NULL,
  ADD COLUMN `id_catalogo_estado` int(11) DEFAULT NULL,
  ADD COLUMN `id_catalogo_ciudad` int(11) DEFAULT NULL,
  ADD CONSTRAINT `fk_customers_pais` FOREIGN KEY (`id_catalogo_pais`) REFERENCES `catalogo_paises` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customers_estado` FOREIGN KEY (`id_catalogo_estado`) REFERENCES `catalogo_estados` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customers_ciudad` FOREIGN KEY (`id_catalogo_ciudad`) REFERENCES `catalogo_ciudades` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- =====================================================
-- PERMISOS REQUERIDOS (PARA REFERENCIA)
-- =====================================================
-- Estos comandos deben ejecutarse con el usuario root después de crear la empresa:
-- 1. GRANT EXECUTE ON `api_empresas`.* TO 'api_user_N'@'localhost';
-- 2. GRANT EXECUTE ON `api_empresas`.* TO 'api_user_N'@'%';
-- 3. FLUSH PRIVILEGES;

-- Permisos necesarios para consultas cruzadas (Módulo Administrador / Empleados)
-- GRANT SELECT ON `api_emp_N`.* TO 'api_adminemp'@'localhost';

-- =====================================================
-- TABLAS DEL SERVICIO msg_ninesys (WhatsApp / Baileys)
-- =====================================================
-- Sincronizado con: msg_ninesys/db/migrations/001_wa_tables.sql
-- Cualquier cambio en este bloque debe replicarse allá (y viceversa).

CREATE TABLE IF NOT EXISTS `wa_session_auth` (
  `key_name`   VARCHAR(255) NOT NULL,
  `key_value`  LONGBLOB NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_session_state` (
  `id`           TINYINT NOT NULL DEFAULT 1,
  `phone_number` VARCHAR(32)  NULL,
  `pushname`     VARCHAR(128) NULL,
  `status`       ENUM('NOT_REGISTERED','INITIALIZING','REQUIRES_QR','AUTHENTICATED',
                      'READY','PAUSED','ERROR','DISCONNECTED','DEGRADED')
                 NOT NULL DEFAULT 'NOT_REGISTERED',
  `last_error`   TEXT NULL,
  `qr_attempts`  INT NOT NULL DEFAULT 0,
  `paused_until` BIGINT NULL,
  `last_seen_at` DATETIME NULL,
  `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `wa_session_state_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_conversations` (
  `id`           BIGINT NOT NULL AUTO_INCREMENT,
  `jid`          VARCHAR(64)  NOT NULL,
  `name`         VARCHAR(255) NULL,
  `is_group`     TINYINT(1) NOT NULL DEFAULT 0,
  `mode`         ENUM('bot','human','hybrid') NOT NULL DEFAULT 'hybrid',
  `ai_enabled`   TINYINT(1) NOT NULL DEFAULT 1,
  `ai_agent_id`     INT       NULL,
  `assigned_to`          INT       NULL,
  `owner_id`             INT       NULL,
  `last_inbound_at`      DATETIME  NULL,
  `assigned_at`          DATETIME  NULL,
  `last_vendor_reply_at` DATETIME  NULL,
  `unread_count`    INT NOT NULL DEFAULT 0,
  `last_message`    TEXT NULL,
  `last_ts`         BIGINT NULL,
  `tags`            JSON NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME NULL,
  `deleted_by`      INT       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_jid` (`jid`),
  KEY `idx_last_ts` (`last_ts` DESC),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_ai_agent` (`ai_agent_id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_assigned_at` (`assigned_at`),
  KEY `idx_last_vendor_reply_at` (`last_vendor_reply_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estado de disponibilidad y límites de carga por vendedor (Fase D.2).
-- user_id = id del empleado en la tabla de la empresa.
CREATE TABLE IF NOT EXISTS `wa_vendor_state` (
  `user_id`           INT NOT NULL PRIMARY KEY,
  `is_available`      TINYINT(1) NOT NULL DEFAULT 1,
  `max_active`        INT NOT NULL DEFAULT 0,  -- 0 = sin tope
  `allow_auto_assign` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mapeo LID ↔ JID-fono para contactos de WhatsApp.
-- WhatsApp puede entregar mensajes con remoteJid = `<numero>@lid`
-- (privacy feature) sin teléfono real. Se popula desde los eventos
-- contacts.upsert/update, chats.phoneNumberShare y messaging-history.set
-- de Baileys. Permite identificar al cliente en `customers.phone` cuando
-- el chat llega por LID.
CREATE TABLE IF NOT EXISTS `wa_lid_phone_map` (
  `lid_jid`       VARCHAR(100) NOT NULL,
  `phone_jid`     VARCHAR(100) NOT NULL,
  `pushname`      VARCHAR(255) DEFAULT NULL,
  `first_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lid_jid`),
  KEY `idx_phone_jid` (`phone_jid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_messages` (
  `id`                   BIGINT NOT NULL AUTO_INCREMENT,
  `jid`                  VARCHAR(64) NOT NULL,
  `wa_message_id`        VARCHAR(128) NULL,
  `from_me`              TINYINT(1) NOT NULL,
  `sender`               VARCHAR(64) NULL,
  `type`                 ENUM('text','image','audio','video','document','sticker','location','contact','system')
                         NOT NULL DEFAULT 'text',
  `body`                 MEDIUMTEXT NULL,
  `transcript`           TEXT           NULL,
  `transcript_lang`      VARCHAR(8)     NULL,
  `transcript_cost_usd`  DECIMAL(10,6)  NULL,
  `transcript_error`     VARCHAR(255)   NULL,
  `media_url`            VARCHAR(512) NULL,
  `media_mime`           VARCHAR(128) NULL,
  `via`                  ENUM('human','api','ai','template') NOT NULL DEFAULT 'api',
  `sent_by_user`         INT NULL,
  `status`               ENUM('pending','sent','delivered','read','failed') NOT NULL DEFAULT 'pending',
  `ts`                   BIGINT NOT NULL,
  `created_at`           DATETIME DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`           DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wa_msg` (`wa_message_id`),
  KEY `idx_conv` (`jid`, `ts`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_templates` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(128) NOT NULL,
  `body`       TEXT NOT NULL,
  `variables`  JSON NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_ai_settings` (
  `id`             TINYINT NOT NULL DEFAULT 1,
  `provider`          ENUM('anthropic','gemini') NOT NULL DEFAULT 'gemini',
  `enabled`           TINYINT(1) NOT NULL DEFAULT 0,
  `model`             VARCHAR(64) NOT NULL DEFAULT 'claude-sonnet-4-6',
  `system_prompt`     TEXT NULL,
  `temperature`       DECIMAL(3,2) NOT NULL DEFAULT 0.30,
  `max_tokens`        INT NOT NULL DEFAULT 1024,
  `handoff_rules`     JSON NULL,
  `knowledge_base`    JSON NULL,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `respond_in_groups` TINYINT(1) NOT NULL DEFAULT 0,
  `always_ai`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=handoff normal; 1=IA siempre activa (solo notifica, no pasa a modo humano)',
  `notify_vendors_whatsapp` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=envia avisos automaticos de asignacion por WhatsApp al vendedor; 0=silencia esos avisos automaticos',
  PRIMARY KEY (`id`),
  CONSTRAINT `wa_ai_settings_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_ai_agents` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(128) NOT NULL,
  `slug`           VARCHAR(64)  NOT NULL,
  `system_prompt`  TEXT NULL,
  `knowledge_base` JSON NULL,
  `model`          VARCHAR(64) NOT NULL DEFAULT 'gemini-2.5-flash',
  `temperature`    DECIMAL(3,2) NOT NULL DEFAULT 0.30,
  `max_tokens`     INT NOT NULL DEFAULT 1024,
  `enabled`        TINYINT(1) NOT NULL DEFAULT 1,
  `is_default`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_send_log` (
  `id`           BIGINT NOT NULL AUTO_INCREMENT,
  `endpoint`     VARCHAR(64) NOT NULL,
  `phone`        VARCHAR(32) NOT NULL,
  `template`     VARCHAR(128) NULL,
  `status`       ENUM('ok','error') NOT NULL,
  `error`        TEXT NULL,
  `requested_by` VARCHAR(128) NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acumulado mensual de consumo por proveedor de IA (Whisper, Gemini).
-- usd_micros = millonésimas de USD (1e-6). Int para ser aditivo sin
-- errores de float; evita redondear a 0 los costos por llamada pequeños
-- (una nota de voz de 30s en Whisper cuesta ~$0.003 = 3000 micros).
CREATE TABLE IF NOT EXISTS `wa_usage_monthly` (
  `year_month`  CHAR(7)     NOT NULL,
  `provider`    VARCHAR(16) NOT NULL,
  `usd_micros`  BIGINT      NOT NULL DEFAULT 0,
  `call_count`  INT         NOT NULL DEFAULT 0,
  `updated_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`year_month`, `provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Config STT por tenant (singleton id=1). Toggle, tope mensual en USD,
-- umbral de audio largo (handoff humano sin transcribir) e idioma hint.
CREATE TABLE IF NOT EXISTS `wa_tenant_config` (
  `id`                      TINYINT       NOT NULL DEFAULT 1,
  `stt_enabled`             TINYINT(1)    NOT NULL DEFAULT 1,
  `stt_monthly_usd_limit`   DECIMAL(10,2) NOT NULL DEFAULT 3.00,
  `stt_long_audio_seconds`  INT           NOT NULL DEFAULT 120,
  `stt_language`            VARCHAR(8)    NOT NULL DEFAULT 'es',
  `updated_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `wa_tenant_config_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `wa_session_state` (`id`, `status`) VALUES (1, 'NOT_REGISTERED');
INSERT IGNORE INTO `wa_ai_settings` (`id`) VALUES (1);
INSERT IGNORE INTO `wa_tenant_config` (`id`) VALUES (1);
INSERT IGNORE INTO `wa_ai_agents` (`id`, `name`, `slug`, `system_prompt`, `enabled`, `is_default`)
VALUES (1, 'General', 'general', 'Eres un asistente de atención al cliente vía WhatsApp. Responde de forma breve, amable y útil.', 1, 1);
INSERT IGNORE INTO `wa_ai_agents` (`id`, `name`, `slug`, `system_prompt`, `knowledge_base`, `model`, `temperature`, `max_tokens`, `enabled`, `is_default`) VALUES (2,'Ventas','ventas','Eres un agente virtual especializado en atención al cliente para **[NOMBRE DE TU EMPRESA]**, una empresa dedicada a la confección e impresión de prendas personalizadas.\\n\\n---\\n\\n## Audios y Notas de Voz\\nSi recibes un mensaje que proviene de una nota de voz transcrita, trátalo como texto normal.\\nNo menciones que fue audio, no digas \"no puedo escuchar\", simplemente responde la pregunta del cliente de forma natural y profesional. La transcripción es tan válida como un mensaje escrito.\\n\\n---\\n\\n## INSTRUCCIÓN CRÍTICA — Leer primero\\nCuando en el contexto aparezca un bloque \"Productos encontrados\", esos precios son exactos y actualizados. **Muéstralos directamente sin pedir información adicional ni escalar a un asesor.** Solo pide información adicional (tela, cantidad, tallas) cuando el cliente claramente esté listo para hacer una cotización.\\n\\n**REGLA ABSOLUTA — Función submit_presupuesto:** Cada vez que envíes el mensaje de confirmación del presupuesto (el que contiene el resumen y pide al cliente que responda SÍ), DEBES llamar OBLIGATORIAMENTE a la función submit_presupuesto con todos los datos del pedido. Si omites esa llamada, el sistema no podrá registrar el pedido y el trabajo de recopilación se perderá. Esta es la instrucción más importante de este prompt.\\n\\n---\\n\\n## Flujo de atención\\n\\n### 1. Cliente consulta información o precios\\nSi el cliente pregunta por un producto o sus precios y el contexto contiene ese producto con precios:\\n- Muestra el nombre del producto, descripción breve y **todos los precios** por cantidad\\n- Pregunta si le interesa cotizar ese producto\\n- **Nunca escales ni digas que no tienes precio**\\n\\n### 2. Cliente quiere cotizar (presupuesto)\\nCuando el cliente manifieste interés en cotizar, recopila la información **UN PASO A LA VEZ**: envía un único mensaje por paso y **ESPERA la respuesta del cliente antes de continuar** con el siguiente paso. No agrupes preguntas en un mismo mensaje.\\n\\n**REGLA CRÍTICA — Información ya proporcionada:** Antes de hacer cualquier pregunta de los Pasos A–D, revisa el historial completo de la conversación. Si el cliente ya indicó un dato, está ABSOLUTAMENTE PROHIBIDO volvérselo a preguntar.\\n\\nCómo aplicarlo:\\n- Si ya tienes producto + cantidad + talla → solo pregunta el corte (Paso B parcial), luego pasa al Paso C.\\n- Si ya tienes producto + cantidad + talla + corte → Paso B completo, pasa directo al Paso C (tela).\\n- Si ya tienes todo el Paso B → pasa directo al Paso C sin mencionar los datos ya recabados.\\n\\nEjemplos concretos:\\n• Cliente dijo \"3 camisas talla M\" y luego eligió tipo → tienes cantidad=3 y talla=M. Solo pregunta el corte: \"¿Serían de corte Damas, Caballeros o Niños?\" — NUNCA preguntes cantidad ni talla de nuevo.\\n• Cliente dijo \"3 camisas sublimadas talla M\" desde el primer mensaje → tienes Paso A y Paso B completos. Ve directo al Paso C (tela).\\n\\nSi el cliente pide varios productos, completa los pasos A–D para el primero antes de preguntar por el siguiente.\\n\\n**Paso A: Confirmar producto**\\nConfirma el producto seleccionado con el cliente.\\n*Espera respuesta.*\\n\\n**Paso B: Cantidades, tallas y corte**\\nAntes de preguntar, revisa el historial y determina cuáles de estos tres datos ya tienes para este producto: **cantidad**, **talla(s)** y **corte**. Luego actúa así:\\n\\n- Si no tienes **ninguno**: pregunta los tres juntos en un solo mensaje. Ejemplo: \"¿En qué talla(s) y corte necesitas y cuántas de cada una? Por ejemplo: 10 talla S Damas, 10 talla XL Caballeros.\"\\n- Si te faltan **dos**: pregunta solo los dos que faltan en un mismo mensaje.\\n- Si te falta **uno solo**: pregunta únicamente ese dato. Ejemplos:\\n  - Solo falta corte → \"¿Serían de corte Damas, Caballeros o Niños?\"\\n  - Solo falta talla → \"¿En qué talla(s) la necesitas?\"\\n  - Solo falta cantidad → \"¿Cuántas unidades necesitas?\"\\n- Si ya tienes los **tres**: no preguntes nada de Paso B, pasa directamente al Paso C.\\n\\nReglas importantes:\\n- No preguntes la cantidad total por separado; se calcula sumando las cantidades individuales\\n- Cada combinación de talla + corte es un ítem independiente\\n- Las tallas van desde 0 hasta 10XL; por cada X adicional a la XL se agrega $1\\n- Los cortes posibles son: Damas, Caballeros, Niños, No aplica\\n- **CRÍTICO — Tallas literales:** Registra las tallas EXACTAMENTE como el cliente las indica. Las tallas infantiles son números (2, 4, 6, 8, 10, 12, 14, 16); NUNCA las conviertas a tallas adulto (S, M, L, XL). Lo mismo aplica a cualquier talla que el cliente indique fuera del rango adulto estándar.\\n\\n*Espera respuesta.*\\n\\n**Paso C: Tela**\\nConsulta el bloque \"Telas disponibles\" del contexto. Muéstrale al cliente únicamente los **nombres** de cada opción (NO menciones los _id). Cuando el cliente elija, registra internamente el **_id** numérico de esa tela para usarlo en el campo \"tela\" de la función submit_presupuesto.\\n\"¿Qué tipo de tela prefieres para tu pedido? Estas son nuestras opciones: [listar solo los nombres del contexto]\"\\n(La tela aplica igual a todas las combinaciones de ese producto.)\\n*Espera respuesta.*\\n\\n**Paso D: Detalles y descripción del diseño**\\nPregunta amablemente sobre los detalles de diseño de la prenda:\\n\"¿Tienes algún detalle de diseño en mente? Por ejemplo: colores, logotipo, texto, tipo de estampado, etc. (puedes omitirlo si aún no lo tienes definido)\"\\n*Espera respuesta. Anota la descripción COMPLETA Y TEXTUAL del cliente sin resumir ni parafrasear; irá íntegra en el campo \"obs\" del presupuesto.*\\n\\n**Paso E: ¿Más productos?**\\n\"¿Deseas agregar otro producto al presupuesto, o con esto es suficiente?\"\\n*Espera respuesta. Si quiere más productos, repite Pasos A–D para cada uno adicional.*\\n\\n### 3. Datos del cliente\\nUna vez confirmados todos los productos, solicita los datos personales **UN CAMPO A LA VEZ**, esperando la respuesta del cliente antes de pedir el siguiente. No agrupes los campos en un mismo mensaje.\\n\\n**Dato 1:** \"¿Cuál es tu nombre y apellido?\"\\n*Espera respuesta.*\\n\\n**Dato 2:** \"¿Cuál es tu número de cédula?\"\\n*Espera respuesta.*\\n\\n**Dato 3:** \"¿Cuál es tu dirección? (opcional, puedes omitirla)\"\\n*Espera respuesta.*\\n\\n### 4. Confirmación del presupuesto — OBLIGATORIO llamar a submit_presupuesto\\n⚠️ Antes de redactar el resumen, verifica que las tallas y cantidades de cada ítem coincidan EXACTAMENTE con lo que el cliente indicó.\\n\\nMuestra un resumen completo con todos los productos, cantidades, tallas, cortes, telas y el total calculado. Finaliza con:\\n*\"¿Confirmas este presupuesto? Responde **SÍ** para que lo registremos y un asesor te contacte.\"*\\n\\n⚠️ OBLIGATORIO: Al enviar ese mensaje de confirmación, DEBES llamar a la función submit_presupuesto con todos los datos del pedido. Si no llamas a la función, el pedido no podrá registrarse. Cada combinación de talla+corte diferente va como ítem separado en el array items.\\n\\nReglas de los datos:\\n- \"cod\" e \"idCategory\" deben venir del catálogo mostrado en la conversación ([cod:X][idCat:X])\\n- \"precio\" es el precio UNITARIO del tramo que corresponde a la cantidad pedida (NO dividas el precio entre la cantidad)\\n- \"obs\" es la descripción de diseño del cliente, íntegra y sin resumir\\n- \"tela\" es el _id numérico de la tela tal como aparece en el catálogo de telas inyectado\\n- NUNCA uses cod=0 ni idCategory=0 — si no tienes esos valores del catálogo, NO crees el presupuestoes desconocido usa cadena vacía; si \"precio\" es desconocido, usa el menor precio del catálogo para ese producto — pero \"precio\" NUNCA debe ser null: si no tienes el precio exacto del catálogo usa 0 para que el asesor lo ajuste\\n- NO omitas este bloque bajo ninguna circunstancia — es lo que activa el registro automático del pedido\\n\\n### 5. Handoff al asesor\\nUna vez que el cliente responda SÍ, el sistema registrará el presupuesto automáticamente y recibirá este mensaje:\\n*\"Tu presupuesto ha sido generado. Un asesor revisará tu pedido y te contactará en breve.\"*\\n\\n---\\n\\n## Información sobre precios y monedas\\n- Los precios están en **dólares** y pueden variar según cantidad\\n- Para **bolívares**: multiplica el precio en dólares por la tasa euro BCV × 1.5\\n- Los precios **no incluyen IVA**\\n- Las tallas van desde **0 hasta 10XL**; por cada X adicional a la XL se agrega **$1**\\n- Ofrecemos **envío gratis a toda Venezuela** a partir de 12 unidades (por Zoom o MRW)\\n\\n---\\n\\n## Contexto de la empresa\\n- Personaliza este bloque con los servicios y diferenciales reales de tu empresa\\n- Ubicación: [UBICACIÓN DE TU EMPRESA]\\n\\n---\\n\\n## Tono y estilo\\n- Profesional, cálido y cercano — nunca robótico\\n- Español neutro, claro y directo\\n- Usa emojis cuando sea útil para resaltar información\\n- Muestra disposición para ayudar sin forzar la venta\\n\\nEjemplo de saludo:\\n*\"¡Hola! Bienvenido a [NOMBRE DE TU EMPRESA]. Estaré encantado de ayudarte. ¿Estás buscando franelas, chemises, o algún otro producto personalizado?\"*\\n\\n---\\n\\n## Escalada a asesor humano\\n\\nExiste un único caso en que debes incluir el marker `[HANDOFF_IA]` al final de tu mensaje: cuando el cliente plantea una situación que **va más allá de productos y cotizaciones** y requiere intervención humana directa.\\n\\nCasos concretos en los que DEBES usar `[HANDOFF_IA]`:\\n- Reclamos por pedidos ya realizados (retrasos, artículos incorrectos, problemas de calidad)\\n- Solicitudes de devolución, cambio o garantía\\n- Problemas de pago o facturación sobre órdenes existentes\\n- Consultas sobre el estado de un pedido específico ya colocado\\n- El cliente describe un producto o diseño que NO corresponde a ningún ítem del catálogo inyectado (ej. un diseño ya armado en una plataforma externa/interna de diseño, o un servicio de impresión bajo demanda sin producto de catálogo asociado) -- si tras buscarlo no aparece en el bloque \"Productos encontrados\", escala en vez de intentar registrar un presupuesto con datos inventados o con cod/idCategory en cero\\n\\nCómo usarlo: responde con empatía e incluye el marker en su propia línea al final del mensaje (el cliente no lo verá):\\n\"Entiendo tu situación, para atenderte correctamente necesito comunicarte con uno de nuestros asesores. ?\\n[HANDOFF_IA]\"\\n\\n**NO uses `[HANDOFF_IA]` cuando:**\\n- El cliente pregunta por precios o productos — están en el contexto, respóndelos directamente\\n- El cliente quiere cotizar, o estás en cualquiera de los Pasos A–E, o estás enviando el resumen de confirmación del presupuesto — **el flujo de cotización usa la función submit_presupuesto; usar `[HANDOFF_IA]` aquí rompe el presupuesto** (EXCEPCION: si el producto/diseño descrito no aparece en el catálogo inyectado en ningún momento de la conversación, SÍ corresponde escalar con `[HANDOFF_IA]` en vez de forzar submit_presupuesto)\\n- No tienes algún dato puntual — simplemente indícalo sin escalar\\n- El cliente expresa que quiere hablar con un humano — el sistema lo detecta automáticamente, no hace falta que hagas nada\\n- El mensaje menciona que \"un asesor te contactará\" — eso es parte normal del flujo de presupuesto, no requiere escalada\\n\\n## Qué NUNCA debes hacer\\n- Escalar a un asesor si el catálogo tiene los precios\\n- Decir \"no tengo ese producto\" si está en el contexto\\n- Cotizar o presupuestar un producto que NUNCA apareció en el bloque \"Productos encontrados\" durante toda la conversación — el catálogo es la única fuente de verdad, aunque no esté en el turno actual\\n- Usar nombres de productos de tu conocimiento general (franela, camiseta, camisa, etc.) como si fueran del catálogo — solo son válidos los productos que el sistema mostró con [cod:X][idCat:X]\\n- Enviar el mensaje de confirmación de presupuesto SIN llamar a submit_presupuesto — esa llamada es OBLIGATORIA en el mismo turno del resumen\\n- Preguntar la cantidad total por separado — siempre pide cantidad + talla + corte juntos\\n- Dividir el precio del catálogo entre la cantidad para obtener el precio unitario — los precios del catálogo YA SON por unidad; el total es precio_unitario × cantidad\\n- Preguntar la talla y el corte en mensajes separados\\n- Agrupar varias preguntas de cotización en un mismo mensaje — un paso a la vez\\n- Poner el nombre de la tela en el campo \"tela\" del bloque — siempre usa el _id numérico del contexto\\n- Convertir o normalizar las tallas del cliente — si el cliente dice \"talla 6 Niños\" escribe talla:\"6\" y corte:\"Niños\", jamás talla:\"XS\" ni ningún equivalente adulto\\n- Resumir u omitir la descripción de diseño del cliente — \"obs\" debe ser una copia fiel y completa de sus palabras, sin condensar\\n- Incluir `[HANDOFF_IA]` en ningún paso del flujo de cotización ni en el mensaje de confirmación del presupuesto — ese flujo usa exclusivamente la función submit_presupuesto; el sistema se encarga del resto automáticamente\\n- Responder de forma robótica — siempre sé cercano y conversacional\n\n## Galería de imágenes — INSTRUCCIÓN OBLIGATORIA\\n\\nCuando el contexto incluya un bloque \"=== INSTRUCCIÓN OBLIGATORIA DE IMAGEN ===\" con una URL, DEBES llamar a la función send_gallery_image con esa URL exacta. Nunca describas la imagen ni digas que no tienes fotos cuando hay una URL en el contexto.\\n\\nSi por alguna razón no puedes llamar a la función, incluye [IMG:URL] en tu respuesta.\\n\\nCORRECTO:\\n  Llamar a send_gallery_image con la URL del contexto, acompañado de un texto breve como \"¡Aquí te muestro!\"\\n\\nINCORRECTO (nunca hagas esto cuando hay URL en el contexto):\\n  \"Por el momento no tengo imágenes disponibles...\"\\n  \"No puedo mostrarte fotos...\"\\n\\nNUNCA digas \"es el único modelo que tenemos\" — la galería muestra fotos disponibles, el catálogo puede tener muchos más productos.','{"empresa":{"nombre":"[NOMBRE DE TU EMPRESA]","rubro":"textil","descripcion":"Personaliza esta descripcion con el rubro y los diferenciales reales de tu empresa."},\"productos\":{\"categorias\":[\"Franelas\",\"Chemises\",\"Jersey\",\"Camisas\",\"Leggins\",\"Banderines\"],\"tipo\":\"Prendas confeccionadas personalizables (camisetas, chemises, uniformes, ropa deportiva personalizada)\",\"nota\":\"No vendemos telas ni hilos como materia prima\"},\"servicios_adicionales\":[\"Diseno grafico\",\"Diseno de logotipos\",\"Confeccion de prendas a medida\"],\"precios\":{\"nota\":\"Los precios varian segun el producto, cantidad y personalizacion. Siempre cotizar con el equipo de ventas antes de dar cifras\",\"minimo_compra\":\"No hay minimo de precio de compra\",\"pedido_minimo\":\"1 pieza\"},\"metodos_pago\":{\"formas\":[\"Efectivo\",\"Transferencia bancaria\",\"Pago movil\",\"Zelle\"],\"monedas\":[\"Bolivares\",\"Dolares\"],\"modalidades\":[\"Contado\",\"Credito (consultar condiciones con ventas)\"]},\"envios\":{\"cobertura\":\"Toda Venezuela\",\"costo_desde\":\"15 USD en adelante segun destino\",\"tiempo_entrega\":\"Depende de la cantidad de prendas, desde 1 dia\"},\"contacto\":{\"telefono\":\"[TELEFONO DE TU EMPRESA]\",\"email\":\"[EMAIL DE TU EMPRESA]\",\"instagram\":\"[INSTAGRAM DE TU EMPRESA]\",\"direccion\":\"[DIRECCION DE TU EMPRESA]\"},\"faq\":[{\"pregunta\":\"Que precio tienen los productos?\",\"respuesta\":\"Los precios dependen del tipo de prenda, cantidad y nivel de personalizacion. Por favor indicanos que necesitas (tipo de prenda, cantidad, si llevara diseno/logo) y con gusto te cotizamos.\"},{\"pregunta\":\"Que tipos de prendas venden?\",\"respuesta\":\"Vendemos prendas confeccionadas y personalizables: franelas, chemises, jersey, camisas, leggins y banderines. Tambien ofrecemos diseno grafico y diseno de logotipos.\"},{\"pregunta\":\"Cuales son las formas de pago?\",\"respuesta\":\"Aceptamos efectivo, transferencia bancaria, pago movil y Zelle, tanto en bolivares como en dolares. Manejamos ventas de contado y credito (consultar condiciones con nuestro equipo).\"},{\"pregunta\":\"Dan garantia?\",\"respuesta\":\"Consultar con el equipo de ventas las condiciones especificas de garantia segun el producto.\"},{\"pregunta\":\"Hacen envios a toda Venezuela?\",\"respuesta\":\"Si, enviamos a toda Venezuela. El costo de envio desde 15 USD segun el destino. El tiempo de entrega depende de la cantidad de prendas, desde 1 dia.\"}]}','gemini-2.5-flash',0.15,3000,0,1);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;
