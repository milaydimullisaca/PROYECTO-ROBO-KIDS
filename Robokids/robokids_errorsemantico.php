<?php

/*
 * ROBO-KIDS: la lista de sentencias SALE DEL AUTÓMATA.
 * Sincronizado estrictamente con la leyenda y el autómata corregido de la imagen.
 */

/* ---------- Grupos de tokens (leyenda del cuaderno) ---------- */

$grupos = [
    'CMD'    => ['DECIR', 'SONAR', 'LEER', 'ESCANEAR', 'AVANZAR'],
    'BLOQUE' => ['FUNCION', 'REPETIR'],
    'VAR'    => ['MEMORIA', 'ACTUALIZAR'],
    'ARG'    => ['ID', 'NUM', 'STRING'],
    'DIR'    => ['DERECHA', 'IZQUIERDA'],
    'COND'   => ['SI', 'PATRULLAR', 'COMPROBAR'],
    'OPER'   => ['+', '-', '*', '/'],
    'COMP'   => ['<', '>', '=='],
    'LOG'    => ['Y', 'O', 'NO'],
];

/* Grupos cuyas palabras son sentencias distintas (salen del estado 3) */
$gruposSentencia = ['CMD', 'BLOQUE', 'VAR', 'COND'];

/* ---------- Autómata: [estado origen, token, estado destino] ---------- */

$estadoInicial = 0;
$estadoCuerpo  = 3;         // dentro de ROBOT [ ... ]
$estadosFinales = [17];

$transiciones = [
    [0, 'ROBOT', 1],
    [1, 'ID', 2],
    [2, '[', 3],

    [3, 'GIRAR', 6],
    [6, 'DIR', 5],
    [3, 'CMD', 5],
    [5, 'ARG', 4],
    [4, ':D', 3],

    [3, 'BLOQUE', 15],
    [15, 'ARG', 16],
    [16, '[', 3],

    [3, 'VAR', 7],
    [7, 'ID', 8],
    [8, '=', 9],
    [9, 'ARG', 10],
    [10, 'OPER', 9],
    [10, ':D', 3],

    [3, 'COND', 11],
    [11, 'ARG', 12],
    [12, 'COMP', 13],
    [13, 'ARG', 14],
    [14, 'LOG', 11], // Lógica corregida: Y, O, NO retorna al estado 11 para otra condición
    [14, '[', 3],

    [3, ']', 17],
    [17, ']', 17],
    [17, 'SINO', 18],
    [18, '[', 3],
];

/* ---------- Errores semánticos posibles por sentencia ---------- */

$erroresNumerico = [
    'El parámetro debe ser numérico.',
    'El valor debe ser mayor que cero.',
    'La variable usada como parámetro no ha sido declarada.',
];

$errores = [
    'ROBOT' => [
        'El robot ya fue definido anteriormente (solo puede haber un ROBOT).',
        'El nombre del robot es una palabra reservada.',
    ],
    'DECIR' => [
        'La variable usada no ha sido declarada.',
        'La cadena de texto no fue cerrada con comillas.',
        'Tipos incompatibles en la expresión (cadena con número).',
    ],
    'SONAR' => [
        'La variable usada no ha sido declarada.',
        'La cadena de texto no fue cerrada con comillas.',
    ],
    'LEER' => [
        'No se puede leer: la variable no ha sido declarada.',
    ],
    'ESCANEAR' => [
        'El sensor no es válido (solo DISTANCIA, OBSTACULO, LUZ o SONIDO).',
        'Falta indicar el sensor a consultar.',
    ],
    'AVANZAR' => $erroresNumerico,
    'GIRAR' => [
        'La dirección no es válida (solo DERECHA o IZQUIERDA).',
        'El ángulo debe ser numérico.',
        'El ángulo debe ser mayor que cero.',
        'La variable usada como ángulo no ha sido declarada.',
    ],
    'FUNCION' => [
        'La función ya fue declarada anteriormente.',
        'El nombre de la función es una palabra reservada.',
        'El nombre de la función coincide con una variable declarada.',
    ],
    'REPETIR' => [
        'La cantidad de repeticiones no es un valor numérico.',
        'La cantidad de repeticiones debe ser mayor que cero.',
        'La cantidad usa una variable no declarada.',
    ],
    'MEMORIA' => [
        'La variable ya fue declarada anteriormente.',
        'El nombre de la variable es una palabra reservada.',
        'El valor usa una variable que no ha sido declarada.',
        'División entre cero en el valor asignado.',
    ],
    'ACTUALIZAR' => [
        'La variable no ha sido declarada.',
        'Tipo incompatible: se asigna un valor de otro tipo (ej. cadena a numérico).',
        'La expresión usa una variable que no ha sido declarada.',
        'División entre cero en la expresión.',
    ],
    'SI' => [
        'La condición usa una variable no declarada.',
        'La condición no es de tipo booleano.',
        'Se comparan tipos incompatibles (ej. cadena > número).',
    ],
    'PATRULLAR' => [
        'La condición usa una variable no declarada.',
        'La condición no es de tipo booleano.',
        'La condición nunca cambia dentro del bloque (bucle infinito).',
    ],
    'COMPROBAR' => [
        'La variable comparada no ha sido declarada.',
        'Se comparan tipos incompatibles (ej. cadena con número).',
        'Los operadores > y < no se pueden usar con cadenas o booleanos.',
        'Los operandos de Y, O, NO no son booleanos.',
    ],
    ']' => [
        'Hay un ] sin su [ de apertura.',
        'Quedaron bloques [ sin cerrar al terminar el programa.',
    ],
    'SI NO' => [
        'SI NO aparece sin un SI previo.',
        'Hay más de un SI NO para el mismo SI.',
    ],
];

