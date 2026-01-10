-- Crear base de datos (cámbiale el nombre si quieres)
CREATE DATABASE IF NOT EXISTS vinos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE vinos;

-- =========================
-- Tabla: usuarios
-- =========================
DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    rol         ENUM('admin','coleccionista','sommelier') NOT NULL DEFAULT 'coleccionista',
    certificado TINYINT(1) NOT NULL DEFAULT 0,
    sommelier_description TEXT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================
-- Tabla: denominaciones
-- =========================
DROP TABLE IF EXISTS denominaciones;
CREATE TABLE denominaciones (
    id_denominacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- =========================
-- Tabla: vinos
-- =========================
DROP TABLE IF EXISTS vinos;
CREATE TABLE vinos (
    id_vino              INT AUTO_INCREMENT PRIMARY KEY,
    nombre               VARCHAR(150) NOT NULL,
    bodega               VARCHAR(150),
    annada               YEAR NOT NULL,
    id_denominacion      INT NOT NULL,
    tipo                 VARCHAR(50),  -- tinto, blanco, espumoso, etc.
    pais                 VARCHAR(80),
    ventana_optima_inicio YEAR,
    ventana_optima_fin    YEAR,
    descripcion          TEXT,
    CONSTRAINT fk_vinos_denominaciones
      FOREIGN KEY (id_denominacion) REFERENCES denominaciones(id_denominacion)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Tabla: colecciones
-- (botellas en la colección de cada coleccionista)
-- =========================
DROP TABLE IF EXISTS colecciones;
CREATE TABLE colecciones (
    id_coleccion     INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT NOT NULL,
    id_vino          INT NOT NULL,
    cantidad         INT NOT NULL DEFAULT 1,
    ubicacion        VARCHAR(150),
    fecha_adquisicion DATE,
    CONSTRAINT fk_colecciones_usuarios
      FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
      ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_colecciones_vinos
      FOREIGN KEY (id_vino) REFERENCES vinos(id_vino)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Tabla: catas
-- =========================
DROP TABLE IF EXISTS catas;
CREATE TABLE catas (
    id_cata          INT AUTO_INCREMENT PRIMARY KEY,
    id_vino          INT NOT NULL,
    id_sommelier     INT NOT NULL,
    fecha_cata       DATE NOT NULL,
    puntuacion       INT,       -- 0-100 o 1-5, lo decides tú
    nota_cata        TEXT,
    maridaje_sugerido TEXT,
    CONSTRAINT fk_catas_vinos
      FOREIGN KEY (id_vino) REFERENCES vinos(id_vino)
      ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_catas_sommeliers
      FOREIGN KEY (id_sommelier) REFERENCES usuarios(id_usuario)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Datos iniciales
-- =========================

-- Denominaciones
INSERT INTO denominaciones (nombre) VALUES
  ('DOCG'),
  ('DOC'),
  ('IGT'),
  ('Orgánico');

-- Usuarios de ejemplo
-- OJO: de momento las contraseñas son texto plano; cuando tengas
-- el registro o un script en PHP, las actualizarás a hashes con password_hash().
INSERT INTO usuarios (nombre, email, password, rol, certificado) VALUES
  ('Admin', 'admin@vinos.test', 'admin123', 'admin', 0),
  ('Coleccionista Demo', 'coleccionista@vinos.test', 'coleccionista123', 'coleccionista', 0),
  ('Sommelier Demo', 'sommelier@vinos.test', 'sommelier123', 'sommelier', 1);

-- Unos vinos de ejemplo (ajusta los años si quieres)
INSERT INTO vinos (nombre, bodega, annada, id_denominacion, tipo, pais, ventana_optima_inicio, ventana_optima_fin, descripcion)
VALUES
  ('Barolo Riserva', 'Cantina del Piemonte', 2015, 1, 'tinto', 'Italia', 2022, 2030, 'Barolo DOCG de larga guarda.'),
  ('Chianti Classico', 'Fattoria Toscana', 2019, 2, 'tinto', 'Italia', 2023, 2028, 'Chianti DOC ideal para maridar con pastas.'),
  ('Vermentino Bio', 'Vigna Verde', 2021, 4, 'blanco', 'Italia', 2022, 2025, 'Vino blanco orgánico fresco y aromático.');

-- Ejemplo de colección (el coleccionista tiene algunas botellas)
INSERT INTO colecciones (id_usuario, id_vino, cantidad, ubicacion, fecha_adquisicion) VALUES
  (2, 1, 3, 'Bodega particular - Estantería A', '2022-10-15'),
  (2, 3, 6, 'Bodega particular - Estantería B', '2023-05-02');

-- Ejemplo de catas realizadas por el sommelier demo
INSERT INTO catas (id_vino, id_sommelier, fecha_cata, puntuacion, nota_cata, maridaje_sugerido) VALUES
  (1, 3, '2023-11-20', 95, 'Taninos finos, notas de cuero y cereza madura.', 'Carnes rojas, caza.'),
  (3, 3, '2024-03-10', 89, 'Muy fresco, buena acidez y notas cítricas.', 'Pescados, mariscos, ensaladas.');
-- Fin del script de creación inicial

USE vinos;

-- Asegurar columnas que tu código espera en vinos
ALTER TABLE vinos
  ADD COLUMN IF NOT EXISTS precio DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS stock INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS ventana_optima_inicio VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS ventana_optima_fin VARCHAR(20) NULL;

-- Asegurar columna is_active que tu login intenta leer (si aplica)
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- (Opcional pero recomendado) Asegurar charset/collation
ALTER TABLE vinos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- (Opcional) Crear usuarios demo si NO existen
INSERT INTO usuarios (nombre, email, password, rol, certificado, is_active)
SELECT 'Admin', 'admin@vinos.test', '$2y$10$uQm8mWmZbQk1x7Gg4lJZ3O6gG0yU8oQpYB3jQqk8p5kq7xKqWwHjK', 'admin', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email='admin@vinos.test');

INSERT INTO usuarios (nombre, email, password, rol, certificado, is_active)
SELECT 'Coleccionista', 'coleccionista@vinos.test', '$2y$10$uQm8mWmZbQk1x7Gg4lJZ3O6gG0yU8oQpYB3jQqk8p5kq7xKqWwHjK', 'coleccionista', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email='coleccionista@vinos.test');

INSERT INTO usuarios (nombre, email, password, rol, certificado, is_active)
SELECT 'Sommelier', 'sommelier@vinos.test', '$2y$10$uQm8mWmZbQk1x7Gg4lJZ3O6gG0yU8oQpYB3jQqk8p5kq7xKqWwHjK', 'sommelier', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email='sommelier@vinos.test');