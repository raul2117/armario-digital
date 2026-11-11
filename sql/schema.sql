-- Armario digital: base de datos, usuario y tablas.
-- Ejecutar en la Raspberry con:  sudo mysql < schema.sql
-- Cambia 'cambia_esta_clave' por la misma clave que pongas en config.php.

CREATE DATABASE IF NOT EXISTS armario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'armario'@'localhost' IDENTIFIED BY 'cambia_esta_clave';
GRANT ALL PRIVILEGES ON armario.* TO 'armario'@'localhost';
FLUSH PRIVILEGES;

USE armario;

CREATE TABLE IF NOT EXISTS usuarios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(60)  NOT NULL,
  email       VARCHAR(190) NOT NULL UNIQUE,
  clave_hash  VARCHAR(255) NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(50) NOT NULL,
  slug    VARCHAR(50) NOT NULL UNIQUE,
  color   CHAR(7)     NOT NULL DEFAULT '#ECE6FF',   -- color pastel de fondo
  icono   VARCHAR(10) NOT NULL DEFAULT 'tee',       -- silueta: tee, pant, dress, skirt, jacket, shoe, bag
  hueco   ENUM('arriba','abajo','abrigo','calzado','complemento') NOT NULL,
  orden   TINYINT     NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prendas (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  color_hex    CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
  temporada    ENUM('todo','primavera','verano','otono','invierno') NOT NULL DEFAULT 'todo',
  marca        VARCHAR(80)  NULL,
  notas        TEXT         NULL,
  foto         VARCHAR(120) NULL,
  favorita     TINYINT(1)   NOT NULL DEFAULT 0,
  veces_usada  INT UNSIGNED NOT NULL DEFAULT 0,
  ultima_vez   DATE         NULL,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prenda_cat FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS outfits (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(100) NOT NULL,
  ocasion    ENUM('casual','trabajo','fiesta','deporte') NOT NULL DEFAULT 'casual',
  temporada  ENUM('todo','primavera','verano','otono','invierno') NOT NULL DEFAULT 'todo',
  fecha      DATE NULL,                 -- día programado para ponérselo
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS outfit_prendas (
  outfit_id INT UNSIGNED NOT NULL,
  prenda_id INT UNSIGNED NOT NULL,
  hueco     ENUM('arriba','abajo','abrigo','calzado','complemento') NOT NULL,
  PRIMARY KEY (outfit_id, hueco),
  CONSTRAINT fk_op_outfit FOREIGN KEY (outfit_id) REFERENCES outfits(id) ON DELETE CASCADE,
  CONSTRAINT fk_op_prenda FOREIGN KEY (prenda_id) REFERENCES prendas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO categorias (nombre, slug, color, icono, hueco, orden) VALUES
  ('Camisetas',   'camisetas',   '#DCE7FF', 'tee',    'arriba',      1),
  ('Jerséis',     'jerseis',     '#ECE6FF', 'tee',    'arriba',      2),
  ('Camisas',     'camisas',     '#E3F0FF', 'tee',    'arriba',      3),
  ('Vestidos',    'vestidos',    '#FFE0EA', 'dress',  'arriba',      4),
  ('Pantalones',  'pantalones',  '#D6E4F5', 'pant',   'abajo',       5),
  ('Faldas',      'faldas',      '#DDF3E8', 'skirt',  'abajo',       6),
  ('Abrigos',     'abrigos',     '#F6EBD9', 'jacket', 'abrigo',      7),
  ('Calzado',     'calzado',     '#D8F5E8', 'shoe',   'calzado',     8),
  ('Accesorios',  'accesorios',  '#FFF1C2', 'bag',    'complemento', 9);

-- Multiusuario: cada prenda y cada outfit pertenece a un usuario.
-- (Se puede ejecutar varias veces; MariaDB ignora lo que ya existe.)
ALTER TABLE prendas ADD COLUMN IF NOT EXISTS usuario_id INT UNSIGNED NULL AFTER id;
ALTER TABLE prendas ADD INDEX IF NOT EXISTS idx_prendas_usuario (usuario_id);
ALTER TABLE prendas ADD CONSTRAINT fk_prenda_usuario FOREIGN KEY IF NOT EXISTS (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE outfits ADD COLUMN IF NOT EXISTS usuario_id INT UNSIGNED NULL AFTER id;
ALTER TABLE outfits ADD INDEX IF NOT EXISTS idx_outfits_usuario (usuario_id);
ALTER TABLE outfits ADD CONSTRAINT fk_outfit_usuario FOREIGN KEY IF NOT EXISTS (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;

-- Gestión de usuarios y seguridad
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS rol ENUM('admin','usuario') NOT NULL DEFAULT 'usuario' AFTER clave_hash;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS debe_cambiar_clave TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS clave_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER debe_cambiar_clave;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER clave_version;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME NULL AFTER intentos_fallidos;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL AFTER bloqueado_hasta;

CREATE TABLE IF NOT EXISTS accesos (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NULL,
  email      VARCHAR(190) NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  exito      TINYINT(1)   NOT NULL,
  motivo     VARCHAR(40)  NULL,
  fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_accesos_fecha (fecha),
  INDEX idx_accesos_ip (ip, fecha)
) ENGINE=InnoDB;

-- Si ya había usuarios pero ningún administrador, el más antiguo pasa a serlo
UPDATE usuarios SET rol = 'admin'
 WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM usuarios) t)
   AND (SELECT n FROM (SELECT COUNT(*) AS n FROM usuarios WHERE rol = 'admin') a) = 0;

-- Historial de usos (calendario y estadísticas)
CREATE TABLE IF NOT EXISTS usos (
  usuario_id INT UNSIGNED NOT NULL,
  prenda_id  INT UNSIGNED NOT NULL,
  fecha      DATE NOT NULL,
  PRIMARY KEY (prenda_id, fecha),
  INDEX idx_usos_usuario (usuario_id, fecha),
  CONSTRAINT fk_uso_prenda FOREIGN KEY (prenda_id) REFERENCES prendas(id) ON DELETE CASCADE,
  CONSTRAINT fk_uso_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ajustes por usuario
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS dias_olvido SMALLINT UNSIGNED NOT NULL DEFAULT 90 AFTER ultimo_acceso;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS sugerir_temporada TINYINT(1) NOT NULL DEFAULT 1 AFTER dias_olvido;

-- Apariencia: auto (según el sistema), claro u oscuro
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS tema ENUM('auto','claro','oscuro') NOT NULL DEFAULT 'auto' AFTER sugerir_temporada;

-- Ubicación para el tiempo (Open-Meteo)
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ciudad VARCHAR(120) NULL AFTER tema;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS latitud DECIMAL(8,5) NULL AFTER ciudad;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS longitud DECIMAL(8,5) NULL AFTER latitud;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS usar_tiempo TINYINT(1) NOT NULL DEFAULT 1 AFTER longitud;
