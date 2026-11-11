<?php
/** @var string $titulo  @var string $activo */
$titulo = $titulo ?? APP_NOMBRE;
$activo = $activo ?? '';
$menu = [
    'inicio'  => ['index.php', 'Inicio', '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>'],
    'armario' => ['armario.php', 'Mi armario', '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M12 3v18M9.5 11v2M14.5 11v2"/>'],
    'nueva'   => ['prenda.php', 'Añadir', '<path d="M12 5v14M5 12h14"/>'],
    'outfits' => ['outfit.php', 'Outfits', '<path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z"/>'],
    'calendario' => ['calendario.php', 'Calendario', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'],
    'estadisticas' => ['estadisticas.php', 'Estadísticas', '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
    'ajustes' => ['ajustes.php', 'Ajustes', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>'],
];
if (es_admin($USUARIO ?? null)) {
    $menu['usuarios'] = ['usuarios.php', 'Usuarios', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 3.6-5.5 6.5-5.5s5.5 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c1.8.8 3 2.6 3.5 5.2"/>'];
}
?><!doctype html>
<html lang="es" data-tema="<?= tema_actual($USUARIO ?? null) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<title><?= h($titulo) ?> · <?= h(APP_NOMBRE) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css?v=5">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app">
  <nav class="sidebar" aria-label="Menú principal">
    <a class="marca" href="index.php">
      <span class="marca-logo"><svg width="26" height="26" viewBox="0 0 48 48" fill="none" stroke="#fff" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="M24 10 C24 6 30 6 30 10 C30 13 24 14 24 18 L6 34 L42 34 L24 18"/></svg></span>
      <span><?= h(APP_NOMBRE) ?></span>
    </a>
    <div class="menu">
      <?php foreach ($menu as $clave => [$url, $texto, $icono]): ?>
        <a href="<?= $url ?>" class="menu-item<?= $clave === $activo ? ' activo' : '' ?><?= $clave === 'nueva' ? ' menu-nueva' : '' ?>"<?= $clave === $activo ? ' aria-current="page"' : '' ?>>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icono ?></svg>
          <span><?= $texto ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <details class="perfil">
      <summary aria-label="Menú de <?= h($USUARIO['nombre']) ?>">
        <span class="avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($USUARIO['nombre'], 0, 1))) ?></span>
        <span class="perfil-texto">
          <strong><?= h($USUARIO['nombre']) ?></strong>
          <small><?= es_admin($USUARIO) ? 'Administrador' : 'Usuario' ?></small>
        </span>
        <svg class="perfil-flecha" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
      </summary>
      <div class="perfil-menu">
        <div class="perfil-email"><?= h($USUARIO['email']) ?></div>
        <div class="tema-form"><?= selector_tema(tema_actual($USUARIO)) ?></div>
        <a href="ajustes.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/></svg>Ajustes</a>
        <a href="cambiar_clave.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Cambiar contraseña</a>
        <form method="post" action="salir.php">
          <?= csrf_campo() ?>
          <button type="submit" class="perfil-salir"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11"/></svg>Cerrar sesión</button>
        </form>
      </div>
    </details>
  </nav>
  <main class="contenido">
