<?php
// Estadísticas del armario del usuario
require __DIR__ . '/includes/bootstrap.php';

function uno(PDO $pdo, string $sql, array $args)
{
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}
function todos(PDO $pdo, string $sql, array $args): array
{
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

$total      = (int)uno($pdo, 'SELECT COUNT(*) FROM prendas WHERE usuario_id = ?', [$UID]);
$sinEstrenar = (int)uno($pdo, 'SELECT COUNT(*) FROM prendas WHERE usuario_id = ? AND veces_usada = 0', [$UID]);
$usos30     = (int)uno($pdo, 'SELECT COUNT(*) FROM usos WHERE usuario_id = ? AND fecha >= CURDATE() - INTERVAL 30 DAY', [$UID]);
$usadas90   = (int)uno($pdo, 'SELECT COUNT(*) FROM prendas WHERE usuario_id = ? AND ultima_vez >= CURDATE() - INTERVAL 90 DAY', [$UID]);
$rotacion   = $total ? round($usadas90 * 100 / $total) : 0;

$porCategoria = todos($pdo, 'SELECT c.nombre, c.color, COUNT(p.id) AS n FROM categorias c LEFT JOIN prendas p ON p.categoria_id = c.id AND p.usuario_id = ? GROUP BY c.id ORDER BY n DESC, c.orden', [$UID]);
$maxCat = max(1, ...array_map(fn($r) => (int)$r['n'], $porCategoria ?: [['n' => 1]]));

// Usos por mes (últimos 6 meses, incluidos los meses sin usos)
$filas = todos($pdo, "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, COUNT(*) AS n FROM usos WHERE usuario_id = ? AND fecha >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY mes", [$UID]);
$porMesBD = array_column($filas, 'n', 'mes');
$nombresMes = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$porMes = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
    $porMes[] = ['etiqueta' => $nombresMes[(int)substr($m, 5)], 'n' => (int)($porMesBD[$m] ?? 0)];
}
$maxMes = max(1, ...array_column($porMes, 'n'));

$masUsadas = todos($pdo, SQL_PRENDAS . ' WHERE p.usuario_id = ? AND p.veces_usada > 0 ORDER BY p.veces_usada DESC, p.ultima_vez DESC LIMIT 5', [$UID]);
$olvidadas = todos($pdo, SQL_PRENDAS . ' WHERE p.usuario_id = ? AND p.creado_en < NOW() - INTERVAL 30 DAY ORDER BY p.veces_usada ASC, p.ultima_vez IS NOT NULL, p.ultima_vez ASC LIMIT 5', [$UID]);
$colores = todos($pdo, 'SELECT color_hex, COUNT(*) AS n FROM prendas WHERE usuario_id = ? GROUP BY color_hex ORDER BY n DESC LIMIT 10', [$UID]);
$porTemporada = todos($pdo, 'SELECT temporada, COUNT(*) AS n FROM prendas WHERE usuario_id = ? GROUP BY temporada ORDER BY n DESC', [$UID]);

$titulo = 'Estadísticas';
$activo = 'estadisticas';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>Estadísticas</h1>
    <p>Cómo usas tu armario.</p>
  </div>
</header>

<?php if (!$total): ?>
  <section class="panel vacio">
    <h2>Aún no hay datos</h2>
    <p>Añade prendas y pulsa «Me lo pongo» cuando las uses: aquí verás cómo rota tu armario.</p>
    <a class="btn btn-primario" href="prenda.php">Añadir prenda</a>
  </section>
<?php else: ?>

<section class="kpis">
  <div class="stat" style="background:var(--melocoton)"><span>Prendas</span><strong><?= $total ?></strong></div>
  <div class="stat" style="background:var(--menta)"><span>Usos en 30 días</span><strong><?= $usos30 ?></strong></div>
  <div class="stat" style="background:var(--morado-suave)"><span>Armario en uso (90 días)</span><strong><?= $rotacion ?>%</strong><span style="font-weight:500"><?= $usadas90 ?> de <?= $total ?> prendas</span></div>
  <div class="stat" style="background:var(--vainilla)"><span>Sin estrenar</span><strong><?= $sinEstrenar ?></strong></div>
</section>

<div class="fila" style="align-items:stretch">
  <section class="panel" style="flex:1 1 380px;min-width:0">
    <h2 class="mini-titulo" style="font-size:20px">Prendas por categoría</h2>
    <div class="barras">
      <?php foreach ($porCategoria as $c): ?>
        <div class="barra-fila">
          <span class="barra-etq"><?= h($c['nombre']) ?></span>
          <span class="barra-pista"><span class="barra" style="width:<?= round($c['n'] * 100 / $maxCat) ?>%"></span></span>
          <span class="barra-num"><?= (int)$c['n'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel" style="flex:1 1 380px;min-width:0">
    <h2 class="mini-titulo" style="font-size:20px">Usos por mes</h2>
    <div class="columnas" role="img" aria-label="Usos por mes: <?= h(implode(', ', array_map(fn($m) => $m['etiqueta'] . ' ' . $m['n'], $porMes))) ?>">
      <?php foreach ($porMes as $m): ?>
        <div class="columna">
          <span class="barra-num"><?= $m['n'] ?></span>
          <span class="columna-barra" style="height:<?= max(2, round($m['n'] * 100 / $maxMes)) ?>%"></span>
          <span class="barra-etq"><?= $m['etiqueta'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<div class="fila" style="align-items:stretch">
  <section class="panel" style="flex:1 1 320px;min-width:0">
    <h2 class="mini-titulo" style="font-size:20px">Tus favoritas de verdad</h2>
    <?php if (!$masUsadas): ?><p class="suave">Todavía no has apuntado ningún uso.</p><?php endif; ?>
    <ol class="ranking">
      <?php foreach ($masUsadas as $p): ?>
        <li><?= miniatura($p, 28) ?><a href="prenda.php?id=<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?></a><span class="usos"><?= (int)$p['veces_usada'] ?> usos</span></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="panel" style="flex:1 1 320px;min-width:0">
    <h2 class="mini-titulo" style="font-size:20px">Las que menos usas</h2>
    <?php if (!$olvidadas): ?><p class="suave">Aparecerán las prendas con más de un mes en tu armario.</p><?php endif; ?>
    <ol class="ranking">
      <?php foreach ($olvidadas as $p): ?>
        <li><?= miniatura($p, 28) ?><a href="prenda.php?id=<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?></a><span class="usos"><?= $p['veces_usada'] ? (int)$p['veces_usada'] . ' usos' : 'Sin estrenar' ?></span></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="panel" style="flex:1 1 260px;min-width:0">
    <h2 class="mini-titulo" style="font-size:20px">Tus colores</h2>
    <div class="colores">
      <?php foreach ($colores as $c): ?>
        <span class="color-pastilla"><span class="punto" style="background:<?= h($c['color_hex']) ?>;width:22px;height:22px"></span><?= (int)$c['n'] ?></span>
      <?php endforeach; ?>
    </div>
    <h2 class="mini-titulo" style="font-size:20px;margin-top:22px">Por temporada</h2>
    <div class="etiquetas">
      <?php foreach ($porTemporada as $t): ?><span><?= h(TEMPORADAS[$t['temporada']] ?? $t['temporada']) ?> · <?= (int)$t['n'] ?></span><?php endforeach; ?>
    </div>
  </section>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
