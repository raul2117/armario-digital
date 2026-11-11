<?php

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $tipo, string $mensaje, ?string $clave = null): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje, 'clave' => $clave];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_comprobar(): void
{
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(400);
        exit('La sesión ha caducado. Vuelve a cargar la página.');
    }
}

/** Siluetas SVG de prenda (se usan cuando no hay foto). */
function silueta_path(string $tipo): string
{
    $p = [
        'tee'    => 'M16 8 L8 13 L11 21 L15 19 L15 40 L33 40 L33 19 L37 21 L40 13 L32 8 C30 11 27 12 24 12 C21 12 18 11 16 8 Z',
        'pant'   => 'M15 6 L33 6 L35 42 L27 42 L24 18 L21 42 L13 42 Z',
        'dress'  => 'M19 6 L29 6 L28 14 L36 42 L12 42 L20 14 Z',
        'shoe'   => 'M6 32 L6 22 L16 20 C19 24 24 25 30 25 L40 27 C43 28 44 31 43 34 L6 34 Z',
        'jacket' => 'M18 7 L8 12 L8 41 L23 41 L23 15 Z M30 7 L40 12 L40 41 L25 41 L25 15 Z',
        'bag'    => 'M10 18 L38 18 L36 41 L12 41 Z M18 18 C18 9 30 9 30 18',
        'skirt'  => 'M16 10 L32 10 L38 40 L10 40 Z',
    ];
    return $p[$tipo] ?? $p['tee'];
}

function silueta(string $tipo, string $color, int $tam = 80, string $etiqueta = ''): string
{
    return '<svg width="' . $tam . '" height="' . $tam . '" viewBox="0 0 48 48" fill="' . h($color)
        . '" stroke="#1E1B3A" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" role="img" aria-label="'
        . h($etiqueta) . '"><path d="' . silueta_path($tipo) . '"/></svg>';
}

/** Miniatura de una prenda: foto si la tiene, si no silueta sobre el color de su categoría. */
function miniatura(array $p, int $tam = 80, string $clase = 'tile'): string
{
    $fondo = h($p['cat_color'] ?? '#ECE6FF');
    if (!empty($p['foto'])) {
        $dentro = '<img src="' . h(UPLOAD_URL . $p['foto']) . '" alt="' . h($p['nombre']) . '" loading="lazy">';
    } else {
        $dentro = silueta($p['icono'] ?? 'tee', $p['color_hex'] ?: '#FFFFFF', $tam, $p['nombre']);
    }
    return '<div class="' . $clase . '" style="background:' . $fondo . '">' . $dentro . '</div>';
}

function fecha_larga(?string $fecha = null): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = $fecha ? strtotime($fecha) : time();
    return $dias[(int)date('w', $t)] . ', ' . date('j', $t) . ' de ' . $meses[(int)date('n', $t)];
}

const HUECOS = [
    'arriba'      => 'Arriba',
    'abajo'       => 'Abajo',
    'abrigo'      => 'Abrigo',
    'calzado'     => 'Calzado',
    'complemento' => 'Complemento',
];

const TEMPORADAS = ['todo' => 'Todo el año', 'primavera' => 'Primavera', 'verano' => 'Verano', 'otono' => 'Otoño', 'invierno' => 'Invierno'];

const OCASIONES = ['casual' => 'Casual', 'trabajo' => 'Trabajo', 'fiesta' => 'Fiesta', 'deporte' => 'Deporte'];

/** SQL base de prendas con los datos de su categoría. */
const SQL_PRENDAS = 'SELECT p.*, c.nombre AS cat_nombre, c.slug AS cat_slug, c.color AS cat_color, c.icono, c.hueco
                     FROM prendas p JOIN categorias c ON c.id = p.categoria_id';

/* ---------- Usuarios y seguridad ---------- */

const MAX_INTENTOS = 5;          // fallos seguidos antes de bloquear la cuenta
const MINUTOS_BLOQUEO = 15;
const MAX_FALLOS_IP = 20;        // fallos desde una misma IP en 15 minutos

function es_admin(?array $u): bool
{
    return $u && ($u['rol'] ?? '') === 'admin';
}

function ip_cliente(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function registrar_acceso(PDO $pdo, ?int $uid, string $email, bool $exito, ?string $motivo = null): void
{
    $pdo->prepare('INSERT INTO accesos (usuario_id, email, ip, exito, motivo) VALUES (?, ?, ?, ?, ?)')
        ->execute([$uid, mb_substr($email, 0, 190), ip_cliente(), $exito ? 1 : 0, $motivo]);
    // Se guardan 90 días
    if (random_int(1, 50) === 1) {
        $pdo->exec('DELETE FROM accesos WHERE fecha < NOW() - INTERVAL 90 DAY');
    }
}

/** Devuelve el error de la contraseña o null si es válida. */
function validar_clave(string $clave, string $email = ''): ?string
{
    if (mb_strlen($clave) < 10) return 'La contraseña debe tener al menos 10 caracteres.';
    if (!preg_match('/[a-zA-Z]/', $clave) || !preg_match('/\d/', $clave)) return 'La contraseña debe tener letras y números.';
    if ($email !== '' && stripos($clave, strtok($email, '@')) !== false) return 'La contraseña no puede contener tu email.';
    return null;
}

/** Contraseña temporal legible (sin 0/O, 1/l) */
function generar_clave(int $largo = 12): string
{
    $letras = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $numeros = '23456789';
    $todo = $letras . $numeros;
    $c = $letras[random_int(0, strlen($letras) - 1)] . $numeros[random_int(0, strlen($numeros) - 1)];
    while (strlen($c) < $largo) {
        $c .= $todo[random_int(0, strlen($todo) - 1)];
    }
    return str_shuffle($c);
}

function temporada_actual(): string
{
    $m = (int)date('n');
    $d = (int)date('j');
    if (($m === 3 && $d >= 21) || $m === 4 || $m === 5 || ($m === 6 && $d < 21)) return 'primavera';
    if (($m === 6 && $d >= 21) || $m === 7 || $m === 8 || ($m === 9 && $d < 23)) return 'verano';
    if (($m === 9 && $d >= 23) || $m === 10 || $m === 11 || ($m === 12 && $d < 21)) return 'otono';
    return 'invierno';
}

/* ---------- Tema claro / oscuro ---------- */

const TEMAS = ['auto' => 'Auto', 'claro' => 'Claro', 'oscuro' => 'Oscuro'];

/** Tema del usuario conectado; si no hay sesión, el de la cookie */
function tema_actual(?array $usuario): string
{
    $t = $usuario['tema'] ?? ($_COOKIE['tema'] ?? 'auto');
    return array_key_exists($t, TEMAS) ? $t : 'auto';
}

/** Selector de tres botones (Auto / Claro / Oscuro). Lo maneja app.js */
function selector_tema(string $actual): string
{
    $iconos = [
        'auto'   => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'claro'  => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'oscuro' => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
    ];
    $html = '<div class="tema-selector" role="group" aria-label="Apariencia">';
    foreach (TEMAS as $clave => $texto) {
        $html .= '<button type="button" data-tema="' . $clave . '" aria-pressed="' . ($clave === $actual ? 'true' : 'false') . '">'
            . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $iconos[$clave] . '</svg>'
            . $texto . '</button>';
    }
    return $html . '</div>';
}
