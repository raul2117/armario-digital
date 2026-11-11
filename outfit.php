<?php
// Crear outfits y ver los guardados.
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'borrar') {
        $pdo->prepare('DELETE FROM outfits WHERE id = ? AND usuario_id = ?')->execute([(int)($_POST['id'] ?? 0), $UID]);
        flash('success', 'Outfit eliminado');
        redirigir('outfit.php');
    }

    if ($accion === 'guardar') {
        $huecos = [];
        foreach (array_keys(HUECOS) as $h) {
            $pid = (int)($_POST['hueco'][$h] ?? 0);
            if ($pid) {
                $huecos[$h] = $pid;
            }
        }
        $nombre = trim($_POST['nombre'] ?? '') ?: 'Outfit del ' . date('j/n');
        $ocasion = array_key_exists($_POST['ocasion'] ?? '', OCASIONES) ? $_POST['ocasion'] : 'casual';
        $temporada = array_key_exists($_POST['temporada'] ?? '', TEMPORADAS) ? $_POST['temporada'] : 'todo';
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha'] ?? '') ? $_POST['fecha'] : null;

        if (count($huecos) < 2) {
            flash('info', 'Elige al menos dos prendas');
            redirigir('outfit.php');
        }
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO outfits (usuario_id, nombre, ocasion, temporada, fecha) VALUES (?, ?, ?, ?, ?)')
            ->execute([$UID, mb_substr($nombre, 0, 100), $ocasion, $temporada, $fecha]);
        $oid = (int)$pdo->lastInsertId();
        $st = $pdo->prepare('INSERT INTO outfit_prendas (outfit_id, prenda_id, hueco) SELECT ?, id, ? FROM prendas WHERE id = ? AND usuario_id = ?');
        foreach ($huecos as $h => $pid) {
            $st->execute([$oid, $h, $pid, $UID]);
        }
        $pdo->commit();
        flash('success', '¡Outfit guardado!');
        redirigir('outfit.php#guardados');
    }
}

// Prendas para el creador, agrupadas por hueco
$st = $pdo->prepare(SQL_PRENDAS . ' WHERE p.usuario_id = ? ORDER BY c.orden, p.nombre');
$st->execute([$UID]);
$prendas = $st->fetchAll();

