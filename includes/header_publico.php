<?php /** @var string $titulo */ ?><!doctype html>
<html lang="es" data-tema="<?= tema_actual(null) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<title><?= h($titulo ?? '') ?> · <?= h(APP_NOMBRE) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css?v=6">
</head>
<body class="publico">
<div class="publico-tema"><?= selector_tema(tema_actual(null)) ?></div>
<main class="publico-caja">
  <div class="marca" style="margin-bottom:28px">
    <span class="marca-logo"><svg width="26" height="26" viewBox="0 0 48 48" fill="none" stroke="#fff" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="M24 10 C24 6 30 6 30 10 C30 13 24 14 24 18 L6 34 L42 34 L24 18"/></svg></span>
    <span><?= h(APP_NOMBRE) ?></span>
  </div>
