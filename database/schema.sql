-- Esquema de la base de datos del gestor de tareas (MySQL 8)

CREATE TABLE IF NOT EXISTS categorias (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(60)  NOT NULL,
  color       CHAR(7)      NOT NULL DEFAULT '#6b6b6b',
  creada_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tareas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo        VARCHAR(160) NOT NULL,
  descripcion   TEXT         NULL,
  completada    TINYINT(1)   NOT NULL DEFAULT 0,
  prioridad     ENUM('baja', 'media', 'alta') NOT NULL DEFAULT 'media',
  vence_el      DATE         NULL,
  categoria_id  INT UNSIGNED NULL,
  creada_en     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizada_en TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- Si se borra una categoría, sus tareas quedan sin categoría en lugar
  -- de desaparecer con ella.
  CONSTRAINT fk_tareas_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias (id)
    ON DELETE SET NULL,

  -- Índices para los filtros más habituales de la interfaz.
  KEY idx_tareas_completada (completada),
  KEY idx_tareas_categoria (categoria_id),
  KEY idx_tareas_vence (vence_el)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
