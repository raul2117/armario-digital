<?php
require __DIR__ . '/includes/bootstrap.php';

/** Ejecuta una consulta preparada y devuelve el primer valor */
function valor(PDO $pdo, string $sql, array $args = [])
{
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}

$total     = (int)valor($pdo, 'SELECT COUNT(*) FROM prendas WHERE usuario_id = ?', [$UID]);
$nuevasMes = (int)valor($pdo, "SELECT COUNT(*) FROM prendas WHERE usuario_id = ? AND creado_en >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", [$UID]);
$nOutfits  = (int)valor($pdo, 'SELECT COUNT(*) FROM outfits WHERE usuario_id = ?', [$UID]);
$diasOlvido = (int)$USUARIO['dias_olvido'];
$olvidadas = (int)valor($pdo, 'SELECT COUNT(*) FROM prendas WHERE usuario_id = ? AND (ultima_vez IS NULL OR ultima_vez < CURDATE() - INTERVAL ? DAY)', [$UID, $diasOlvido]);

$tiempo = tiempo_para_usuario($USUARIO);

// Sugerencia del día: una prenda al azar por hueco (si hay outfit programado para hoy, se usa ese)
$st = $pdo->prepare('SELECT id, nombre FROM outfits WHERE usuario_id = ? AND fecha = CURDATE() ORDER BY id DESC LIMIT 1');
$st->execute([$UID]);
$hoy = $st->fetch();
$sugerencia = [];
if ($hoy) {
    $st = $pdo->prepare(SQL_PRENDAS . " JOIN outfit_prendas op ON op.prenda_id = p.id WHERE op.outfit_id = ? AND p.usuario_id = ? ORDER BY FIELD(op.hueco, 'arriba', 'abajo', 'abrigo', 'calzado', 'complemento')");
    $st->execute([$hoy['id'], $UID]);
    $sugerencia = $st->fetchAll();
    $tituloSugerencia = $hoy['nombre'];
    $etiquetaSugerencia = 'Programado para hoy';
} else {
    // Si hay ciudad configurada, la sugerencia se basa en el tiempo; si no, en la temporada
    if ($tiempo && empty($tiempo['error'])) {
        $temporadas = $tiempo['rec']['temporadas'];
        $huecos = $tiempo['rec']['abrigo'] ? ['abrigo', 'arriba', 'abajo', 'calzado', 'complemento'] : ['arriba', 'abajo', 'calzado', 'complemento'];
        // Categorías preferidas según el nivel de frío o calor
        $preferidas = [
            'frio' => ['jerseis', 'pantalones'], 'fresco' => ['jerseis', 'camisas', 'pantalones'],
            'templado' => ['camisas', 'camisetas', 'pantalones'], 'agradable' => ['camisetas', 'faldas', 'vestidos'],
            'calor' => ['camisetas', 'vestidos', 'faldas'],
        ][$tiempo['rec']['nivel']] ?? [];
    } else {
        $temporadas = $USUARIO['sugerir_temporada'] ? ['todo', temporada_actual()] : [];
        $huecos = ['arriba', 'abajo', 'calzado', 'complemento'];
        $preferidas = [];
    }
    $filtro = $temporadas ? ' AND p.temporada IN (' . implode(',', array_fill(0, count($temporadas), '?')) . ')' : '';
    $orden = $preferidas ? ' ORDER BY FIELD(c.slug, ' . implode(',', array_fill(0, count($preferidas), '?')) . ') > 0 DESC, RAND()' : ' ORDER BY RAND()';
    $conFiltro = $pdo->prepare(SQL_PRENDAS . ' WHERE p.usuario_id = ? AND c.hueco = ?' . $filtro . $orden . ' LIMIT 1');
    $sinFiltro = $pdo->prepare(SQL_PRENDAS . ' WHERE p.usuario_id = ? AND c.hueco = ?' . $orden . ' LIMIT 1');
    $vistoVestido = false;
    foreach ($huecos as $hueco) {
        if ($hueco === 'abajo' && $vistoVestido) continue;   // con vestido no hace falta parte de abajo
        $conFiltro->execute(array_merge([$UID, $hueco], $temporadas, $preferidas));
        $p = $conFiltro->fetch();
        if (!$p && $hueco !== 'abrigo') {                     // si no hay de la temporada, cualquiera
            $sinFiltro->execute(array_merge([$UID, $hueco], $preferidas));
            $p = $sinFiltro->fetch();
        }
        if ($p) {
            $sugerencia[] = $p;
            $vistoVestido = $vistoVestido || $p['cat_slug'] === 'vestidos';
        }
    }
    $tituloSugerencia = $tiempo && empty($tiempo['error']) ? $tiempo['rec']['titulo'] : 'Idea para hoy';
    $etiquetaSugerencia = $tiempo && empty($tiempo['error']) ? 'Sugerencia para ' . $tiempo['rec']['para'] : 'Sugerencia';
}

