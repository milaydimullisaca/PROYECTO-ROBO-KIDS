<?php
declare(strict_types=1);

/* =====================================================================
   FASE 1: ANALIZADOR LÉXICO EN PHP
   Autómata finito determinista A=(Q,Σ,δ,q0,F) con regla del lexema más largo
   ===================================================================== */
class Lexico
{
    public const KW = [
        'AVANZAR'    => ['Comandos', 'cmd'],
        'RETROCEDER' => ['Comandos', 'cmd'],
        'GIRAR'      => ['Comandos', 'cmd'],
        'FRENAR'     => ['Comandos', 'cmd'],
        'ROBOT'      => ['Programa y energía', 'prg'],
        'ACTIVAR'    => ['Programa y energía', 'prg'],
        'APAGAR'     => ['Programa y energía', 'prg'],
        'DECIR'      => ['Comunicación', 'com'],
        'HABLAR'     => ['Comunicación', 'com'],
        'RESPONDER'  => ['Comunicación', 'com'],
        'RECIBIR'    => ['Comunicación', 'com'],
        'SONAR'      => ['Sensores e interacción', 'sen'],
        'LEER'       => ['Sensores e interacción', 'sen'],
        'ESCANEAR'   => ['Sensores e interacción', 'sen'],
        'DETECTAR'   => ['Sensores e interacción', 'sen'],
        'OBSTACULO'  => ['Sensores e interacción', 'sen'],
        'LIBRE'      => ['Sensores e interacción', 'sen'],
        'META'       => ['Sensores e interacción', 'sen'],
        'ESTRELLA'   => ['Sensores e interacción', 'sen'],
        'SI'         => ['Control', 'ctl'],
        'SINO'       => ['Control', 'ctl'],
        'REPETIR'    => ['Control', 'ctl'],
        'PATRULLAR'  => ['Control', 'ctl'],
        'COMPROBAR'  => ['Control', 'ctl'],
        'MEMORIA'    => ['Memoria', 'mem'],
        'ACTUALIZAR' => ['Memoria', 'mem'],
        'VERDADERO'  => ['Constantes', 'bool'],
        'FALSO'      => ['Constantes', 'bool'],
        'Y'          => ['Operadores', 'op'],
        'O'          => ['Operadores', 'op'],
        'NO'         => ['Operadores', 'op'],
    ];

    public const SENSORS = ['OBSTACULO', 'LIBRE', 'META', 'ESTRELLA'];

    public const INSTR = [
        'AVANZAR', 'RETROCEDER', 'GIRAR', 'FRENAR', 'ACTIVAR', 'APAGAR',
        'DECIR', 'HABLAR', 'RESPONDER', 'RECIBIR', 'SONAR', 'LEER',
        'ESCANEAR', 'DETECTAR', 'SI', 'REPETIR', 'PATRULLAR', 'COMPROBAR',
        'MEMORIA', 'ACTUALIZAR'
    ];

    public const OPNAME = [
        '+'  => 'SUMA',
        '-'  => 'RESTA',
        '*'  => 'MULTIPLICACION',
        '/'  => 'DIVISION',
        '%'  => 'MODULO',
        '==' => 'IGUAL',
        '!=' => 'DISTINTO',
        '<'  => 'MENOR',
        '>'  => 'MAYOR',
        '<=' => 'MENOR_IGUAL',
        '>=' => 'MAYOR_IGUAL',
        'Y'  => 'Y_LOGICO',
        'O'  => 'O_LOGICO',
        'NO' => 'NO_LOGICO',
    ];

    public const REL = ['==', '!=', '<', '>', '<=', '>='];

    public const STATES = [
        0  => 'inicio',
        1  => 'identificador / palabra reservada',
        2  => 'número entero',
        3  => 'punto decimal (incompleto)',
        4  => 'número decimal',
        5  => 'leyendo texto (incompleto)',
        6  => 'texto entre comillas',
        7  => 'dos puntos (incompleto)',
        8  => 'fin de instrucción :D',
        9  => 'delimitador { } ( )',
        10 => 'operador + - * %',
        11 => 'operador /',
        12 => 'comentario //',
        13 => 'asignación =',
        14 => 'igual ==',
        15 => 'menor / mayor < >',
        16 => '<= >=',
        17 => 'signo ! (incompleto)',
        18 => 'distinto !=',
    ];

    public const FINAL_STATES = [
        1  => 'ID',
        2  => 'NUM',
        4  => 'NUM',
        6  => 'STR',
        8  => 'END',
        9  => 'DEL',
        10 => 'OP',
        11 => 'OP',
        12 => 'CMT',
        13 => 'ASSIGN',
        14 => 'OP',
        15 => 'OP',
        16 => 'OP',
        18 => 'OP',
    ];

