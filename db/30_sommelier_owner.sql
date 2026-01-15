USE vinos;

-- Detecta la PK real de usuarios (si no encuentra, asume id_usuario INT)
SET @pk_col := (SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA=DATABASE()
                  AND TABLE_NAME='usuarios'
                  AND COLUMN_KEY='PRI'
                LIMIT 1);
SET @pk_type := (SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='usuarios'
                   AND COLUMN_NAME=@pk_col
                 LIMIT 1);

SET @pk_col  := IFNULL(@pk_col,  'id_usuario');
SET @pk_type := IFNULL(@pk_type, 'INT');

-- 1) Asegura columna sommelier_id en tastings
SET @col_exists := (SELECT COUNT(*)
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE()
                      AND TABLE_NAME='tastings'
                      AND COLUMN_NAME='sommelier_id');
SET @sql := IF(@col_exists=0,
               CONCAT('ALTER TABLE tastings ADD COLUMN sommelier_id ', @pk_type, ' NULL'),
               'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Si existe id_sommelier, copia valores a sommelier_id
SET @id_somm_exists := (SELECT COUNT(*)
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE()
                          AND TABLE_NAME='tastings'
                          AND COLUMN_NAME='id_sommelier');
SET @sql := IF(@id_somm_exists>0,
               'UPDATE tastings
                SET sommelier_id = id_sommelier
                WHERE sommelier_id IS NULL AND id_sommelier IS NOT NULL',
               'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Índice
SET @idx_exists := (SELECT COUNT(*)
                    FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA=DATABASE()
                      AND TABLE_NAME='tastings'
                      AND INDEX_NAME='idx_tastings_sommelier');
SET @sql := IF(@idx_exists=0,
               'CREATE INDEX idx_tastings_sommelier ON tastings (sommelier_id)',
               'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Si NO hay tastings sin sommelier_id, fuerza NOT NULL y crea la FK
SET @nulls := (SELECT COUNT(*) FROM tastings WHERE sommelier_id IS NULL);

SET @sql := IF(@nulls=0,
               CONCAT('ALTER TABLE tastings MODIFY sommelier_id ', @pk_type, ' NOT NULL'),
               'SELECT "SKIP_NOT_NULL: Hay tastings sin sommelier_id"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*)
                   FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA=DATABASE()
                     AND TABLE_NAME='tastings'
                     AND CONSTRAINT_TYPE='FOREIGN KEY'
                     AND CONSTRAINT_NAME='fk_tastings_sommelier');

SET @sql := IF(@nulls=0 AND @fk_exists=0,
               CONCAT('ALTER TABLE tastings
                       ADD CONSTRAINT fk_tastings_sommelier
                       FOREIGN KEY (sommelier_id)
                       REFERENCES usuarios(', @pk_col, ')
                       ON DELETE RESTRICT ON UPDATE CASCADE'),
               'SELECT "SKIP_FK"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Report
SELECT COUNT(*) AS tastings_sin_sommelier
FROM tastings
WHERE sommelier_id IS NULL;