/* ---------- Funciones ---------- */

function Mostrar(string $token): string
{
    return $token === 'SINO' ? 'SI NO' : $token;
}

function Rellenar(string $texto, int $ancho): string
{
    return $texto . str_repeat(' ', max(0, $ancho - mb_strlen($texto, 'UTF-8')));
}

/* Recorre el autómata y devuelve todos los caminos que vuelven al estado meta
   (o que terminan en un estado final). */
function EnumerarCaminos(array $trans, array $finales, int $desde, int $meta): array
{
    $salida = [];

    $recorrer = function (int $estado, array $estados, array $tokens)
        use (&$recorrer, &$salida, $trans, $finales, $meta) {

        foreach ($trans as [$origen, $token, $destino]) {
            if ($origen !== $estado) {
                continue;
            }

            $tokens2 = [...$tokens, $token];

            if ($destino === $meta) {
                $salida[] = ['estados' => [...$estados, $destino], 'tokens' => $tokens2];
                continue;
            }

            if (in_array($destino, $estados, true)) {
                continue;
            }

            $estados2 = [...$estados, $destino];

            if (in_array($destino, $finales, true)) {
                $salida[] = ['estados' => $estados2, 'tokens' => $tokens2];
            }

            $recorrer($destino, $estados2, $tokens2);
        }
    };

    $recorrer($desde, [$desde], []);

    return $salida;
}

/* Estructura de la sentencia; los ciclos del autómata salen como ( ... )* */
function Estructura(array $camino, array $trans, int $meta, string $primero): string
{
    $estados = $camino['estados'];
    $tokens  = $camino['tokens'];
    $partes  = [];

    foreach ($tokens as $i => $token) {
        $partes[] = $i === 0 ? $primero : Mostrar($token);

        $j = $i + 1;
        $s = $estados[$j];

        if ($s === $meta) {
            continue;
        }

        foreach ($trans as [$origen, $tk, $destino]) {
            if ($origen !== $s) {
                continue;
            }

            $k = array_search($destino, $estados, true);

            if ($k === false || $k === 0 || $k > $j) {
                continue;
            }

            if ($j < count($tokens) && $destino === $estados[$j + 1] && $tk === $tokens[$j]) {
                continue;
            }

            $ciclo = array_merge([$tk], array_slice($tokens, $k, $j - $k));
            $partes[] = '(' . implode(' ', array_map('Mostrar', $ciclo)) . ')*';
        }
    }

    return implode(' ', $partes);
}

function ConstruirFilas(
    array $grupos, array $gruposSentencia, array $trans, array $finales,
    int $inicial, int $meta, array $errores
): array {
    $caminos = array_merge(
        EnumerarCaminos($trans, $finales, $inicial, $meta),
        EnumerarCaminos($trans, $finales, $meta, $meta)
    );

    $filas = [];

    foreach ($caminos as $camino) {
        $primero = $camino['tokens'][0];

        if (in_array('SINO', $camino['tokens'], true)) {
            $nombres = ['SI NO'];
        } elseif (in_array($primero, $gruposSentencia, true)) {
            $nombres = $grupos[$primero];
        } else {
            $nombres = [$primero];
        }

        $recorrido = implode('>', $camino['estados']);

        if (in_array(end($camino['estados']), $finales, true)) {
            $recorrido .= ' (FINAL)';
        }

        foreach ($nombres as $nombre) {
            $filas[] = [
                'sentencia'  => $nombre,
                'estructura' => Estructura(
                    $camino, $trans, $meta,
                    in_array($primero, $gruposSentencia, true) ? $nombre : Mostrar($primero)
                ),
                'camino'     => $recorrido,
                'errores'    => $errores[$nombre] ?? ['(sin errores semánticos definidos)'],
            ];
        }
    }

    return $filas;
}

