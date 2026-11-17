<?php
define('PAGINA_PUBLICA', true);
require __DIR__ . '/includes/bootstrap.php';

if ($USUARIO) {
    redirigir('index.php');
}

// Sin usuarios todavía: configuración inicial del administrador
if ((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0) {
    redirigir('instalar.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_comprobar();
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');

    // Freno por IP: demasiados fallos recientes desde el mismo equipo
    $st = $pdo->prepare('SELECT COUNT(*) FROM accesos WHERE ip = ? AND exito = 0 AND fecha > NOW() - INTERVAL 15 MINUTE');
    $st->execute([ip_cliente()]);
    $fallosIp = (int)$st->fetchColumn();

    $st = $pdo->prepare('SELECT id, clave_hash, activo, clave_version FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    $mensajeGenerico = 'El email o la contraseña no son correctos.';

    if ($fallosIp >= MAX_FALLOS_IP) {
        registrar_acceso($pdo, $u['id'] ?? null, $email, false, 'ip_bloqueada');
        $error = 'Demasiados intentos desde este equipo. Espera unos minutos.';
    } elseif (!$u) {
        password_verify($clave, '$2y$10$7QbmDAMtuDgcJ1rqWUU/L.jXVc61zuCQGihOfPYbBmip/QFQg/9V.'); // mismo tiempo de respuesta
        registrar_acceso($pdo, null, $email, false, 'no_existe');
        $error = $mensajeGenerico;
    } elseif (fallos_email_ip($pdo, $email, ip_cliente()) >= MAX_INTENTOS) {
        // Bloqueo solo para este email desde este equipo: desde otros dispositivos se puede seguir entrando
        registrar_acceso($pdo, (int)$u['id'], $email, false, 'bloqueado');
        $error = 'Demasiados intentos. Vuelve a probar dentro de unos minutos.';
    } elseif (!password_verify($clave, $u['clave_hash'])) {
        registrar_acceso($pdo, (int)$u['id'], $email, false, 'clave');
        $error = $mensajeGenerico;
    } elseif (!$u['activo']) {
        registrar_acceso($pdo, (int)$u['id'], $email, false, 'inactivo');
        $error = 'Esta cuenta está desactivada. Habla con el administrador.';
    } else {
        if (password_needs_rehash($u['clave_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
        }
        $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$u['id']]);
        registrar_acceso($pdo, (int)$u['id'], $email, true);
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$u['id'];
        $_SESSION['clave_version'] = (int)$u['clave_version'];
        $_SESSION['renovada'] = time();
        unset($_SESSION['csrf']);   // token CSRF nuevo para la sesión iniciada
        redirigir('index.php');
    }
    usleep(random_int(200000, 500000));
}

$titulo = 'Entrar';
require __DIR__ . '/includes/header_publico.php';
?>
<h1>Entrar</h1>
<p class="publico-sub">Tu armario te está esperando.</p>

<?php if ($error): ?><div class="aviso" role="alert"><?= h($error) ?></div><?php endif; ?>

<form method="post" class="publico-form">
  <?= csrf_campo() ?>
  <label class="campo">Email
    <input type="email" name="email" value="<?= h($email) ?>" required autocomplete="username" autofocus>
  </label>
  <label class="campo">Contraseña
    <input type="password" name="clave" required autocomplete="current-password">
  </label>
  <button class="btn btn-primario" type="submit" style="justify-content:center">Entrar</button>
</form>
<p class="publico-pie">¿No tienes cuenta o has olvidado la contraseña? Pídesela al administrador.</p>
<?php require __DIR__ . '/includes/footer_publico.php'; ?>
