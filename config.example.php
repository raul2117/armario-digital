<?php
// Configuración del Armario digital.
// Copia este archivo como config.php y pon tus datos. config.php no se sube a git.
// Con el script de despliegue no hace falta: en la primera instalación se crea solo con una clave aleatoria.
define('DB_HOST', 'localhost');
define('DB_NAME', 'armario');
define('DB_USER', 'armario');
define('DB_PASS', 'cambia_esta_clave');

define('APP_NOMBRE', 'Mi Armario');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('UPLOAD_MAX_MB', 6);
