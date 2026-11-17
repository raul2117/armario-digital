<?php
// Alta, edición y borrado de una prenda.
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$prenda = null;
if ($id) {
    $st = $pdo->prepare(SQL_PRENDAS . ' WHERE p.id = ? AND p.usuario_id = ?');
    $st->execute([$id, $UID]);
    $prenda = $st->fetch();
    if (!$prenda) {
        flash('error', 'Esa prenda no existe');
        redirigir('armario.php');
    }
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY orden')->fetchAll();
$errores = [];

/** Guarda la foto subida, reducida a 1200 px si el servidor tiene GD. Devuelve el nombre de archivo. */
function guardar_foto(array $f, array &$errores): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > UPLOAD_MAX_MB * 1048576) {
        $errores[] = 'La foto pesa demasiado (máximo ' . UPLOAD_MAX_MB . ' MB).';
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'No se pudo subir la foto.';
        return null;
    }
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($tipos[$mime])) {
        $errores[] = 'La foto tiene que ser JPG, PNG o WEBP.';
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    // Resolución: una imagen pequeña en MB pero enorme en píxeles agotaría la memoria al abrirla
    $dim = @getimagesize($f['tmp_name']);
    if (!$dim || $dim[0] < 1 || $dim[1] < 1) {
        $errores[] = 'No se pudo leer la foto.';
        return null;
    }
    if ($dim[0] > FOTO_MAX_LADO || $dim[1] > FOTO_MAX_LADO || $dim[0] * $dim[1] > FOTO_MAX_MPX * 1000000) {
        $errores[] = 'La foto tiene demasiada resolución (máximo ' . FOTO_MAX_MPX . ' megapíxeles).';
        return null;
    }
    $nombre = date('Ymd') . '_' . bin2hex(random_bytes(6));

    // Con GD: girar según EXIF, reducir y guardar como JPG
    if (function_exists('imagecreatefromstring')) {
        // Memoria suficiente para la foto original y la reducida (unos 5 bytes por píxel con margen)
        $necesaria = (int)($dim[0] * $dim[1] * 5 + 64 * 1048576);
        if (memoria_en_bytes(ini_get('memory_limit')) < $necesaria) {
            @ini_set('memory_limit', (string)$necesaria);
        }
        $img = @imagecreatefromstring(file_get_contents($f['tmp_name']));
        if ($img) {
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($f['tmp_name']);
                $giro = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
                if ($giro) {
                    $img = imagerotate($img, $giro, 0);
                }
            }
            $w = imagesx($img);
            $hgt = imagesy($img);
            // Se pinta siempre sobre fondo blanco (los PNG transparentes no quedan en negro)
            $k = min(1, 1200 / max($w, $hgt));
            $nueva = imagecreatetruecolor(max(1, (int)($w * $k)), max(1, (int)($hgt * $k)));
            imagefill($nueva, 0, 0, imagecolorallocate($nueva, 255, 255, 255));
            imagecopyresampled($nueva, $img, 0, 0, 0, 0, imagesx($nueva), imagesy($nueva), $w, $hgt);
            imagedestroy($img);
            $img = $nueva;
            imagejpeg($img, UPLOAD_DIR . $nombre . '.jpg', 85);
            imagedestroy($img);
            return $nombre . '.jpg';
        }
    }
    // Sin GD: se guarda tal cual
    $archivo = $nombre . '.' . $tipos[$mime];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $archivo)) {
        $errores[] = 'No se pudo guardar la foto. Revisa los permisos de la carpeta uploads.';
        return null;
    }
    return $archivo;
}