    public static function norm(string $s): string
    {
        $map = [
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U', 'ñ' => 'N',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ];
        return strtoupper(strtr($s, $map));
    }

    public static function suggest(string $w, array $list): ?string
    {
        $w = self::norm($w);
        $best = null;
        $min = 99;
        foreach ($list as $k) {
            $d = levenshtein($w, $k);
            if ($d < $min) {
                $min = $d;
                $best = $k;
            }
        }
        return $min <= (strlen($w) <= 4 ? 1 : 2) ? $best : null;
    }

    private static function isL(?string $c): bool
    {
        return $c !== null && preg_match('/^[A-Za-zÁÉÍÓÚÑÜáéíóúñü_]$/u', $c) === 1;
    }

    private static function isD(?string $c): bool
    {
        return $c !== null && $c >= '0' && $c <= '9';
    }

    public static function delta(int $s, ?string $c): int
    {
        switch ($s) {
            case 0:
                if (self::isL($c)) return 1;
                if (self::isD($c)) return 2;
                if ($c === '"') return 5;
                if ($c === ':') return 7;
                if ($c !== null && $c !== '' && strpos('{}()', $c) !== false) return 9;
                if ($c !== null && $c !== '' && strpos('+-*%', $c) !== false) return 10;
                if ($c === '/') return 11;
                if ($c === '=') return 13;
                if ($c === '<' || $c === '>') return 15;
                if ($c === '!') return 17;
                return -1;
            case 1:
                return (self::isL($c) || self::isD($c)) ? 1 : -1;
            case 2:
                return self::isD($c) ? 2 : ($c === '.' ? 3 : -1);
            case 3:
            case 4:
                return self::isD($c) ? 4 : -1;
            case 5:
                if ($c === '"') return 6;
                return ($c === null || $c === "\n") ? -1 : 5;
            case 7:
                return ($c === 'D' || $c === 'd') ? 8 : -1;
            case 11:
                return $c === '/' ? 12 : -1;
            case 12:
                return ($c !== null && $c !== "\n") ? 12 : -1;
            case 13:
                return $c === '=' ? 14 : -1;
            case 15:
                return $c === '=' ? 16 : -1;
            case 17:
                return $c === '=' ? 18 : -1;
            default:
                return -1;
        }
    }

    /**
     * Recorre el autómata desde q0 sobre $chars[$i..] y devuelve el lexema más largo aceptado.
     */
    public static function runDFA(array $chars, int $i): array
    {
        $s = 0;
        $j = $i;
        $n = count($chars);
        $path = [0];
        $acc = null;

        while ($j < $n) {
            $ns = self::delta($s, $chars[$j]);
            if ($ns < 0) break;
            $s = $ns;
            $j++;
            $path[] = $s;
            if (isset(self::FINAL_STATES[$s])) {
                $acc = ['s' => $s, 'j' => $j, 'path' => $path];
            }
        }

        return ['acc' => $acc, 'last' => $s, 'j' => $j, 'path' => $path];
    }

    /**
     * Prueba un lexema aislado (usado en la pestaña Autómata del Laboratorio).
     */
    public static function tryLexeme(string $w): ?array
    {
        if ($w === '') return null;
        $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $r = self::runDFA($chars, 0);
        $ok = $r['acc'] !== null && $r['acc']['j'] === count($chars);
        $name = '';

        if ($ok) {
            $L = self::lex($w);
            if (!empty($L['toks']) && $L['toks'][0]['k'] !== 'EOF') {
                $name = $L['toks'][0]['name'] . ' · ' . $L['toks'][0]['grp'];
            }
        }

        $stuck = '';
        if (!$ok) {
            if ($r['acc'] !== null) {
                $parcial = implode('', array_slice($chars, 0, $r['acc']['j']));
                $stuck = "acepta solo «{$parcial}», luego se detiene";
            } else {
                $stName = self::STATES[$r['last']] ?? '';
                $stuck = "se detiene en q{$r['last']} ({$stName})";
            }
        }

        return [
            'lex'   => $w,
            'path'  => $ok ? $r['acc']['path'] : $r['path'],
            'ok'    => $ok,
            'name'  => $name,
            'stuck' => $stuck,
        ];
    }

    public static function lex(string $src): array
    {
        $chars = preg_split('//u', $src, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($chars);

        // Tabla de desplazamientos UTF-16 para que el resaltado en JS (src.slice) coincida con tildes y emojis
        $u16 = [0];
        $accU16 = 0;
        foreach ($chars as $ch) {
            $accU16 += (strlen($ch) === 4) ? 2 : 1;
            $u16[] = $accU16;
        }

        $toks = [];
        $errs = [];
        $spans = [];
        $i = 0;
        $line = 1;
        $col = 1;

        $adv = function (int $steps) use (&$i, &$line, &$col, $chars, $n): void {
            for ($k = 0; $k < $steps && $i < $n; $k++) {
                if ($chars[$i] === "\n") {
                    $line++;
                    $col = 1;
                } else {
                    $col++;
                }
                $i++;
            }
        };

        while ($i < $n) {
            $c = $chars[$i];
            if ($c === ' ' || $c === "\t" || $c === "\r" || $c === "\n") {
                $adv(1);
                continue;
            }

            $r = self::runDFA($chars, $i);
            $L0 = $line;
            $C0 = $col;

            if ($r['acc'] === null) {
                $len = 1;
                if ($r['last'] === 5) {
                    $msg = 'Texto sin cerrar: falta la comilla " final.';
                    $hint = 'Escribe el texto así: DECIR "hola" :D';
                    $len = $r['j'] - $i;
                } elseif ($r['last'] === 7) {
                    $msg = 'Los dos puntos « : » deben ir pegados a la D, así: « :D ».';
                    $hint = 'Todas las instrucciones terminan con :D';
                } elseif ($r['last'] === 17) {
                    $msg = 'El signo « ! » solo se usa en « != » (distinto).';
                    $hint = 'Ejemplo: SI pasos != 3 { ... }';
                } else {
                    $msg = "El símbolo «{$c}» no existe en ROBO-KIDS.";
                    $hint = 'Quítalo. Recuerda que cada instrucción termina con :D';
                }
                $errs[] = ['phase' => 'Léxico', 'line' => $L0, 'col' => $C0, 'msg' => $msg, 'hint' => $hint];
                $spans[] = ['a' => $u16[$i], 'b' => $u16[$i + $len], 'css' => 'err'];
                $adv($len);
                continue;
            }

            $b = $r['acc']['j'];
            $lexeme = implode('', array_slice($chars, $i, $b - $i));
            $tok = [
                'lex'  => $lexeme,
                'line' => $L0,
                'col'  => $C0,
                'a'    => $u16[$i],
                'b'    => $u16[$b],
                'path' => $r['acc']['path'],
                'st'   => $r['acc']['s'],
            ];

            switch (self::FINAL_STATES[$r['acc']['s']]) {
                case 'ID':
                    $w = self::norm($lexeme);
                    if (isset(self::KW[$w])) {
                        $tok['grp'] = self::KW[$w][0];
                        $tok['css'] = self::KW[$w][1];
                        $tok['name'] = $w;
                        if ($w === 'VERDADERO' || $w === 'FALSO') {
                            $tok['k'] = 'BOOL';
                            $tok['v'] = ($w === 'VERDADERO');
                            $tok['name'] = 'BOOLEANO';
                        } elseif ($w === 'Y' || $w === 'O' || $w === 'NO') {
                            $tok['k'] = 'OP';
                            $tok['v'] = $w;
                            $tok['name'] = self::OPNAME[$w];
                        } elseif (in_array($w, self::SENSORS, true)) {
                            $tok['k'] = 'SENS';
                            $tok['v'] = $w;
                        } else {
                            $tok['k'] = 'WORD';
                            $tok['v'] = $w;
                        }
                    } else {
                        $tok['k'] = 'ID';
                        $tok['v'] = strtolower($w);
                        $tok['name'] = 'IDENTIFICADOR';
                        $tok['grp'] = 'Identificadores';
                        $tok['css'] = 'id';
                    }
                    break;

                case 'NUM':
                    $numVal = (strpos($lexeme, '.') !== false) ? (float)$lexeme : (int)$lexeme;
                    $tok += ['k' => 'NUM', 'v' => $numVal, 'name' => 'NUMERO', 'grp' => 'Constantes', 'css' => 'num'];
                    break;

                case 'STR':
                    $strVal = implode('', array_slice($chars, $i + 1, ($b - $i) - 2));
                    $tok += ['k' => 'STR', 'v' => $strVal, 'name' => 'CADENA', 'grp' => 'Constantes', 'css' => 'str'];
                    break;

                case 'END':
                    $tok += ['k' => 'END', 'v' => ':D', 'name' => 'FIN_INSTRUCCION', 'grp' => 'Delimitadores', 'css' => 'del'];
                    break;

                case 'DEL':
                    $m = [
                        '{' => ['LB', 'LLAVE_ABRE'],
                        '}' => ['RB', 'LLAVE_CIERRA'],
                        '(' => ['LP', 'PAREN_ABRE'],
                        ')' => ['RP', 'PAREN_CIERRA'],
                    ][$lexeme];
                    $tok += ['k' => $m[0], 'v' => $lexeme, 'name' => $m[1], 'grp' => 'Delimitadores', 'css' => 'del'];
                    break;

                case 'ASSIGN':
                    $tok += ['k' => 'ASSIGN', 'v' => '=', 'name' => 'ASIGNACION', 'grp' => 'Operadores', 'css' => 'op'];
                    break;

                case 'OP':
                    $tok += ['k' => 'OP', 'v' => $lexeme, 'name' => self::OPNAME[$lexeme], 'grp' => 'Operadores', 'css' => 'op'];
                    break;

                case 'CMT':
                    $spans[] = ['a' => $u16[$i], 'b' => $u16[$b], 'css' => 'cmt'];
                    $adv($b - $i);
                    continue 2;
            }

            $toks[] = $tok;
            $spans[] = ['a' => $u16[$i], 'b' => $u16[$b], 'css' => $tok['css']];
            $adv($b - $i);
        }

        $toks[] = ['k' => 'EOF', 'v' => '', 'lex' => '', 'line' => $line, 'col' => $col];
        return ['toks' => $toks, 'errs' => $errs, 'spans' => $spans];
    }
}