function ConstruirSalida(
    array $grupos, array $trans, array $finales, array $filas
): string {
    $gap = '    ';

    /* Anchos de las tablas */
    $wSen = mb_strlen('SENTENCIA');
    $wEst = mb_strlen('ESTRUCTURA');
    $wCam = mb_strlen('CAMINO');
    $wErr = mb_strlen('ERROR SEMANTICO POSIBLE');

    foreach ($filas as $f) {
        $wSen = max($wSen, mb_strlen($f['sentencia'], 'UTF-8'));
        $wEst = max($wEst, mb_strlen($f['estructura'], 'UTF-8'));
        $wCam = max($wCam, mb_strlen($f['camino'], 'UTF-8'));

        foreach ($f['errores'] as $e) {
            $wErr = max($wErr, mb_strlen($e, 'UTF-8'));
        }
    }

    $wNum  = 4;
    $ancho = max(100, $wNum + $wSen + $wEst + $wCam + $wErr + 4 * strlen($gap));
    $dobles  = str_repeat('=', $ancho);
    $guiones = str_repeat('-', $ancho);

    $s  = "ANALIZADOR SEMANTICO ROBO-KIDS\n";
    $s .= $dobles . "\n\n";

    /* 1. Transiciones */
    $s .= "TABLA DE TRANSICIONES DEL AUTÓMATA\n";
    $s .= $guiones . "\n";
    $s .= Rellenar('ESTADO', 10) . Rellenar('TOKEN / TRANSICIÓN PERMITIDA', 36) . "SIGUIENTE ESTADO\n";
    $s .= $guiones . "\n";

    foreach ($trans as [$origen, $token, $destino]) {
        $s .= Rellenar((string)$origen, 10)
            . Rellenar(Mostrar($token), 36)
            . $destino
            . (in_array($destino, $finales, true) ? '  (FINAL)' : '')
            . "\n";
    }

    $s .= $guiones . "\n\n";

    /* 2. Grupos */
    $s .= "GRUPOS DE TOKENS\n";
    $s .= $guiones . "\n";

    foreach ($grupos as $nombre => $palabras) {
        $s .= Rellenar($nombre, 10) . implode(', ', $palabras) . "\n";
    }

    $s .= $guiones . "\n\n";

    /* 3. Sentencias derivadas del autómata */
    $s .= "SENTENCIAS DEL AUTÓMATA Y SUS ERRORES SEMÁNTICOS POSIBLES\n";
    $s .= $guiones . "\n";
    $s .= Rellenar('N', $wNum) . $gap
        . Rellenar('SENTENCIA', $wSen) . $gap
        . Rellenar('ESTRUCTURA', $wEst) . $gap
        . Rellenar('CAMINO', $wCam) . $gap
        . "ERROR SEMÁNTICO POSIBLE\n";
    $s .= $guiones . "\n";

    $sangria = str_repeat(' ', $wNum + $wSen + $wEst + $wCam + 4 * strlen($gap));

    foreach ($filas as $i => $f) {
        foreach ($f['errores'] as $k => $error) {
            if ($k === 0) {
                $s .= Rellenar((string)($i + 1), $wNum) . $gap
                    . Rellenar($f['sentencia'], $wSen) . $gap
                    . Rellenar($f['estructura'], $wEst) . $gap
                    . Rellenar($f['camino'], $wCam) . $gap;
            } else {
                $s .= $sangria;
            }

            $s .= $error . "\n";
        }

        $s .= "\n";
    }

    $s .= $guiones . "\n";
    $s .= 'TOTAL SENTENCIAS: ' . count($filas) . "\n";

    return $s;
}

/* ---------- Programa principal ---------- */

$filas  = ConstruirFilas(
    $grupos, $gruposSentencia, $transiciones, $estadosFinales,
    $estadoInicial, $estadoCuerpo, $errores
);
$salida = ConstruirSalida($grupos, $transiciones, $estadosFinales, $filas);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Autómata semántico ROBO-KIDS</title>
<style>
body {
    font-family: Consolas, "Courier New", monospace;
    font-size: 12px;
    background: white;
    color: black;
    margin: 8px;
}

pre {
    font-family: inherit;
    font-size: inherit;
    margin: 0;
}
</style>
</head>

<body>
<pre><?= htmlspecialchars($salida, ENT_QUOTES, 'UTF-8') ?></pre>
</body>
</html>