<?php
// Configuración inicial: solo funciona mientras no exista ningún usuario. Crea el administrador.
define('PAGINA_PUBLICA', true);
require __DIR__ . '/includes/bootstrap.php';

if ((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
    redirigir('login.php');
}

$errores = [];
$nombre = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = mb_strtolower(trim($_POST['email'] ?? ''));
    $clave  = (string)($_POST['clave'] ?? '');

    if ($nombre === '') $errores[] = 'Escribe tu nombre.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es válido.';
    if ($e = validar_clave($clave, $email)) $errores[] = $e;
    if ($clave !== ($_POST['clave2'] ?? '')) $errores[] = 'Las contraseñas no coinciden.';

    if (!$errores) {
        $pdo->beginTransaction();
        // Bloqueo para que dos peticiones a la vez no creen dos administradores
        if ((int)$pdo->query('SELECT COUNT(*) FROM usuarios FOR UPDATE')->fetchColumn() > 0) {
            $pdo->rollBack();
            redirigir('login.php');
        }
        $pdo->prepare("INSERT INTO usuarios (nombre, email, clave_hash, rol, ultimo_acceso) VALUES (?, ?, ?, 'admin', NOW())")
            ->execute([mb_substr($nombre, 0, 60), $email, password_hash($clave, PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
        // Se queda con las prendas y outfits que hubiera de antes
        $pdo->prepare('UPDATE prendas SET usuario_id = ? WHERE usuario_id IS NULL')->execute([$id]);
        $pdo->prepare('UPDATE outfits SET usuario_id = ? WHERE usuario_id IS NULL')->execute([$id]);
        $pdo->commit();

        registrar_acceso($pdo, $id, $email, true, 'instalacion');
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $id;
        $_SESSION['clave_version'] = 1;
        flash('success', '¡Listo! Ya eres el administrador');
        redirigir('index.php');
    }
}

$titulo = 'Configuración inicial';
require __DIR__ . '/includes/header_publico.php';
?>
<h1>Configuración inicial</h1>
<p class="publico-sub">Crea la cuenta de administrador. Después podrás dar de alta al resto de usuarios.</p>

<?php if ($errores): ?>
  <div class="aviso" role="alert"><?php foreach ($errores as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" class="publico-form">
  <?= csrf_campo() ?>
  <label class="campo">Nombre
    <input type="text" name="nombre" value="<?= h($nombre) ?>" required maxlength="60" autofocus>
  </label>
  <label class="campo">Email
    <input type="email" name="email" value="<?= h($email) ?>" required autocomplete="username">
  </label>
  <label class="campo">Contraseña <small>(mínimo 10 caracteres, con letras y números)</small>
    <input type="password" name="clave" required minlength="10" autocomplete="new-password">
  </label>
  <label class="campo">Repite la contraseña
    <input type="password" name="clave2" required minlength="10" autocomplete="new-password">
  </label>
  <button class="btn btn-primario" type="submit" style="justify-content:center">Crear administrador</button>
</form>
<?php require __DIR__ . '/includes/footer_publico.php'; ?>
