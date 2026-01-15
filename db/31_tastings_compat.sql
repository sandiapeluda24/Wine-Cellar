USE vinos;

-- ============
-- tastings: columnas mínimas para que funcionen tastings.php y create_tasting.php
-- ============

-- tasting_date (DATETIME)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tastings' AND COLUMN_NAME='tasting_date'
);
SET @sql := IF(@exists=0, 'ALTER TABLE tastings ADD COLUMN tasting_date DATETIME NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ubicacion (VARCHAR)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tastings' AND COLUMN_NAME='ubicacion'
);
SET @sql := IF(@exists=0, 'ALTER TABLE tastings ADD COLUMN ubicacion VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- max_participantes (INT)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tastings' AND COLUMN_NAME='max_participantes'
);
SET @sql := IF(@exists=0, 'ALTER TABLE tastings ADD COLUMN max_participantes INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- id_sommelier (INT)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tastings' AND COLUMN_NAME='id_sommelier'
);
SET @sql := IF(@exists=0, 'ALTER TABLE tastings ADD COLUMN id_sommelier INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sommelier_id (INT) (alias/compat)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tastings' AND COLUMN_NAME='sommelier_id'
);
SET @sql := IF(@exists=0, 'ALTER TABLE tastings ADD COLUMN sommelier_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- estado (VARCHAR)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tastings' AND COLUMN_NAME='estado'
);
SET @sql := IF(@exists=0, "ALTER TABLE tastings ADD COLUMN estado VARCHAR(30) NOT NULL DEFAULT 'programada'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- índices
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tastings' AND INDEX_NAME='idx_tastings_id_sommelier'
);
SET @sql := IF(@idx=0, 'CREATE INDEX idx_tastings_id_sommelier ON tastings (id_sommelier)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tastings' AND INDEX_NAME='idx_tastings_sommelier_id'
);
SET @sql := IF(@idx=0, 'CREATE INDEX idx_tastings_sommelier_id ON tastings (sommelier_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ============
-- tasting_wines: columnas usadas por create_tasting.php
-- ============

-- orden_degustacion
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tasting_wines' AND COLUMN_NAME='orden_degustacion'
);
SET @sql := IF(@exists=0, 'ALTER TABLE tasting_wines ADD COLUMN orden_degustacion INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- notas_cata
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tasting_wines' AND COLUMN_NAME='notas_cata'
);
SET @sql := IF(@exists=0, 'ALTER TABLE tasting_wines ADD COLUMN notas_cata TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