// Outfits guardados con sus prendas
$st = $pdo->prepare('SELECT * FROM outfits WHERE usuario_id = ? ORDER BY (fecha IS NULL), fecha DESC, id DESC');
$st->execute([$UID]);
$outfits = $st->fetchAll();
$porOutfit = [];
$st = $pdo->prepare('SELECT op.outfit_id, op.hueco AS hueco_outfit, p.*, c.nombre AS cat_nombre, c.color AS cat_color, c.icono
                     FROM outfit_prendas op JOIN prendas p ON p.id = op.prenda_id JOIN categorias c ON c.id = p.categoria_id
                     WHERE p.usuario_id = ?');
$st->execute([$UID]);
foreach ($st as $row) {
    $porOutfit[$row['outfit_id']][$row['hueco_outfit']] = $row;
}

$titulo = 'Outfits';
$activo = 'outfits';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>Crear outfit</h1>
    <p>Toca una prenda y se coloca en su hueco. Toca un hueco para vaciarlo.</p>
  </div>
</header>

<?php if (!$prendas): ?>
  <section class="panel vacio">
    <h2>Primero necesitas prendas</h2>
    <p>Añade algunas a tu armario y vuelve para combinarlas.</p>
    <a class="btn btn-primario" href="prenda.php">Añadir prenda</a>
  </section>
<?php else: ?>
<form id="form-outfit" class="creador" method="post">
  <?= csrf_campo() ?>
  <input type="hidden" name="accion" value="guardar">

  <section class="panel pool" aria-label="Tus prendas">
    <h2 style="font-size:20px;margin-bottom:14px">Tus prendas</h2>
    <div class="pool-rejilla">
      <?php foreach ($prendas as $p): ?>
        <button type="button" class="pool-item" data-id="<?= (int)$p['id'] ?>" data-hueco="<?= h($p['hueco']) ?>" data-nombre="<?= h($p['nombre']) ?>" aria-label="Añadir <?= h($p['nombre']) ?>" title="<?= h($p['nombre']) ?>">
          <?= miniatura($p, 48) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="lienzo" aria-label="Tu outfit">
    <div class="huecos">
      <?php foreach (HUECOS as $clave => $texto): ?>
        <button type="button" class="hueco" data-hueco="<?= $clave ?>" aria-label="Hueco <?= $texto ?>">
          <input type="hidden" name="hueco[<?= $clave ?>]" value="">
          <span class="hueco-contenido" style="display:flex;flex-direction:column;align-items:center;gap:8px">
            <span class="hueco-vacio"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span>
          </span>
          <span class="tipo"><?= $texto ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <button type="button" id="vaciar" class="btn btn-peque" style="align-self:flex-start">Vaciar</button>
  </section>

  <section class="panel detalles" aria-label="Detalles del outfit">
    <label class="campo">Nombre
      <input type="text" name="nombre" placeholder="Ej.: Paseo de domingo" maxlength="100">
    </label>
    <fieldset class="campo" style="border:0;padding:0;margin:0">
      <legend style="margin-bottom:8px">Ocasión</legend>
      <div class="chips" style="flex-wrap:wrap;margin:0;padding:0">
        <?php $i = 0; foreach (OCASIONES as $k => $t): ?>
          <label class="chip-radio"><input type="radio" name="ocasion" value="<?= $k ?>"<?= $i++ === 0 ? ' checked' : '' ?>><span class="chip" style="--chip-bg:var(--fondo)"><?= $t ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label class="campo">Temporada
      <select name="temporada">
        <?php foreach (TEMPORADAS as $k => $t): ?><option value="<?= $k ?>"><?= $t ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="campo">Programar para <small>(opcional: saldrá en Inicio ese día)</small>
      <input type="date" name="fecha">
    </label>
    <button class="btn btn-primario" type="submit" style="justify-content:center">Guardar outfit</button>
  </section>
</form>
<?php endif; ?>

<section id="guardados">
  <h2 style="margin-bottom:16px">Tus outfits (<?= count($outfits) ?>)</h2>
  <?php if (!$outfits): ?>
    <p style="color:var(--suave)">Todavía no has guardado ninguno.</p>
  <?php else: ?>
  <div class="outfits-lista">
    <?php foreach ($outfits as $o): $ps = $porOutfit[$o['id']] ?? []; ?>
      <article class="panel outfit-card">
        <div class="outfit-mini">
          <?php foreach (array_keys(HUECOS) as $hk): ?>
            <?= isset($ps[$hk]) ? miniatura($ps[$hk], 32) : '<div class="tile" style="background:var(--fondo)"></div>' ?>
          <?php endforeach; ?>
        </div>
        <div>
          <h3 style="font-size:19px"><?= h($o['nombre']) ?></h3>
          <div class="etiquetas" style="margin-top:8px">
            <span><?= h(OCASIONES[$o['ocasion']] ?? $o['ocasion']) ?></span>
            <span><?= h(TEMPORADAS[$o['temporada']] ?? $o['temporada']) ?></span>
            <?php if ($o['fecha']): ?><span style="background:var(--vainilla)"><?= h(ucfirst(fecha_larga($o['fecha']))) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="prenda-botones">
          <form method="post" action="usar.php">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="outfit.php">
            <?php foreach ($ps as $p): ?><input type="hidden" name="prendas[]" value="<?= (int)$p['id'] ?>"><?php endforeach; ?>
            <button class="btn btn-peque btn-primario" type="submit">Me lo pongo</button>
          </form>
          <form method="post" data-confirmar="¿Borrar este outfit?" data-texto="Las prendas se quedan en tu armario.">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="borrar">
            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <button class="btn btn-peque btn-peligro" type="submit">Borrar</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
