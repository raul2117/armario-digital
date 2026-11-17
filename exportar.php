<?php
// Exporta las prendas del usuario en CSV (separado por ; para que Excel en español lo abra bien)
require __DIR__ . '/includes/bootstrap.php';

$st = $pdo->prepare(SQL_PRENDAS . ' WHERE p.usuario_id = ? ORDER BY c.orden, p.nombre');
$st->execute([$UID]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="mi-armario-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Nombre', 'Categoría', 'Color', 'Temporada', 'Marca', 'Favorita', 'Veces usada', 'Última vez', 'Añadida', 'Notas'], ';');
foreach ($st as $p) {
    // csv_seguro() evita que Excel interprete como fórmula un texto que empiece por = + - @, tabulador o retorno
    fputcsv($out, array_map('csv_seguro', [
        $p['nombre'],
        $p['cat_nombre'],
        $p['color_hex'],
        TEMPORADAS[$p['temporada']] ?? $p['temporada'],
        $p['marca'],
        $p['favorita'] ? 'Sí' : 'No',
        $p['veces_usada'],
        $p['ultima_vez'] ? date('d/m/Y', strtotime($p['ultima_vez'])) : '',
        date('d/m/Y', strtotime($p['creado_en'])),
        $p['notas'],
    ]), ';');
}
fclose($out);
