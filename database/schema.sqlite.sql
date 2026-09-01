-- Mismo esquema adaptado a SQLite, para desarrollo y pruebas sin MySQL.

CREATE TABLE IF NOT EXISTS categorias (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre    TEXT NOT NULL UNIQUE,
  color     TEXT NOT NULL DEFAULT '#6b6b6b',
  creada_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tareas (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  titulo         TEXT NOT NULL,
  descripcion    TEXT,
  completada     INTEGER NOT NULL DEFAULT 0,
  prioridad      TEXT NOT NULL DEFAULT 'media'
                 CHECK (prioridad IN ('baja', 'media', 'alta')),
  vence_el       TEXT,
  categoria_id   INTEGER REFERENCES categorias (id) ON DELETE SET NULL,
  creada_en      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizada_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tareas_completada ON tareas (completada);
CREATE INDEX IF NOT EXISTS idx_tareas_categoria  ON tareas (categoria_id);
CREATE INDEX IF NOT EXISTS idx_tareas_vence      ON tareas (vence_el);
