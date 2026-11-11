<?php
/**
 * El tiempo con Open-Meteo (gratis, sin clave, uso no comercial) y la recomendación de ropa.
 * La previsión se guarda 30 minutos en caché para no llamar a la API en cada visita.
 */

const TIEMPO_CACHE_MIN = 30;

/** Petición GET que devuelve el JSON decodificado o null */
function pedir_json(string $url): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_USERAGENT => 'ArmarioDigital/1.0']);
        $txt = curl_exec($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'ArmarioDigital/1.0']]);
        $txt = @file_get_contents($url, false, $ctx);
        $ok = $txt !== false;
    }
    if (!$ok || !$txt) return null;
    $json = json_decode($txt, true);
    return is_array($json) ? $json : null;
}

/** Busca una ciudad por nombre. Devuelve hasta 5 resultados [nombre, detalle, lat, lon] */
function buscar_ciudad(string $nombre): array
{
    $url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query(['name' => $nombre, 'count' => 5, 'language' => 'es', 'format' => 'json']);
    $json = pedir_json($url);
    $res = [];
    foreach ($json['results'] ?? [] as $r) {
        $detalle = implode(', ', array_filter([$r['admin2'] ?? null, $r['admin1'] ?? null, $r['country'] ?? null]));
        $res[] = ['nombre' => $r['name'], 'region' => $r['admin1'] ?? ($r['country'] ?? ''), 'detalle' => $detalle, 'lat' => (float)$r['latitude'], 'lon' => (float)$r['longitude']];
    }
    return $res;
}

/** Previsión de hoy y los 3 días siguientes. null si no hay conexión. */
function obtener_tiempo(float $lat, float $lon): ?array
{
    $clave = sprintf('%.3f_%.3f', $lat, $lon);
    $dir = sys_get_temp_dir() . '/armario-tiempo';
    $archivo = "$dir/$clave.json";
    if (is_file($archivo) && filemtime($archivo) > time() - TIEMPO_CACHE_MIN * 60) {
        $json = json_decode((string)file_get_contents($archivo), true);
        if (is_array($json)) return $json;
    }
    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude'      => $lat,
        'longitude'     => $lon,
        'current'       => 'temperature_2m,apparent_temperature,weather_code,wind_speed_10m',
        'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,apparent_temperature_max,apparent_temperature_min,precipitation_probability_max,wind_speed_10m_max',
        'timezone'      => 'Europe/Madrid',
        'forecast_days' => 4,
    ]);
    $json = pedir_json($url);
    if (!$json || empty($json['daily']['time'])) {
        // Sin conexión: mejor una previsión de hace un rato que nada
        $viejo = is_file($archivo) ? json_decode((string)file_get_contents($archivo), true) : null;
        return is_array($viejo) ? $viejo : null;
    }
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($archivo, json_encode($json));
    return $json;
}

/** Texto e icono a partir del código WMO */
function describir_tiempo(int $codigo): array
{
    if ($codigo === 0) return ['Despejado', 'sol'];
    if ($codigo <= 2) return ['Poco nuboso', 'sol-nube'];
    if ($codigo === 3) return ['Nublado', 'nube'];
    if ($codigo <= 48) return ['Niebla', 'niebla'];
    if ($codigo <= 57) return ['Llovizna', 'lluvia'];
    if ($codigo <= 67) return ['Lluvia', 'lluvia'];
    if ($codigo <= 77) return ['Nieve', 'nieve'];
    if ($codigo <= 82) return ['Chubascos', 'lluvia'];
    if ($codigo <= 86) return ['Chubascos de nieve', 'nieve'];
    return ['Tormenta', 'tormenta'];
}

function icono_tiempo(string $icono, int $tam = 28): string
{
    $p = [
        'sol'      => '<circle cx="12" cy="12" r="4.5" fill="#FFD15C" stroke="#E0A800"/><path stroke="#E0A800" d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/>',
        'sol-nube' => '<circle cx="9" cy="9" r="3.5" fill="#FFD15C" stroke="#E0A800"/><path fill="#E9E6F5" stroke="#8C86A8" d="M8 20h9a4 4 0 0 0 .5-8 5 5 0 0 0-9.5 1.5A3.3 3.3 0 0 0 8 20z"/>',
        'nube'     => '<path fill="#E9E6F5" stroke="#8C86A8" d="M7 19h10a4.5 4.5 0 0 0 .6-9A6 6 0 0 0 6 11.5 3.8 3.8 0 0 0 7 19z"/>',
        'niebla'   => '<path stroke="#8C86A8" d="M4 9h16M3 13h18M5 17h14"/>',
        'lluvia'   => '<path fill="#E9E6F5" stroke="#8C86A8" d="M7 15h10a4 4 0 0 0 .5-8A5.5 5.5 0 0 0 6.5 8.5 3.3 3.3 0 0 0 7 15z"/><path stroke="#3B82F6" d="M8 18l-1 3M12 18l-1 3M16 18l-1 3"/>',
        'nieve'    => '<path fill="#E9E6F5" stroke="#8C86A8" d="M7 15h10a4 4 0 0 0 .5-8A5.5 5.5 0 0 0 6.5 8.5 3.3 3.3 0 0 0 7 15z"/><path stroke="#60A5FA" d="M8 19h.01M12 20h.01M16 19h.01M10 22h.01M14 22h.01"/>',
        'tormenta' => '<path fill="#D6D2E6" stroke="#6E6889" d="M7 14h10a4 4 0 0 0 .5-8A5.5 5.5 0 0 0 6.5 7.5 3.3 3.3 0 0 0 7 14z"/><path fill="#FFD15C" stroke="#E0A800" d="M12 14l-2 4h3l-1.5 4 4-6h-3l1.5-2z"/>',
    ];
    return '<svg width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$icono] ?? $p['nube']) . '</svg>';
}

