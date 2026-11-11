<?php
require __DIR__ . '/includes/bootstrap.php';

$cat       = $_GET['cat'] ?? '';
$q         = trim($_GET['q'] ?? '');
$olvidadas = !empty($_GET['olvidadas']);
$favoritas = !empty($_GET['fav']);

$where = ['p.usuario_id = ?'];
$args = [$UID];
if ($cat !== '') {
    $where[] = 'c.slug = ?';
    $args[] = $cat;
}
if ($q !== '') {
    $where[] = '(p.nombre LIKE ? OR p.marca LIKE ? OR c.nombre LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
if ($olvidadas) {
    $where[] = '(p.ultima_vez IS NULL OR p.ultima_vez < CURDATE() - INTERVAL ? DAY)';
    $args[] = (int)$USUARIO['dias_olvido'];
}
if ($favoritas) {
    $where[] = 'p.favorita = 1';
}
$sql = SQL_PRENDAS . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.favorita DESC, p.creado_en DESC';
$st = $pdo->prepare($sql);
$st->execute($args);
$prendas = $st->fetchAll();

$categorias = $pdo->query('SELECT nombre, slug, color FROM categorias ORDER BY orden')->fetchAll();

$subtitulo = count($prendas) . (count($prendas) === 1 ? ' prenda' : ' prendas');
if ($olvidadas) $subtitulo .= ' sin usar en los últimos ' . (int)$USUARIO['dias_olvido'] . ' días';
if ($favoritas) $subtitulo .= ' favoritas';
if ($q !== '') $subtitulo .= ' que coinciden con «' . $q . '»';

$titulo = 'Mi armario';
$activo = 'armario';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>Mi armario</h1>
    <p><?= h($subtitulo) ?></p>
  </div>
  <div class="acciones">
    <form class="buscador" role="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <label for="q" class="sr">Buscar prendas</label>
      <input id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="Nombre, marca, categoría…">
      <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= h($cat) ?>"><?php endif; ?>
    </form>
    <a class="btn btn-primario" href="prenda.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Añadir prenda</a>
  </div>
</header>

<nav class="chips" aria-label="Categorías">
  <a class="chip<?= $cat === '' && !$favoritas && !$olvidadas ? ' activo' : '' ?>" href="armario.php">Todo</a>
  <a class="chip<?= $favoritas ? ' activo' : '' ?>" href="armario.php?fav=1" style="<?= $favoritas ? '' : '--chip-bg:var(--melocoton)' ?>">Favoritas</a>
  <?php foreach ($categorias as $c): $on = $cat === $c['slug']; ?>
    <a class="chip chip-pastel<?= $on ? ' activo' : '' ?>" href="armario.php?cat=<?= h($c['slug']) ?>" style="--chip-bg:<?= h($c['color']) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= h($c['nombre']) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$prendas): ?>
  <section class="panel vacio">
    <h2>No hay prendas por aquí</h2>
    <p>Prueba con otra categoría o añade una prenda nueva.</p>
    <a class="btn btn-primario" href="prenda.php">Añadir prenda</a>
  </section>
<?php else: ?>
  <div class="rejilla">
    <?php foreach ($prendas as $p): ?>
      <article class="prenda">
        <div style="position:relative">
          <?= miniatura($p, 88) ?>
          <button type="button" class="fav<?= $p['favorita'] ? ' on' : '' ?>" data-id="<?= (int)$p['id'] ?>" aria-pressed="<?= $p['favorita'] ? 'true' : 'false' ?>" aria-label="Favorita">
            <svg width="20" height="20" viewBox="0 0 24 24" stroke="#F26B4A" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9z"/></svg>
          </button>
        </div>
        <div>
          <h3><?= h($p['nombre']) ?></h3>
          <div class="meta">
            <span><span class="punto" style="background:<?= h($p['color_hex']) ?>"></span><?= h($p['cat_nombre']) ?></span>
            <span class="usos"><?= $p['veces_usada'] ? 'Usada ' . (int)$p['veces_usada'] . ($p['veces_usada'] == 1 ? ' vez' : ' veces') : 'Sin estrenar' ?></span>
          </div>
        </div>
        <div class="prenda-botones">
          <a class="btn btn-peque" href="prenda.php?id=<?= (int)$p['id'] ?>">Editar</a>
          <form method="post" action="usar.php">
            <?= csrf_campo() ?>
            <input type="hidden" name="prendas[]" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="volver" value="armario.php">
            <button class="btn btn-peque" type="submit">Me la pongo</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
