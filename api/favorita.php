<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido']));
}
csrf_comprobar();

$id = (int)($_POST['id'] ?? 0);
$pdo->prepare('UPDATE prendas SET favorita = 1 - favorita WHERE id = ? AND usuario_id = ?')->execute([$id, $UID]);
$st = $pdo->prepare('SELECT favorita FROM prendas WHERE id = ? AND usuario_id = ?');
$st->execute([$id, $UID]);
$fav = $st->fetchColumn();

if ($fav === false) {
    http_response_code(404);
    exit(json_encode(['error' => 'No existe']));
}
echo json_encode(['favorita' => (bool)$fav]);
