USE vinos;

-- Añadir columnas a vinos solo si no existen (compatible sin IF NOT EXISTS)
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA='vinos' AND TABLE_NAME='vinos' AND COLUMN_NAME='precio') = 0,
  'ALTER TABLE vinos ADD COLUMN precio DECIMAL(10,2) NULL',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA='vinos' AND TABLE_NAME='vinos' AND COLUMN_NAME='stock') = 0,
  'ALTER TABLE vinos ADD COLUMN stock INT NOT NULL DEFAULT 0',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA='vinos' AND TABLE_NAME='vinos' AND COLUMN_NAME='imagen') = 0,
  'ALTER TABLE vinos ADD COLUMN imagen VARCHAR(255) NULL',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA='vinos' AND TABLE_NAME='vinos' AND COLUMN_NAME='ventana_optima_inicio') = 0,
  'ALTER TABLE vinos ADD COLUMN ventana_optima_inicio VARCHAR(20) NULL',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA='vinos' AND TABLE_NAME='vinos' AND COLUMN_NAME='ventana_optima_fin') = 0,
  'ALTER TABLE vinos ADD COLUMN ventana_optima_fin VARCHAR(20) NULL',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Añadir is_active a usuarios si no existe
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA='vinos' AND TABLE_NAME='usuarios' AND COLUMN_NAME='is_active') = 0,
  'ALTER TABLE usuarios ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
