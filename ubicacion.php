<?php
// Elegir la ubicación para el tiempo: buscando la ciudad o con la ubicación del navegador
require __DIR__ . '/includes/bootstrap.php';

$errores = [];
$resultados = null;
$busqueda = trim($_GET['q'] ?? '');

if ($busqueda !== '') {
    if (mb_strlen($busqueda) < 2) {
        $errores[] = 'Escribe al menos dos letras.';
    } else {
        $resultados = buscar_ciudad($busqueda);
        if (!$resultados) {
            $errores[] = 'No he encontrado «' . $busqueda . '». Prueba con otro nombre (sin «provincia de», por ejemplo).';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $lat = (float)($_POST['lat'] ?? 0);
        $lon = (float)($_POST['lon'] ?? 0);
        $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 120);
        if (abs($lat) > 90 || abs($lon) > 180 || ($lat == 0 && $lon == 0)) {
            $errores[] = 'Esa ubicación no es válida.';
        } else {
            if ($nombre === '') {
                $nombre = 'Mi ubicación (' . number_format($lat, 2, ',', '') . ', ' . number_format($lon, 2, ',', '') . ')';
            }
            $pdo->prepare('UPDATE usuarios SET ciudad = ?, latitud = ?, longitud = ?, usar_tiempo = 1 WHERE id = ?')
                ->execute([$nombre, round($lat, 5), round($lon, 5), $UID]);
            flash('success', 'Ubicación guardada: ' . $nombre);
            redirigir('index.php');
        }
    } elseif ($accion === 'quitar') {
        $pdo->prepare('UPDATE usuarios SET ciudad = NULL, latitud = NULL, longitud = NULL WHERE id = ?')->execute([$UID]);
        flash('success', 'Ubicación quitada');
        redirigir('ajustes.php');
    } elseif ($accion === 'usar_tiempo') {
        $pdo->prepare('UPDATE usuarios SET usar_tiempo = ? WHERE id = ?')->execute([!empty($_POST['usar_tiempo']) ? 1 : 0, $UID]);
        flash('success', 'Preferencia guardada');
        redirigir('ubicacion.php');
    }
}

/** Formulario de un botón que guarda una ubicación */
function boton_ubicacion(string $nombre, string $detalle, float $lat, float $lon): string
{
    return '<form method="post" class="ubicacion-opcion">' . csrf_campo()
        . '<input type="hidden" name="accion" value="guardar">'
        . '<input type="hidden" name="lat" value="' . $lat . '"><input type="hidden" name="lon" value="' . $lon . '">'
        . '<input type="hidden" name="nombre" value="' . h($nombre) . '">'
        . '<button type="submit"><strong>' . h($nombre) . '</strong><span class="suave">' . h($detalle) . '</span></button></form>';
}

$titulo = 'Ubicación';
$activo = 'ajustes';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>¿Dónde estás?</h1>
    <p>Para mostrarte el tiempo y recomendarte ropa de abrigo o ligera.</p>
  </div>
  <a class="btn" href="index.php">Volver</a>
</header>

<?php if ($errores): ?>
  <div class="aviso" role="alert"><?php foreach ($errores as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="fila" style="align-items:flex-start">
  <section class="panel" style="flex:2 1 420px;min-width:0;display:flex;flex-direction:column;gap:18px">
    <?php if ($USUARIO['latitud'] !== null): ?>
      <p style="margin:0">Ahora mismo: <strong><?= h($USUARIO['ciudad']) ?></strong></p>
    <?php endif; ?>

    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <label class="campo" style="flex:1 1 220px">Busca tu ciudad o pueblo
        <input type="search" name="q" value="<?= h($busqueda) ?>" placeholder="Ej.: Sevilla, Dos Hermanas, Triana…" maxlength="80" required autofocus>
      </label>
      <button class="btn btn-primario" type="submit">Buscar</button>
    </form>

    <?php if ($resultados): ?>
      <div>
        <h2 class="mini-titulo">Elige la correcta</h2>
        <div class="ubicacion-lista">
          <?php foreach ($resultados as $r): ?>
            <?= boton_ubicacion($r['nombre'] . ($r['region'] && $r['region'] !== $r['nombre'] ? ', ' . $r['region'] : ''), $r['detalle'], $r['lat'], $r['lon']) ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div id="geo" class="geo" hidden>
      <h2 class="mini-titulo">O usa la ubicación de este dispositivo</h2>
      <form method="post" id="form-geo">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="lat" id="geo-lat"><input type="hidden" name="lon" id="geo-lon"><input type="hidden" name="nombre" value="">
        <button type="button" class="btn" id="btn-geo">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="7"/></svg>
          Usar mi ubicación actual
        </button>
      </form>
    </div>
    <p id="geo-no" class="suave" style="margin:0;font-size:14px" hidden>
      La ubicación automática solo funciona si la app se abre con <strong>https</strong>. Mientras tanto, búscala por nombre: es igual de precisa.
    </p>
  </section>

  <?php if ($USUARIO['latitud'] !== null): ?>
  <section class="panel" style="flex:1 1 280px;min-width:0;display:flex;flex-direction:column;gap:16px">
    <form method="post" class="ajustes-form">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="usar_tiempo">
      <label class="interruptor">
        <input type="checkbox" name="usar_tiempo" value="1"<?= $USUARIO['usar_tiempo'] ? ' checked' : '' ?>>
        <span>Sugerir ropa según el tiempo</span>
      </label>
      <button class="btn btn-peque" type="submit" style="align-self:flex-start">Guardar</button>
    </form>
    <form method="post" data-confirmar="¿Quitar tu ubicación?" data-texto="Dejarás de ver el tiempo en Inicio." data-boton="Sí, quitarla">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="quitar">
      <button class="btn btn-peque btn-peligro" type="submit">Quitar ubicación</button>
    </form>
    <small class="suave">Datos del tiempo: Open-Meteo.com</small>
  </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
