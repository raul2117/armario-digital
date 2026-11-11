<?php
/**
 * Rellena el armario con datos de prueba: prendas, outfits e historial de usos de los últimos meses.
 *
 * Uso (en la Raspberry, por SSH):
 *   sudo php /var/www/html/armario/herramientas/datos_prueba.php                  → crea/rehace el usuario «prueba@armario.local»
 *   sudo php /var/www/html/armario/herramientas/datos_prueba.php --email=tu@correo → añade datos a un usuario que ya existe
 *   sudo php /var/www/html/armario/herramientas/datos_prueba.php --borrar          → borra el usuario de prueba y sus datos
 *   Añade --ciudad="Sevilla" para darle ubicación (si no, copia la del primer administrador).
 *
 * Solo se puede ejecutar desde la terminal, nunca desde el navegador.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo desde la terminal.');
}

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/funciones.php';
require __DIR__ . '/../includes/tiempo.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$opciones = getopt('', ['email:', 'borrar', 'ciudad:']);
const EMAIL_PRUEBA = 'prueba@armario.local';

// ---------- Borrar el usuario de prueba ----------
if (isset($opciones['borrar'])) {
    $n = $pdo->prepare('DELETE FROM usuarios WHERE email = ?');
    $n->execute([EMAIL_PRUEBA]);
    echo $n->rowCount() ? "Usuario de prueba borrado con todos sus datos.\n" : "No había usuario de prueba.\n";
    exit(0);
}

// ---------- Usuario destino ----------
$clave = null;
if (!empty($opciones['email'])) {
    $st = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE email = ?');
    $st->execute([mb_strtolower(trim($opciones['email']))]);
    $u = $st->fetch();
    if (!$u) {
        fwrite(STDERR, "No existe ningún usuario con el email {$opciones['email']}.\n");
        exit(1);
    }
    $uid = (int)$u['id'];
    echo "Añadiendo datos de prueba a {$u['nombre']}…\n";
} else {
    $pdo->prepare('DELETE FROM usuarios WHERE email = ?')->execute([EMAIL_PRUEBA]);   // se rehace desde cero
    $clave = generar_clave();
    // Ubicación: la de --ciudad, o la del primer administrador que tenga una
    $ubic = $pdo->query("SELECT ciudad, latitud, longitud FROM usuarios WHERE rol = 'admin' AND latitud IS NOT NULL ORDER BY id LIMIT 1")->fetch() ?: ['ciudad' => null, 'latitud' => null, 'longitud' => null];
    if (!empty($opciones['ciudad'])) {
        $r = buscar_ciudad($opciones['ciudad'])[0] ?? null;
        if ($r) {
            $ubic = ['ciudad' => $r['nombre'] . ($r['region'] ? ', ' . $r['region'] : ''), 'latitud' => $r['lat'], 'longitud' => $r['lon']];
        } else {
            echo "Aviso: no encontré la ciudad «{$opciones['ciudad']}»; el usuario queda sin ubicación.\n";
        }
    }
    $pdo->prepare("INSERT INTO usuarios (nombre, email, clave_hash, rol, ciudad, latitud, longitud) VALUES ('Lucía (prueba)', ?, ?, 'usuario', ?, ?, ?)")
        ->execute([EMAIL_PRUEBA, password_hash($clave, PASSWORD_DEFAULT), $ubic['ciudad'], $ubic['latitud'], $ubic['longitud']]);
    echo $ubic['ciudad'] ? "Ubicación: {$ubic['ciudad']}\n" : "Sin ubicación (elígela al entrar o usa --ciudad=...).\n";
    $uid = (int)$pdo->lastInsertId();
    echo "Creado el usuario de prueba.\n";
}

// ---------- Categorías ----------
$cats = [];
foreach ($pdo->query('SELECT id, slug FROM categorias') as $c) {
    $cats[$c['slug']] = (int)$c['id'];
}

// nombre, categoría, color, temporada, marca, favorita
$prendas = [
    ['Camiseta blanca básica', 'camisetas', '#FFFFFF', 'todo', 'Uniqlo', 1],
    ['Camiseta marinera', 'camisetas', '#DCE7FF', 'primavera', null, 1],
    ['Camiseta negra', 'camisetas', '#1E1B3A', 'todo', null, 0],
    ['Camiseta de lino verde', 'camisetas', '#7FC8A9', 'verano', null, 0],
    ['Top de tirantes coral', 'camisetas', '#F26B4A', 'verano', null, 0],
    ['Jersey de punto lila', 'jerseis', '#B9A4F2', 'invierno', null, 1],
    ['Jersey de cuello alto gris', 'jerseis', '#8E8AA0', 'invierno', null, 0],
    ['Sudadera mostaza', 'jerseis', '#E8B23A', 'otono', null, 0],
    ['Cárdigan beige', 'jerseis', '#E6D2A8', 'otono', null, 0],
    ['Camisa vaquera', 'camisas', '#7FA3D1', 'primavera', "Levi's", 0],
    ['Camisa blanca de oficina', 'camisas', '#FFFFFF', 'todo', null, 0],
    ['Camisa de cuadros', 'camisas', '#C0504D', 'otono', null, 0],
    ['Vestido midi floral', 'vestidos', '#F28DAA', 'primavera', null, 1],
    ['Vestido negro', 'vestidos', '#2B2840', 'todo', null, 0],
    ['Vestido de verano amarillo', 'vestidos', '#FFD15C', 'verano', null, 0],
    ['Vaquero recto', 'pantalones', '#7FA3D1', 'todo', "Levi's", 1],
    ['Vaquero negro', 'pantalones', '#2B2840', 'todo', null, 0],
    ['Chino beige', 'pantalones', '#E6D2A8', 'primavera', null, 0],
    ['Pantalón de traje gris', 'pantalones', '#8E8AA0', 'invierno', null, 0],
    ['Shorts vaqueros', 'pantalones', '#A9C4E8', 'verano', null, 0],
    ['Falda plisada verde', 'faldas', '#5FC08F', 'primavera', null, 0],
    ['Falda de punto marrón', 'faldas', '#8B5E3C', 'invierno', null, 0],
    ['Abrigo de paño camel', 'abrigos', '#C8A27A', 'invierno', null, 1],
    ['Plumífero negro', 'abrigos', '#1E1B3A', 'invierno', null, 0],
    ['Gabardina', 'abrigos', '#D9B98A', 'otono', null, 0],
    ['Cazadora vaquera', 'abrigos', '#7FA3D1', 'primavera', null, 0],
    ['Chaqueta de punto fina', 'abrigos', '#ECE6FF', 'primavera', null, 0],
    ['Zapatillas blancas', 'calzado', '#FFFFFF', 'todo', 'Adidas', 1],
    ['Botines negros', 'calzado', '#2B2840', 'invierno', null, 0],
    ['Botas de agua', 'calzado', '#F2C84B', 'otono', null, 0],
    ['Sandalias', 'calzado', '#C8A27A', 'verano', null, 0],
    ['Mocasines marrones', 'calzado', '#8B5E3C', 'todo', null, 0],
    ['Bolso de mimbre', 'accesorios', '#E8C27A', 'verano', null, 0],
    ['Mochila coral', 'accesorios', '#F26B4A', 'todo', null, 0],
    ['Bufanda de lana', 'accesorios', '#B9A4F2', 'invierno', null, 0],
    ['Gafas de sol', 'accesorios', '#1E1B3A', 'verano', null, 0],
    ['Gorro de lana', 'accesorios', '#E6D2A8', 'invierno', null, 0],
];

$pdo->beginTransaction();

$ins = $pdo->prepare('INSERT INTO prendas (usuario_id, nombre, categoria_id, color_hex, temporada, marca, favorita, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$ids = [];        // slug de categoría => [ids]
$porNombre = [];
foreach ($prendas as [$nombre, $slug, $color, $temp, $marca, $fav]) {
    if (!isset($cats[$slug])) continue;
    // Fechas de alta repartidas en los últimos 6 meses
    $alta = date('Y-m-d H:i:s', strtotime('-' . random_int(20, 180) . ' days -' . random_int(0, 600) . ' minutes'));
    $ins->execute([$uid, $nombre, $cats[$slug], $color, $temp, $marca, $fav, $alta]);
    $id = (int)$pdo->lastInsertId();
    $ids[$slug][] = $id;
    $porNombre[$nombre] = $id;
}
// Un par de prendas recién añadidas y sin estrenar
foreach ([['Camisa de rayas nueva', 'camisas', '#DCE7FF', 'primavera'], ['Botas camel', 'calzado', '#C8A27A', 'invierno']] as [$nombre, $slug, $color, $temp]) {
    $ins->execute([$uid, $nombre, $cats[$slug], $color, $temp, null, 0, date('Y-m-d H:i:s', strtotime('-2 days'))]);
}

// ---------- Historial de usos: unos 4 días de cada 7, durante 4 meses ----------
$usoIns = $pdo->prepare('INSERT IGNORE INTO usos (usuario_id, prenda_id, fecha) VALUES (?, ?, ?)');
$elegir = function (array $slugs) use ($ids) {
    $pool = [];
    foreach ($slugs as $s) $pool = array_merge($pool, $ids[$s] ?? []);
    return $pool ? $pool[array_rand($pool)] : null;
};
$usos = 0;
for ($d = 120; $d >= 1; $d--) {
    if (random_int(1, 7) > 4) continue;
    $fecha = date('Y-m-d', strtotime("-$d days"));
    $mes = (int)date('n', strtotime($fecha));
    $frio = in_array($mes, [11, 12, 1, 2, 3], true);
    $conjunto = random_int(1, 6) === 1
        ? [$elegir(['vestidos'])]
        : [$elegir($frio ? ['jerseis', 'camisas', 'camisetas'] : ['camisetas', 'camisas']), $elegir(['pantalones', 'faldas'])];
    if ($frio) $conjunto[] = $elegir(['abrigos']);
    $conjunto[] = $elegir(['calzado']);
    if (random_int(1, 3) === 1) $conjunto[] = $elegir(['accesorios']);
    // Las favoritas salen más a menudo
    if (random_int(1, 2) === 1) $conjunto[] = $porNombre['Vaquero recto'] ?? null;
    foreach (array_unique(array_filter($conjunto)) as $pid) {
        $usoIns->execute([$uid, $pid, $fecha]);
        $usos += $usoIns->rowCount();
    }
}
// Contador y última vez a partir del historial
$pdo->prepare('UPDATE prendas p
               LEFT JOIN (SELECT prenda_id, COUNT(*) n, MAX(fecha) ultima FROM usos WHERE usuario_id = ? GROUP BY prenda_id) u ON u.prenda_id = p.id
               SET p.veces_usada = COALESCE(u.n, 0), p.ultima_vez = u.ultima
               WHERE p.usuario_id = ?')->execute([$uid, $uid]);

// ---------- Outfits ----------
$outfits = [
    ['Oficina viernes', 'trabajo', 'otono', ['arriba' => 'Camisa blanca de oficina', 'abajo' => 'Vaquero recto', 'abrigo' => 'Gabardina', 'calzado' => 'Mocasines marrones'], '+1 day'],
    ['Domingo de paseo', 'casual', 'primavera', ['arriba' => 'Camiseta marinera', 'abajo' => 'Chino beige', 'abrigo' => 'Cazadora vaquera', 'calzado' => 'Zapatillas blancas', 'complemento' => 'Mochila coral'], '+3 days'],
    ['Frío de verdad', 'casual', 'invierno', ['arriba' => 'Jersey de cuello alto gris', 'abajo' => 'Vaquero negro', 'abrigo' => 'Plumífero negro', 'calzado' => 'Botines negros', 'complemento' => 'Bufanda de lana'], 'today'],
    ['Cena con amigos', 'fiesta', 'todo', ['arriba' => 'Vestido negro', 'abrigo' => 'Abrigo de paño camel', 'calzado' => 'Botines negros'], '+6 days'],
    ['Playa', 'casual', 'verano', ['arriba' => 'Top de tirantes coral', 'abajo' => 'Shorts vaqueros', 'calzado' => 'Sandalias', 'complemento' => 'Gafas de sol'], null],
    ['Reunión importante', 'trabajo', 'invierno', ['arriba' => 'Camisa blanca de oficina', 'abajo' => 'Pantalón de traje gris', 'abrigo' => 'Abrigo de paño camel', 'calzado' => 'Mocasines marrones'], '+9 days'],
    ['Primavera floral', 'casual', 'primavera', ['arriba' => 'Vestido midi floral', 'abrigo' => 'Chaqueta de punto fina', 'calzado' => 'Zapatillas blancas', 'complemento' => 'Bolso de mimbre'], null],
    ['Día de lluvia', 'casual', 'otono', ['arriba' => 'Sudadera mostaza', 'abajo' => 'Vaquero recto', 'abrigo' => 'Gabardina', 'calzado' => 'Botas de agua'], null],
];
$insO = $pdo->prepare('INSERT INTO outfits (usuario_id, nombre, ocasion, temporada, fecha) VALUES (?, ?, ?, ?, ?)');
$insOP = $pdo->prepare('INSERT INTO outfit_prendas (outfit_id, prenda_id, hueco) VALUES (?, ?, ?)');
$nOutfits = 0;
foreach ($outfits as [$nombre, $ocasion, $temp, $huecos, $cuando]) {
    $insO->execute([$uid, $nombre, $ocasion, $temp, $cuando ? date('Y-m-d', strtotime($cuando)) : null]);
    $oid = (int)$pdo->lastInsertId();
    foreach ($huecos as $hueco => $prenda) {
        if (isset($porNombre[$prenda])) $insOP->execute([$oid, $porNombre[$prenda], $hueco]);
    }
    $nOutfits++;
}

$pdo->commit();

echo "Listo: " . (count($porNombre) + 2) . " prendas, $nOutfits outfits y $usos usos en los últimos 4 meses.\n";
if ($clave) {
    echo "\n  Email:      " . EMAIL_PRUEBA . "\n  Contraseña: $clave\n\n";
    echo "Apunta la contraseña: solo se muestra ahora. Para quitar el usuario de prueba: --borrar\n";
}
