<?php
// Ajustes de la cuenta y preferencias del usuario
require __DIR__ . '/includes/bootstrap.php';

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    switch ($_POST['accion'] ?? '') {
        case 'perfil':
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') {
                $errores[] = 'El nombre no puede quedar vacío.';
                break;
            }
            $pdo->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')->execute([mb_substr($nombre, 0, 60), $UID]);
            flash('success', 'Nombre actualizado');
            redirigir('ajustes.php');

        case 'preferencias':
            $dias = (int)($_POST['dias_olvido'] ?? 90);
            $dias = in_array($dias, [30, 60, 90, 180, 365], true) ? $dias : 90;
            $temp = !empty($_POST['sugerir_temporada']) ? 1 : 0;
            $pdo->prepare('UPDATE usuarios SET dias_olvido = ?, sugerir_temporada = ? WHERE id = ?')->execute([$dias, $temp, $UID]);
            flash('success', 'Preferencias guardadas');
            redirigir('ajustes.php');

        case 'sesiones':
            $pdo->prepare('UPDATE usuarios SET clave_version = clave_version + 1 WHERE id = ?')->execute([$UID]);
            $_SESSION['clave_version'] = (int)$USUARIO['clave_version'] + 1;
            session_regenerate_id(true);
            flash('success', 'Se han cerrado tus otras sesiones');
            redirigir('ajustes.php');
    }
}

$st = $pdo->prepare('SELECT ultimo_acceso FROM usuarios WHERE id = ?');
$st->execute([$UID]);
$ultimo = $st->fetchColumn();

$st = $pdo->prepare("SELECT fecha, ip, exito FROM accesos WHERE usuario_id = ? AND (motivo IS NULL OR motivo NOT IN ('desbloqueo', 'instalacion')) ORDER BY id DESC LIMIT 5");
$st->execute([$UID]);
$misAccesos = $st->fetchAll();

$titulo = 'Ajustes';
$activo = 'ajustes';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>Ajustes</h1>
    <p>Tu cuenta y cómo se comporta la app contigo.</p>
  </div>
</header>

<?php if ($errores): ?>
  <div class="aviso" role="alert"><?php foreach ($errores as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="fila" style="align-items:flex-start">
  <section class="panel ajustes-bloque" style="flex:1 1 340px">
    <h2>Mi cuenta</h2>
    <form method="post" class="ajustes-form">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="perfil">
      <label class="campo">Nombre
        <input type="text" name="nombre" value="<?= h($USUARIO['nombre']) ?>" required maxlength="60">
      </label>
      <div class="campo">Email
        <span style="font-weight:500"><?= h($USUARIO['email']) ?></span>
        <small>Para cambiarlo, pídeselo al administrador.</small>
      </div>
      <div class="campo">Rol
        <span style="font-weight:500"><?= es_admin($USUARIO) ? 'Administrador' : 'Usuario' ?></span>
      </div>
      <button class="btn btn-primario" type="submit" style="align-self:flex-start">Guardar</button>
    </form>
  </section>

  <section class="panel ajustes-bloque" style="flex:1 1 340px">
    <h2>Preferencias</h2>
    <form method="post" class="ajustes-form">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="preferencias">
      <label class="campo">Considerar «olvidada» una prenda sin usar en
        <select name="dias_olvido">
          <?php foreach ([30 => '1 mes', 60 => '2 meses', 90 => '3 meses', 180 => '6 meses', 365 => '1 año'] as $d => $t): ?>
            <option value="<?= $d ?>"<?= (int)$USUARIO['dias_olvido'] === $d ? ' selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="interruptor">
        <input type="checkbox" name="sugerir_temporada" value="1"<?= $USUARIO['sugerir_temporada'] ? ' checked' : '' ?>>
        <span>Si no hay ciudad, sugerir solo ropa de la temporada actual (<?= h(TEMPORADAS[temporada_actual()]) ?>)</span>
      </label>
      <button class="btn btn-primario" type="submit" style="align-self:flex-start">Guardar</button>
    </form>
  </section>

  <section class="panel ajustes-bloque" id="ubicacion" style="flex:1 1 340px">
    <h2>El tiempo</h2>
    <div class="ajustes-form">
      <?php if ($USUARIO['latitud'] !== null): ?>
        <p style="margin:0">Ubicación: <strong><?= h($USUARIO['ciudad']) ?></strong></p>
      <?php else: ?>
        <p class="suave" style="margin:0">Sin ubicación: no se muestra el tiempo ni se sugiere ropa según el clima.</p>
      <?php endif; ?>
      <a class="btn" href="ubicacion.php" style="align-self:flex-start"><?= $USUARIO['latitud'] !== null ? 'Cambiar ubicación' : 'Elegir ubicación' ?></a>
    </div>
  </section>

  <section class="panel ajustes-bloque" style="flex:1 1 340px">
    <h2>Apariencia</h2>
    <div class="ajustes-form">
      <p class="suave" style="margin:0">Elige modo claro u oscuro, o «Auto» para seguir el de tu móvil u ordenador. Se guarda en tu cuenta.</p>
      <?= selector_tema(tema_actual($USUARIO)) ?>
    </div>
  </section>

  <section class="panel ajustes-bloque" style="flex:1 1 340px">
    <h2>Seguridad</h2>
    <div class="ajustes-form">
      <p class="suave" style="margin:0">Último acceso: <?= $ultimo ? date('d/m/Y H:i', strtotime($ultimo)) : '—' ?></p>
      <a class="btn" href="cambiar_clave.php" style="align-self:flex-start">Cambiar contraseña</a>
      <form method="post" data-confirmar="¿Cerrar tus otras sesiones?" data-texto="Tendrás que volver a entrar en tus otros dispositivos. Esta sesión sigue abierta." data-boton="Sí, cerrarlas">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="sesiones">
        <button class="btn" type="submit">Cerrar sesión en otros dispositivos</button>
      </form>
      <?php if ($misAccesos): ?>
        <div>
          <h3 class="mini-titulo">Tus últimos accesos</h3>
          <ul class="lista-simple">
            <?php foreach ($misAccesos as $a): ?>
              <li><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?> · <?= h($a['ip']) ?> · <?= $a['exito'] ? 'Correcto' : '<strong style="color:var(--peligro)">Fallido</strong>' ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="panel ajustes-bloque" style="flex:1 1 340px">
    <h2>Mis datos</h2>
    <div class="ajustes-form">
      <p class="suave" style="margin:0">Descarga tus prendas en un archivo que se abre con Excel.</p>
      <a class="btn" href="exportar.php" style="align-self:flex-start">Descargar mis prendas (CSV)</a>
      <?php if (es_admin($USUARIO)): ?>
        <a class="btn" href="usuarios.php" style="align-self:flex-start">Gestionar usuarios</a>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
