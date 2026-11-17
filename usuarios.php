<?php
// Gestión de usuarios (solo administradores)
require __DIR__ . '/includes/bootstrap.php';
requerir_admin($USUARIO);

function admins_activos(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1")->fetchColumn();
}

function cargar_usuario(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

$errores = [];
$form = ['nombre' => '', 'email' => '', 'rol' => 'usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $u = $id ? cargar_usuario($pdo, $id) : null;
    $esYo = $id === $UID;

    if ($accion !== 'crear' && !$u) {
        flash('error', 'Ese usuario no existe');
        redirigir('usuarios.php');
    }

    switch ($accion) {
        case 'crear':
        case 'editar':
            $form = [
                'nombre' => trim($_POST['nombre'] ?? ''),
                'email'  => mb_strtolower(trim($_POST['email'] ?? '')),
                'rol'    => ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'usuario',
            ];
            if ($form['nombre'] === '') $errores[] = 'Escribe el nombre.';
            if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es válido.';
            $st = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?');
            $st->execute([$form['email'], $id]);
            if ($st->fetch()) $errores[] = 'Ya hay otro usuario con ese email.';
            if ($accion === 'editar' && $u['rol'] === 'admin' && $form['rol'] !== 'admin') {
                if ($esYo) $errores[] = 'No puedes quitarte a ti mismo el rol de administrador.';
                elseif (admins_activos($pdo) <= 1) $errores[] = 'Tiene que quedar al menos un administrador.';
            }
            if ($errores) break;

            if ($accion === 'crear') {
                $temporal = generar_clave();
                $pdo->prepare('INSERT INTO usuarios (nombre, email, clave_hash, rol, debe_cambiar_clave) VALUES (?, ?, ?, ?, 1)')
                    ->execute([mb_substr($form['nombre'], 0, 60), $form['email'], password_hash($temporal, PASSWORD_DEFAULT), $form['rol']]);
                flash('success', 'Usuario ' . $form['nombre'] . ' creado', $temporal);
            } else {
                $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?')
                    ->execute([mb_substr($form['nombre'], 0, 60), $form['email'], $form['rol'], $id]);
                flash('success', 'Cambios guardados');
            }
            redirigir('usuarios.php');

        case 'activar':
            $activar = $u['activo'] ? 0 : 1;
            if (!$activar && $esYo) {
                flash('error', 'No puedes desactivar tu propia cuenta');
            } elseif (!$activar && $u['rol'] === 'admin' && admins_activos($pdo) <= 1) {
                flash('error', 'Tiene que quedar al menos un administrador activo');
            } else {
                // Al desactivar se sube la versión de clave: se cierran sus sesiones abiertas
                $pdo->prepare('UPDATE usuarios SET activo = ?, clave_version = clave_version + ? WHERE id = ?')->execute([$activar, $activar ? 0 : 1, $id]);
                flash('success', $activar ? 'Usuario activado' : 'Usuario desactivado');
            }
            redirigir('usuarios.php');

        case 'clave':
            if ($esYo) {
                redirigir('cambiar_clave.php');
            }
            $temporal = generar_clave();
            $pdo->prepare('UPDATE usuarios SET clave_hash = ?, debe_cambiar_clave = 1, clave_version = clave_version + 1 WHERE id = ?')
                ->execute([password_hash($temporal, PASSWORD_DEFAULT), $id]);
            registrar_acceso($pdo, $id, $u['email'], true, 'desbloqueo');
            flash('success', 'Nueva contraseña para ' . $u['nombre'], $temporal);
            redirigir('usuarios.php');

        case 'desbloquear':
            // Un registro de desbloqueo pone a cero los fallos de ese email en todos los equipos
            registrar_acceso($pdo, $id, $u['email'], true, 'desbloqueo');
            flash('success', 'Cuenta desbloqueada');
            redirigir('usuarios.php');

        case 'sesiones':
            $pdo->prepare('UPDATE usuarios SET clave_version = clave_version + 1 WHERE id = ?')->execute([$id]);
            if ($esYo) {
                $_SESSION['clave_version'] = (int)$USUARIO['clave_version'] + 1;
            }
            flash('success', 'Sesiones cerradas en todos sus dispositivos');
            redirigir('usuarios.php');

        case 'borrar':
            if ($esYo) {
                flash('error', 'No puedes borrar tu propia cuenta');
            } elseif ($u['rol'] === 'admin' && admins_activos($pdo) <= 1) {
                flash('error', 'Tiene que quedar al menos un administrador');
            } else {
                // Fotos de sus prendas
                $st = $pdo->prepare('SELECT foto FROM prendas WHERE usuario_id = ? AND foto IS NOT NULL');
                $st->execute([$id]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $foto) {
                    @unlink(UPLOAD_DIR . basename($foto));
                }
                $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                flash('success', 'Usuario y todo su armario eliminados');
            }
            redirigir('usuarios.php');
    }
}

$editar = isset($_GET['editar']) ? cargar_usuario($pdo, (int)$_GET['editar']) : null;
if ($errores && ($_POST['accion'] ?? '') === 'editar') {
    $editar = cargar_usuario($pdo, (int)$_POST['id']);
}
if ($editar && !$errores) {
    $form = ['nombre' => $editar['nombre'], 'email' => $editar['email'], 'rol' => $editar['rol']];
}

$usuarios = $pdo->query('SELECT u.*, (SELECT COUNT(*) FROM prendas p WHERE p.usuario_id = u.id) AS n_prendas
                         FROM usuarios u ORDER BY u.rol = "admin" DESC, u.nombre')->fetchAll();
$accesos = $pdo->query('SELECT a.*, u.nombre FROM accesos a LEFT JOIN usuarios u ON u.id = a.usuario_id ORDER BY a.id DESC LIMIT 25')->fetchAll();
$motivos = ['clave' => 'Contraseña incorrecta', 'no_existe' => 'Email desconocido', 'bloqueado' => 'Cuenta bloqueada', 'inactivo' => 'Cuenta desactivada', 'ip_bloqueada' => 'Equipo bloqueado', 'instalacion' => 'Instalación', 'desbloqueo' => 'Desbloqueo / clave nueva'];

function cuando(?string $f): string
{
    if (!$f) return 'Nunca';
    $t = strtotime($f);
    if (date('Y-m-d', $t) === date('Y-m-d')) return 'Hoy, ' . date('H:i', $t);
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) return 'Ayer, ' . date('H:i', $t);
    return date('d/m/Y H:i', $t);
}

const ICONOS_ACCION = [
    'clave'       => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'activar'     => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
    'desactivar'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
    'desbloquear' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
    'sesiones'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11"/>',
    'borrar'      => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>',
];

/** Acción del usuario: botón grande con icono, título y explicación (mini formulario POST) */
function accion(string $accion, string $icono, int $id, string $titulo, string $explicacion, bool $peligro = false, string $confirmar = '', string $detalle = ''): string
{
    $conf = $confirmar ? ' data-confirmar="' . h($confirmar) . '" data-texto="' . h($detalle) . '" data-boton="Sí, continuar"' : '';
    return '<form method="post"' . $conf . '>' . csrf_campo()
        . '<input type="hidden" name="accion" value="' . $accion . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="accion' . ($peligro ? ' accion-peligro' : '') . '" type="submit">'
        . '<span class="accion-icono"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ICONOS_ACCION[$icono] . '</svg></span>'
        . '<span class="accion-texto"><strong>' . h($titulo) . '</strong><small>' . h($explicacion) . '</small></span>'
        . '</button></form>';
}

$titulo = 'Usuarios';
$activo = 'usuarios';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>Usuarios</h1>
    <p><?= count($usuarios) ?> cuentas · solo el administrador puede darlas de alta</p>
  </div>
  <?php if ($editar): ?>
    <a class="btn btn-primario" href="usuarios.php#form-usuario"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Nuevo usuario</a>
  <?php endif; ?>
</header>

<?php if ($errores): ?>
  <div class="aviso" role="alert"><?php foreach ($errores as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="fila" style="align-items:flex-start">
  <section class="panel" style="flex:2 1 560px;min-width:0" aria-label="Lista de usuarios">
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Prendas</th><th><span class="sr">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u):
            $bloq = (bool)ips_bloqueadas($pdo, $u['email']); $yo = (int)$u['id'] === $UID; ?>
          <tr<?= $editar && (int)$editar['id'] === (int)$u['id'] ? ' class="fila-activa"' : '' ?>>
            <td>
              <strong><?= h($u['nombre']) ?></strong><?= $yo ? ' <span class="badge">Tú</span>' : '' ?><br>
              <small><?= h($u['email']) ?></small>
            </td>
            <td><span class="badge<?= $u['rol'] === 'admin' ? ' badge-morado' : '' ?>"><?= $u['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?></span></td>
            <td>
              <?php if (!$u['activo']): ?><span class="badge badge-gris">Desactivado</span>
              <?php elseif ($bloq): ?><span class="badge badge-coral">Bloqueado</span>
              <?php elseif ($u['debe_cambiar_clave']): ?><span class="badge badge-amarillo">Clave temporal</span>
              <?php else: ?><span class="badge badge-verde">Activo</span><?php endif; ?>
            </td>
            <td><small><?= h(cuando($u['ultimo_acceso'])) ?></small></td>
            <td><?= (int)$u['n_prendas'] ?></td>
            <td>
              <a class="btn btn-peque<?= $editar && (int)$editar['id'] === (int)$u['id'] ? ' btn-primario' : '' ?>" href="usuarios.php?editar=<?= (int)$u['id'] ?>#form-usuario">Gestionar</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel ficha" id="form-usuario" style="flex:1 1 320px;min-width:0" aria-label="<?= $editar ? 'Gestionar usuario' : 'Nuevo usuario' ?>">
    <?php if ($editar):
        $ipsBloqE = ips_bloqueadas($pdo, $editar['email']);
        $bloqE = (bool)$ipsBloqE;
        $yoE = (int)$editar['id'] === $UID; $idE = (int)$editar['id']; ?>
      <div class="ficha-cabecera">
        <span class="avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($editar['nombre'], 0, 1))) ?></span>
        <div style="flex:1;min-width:0">
          <h2 style="font-size:20px"><?= h($editar['nombre']) ?></h2>
          <small class="suave"><?= h($editar['email']) ?></small>
        </div>
        <a class="btn btn-icono btn-peque" href="usuarios.php" aria-label="Cerrar ficha"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
      </div>
    <?php else: ?>
      <h2 style="font-size:20px;margin-bottom:16px">Nuevo usuario</h2>
    <?php endif; ?>

    <form method="post" style="display:flex;flex-direction:column;gap:16px">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
      <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>"><?php endif; ?>
      <label class="campo">Nombre
        <input type="text" name="nombre" value="<?= h($form['nombre']) ?>" required maxlength="60">
      </label>
      <label class="campo">Email
        <input type="text" inputmode="email" name="email" value="<?= h($form['email']) ?>" required autocomplete="off">
      </label>
      <label class="campo">Rol
        <select name="rol">
          <option value="usuario"<?= $form['rol'] === 'usuario' ? ' selected' : '' ?>>Usuario</option>
          <option value="admin"<?= $form['rol'] === 'admin' ? ' selected' : '' ?>>Administrador</option>
        </select>
      </label>
      <?php if (!$editar): ?>
        <p style="margin:0;font-size:14px;color:var(--suave)">Se generará una contraseña temporal que verás una sola vez. Al entrar por primera vez tendrá que cambiarla.</p>
      <?php endif; ?>
      <button class="btn btn-primario" type="submit" style="align-self:flex-start"><?= $editar ? 'Guardar datos' : 'Crear usuario' ?></button>
    </form>

    <?php if ($editar): ?>
      <h3 class="mini-titulo" style="margin-top:26px">Acciones</h3>
      <div class="acciones-lista">
        <?php if ($yoE): ?>
          <p class="suave" style="margin:0 0 6px;font-size:14px">Es tu cuenta: tu contraseña se cambia en <a href="cambiar_clave.php">Cambiar contraseña</a>.</p>
        <?php else: ?>
          <?= accion('clave', 'clave', $idE, 'Generar contraseña nueva', 'Crea una temporal y cierra sus sesiones', false, '¿Generar una contraseña nueva para ' . $editar['nombre'] . '?', 'La actual dejará de funcionar.') ?>
          <?php if ($editar['activo']): ?>
            <?= accion('activar', 'desactivar', $idE, 'Desactivar cuenta', 'No podrá entrar; sus datos se conservan', false, '¿Desactivar a ' . $editar['nombre'] . '?', 'Se cerrarán sus sesiones. Podrás activarla después.') ?>
          <?php else: ?>
            <?= accion('activar', 'activar', $idE, 'Activar cuenta', 'Podrá volver a entrar con su contraseña') ?>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($bloqE): ?>
          <?= accion('desbloquear', 'desbloquear', $idE, 'Desbloquear', 'Bloqueada por intentos fallidos desde ' . implode(', ', $ipsBloqE)) ?>
        <?php endif; ?>
        <?= accion('sesiones', 'sesiones', $idE, 'Cerrar sesiones abiertas', $yoE ? 'Saldrás en tus otros dispositivos' : 'Tendrá que volver a entrar en todos sus dispositivos') ?>
        <?php if (!$yoE): ?>
          <?= accion('borrar', 'borrar', $idE, 'Borrar usuario', 'Elimina la cuenta y todo su armario', true, '¿Borrar a ' . $editar['nombre'] . '?', 'Se borrarán también todas sus prendas, fotos y outfits. No se puede deshacer.') ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="panel">
  <h2 style="font-size:20px;margin-bottom:6px">Últimos accesos</h2>
  <p style="margin:0 0 14px;color:var(--suave);font-size:14px">Tras <?= MAX_INTENTOS ?> fallos seguidos desde un mismo equipo, ese equipo no puede entrar con esa cuenta durante <?= MINUTOS_BLOQUEO ?> minutos (desde los demás sí). Se guardan 90 días.</p>
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr><th>Fecha</th><th>Email</th><th>IP</th><th>Resultado</th></tr></thead>
      <tbody>
      <?php foreach ($accesos as $a): ?>
        <tr>
          <td><small><?= h(cuando($a['fecha'])) ?></small></td>
          <td><?= h($a['email']) ?></td>
          <td><small><?= h($a['ip']) ?></small></td>
          <td><?php if ($a['motivo'] === 'desbloqueo'): ?><span class="badge"><?= h($motivos['desbloqueo']) ?></span>
              <?php elseif ($a['exito']): ?><span class="badge badge-verde">Correcto</span>
              <?php else: ?><span class="badge badge-coral"><?= h($motivos[$a['motivo']] ?? 'Fallido') ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$accesos): ?><tr><td colspan="4" style="color:var(--suave)">Sin registros todavía.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
