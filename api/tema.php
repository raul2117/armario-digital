<?php
// Guarda el tema elegido (auto / claro / oscuro) en la cuenta del usuario
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido']));
}
csrf_comprobar();

$tema = $_POST['tema'] ?? '';
if (!array_key_exists($tema, TEMAS)) {
    http_response_code(422);
    exit(json_encode(['error' => 'Tema no válido']));
}
$pdo->prepare('UPDATE usuarios SET tema = ? WHERE id = ?')->execute([$tema, $UID]);
echo json_encode(['tema' => $tema]);