$st = $pdo->prepare(SQL_PRENDAS . ' WHERE p.usuario_id = ? ORDER BY p.creado_en DESC LIMIT 6');
$st->execute([$UID]);
$recientes = $st->fetchAll();
$st = $pdo->prepare('SELECT c.nombre, c.slug, c.color, COUNT(p.id) AS n FROM categorias c LEFT JOIN prendas p ON p.categoria_id = c.id AND p.usuario_id = ? GROUP BY c.id ORDER BY c.orden');
$st->execute([$UID]);
$categorias = $st->fetchAll();

$titulo = 'Inicio';
$activo = 'inicio';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>¡Hola, <?= h($USUARIO['nombre']) ?>!</h1>
    <p>¿Qué te apetece ponerte hoy? · <?= h(ucfirst(fecha_larga())) ?></p>
  </div>
  <div class="acciones">
    <form class="buscador" action="armario.php" role="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <label for="q" class="sr">Buscar prendas</label>
      <input id="q" type="search" name="q" placeholder="Buscar en tu armario…">
    </form>
    <a class="btn btn-primario" href="prenda.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Añadir prenda</a>
  </div>
</header>

<?php if ($total === 0): ?>
  <section class="panel vacio">
    <h2>Tu armario está vacío</h2>
    <p>Empieza añadiendo tus prendas favoritas: una foto, su categoría y su color.</p>
    <a class="btn btn-primario" href="prenda.php">Añadir la primera prenda</a>
  </section>
<?php else: ?>

