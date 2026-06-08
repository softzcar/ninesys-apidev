-- Migration script for CRM module (to be executed on the Dev VPN DB)

-- 1. Create tables
CREATE TABLE IF NOT EXISTS `crm_campanas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(128) NOT NULL,
  `mensaje_plantilla` text NOT NULL,
  `filtro_productos` text DEFAULT NULL COMMENT 'Arreglo JSON de IDs de productos de la segmentacion',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Campañas de marketing masivas enviadas por WhatsApp';

CREATE TABLE IF NOT EXISTS `crm_campanas_envios` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_campana` int(11) NOT NULL,
  `id_customer` int(10) unsigned NOT NULL,
  `estado_envio` varchar(20) NOT NULL DEFAULT 'enviado' COMMENT 'enviado, fallido',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `id_campana` (`id_campana`),
  KEY `id_customer` (`id_customer`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Bitácora de envíos individuales realizados en una campaña de marketing';

CREATE TABLE IF NOT EXISTS `crm_oportunidades` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_customer` int(10) unsigned DEFAULT NULL,
  `titulo` varchar(128) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `monto_estimado` decimal(12, 2) NOT NULL DEFAULT 0.00,
  `estado` varchar(32) NOT NULL DEFAULT 'nuevo_lead' COMMENT 'nuevo_lead, en_negociacion, propuesta_enviada, cliente_ganado, cliente_perdido',
  `motivo_perdida` varchar(255) DEFAULT NULL,
  `id_campana` int(11) DEFAULT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `id_customer` (`id_customer`),
  KEY `id_campana` (`id_campana`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Oportunidades de venta y leads en el embudo comercial';

CREATE TABLE IF NOT EXISTS `crm_oportunidades_vendedores` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_oportunidad` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL COMMENT 'ID del vendedor de empresas_usuarios',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `id_oportunidad` (`id_oportunidad`),
  KEY `id_vendedor` (`id_vendedor`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Vendedores asignados a las oportunidades del embudo comercial';

CREATE TABLE IF NOT EXISTS `crm_notas` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_customer` int(10) unsigned NOT NULL,
  `id_oportunidad` int(11) DEFAULT NULL,
  `id_usuario_creador` int(11) NOT NULL COMMENT 'ID del empleado/usuario que redacta la nota',
  `nota` text NOT NULL,
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `id_customer` (`id_customer`),
  KEY `id_oportunidad` (`id_oportunidad`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Bitácora de notas de seguimiento de clientes y leads';

CREATE TABLE IF NOT EXISTS `crm_soporte` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_customer` int(10) unsigned NOT NULL,
  `titulo` varchar(128) NOT NULL,
  `descripcion` text NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'abierto' COMMENT 'abierto, resuelto',
  `moment` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`_id`),
  KEY `id_customer` (`id_customer`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_spanish_ci COMMENT = 'Bitácora de incidencias simples de soporte y postventa';

-- 2. Add constraints
ALTER TABLE `crm_campanas_envios`
  ADD CONSTRAINT `crm_camp_env_ibfk_1` FOREIGN KEY (`id_campana`) REFERENCES `crm_campanas` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `crm_camp_env_ibfk_2` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `crm_notas`
  ADD CONSTRAINT `crm_notas_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `crm_notas_ibfk_2` FOREIGN KEY (`id_oportunidad`) REFERENCES `crm_oportunidades` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `crm_oportunidades`
  ADD CONSTRAINT `crm_oport_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `crm_oport_ibfk_2` FOREIGN KEY (`id_campana`) REFERENCES `crm_campanas` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `crm_oportunidades_vendedores`
  ADD CONSTRAINT `crm_oport_vend_ibfk_1` FOREIGN KEY (`id_oportunidad`) REFERENCES `crm_oportunidades` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `crm_soporte`
  ADD CONSTRAINT `crm_soporte_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE;
