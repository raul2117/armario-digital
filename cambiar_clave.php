<?php
require __DIR__ . '/includes/bootstrap.php';

$obligatorio = (bool)$USUARIO['debe_cambiar_clave'];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    $actual = (string)($_POST['actual'] ?? '');
    $nueva  = (string)($_POST['nueva'] ?? '');

    $st = $pdo->prepare('SELECT clave_hash FROM usuarios WHERE id = ?');
    $st->execute([$UID]);
    if (!password_verify($actual, (string)$st->fetchColumn())) {
        $errores[] = 'La contraseña actual no es correcta.';
    }
    if ($e = validar_clave($nueva, $USUARIO['email'])) $errores[] = $e;
    if ($nueva !== ($_POST['nueva2'] ?? '')) $errores[] = 'Las contraseñas nuevas no coinciden.';
    if ($nueva === $actual) $errores[] = 'La nueva contraseña tiene que ser distinta de la actual.';

    if (!$errores) {
        // Subir la versión cierra las demás sesiones abiertas de este usuario
        $pdo->prepare('UPDATE usuarios SET clave_hash = ?, debe_cambiar_clave = 0, clave_version = clave_version + 1 WHERE id = ?')
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), $UID]);
        $_SESSION['clave_version'] = (int)$USUARIO['clave_version'] + 1;
        session_regenerate_id(true);
        flash('success', 'Contraseña cambiada');
        redirigir($obligatorio ? 'index.php' : 'ajustes.php');
    }
}

$titulo = 'Cambiar contraseña';
$activo = 'ajustes';
require __DIR__ . '/includes/header.php';
?>
<header class="cabecera">
  <div>
    <h1>Cambiar contraseña</h1>
    <p><?= $obligatorio ? 'Estás usando una contraseña temporal. Elige una tuya para continuar.' : 'Se cerrará la sesión en tus otros dispositivos.' ?></p>
  </div>
</header>

<?php if ($errores): ?>
  <div class="aviso" role="alert"><?php foreach ($errores as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" class="panel" style="max-width:480px;display:flex;flex-direction:column;gap:16px">
  <?= csrf_campo() ?>
  <input type="text" name="usuario" value="<?= h($USUARIO['email']) ?>" autocomplete="username" hidden>
  <label class="campo"><?= $obligatorio ? 'Contraseña temporal' : 'Contraseña actual' ?>
    <input type="password" name="actual" required autocomplete="current-password">
  </label>
  <label class="campo">Nueva contraseña <small>(mínimo 10 caracteres, con letras y números)</small>
    <input type="password" name="nueva" required minlength="10" autocomplete="new-password">
  </label>
  <label class="campo">Repite la nueva contraseña
    <input type="password" name="nueva2" required minlength="10" autocomplete="new-password">
  </label>
  <button class="btn btn-primario" type="submit" style="justify-content:center">Guardar contraseña</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
