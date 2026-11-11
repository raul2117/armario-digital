<?php
// Marca prendas como usadas hoy (suma 1 al contador y guarda la fecha).
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}
csrf_comprobar();

$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['prendas'] ?? [])))));
if ($ids) {
    $st = $pdo->prepare('UPDATE prendas SET veces_usada = veces_usada + 1, ultima_vez = CURDATE() WHERE id = ? AND usuario_id = ? AND (ultima_vez IS NULL OR ultima_vez < CURDATE())');
    $hist = $pdo->prepare('INSERT IGNORE INTO usos (usuario_id, prenda_id, fecha) SELECT usuario_id, id, CURDATE() FROM prendas WHERE id = ? AND usuario_id = ?');
    foreach ($ids as $id) {
        $st->execute([$id, $UID]);
        $hist->execute([$id, $UID]);
    }
    flash('success', count($ids) === 1 ? '¡Apuntado! Hoy la llevas puesta' : '¡Apuntado! Que lo disfrutes');
}

$volver = $_POST['volver'] ?? 'index.php';
redirigir(in_array($volver, ['index.php', 'armario.php', 'outfit.php', 'calendario.php'], true) ? $volver : 'index.php');
