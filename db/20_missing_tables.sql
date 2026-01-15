USE vinos;

-- =========================
-- TASTINGS (catas)
-- =========================
CREATE TABLE IF NOT EXISTS tastings (
  id_cata INT AUTO_INCREMENT PRIMARY KEY,

  -- Campos típicos (ponemos varios nombres por compatibilidad)
  titulo VARCHAR(255) NULL,
  nombre VARCHAR(255) NULL,
  descripcion TEXT NULL,
  lugar VARCHAR(255) NULL,

  fecha_cata DATE NULL,
  hora_cata TIME NULL,

  -- por si alguna página usa estos nombres
  fecha DATE NULL,
  hora TIME NULL,

  capacidad INT NULL,
  aforo INT NULL,

  precio DECIMAL(10,2) NULL,

  -- compatibilidad: algunos scripts usan id_sommelier y otros sommelier_id
  id_sommelier INT NULL,
  sommelier_id INT NULL,

  estado VARCHAR(30) NOT NULL DEFAULT 'open',

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_tastings_fecha_cata (fecha_cata),
  INDEX idx_tastings_sommelier1 (id_sommelier),
  INDEX idx_tastings_sommelier2 (sommelier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Relación cata-vinos (si tu app la usa)
CREATE TABLE IF NOT EXISTS tasting_wines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cata INT NOT NULL,
  id_vino INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  notas TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_tasting_vino (id_cata, id_vino),
  INDEX idx_tw_cata (id_cata),
  INDEX idx_tw_vino (id_vino)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Inscripciones / participantes (por si las páginas las usan)
CREATE TABLE IF NOT EXISTS tasting_signups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cata INT NOT NULL,
  id_usuario INT NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'coleccionista',
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_signup (id_cata, id_usuario),
  INDEX idx_signup_cata (id_cata),
  INDEX idx_signup_user (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasting_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cata INT NOT NULL,
  id_usuario INT NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'coleccionista',
  status VARCHAR(30) NOT NULL DEFAULT 'confirmed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_participant (id_cata, id_usuario),
  INDEX idx_part_cata (id_cata),
  INDEX idx_part_user (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Disponibilidad sommelier (si existe en tu código)
CREATE TABLE IF NOT EXISTS sommelier_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_sommelier INT NOT NULL,
  fecha DATE NULL,
  dia_semana TINYINT NULL,   -- 1..7 si lo usas
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_sa_sommelier (id_sommelier),
  INDEX idx_sa_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================
-- COMPRAS (purchase history)
-- =========================
CREATE TABLE IF NOT EXISTS compras (
  id INT AUTO_INCREMENT PRIMARY KEY,

  id_usuario INT NOT NULL,
  id_vino INT NOT NULL,

  cantidad INT NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(10,2) NULL,
  total DECIMAL(10,2) NULL,

  status VARCHAR(30) NOT NULL DEFAULT 'paid',
  stripe_session_id VARCHAR(255) NULL,

  fecha_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_compras_user (id_usuario),
  INDEX idx_compras_vino (id_vino),
  INDEX idx_compras_fecha (fecha_compra)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
