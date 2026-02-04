CREATE TABLE IF NOT EXISTS `inventario_remanentes` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_insumo` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `motivo` varchar(255) NOT NULL DEFAULT 'Terminación',
  `observacion` text,
  `id_empleado` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`_id`),
  KEY `id_insumo` (`id_insumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inventario_remanentes` (id_insumo, cantidad, motivo, observacion, fecha)
SELECT _id, remanente, 'Migración Histórica', 'Remanente existente al momento de la migración', NOW()
FROM `inventario`
WHERE remanente > 0;

ALTER TABLE `inventario` DROP COLUMN `remanente`;
