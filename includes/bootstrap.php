<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/tiempo.php';

date_default_timezone_set('Europe/Madrid');

// Cabeceras de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), geolocation=(self), microphone=()');
// CSP: solo JavaScript propio (nada en línea ni de otros dominios). Los estilos en línea se permiten
// porque la interfaz los usa en atributos style; las fuentes vienen de Google Fonts.
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

// Sesión de 30 días, cookie solo accesible desde el servidor
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
ini_set('session.use_strict_mode', '1');   // no acepta identificadores de sesión inventados
session_name('armario_sesion');
session_start();

// El identificador de sesión se renueva una vez al día: una cookie robada deja de servir antes
if (!empty($_SESSION['usuario_id']) && time() - (int)($_SESSION['renovada'] ?? 0) > 86400) {
    session_regenerate_id(true);
    $_SESSION['renovada'] = time();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Revisa config.php.');
}

// Usuario conectado (login.php e instalar.php definen PAGINA_PUBLICA antes de incluir este archivo)
$USUARIO = null;
if (!empty($_SESSION['usuario_id'])) {
    $st = $pdo->prepare('SELECT id, nombre, email, rol, activo, debe_cambiar_clave, clave_version, dias_olvido, sugerir_temporada, tema, ciudad, latitud, longitud, usar_tiempo FROM usuarios WHERE id = ?');
    $st->execute([$_SESSION['usuario_id']]);
    $USUARIO = $st->fetch() ?: null;
    // Fuera si lo han desactivado, borrado o le han cambiado la contraseña desde otra sesión
    if (!$USUARIO || !$USUARIO['activo'] || (int)$USUARIO['clave_version'] !== (int)($_SESSION['clave_version'] ?? 0)) {
        $USUARIO = null;
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

$pagina = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (!$USUARIO && !defined('PAGINA_PUBLICA')) {
    if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['error' => 'Sesión caducada']));
    }
    redirigir('login.php');
}

// Contraseña temporal: obligatorio cambiarla antes de seguir
if ($USUARIO && $USUARIO['debe_cambiar_clave'] && !in_array($pagina, ['cambiar_clave.php', 'salir.php'], true)) {
    redirigir('cambiar_clave.php');
}

/** Solo administradores */
function requerir_admin(?array $u): void
{
    if (!es_admin($u)) {
        http_response_code(403);
        exit('No tienes permiso para ver esta página.');
    }
}

$UID = $USUARIO ? (int)$USUARIO['id'] : 0;