<section class="fila">
  <div class="sugerencia">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px">
      <div>
        <span class="etiqueta"><?= h($etiquetaSugerencia) ?></span>
        <h2><?= h($tituloSugerencia) ?></h2>
      </div>
      <?php if ($tiempo && !empty($tiempo['ahora'])): ?>
        <span class="pastilla"><?= icono_tiempo($tiempo['ahora']['icono'], 20) ?><?= $tiempo['ahora']['temp'] ?>° · <?= h($tiempo['ahora']['texto']) ?></span>
      <?php endif; ?>
    </div>
    <div class="sugerencia-prendas">
      <?php foreach ($sugerencia as $p): ?>
        <div>
          <?= miniatura(['cat_color' => '#FFFFFF'] + $p, 64, 'tile tile-blanco') ?>
          <span><?= h($p['nombre']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="acciones">
      <form method="post" action="usar.php">
        <?= csrf_campo() ?>
        <?php foreach ($sugerencia as $p): ?>
          <input type="hidden" name="prendas[]" value="<?= (int)$p['id'] ?>">
        <?php endforeach; ?>
        <button class="btn btn-blanco" type="submit">Me lo pongo</button>
      </form>
      <?php if (!$hoy): ?>
        <a class="btn btn-linea" href="index.php">Otra idea</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="stats">
    <div class="stat" style="background: var(--melocoton)">
      <span>Prendas en tu armario</span>
      <strong><?= $total ?></strong>
      <span style="font-weight:500">+<?= $nuevasMes ?> este mes</span>
    </div>
    <div class="stat" style="background: var(--menta)">
      <span>Outfits guardados</span>
      <strong><?= $nOutfits ?></strong>
      <a href="outfit.php" style="font-weight:700;color:var(--tinta)">Crear uno nuevo →</a>
    </div>
    <div class="stat" style="background: var(--vainilla)">
      <span>Sin usar en <?= $diasOlvido >= 365 ? '1 año' : ($diasOlvido >= 60 ? ($diasOlvido / 30) . ' meses' : '1 mes') ?></span>
      <strong><?= $olvidadas ?></strong>
      <a href="armario.php?olvidadas=1" style="font-weight:700;color:var(--tinta)">Darles una vuelta →</a>
    </div>
  </div>
</section>

<?php if ($tiempo && empty($tiempo['error'])): ?>
<section class="panel tiempo" aria-label="El tiempo en <?= h($tiempo['ciudad']) ?>">
  <div class="tiempo-ahora">
    <?php if ($tiempo['ahora']): ?>
      <?= icono_tiempo($tiempo['ahora']['icono'], 56) ?>
      <div>
        <div class="tiempo-grados"><?= $tiempo['ahora']['temp'] ?>°</div>
        <div class="suave"><?= h($tiempo['ahora']['texto']) ?> · sensación <?= $tiempo['ahora']['sensacion'] ?>°</div>
        <a class="tiempo-lugar" href="ubicacion.php" title="Cambiar ubicación"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg><?= h($tiempo['ciudad']) ?> · cambiar</a>
      </div>
    <?php endif; ?>
  </div>
  <div class="tiempo-dias">
    <?php foreach ($tiempo['dias'] as $d): ?>
      <div class="tiempo-dia">
        <span class="tiempo-nombre"><?= h($d['nombre']) ?></span>
        <?= icono_tiempo($d['icono'], 32) ?>
        <span><strong><?= $d['max'] ?>°</strong> <span class="suave"><?= $d['min'] ?>°</span></span>
        <?php if ($d['lluvia'] >= 30): ?><span class="tiempo-lluvia"><?= $d['lluvia'] ?>%</span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="tiempo-consejo tiempo-<?= h($tiempo['rec']['nivel']) ?>">
    <strong><?= h($tiempo['rec']['titulo']) ?> <?= $tiempo['rec']['para'] === 'mañana' ? '(mañana)' : '' ?></strong>
    <span><?= h($tiempo['rec']['texto']) ?></span>
  </div>
</section>
<?php elseif ($tiempo && !empty($tiempo['error'])): ?>
  <p class="suave" style="margin:0">No se ha podido consultar el tiempo de <?= h($tiempo['ciudad']) ?> ahora mismo. <a href="ubicacion.php">Cambiar ubicación</a></p>
<?php elseif ($USUARIO['latitud'] === null): ?>
  <a class="panel aviso-tiempo" href="ubicacion.php"><?= icono_tiempo('sol-nube', 32) ?><span><strong>¿Te recomiendo ropa según el tiempo?</strong><br><span class="suave">Dime dónde estás y te digo si toca abrigo o ropa ligera.</span></span></a>
<?php endif; ?>

<section>
  <div class="cabecera" style="margin-bottom:16px">
    <h2>Añadidas recientemente</h2>
    <a href="armario.php" style="font-weight:700">Ver todo</a>
  </div>
  <div class="rejilla-peque">
    <?php foreach ($recientes as $p): ?>
      <a class="prenda" href="prenda.php?id=<?= (int)$p['id'] ?>" style="text-decoration:none;color:inherit">
        <?= miniatura($p, 60) ?>
        <div>
          <h3><?= h($p['nombre']) ?></h3>
          <div class="meta"><?= h($p['cat_nombre']) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section>
  <h2 style="margin-bottom:16px">Por categoría</h2>
  <div class="chips" style="flex-wrap:wrap">
    <?php foreach ($categorias as $c): ?>
      <a class="chip chip-pastel" href="armario.php?cat=<?= h($c['slug']) ?>" style="--chip-bg:<?= h($c['color']) ?>"><?= h($c['nombre']) ?><span class="num"><?= (int)$c['n'] ?></span></a>
    <?php endforeach; ?>
  </div>
</section>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
