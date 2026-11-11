<?php
// Calendario: outfits programados y prendas que te has puesto cada día
require __DIR__ . '/includes/bootstrap.php';

$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
$dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'] ?? '') ? $_GET['dia'] : (date('Y-m') === $mes ? date('Y-m-d') : $mes . '-01');
if (substr($dia, 0, 7) !== $mes) {
    $mes = substr($dia, 0, 7);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha'] ?? '') ? $_POST['fecha'] : null;
    $oid = (int)($_POST['outfit_id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'programar' && $fecha && $oid) {
        $pdo->prepare('UPDATE outfits SET fecha = ? WHERE id = ? AND usuario_id = ?')->execute([$fecha, $oid, $UID]);
        flash('success', 'Outfit programado');
    } elseif (($_POST['accion'] ?? '') === 'quitar' && $oid) {
        $pdo->prepare('UPDATE outfits SET fecha = NULL WHERE id = ? AND usuario_id = ?')->execute([$oid, $UID]);
        flash('success', 'Quitado del calendario');
    }
    redirigir('calendario.php?dia=' . urlencode($fecha ?: $dia));
}

$inicio = $mes . '-01';
$fin = date('Y-m-t', strtotime($inicio));

// Outfits programados en el mes
$st = $pdo->prepare('SELECT id, nombre, ocasion, fecha FROM outfits WHERE usuario_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha, id');
$st->execute([$UID, $inicio, $fin]);
$programados = [];
foreach ($st as $o) {
    $programados[$o['fecha']][] = $o;
}

// Prendas usadas en el mes
$st = $pdo->prepare('SELECT p.*, c.nombre AS cat_nombre, c.color AS cat_color, c.icono, u.fecha AS fecha_uso
                     FROM usos u JOIN prendas p ON p.id = u.prenda_id JOIN categorias c ON c.id = p.categoria_id
                     WHERE u.usuario_id = ? AND u.fecha BETWEEN ? AND ? ORDER BY u.fecha, c.orden');
$st->execute([$UID, $inicio, $fin]);
$usadas = [];
foreach ($st as $p) {
    $usadas[$p['fecha_uso']][] = $p;
}

// Rejilla: semanas de lunes a domingo
$primerDia = (int)date('N', strtotime($inicio));   // 1 = lunes
$diasMes = (int)date('t', strtotime($inicio));
$celdas = array_fill(0, $primerDia - 1, null);
for ($d = 1; $d <= $diasMes; $d++) {
    $celdas[] = sprintf('%s-%02d', $mes, $d);
}
while (count($celdas) % 7) {
    $celdas[] = null;
}

$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$tituloMes = $meses[(int)substr($mes, 5, 2)] . ' ' . substr($mes, 0, 4);
$anterior = date('Y-m', strtotime($inicio . ' -1 month'));
$siguiente = date('Y-m', strtotime($inicio . ' +1 month'));

// Outfits para programar en el día elegido
$st = $pdo->prepare('SELECT id, nombre, fecha FROM outfits WHERE usuario_id = ? ORDER BY nombre');
$st->execute([$UID]);
$todosOutfits = $st->fetchAll();

// Prendas de los outfits del día elegido
$delDia = $programados[$dia] ?? [];
$prendasOutfit = [];
if ($delDia) {
    $st = $pdo->prepare(SQL_PRENDAS . ' JOIN outfit_prendas op ON op.prenda_id = p.id WHERE op.outfit_id = ? AND p.usuario_id = ?');
    foreach ($delDia as $o) {
        $st->execute([$o['id'], $UID]);
        $prendasOutfit[$o['id']] = $st->fetchAll();
    }
}

$titulo = 'Calendario';
$activo = 'calendario';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>Calendario</h1>
    <p>Planifica tus outfits y mira qué te has puesto cada día.</p>
  </div>
  <div class="acciones">
    <a class="btn btn-icono" href="calendario.php?mes=<?= $anterior ?>" aria-label="Mes anterior"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></a>
    <strong style="font-family:var(--titulos);font-size:22px;min-width:180px;text-align:center"><?= $tituloMes ?></strong>
    <a class="btn btn-icono" href="calendario.php?mes=<?= $siguiente ?>" aria-label="Mes siguiente"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
    <a class="btn" href="calendario.php">Hoy</a>
  </div>
</header>

<div class="fila" style="align-items:flex-start">
  <section class="panel" style="flex:2 1 560px;min-width:0" aria-label="<?= $tituloMes ?>">
    <div class="cal">
      <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $n): ?><div class="cal-cab"><?= $n ?></div><?php endforeach; ?>
      <?php foreach ($celdas as $f): ?>
        <?php if (!$f): ?><div class="cal-vacio"></div><?php continue; endif;
          $clases = 'cal-dia' . ($f === date('Y-m-d') ? ' hoy' : '') . ($f === $dia ? ' elegido' : ''); ?>
        <a class="<?= $clases ?>" href="calendario.php?dia=<?= $f ?>"<?= $f === $dia ? ' aria-current="date"' : '' ?>>
          <span class="cal-num"><?= (int)substr($f, 8) ?></span>
          <?php foreach ($programados[$f] ?? [] as $o): ?><span class="cal-outfit"><?= h($o['nombre']) ?></span><?php endforeach; ?>
          <?php if (!empty($usadas[$f])): ?>
            <span class="cal-puntos" aria-label="<?= count($usadas[$f]) ?> prendas usadas">
              <?php foreach (array_slice($usadas[$f], 0, 5) as $p): ?><span class="punto" style="background:<?= h($p['color_hex']) ?>"></span><?php endforeach; ?>
            </span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel" style="flex:1 1 300px;min-width:0;display:flex;flex-direction:column;gap:18px" aria-label="Día elegido">
    <h2 style="font-size:22px"><?= h(ucfirst(fecha_larga($dia))) ?></h2>

    <div>
      <h3 class="mini-titulo">Programado</h3>
      <?php if (!$delDia): ?><p class="suave">Nada programado.</p><?php endif; ?>
      <?php foreach ($delDia as $o): ?>
        <div class="outfit-card" style="margin-bottom:14px">
          <strong><?= h($o['nombre']) ?></strong>
          <div class="outfit-mini">
            <?php foreach ($prendasOutfit[$o['id']] ?? [] as $p): ?><?= miniatura($p, 30) ?><?php endforeach; ?>
          </div>
          <div class="prenda-botones">
            <?php if ($dia === date('Y-m-d')): ?>
              <form method="post" action="usar.php">
                <?= csrf_campo() ?><input type="hidden" name="volver" value="calendario.php">
                <?php foreach ($prendasOutfit[$o['id']] ?? [] as $p): ?><input type="hidden" name="prendas[]" value="<?= (int)$p['id'] ?>"><?php endforeach; ?>
                <button class="btn btn-peque btn-primario" type="submit">Me lo pongo</button>
              </form>
            <?php endif; ?>
            <form method="post">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="quitar"><input type="hidden" name="fecha" value="<?= h($dia) ?>"><input type="hidden" name="outfit_id" value="<?= (int)$o['id'] ?>">
              <button class="btn btn-peque" type="submit">Quitar</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($todosOutfits): ?>
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
          <?= csrf_campo() ?><input type="hidden" name="accion" value="programar"><input type="hidden" name="fecha" value="<?= h($dia) ?>">
          <label class="campo" style="flex:1 1 180px">Programar un outfit
            <select name="outfit_id">
              <?php foreach ($todosOutfits as $o): ?><option value="<?= (int)$o['id'] ?>"><?= h($o['nombre']) ?><?= $o['fecha'] ? ' (' . date('d/m', strtotime($o['fecha'])) . ')' : '' ?></option><?php endforeach; ?>
            </select>
          </label>
          <button class="btn btn-primario" type="submit">Programar</button>
        </form>
      <?php else: ?>
        <p class="suave"><a href="outfit.php">Crea un outfit</a> para poder programarlo.</p>
      <?php endif; ?>
    </div>

    <div>
      <h3 class="mini-titulo">Lo que llevaste</h3>
      <?php if (empty($usadas[$dia])): ?>
        <p class="suave">No hay prendas apuntadas este día.</p>
      <?php else: ?>
        <div class="outfit-mini">
          <?php foreach ($usadas[$dia] as $p): ?><a href="prenda.php?id=<?= (int)$p['id'] ?>" title="<?= h($p['nombre']) ?>"><?= miniatura($p, 30) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