/**
 * Recomendación según la sensación térmica del día y la lluvia.
 * Devuelve: nivel, titulo, texto, temporadas a sugerir y si hace falta abrigo.
 */
function recomendar_ropa(float $sensMax, float $sensMin, int $probLluvia, float $viento): array
{
    $media = ($sensMax + $sensMin) / 2;
    if ($media < 6) {
        $r = ['nivel' => 'frio', 'titulo' => 'Abrígate bien', 'texto' => 'Abrigo grueso, jersey y algo para el cuello.', 'temporadas' => ['invierno', 'otono', 'todo'], 'abrigo' => true];
    } elseif ($media < 13) {
        $r = ['nivel' => 'fresco', 'titulo' => 'Toca abrigo', 'texto' => 'Capas: jersey o sudadera y un abrigo o chaqueta.', 'temporadas' => ['otono', 'invierno', 'todo'], 'abrigo' => true];
    } elseif ($media < 19) {
        $r = ['nivel' => 'templado', 'titulo' => 'Entretiempo', 'texto' => 'Manga larga ligera y una chaqueta fina por si acaso.', 'temporadas' => ['primavera', 'otono', 'todo'], 'abrigo' => $sensMin < 14];
    } elseif ($media < 25) {
        $r = ['nivel' => 'agradable', 'titulo' => 'Ropa ligera', 'texto' => 'Camiseta y prendas frescas; quizá algo fino para la noche.', 'temporadas' => ['primavera', 'verano', 'todo'], 'abrigo' => false];
    } else {
        $r = ['nivel' => 'calor', 'titulo' => 'Hace calor', 'texto' => 'Tejidos frescos y colores claros. Nada de capas.', 'temporadas' => ['verano', 'todo'], 'abrigo' => false];
    }
    if ($sensMax - $sensMin >= 10 && !$r['abrigo'] && $r['nivel'] !== 'calor') {
        $r['texto'] .= ' Mucha diferencia entre mañana y tarde: mejor por capas.';
    }
    if ($probLluvia >= 50) {
        $r['texto'] .= ' Probabilidad de lluvia del ' . $probLluvia . '%: paraguas o impermeable.';
    }
    if ($viento >= 35) {
        $r['texto'] .= ' Viento fuerte.';
    }
    return $r;
}

/** Datos listos para pintar: hoy y los 3 días siguientes, con recomendación para hoy */
function tiempo_para_usuario(?array $usuario): ?array
{
    if (!$usuario || $usuario['latitud'] === null || !$usuario['usar_tiempo']) return null;
    $t = obtener_tiempo((float)$usuario['latitud'], (float)$usuario['longitud']);
    if (!$t) return ['error' => true, 'ciudad' => $usuario['ciudad']];

    $d = $t['daily'];
    $dias = [];
    $nombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    foreach ($d['time'] as $i => $fecha) {
        [$texto, $icono] = describir_tiempo((int)$d['weather_code'][$i]);
        $dias[] = [
            'fecha'  => $fecha,
            'nombre' => $i === 0 ? 'Hoy' : ($i === 1 ? 'Mañana' : $nombres[(int)date('w', strtotime($fecha))]),
            'max'    => (int)round($d['temperature_2m_max'][$i]),
            'min'    => (int)round($d['temperature_2m_min'][$i]),
            'lluvia' => (int)($d['precipitation_probability_max'][$i] ?? 0),
            'texto'  => $texto,
            'icono'  => $icono,
        ];
    }
    // A partir de las 20:00 la recomendación es para mañana
    $i = (int)date('G') >= 20 && count($d['time']) > 1 ? 1 : 0;
    $rec = recomendar_ropa(
        (float)$d['apparent_temperature_max'][$i],
        (float)$d['apparent_temperature_min'][$i],
        (int)($d['precipitation_probability_max'][$i] ?? 0),
        (float)($d['wind_speed_10m_max'][$i] ?? 0)
    );
    $rec['para'] = $i === 0 ? 'hoy' : 'mañana';

    $ahora = null;
    if (!empty($t['current'])) {
        [$texto, $icono] = describir_tiempo((int)$t['current']['weather_code']);
        $ahora = ['temp' => (int)round($t['current']['temperature_2m']), 'sensacion' => (int)round($t['current']['apparent_temperature']), 'texto' => $texto, 'icono' => $icono];
    }
    return ['ciudad' => $usuario['ciudad'], 'ahora' => $ahora, 'dias' => $dias, 'rec' => $rec];
}