function borrar_foto(?string $foto): void
{
    if ($foto && is_file(UPLOAD_DIR . basename($foto))) {
        @unlink(UPLOAD_DIR . basename($foto));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();

    if (($_POST['accion'] ?? '') === 'borrar' && $prenda) {
        $pdo->prepare('DELETE FROM prendas WHERE id = ? AND usuario_id = ?')->execute([$id, $UID]);
        borrar_foto($prenda['foto']);
        flash('success', 'Prenda eliminada');
        redirigir('armario.php');
    }

    $datos = [
        'nombre'       => trim($_POST['nombre'] ?? ''),
        'categoria_id' => (int)($_POST['categoria_id'] ?? 0),
        'color_hex'    => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color_hex'] ?? '') ? strtoupper($_POST['color_hex']) : '#FFFFFF',
        'temporada'    => array_key_exists($_POST['temporada'] ?? '', TEMPORADAS) ? $_POST['temporada'] : 'todo',
        'marca'        => trim($_POST['marca'] ?? '') ?: null,
        'notas'        => trim($_POST['notas'] ?? '') ?: null,
    ];
    if ($datos['nombre'] === '') {
        $errores[] = 'Ponle un nombre a la prenda.';
    }
    if (!in_array($datos['categoria_id'], array_map('intval', array_column($categorias, 'id')), true)) {
        $errores[] = 'Elige una categoría.';
    }

    $foto = $errores ? null : guardar_foto($_FILES['foto'] ?? [], $errores);

    if (!$errores) {
        if ($prenda) {
            $sql = 'UPDATE prendas SET nombre=:nombre, categoria_id=:categoria_id, color_hex=:color_hex, temporada=:temporada, marca=:marca, notas=:notas'
                 . ($foto ? ', foto=:foto' : '') . ' WHERE id=:id AND usuario_id=:uid';
            $datos['id'] = $id;
            $datos['uid'] = $UID;
            if ($foto) {
                $datos['foto'] = $foto;
            }
            $pdo->prepare($sql)->execute($datos);
            if ($foto) {
                borrar_foto($prenda['foto']);
            }
            flash('success', 'Cambios guardados');
        } else {
            $datos['foto'] = $foto;
            $datos['uid'] = $UID;
            $pdo->prepare('INSERT INTO prendas (usuario_id, nombre, categoria_id, color_hex, temporada, marca, notas, foto) VALUES (:uid, :nombre, :categoria_id, :color_hex, :temporada, :marca, :notas, :foto)')
                ->execute($datos);
            flash('success', '¡Prenda añadida a tu armario!');
        }
        redirigir(isset($_POST['otra']) ? 'prenda.php' : 'armario.php');
    }
    $prenda = array_merge($prenda ?? [], $datos);
}

$v = $prenda ?? ['nombre' => '', 'categoria_id' => $categorias[0]['id'] ?? 0, 'color_hex' => '#FFFFFF', 'temporada' => 'todo', 'marca' => '', 'notas' => '', 'foto' => null];

$titulo = $id ? 'Editar prenda' : 'Añadir prenda';
$activo = $id ? 'armario' : 'nueva';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1><?= $id ? 'Editar prenda' : 'Añadir prenda' ?></h1>
    <p><?= $id ? h($v['nombre']) : 'Una foto, su categoría y su color. ¡Listo!' ?></p>
  </div>
  <a class="btn" href="armario.php">Volver al armario</a>
</header>

<?php if ($errores): ?>
  <div class="aviso" role="alert">
    <strong>Revisa esto:</strong>
    <ul style="margin:8px 0 0"><?php foreach ($errores as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form class="form-prenda" method="post" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

  <div class="foto">
    <label class="subir">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
      <span>Haz una foto o elige una imagen</span>
      <small style="font-weight:500">JPG, PNG o WEBP · máx. <?= UPLOAD_MAX_MB ?> MB</small>
      <input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp" capture="environment">
      <?php if (!empty($v['foto'])): ?><img src="<?= h(UPLOAD_URL . $v['foto']) ?>" alt=""><?php endif; ?>
    </label>
  </div>

  <div class="datos panel">
    <label class="campo">Nombre
      <input type="text" name="nombre" value="<?= h($v['nombre']) ?>" placeholder="Ej.: Vaquero recto" required maxlength="100">
    </label>

    <fieldset class="campo" style="border:0;padding:0;margin:0">
      <legend style="margin-bottom:8px">Categoría</legend>
      <div class="chips" style="flex-wrap:wrap;margin:0;padding:0">
        <?php foreach ($categorias as $c): ?>
          <label class="chip-radio">
            <input type="radio" name="categoria_id" value="<?= (int)$c['id'] ?>"<?= (int)$v['categoria_id'] === (int)$c['id'] ? ' checked' : '' ?>>
            <span class="chip chip-pastel" style="--chip-bg:<?= h($c['color']) ?>"><?= h($c['nombre']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="fila">
      <label class="campo">Color
        <input type="color" name="color_hex" value="<?= h($v['color_hex']) ?>">
      </label>
      <label class="campo" style="flex:1 1 200px">Temporada
        <select name="temporada">
          <?php foreach (TEMPORADAS as $k => $t): ?>
            <option value="<?= $k ?>"<?= $v['temporada'] === $k ? ' selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="campo" style="flex:1 1 200px">Marca <small>(opcional)</small>
        <input type="text" name="marca" value="<?= h($v['marca']) ?>" maxlength="80">
      </label>
    </div>

    <label class="campo">Notas <small>(opcional)</small>
      <textarea name="notas" placeholder="Talla, dónde la compraste, cómo lavarla…"><?= h($v['notas']) ?></textarea>
    </label>

    <div class="acciones">
      <button class="btn btn-primario" type="submit">Guardar</button>
      <?php if (!$id): ?><button class="btn" type="submit" name="otra" value="1">Guardar y añadir otra</button><?php endif; ?>
    </div>
  </div>
</form>

<?php if ($id): ?>
  <form method="post" data-confirmar="¿Borrar esta prenda?" data-texto="También se quitará de los outfits donde aparezca.">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="accion" value="borrar">
    <button class="btn btn-peligro" type="submit">Borrar prenda</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
