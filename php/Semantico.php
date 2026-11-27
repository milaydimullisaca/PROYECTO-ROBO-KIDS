<?php
declare(strict_types=1);

/* =====================================================================
   FASE 3: ANALIZADOR SEMÁNTICO EN PHP
   Tabla de símbolos con ámbitos, verificación de tipos y reglas de estado
   ===================================================================== */
class Semantico
{
    private array $errs = [];
    private array $warns = [];
    private array $symbols = [];
    private array $scopes = [];
    private bool $active = false;
    private bool $off = false;
    private bool $sawOff = false;

    public static function esrc(?array $e): string
    {
        if ($e === null) return '';
        switch ($e['k']) {
            case 'num':    return (string)$e['v'];
            case 'str':    return '"' . $e['v'] . '"';
            case 'bool':   return $e['v'] ? 'VERDADERO' : 'FALSO';
            case 'sensor': return $e['v'];
            case 'var':    return $e['lex'];
            case 'par':    return '(' . self::esrc($e['a']) . ')';
            case 'un':     return $e['op'] === 'NO' ? 'NO ' . self::esrc($e['a']) : '-' . self::esrc($e['a']);
            case 'bin':    return self::esrc($e['a']) . ' ' . $e['op'] . ' ' . self::esrc($e['b']);
        }
        return '';
    }

    private static function usesSensor(?array $e): bool
    {
        if ($e === null) return false;
        return $e['k'] === 'sensor'
            || (isset($e['a']) && self::usesSensor($e['a']))
            || (isset($e['b']) && self::usesSensor($e['b']));
    }

    private static function lit(?array $e): int|float|null
    {
        if ($e === null) return null;
        if ($e['k'] === 'num') return $e['v'];
        if ($e['k'] === 'un' && $e['op'] === '-' && $e['a']['k'] === 'num') return -$e['a']['v'];
        return null;
    }

    public static function analizar(array &$ast): array
    {
        $sem = new self();
        return $sem->run($ast);
    }

    private function E(int $l, string $m, string $h): void
    {
        $this->errs[] = ['phase' => 'Semántico', 'line' => $l, 'msg' => $m, 'hint' => $h];
    }

    private function W(int $l, string $m): void
    {
        $this->warns[] = ['line' => $l, 'msg' => $m];
    }

    private function look(string $n): ?stdClass
    {
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (isset($this->scopes[$i]['vars'][$n])) {
                return $this->scopes[$i]['vars'][$n];
            }
        }
        return null;
    }

    private function declareVar(string $key, string $name, string $type, int $line, string $init, string $how): stdClass
    {
        $lastIdx = count($this->scopes) - 1;
        $s = (object)[
            'id'    => count($this->symbols),
            'key'   => $key,
            'name'  => $name,
            'type'  => $type,
            'scope' => $this->scopes[$lastIdx]['name'],
            'line'  => $line,
            'init'  => $init,
            'uses'  => 0,
            'how'   => $how,
            'cur'   => null,
        ];
        $this->scopes[$lastIdx]['vars'][$key] = $s;
        $this->symbols[] = $s;
        return $s;
    }

    private function symRef(stdClass $s): array
    {
        return ['id' => $s->id, 'name' => $s->name, 'type' => $s->type];
    }

    private function ty(array &$e): string
    {
        $t = '?';
        switch ($e['k']) {
            case 'num':    $t = 'NUMERO'; break;
            case 'str':    $t = 'TEXTO'; break;
            case 'bool':
            case 'sensor': $t = 'BOOLEANO'; break;
            case 'par':    $t = $this->ty($e['a']); break;
            case 'var':
                $s = $this->look($e['name']);
                if ($s === null) {
                    $sg = Lexico::suggest($e['lex'], array_keys(Lexico::KW));
                    $this->E(
                        $e['line'],
                        "La variable «{$e['lex']}» no existe: no fue declarada.",
                        $sg ? "¿Quisiste escribir {$sg}?" : "Declárala antes con MEMORIA {$e['lex']} = 0 :D"
                    );
                } else {
                    $s->uses++;
                    $e['sym'] = $this->symRef($s);
                    $t = $s->type;
                }
                break;

            case 'un':
                $a = $this->ty($e['a']);
                if ($e['op'] === '-') {
                    if ($a !== '?' && $a !== 'NUMERO') {
                        $this->E($e['line'], "No se puede poner « - » delante de un {$a}.", 'El signo menos solo funciona con números.');
                    }
                    $t = 'NUMERO';
                } else {
                    if ($a !== '?' && $a !== 'BOOLEANO') {
                        $this->E($e['line'], "« NO » necesita una condición (BOOLEANO) y recibió un {$a}.", 'Ejemplo: NO OBSTACULO');
                    }
                    $t = 'BOOLEANO';
                }
                break;

            case 'bin':
                $a = $this->ty($e['a']);
                $b = $this->ty($e['b']);
                $op = $e['op'];
                $unk = ($a === '?' || $b === '?');

                if ($op === '+') {
                    if ($unk) $t = '?';
                    elseif ($a === 'NUMERO' && $b === 'NUMERO') $t = 'NUMERO';
                    elseif ($a === 'TEXTO' || $b === 'TEXTO') $t = 'TEXTO';
                    else $this->E($e['line'], "Tipos incompatibles: no se puede sumar {$a} + {$b}.", 'Suma números, o une textos con +.');
                } elseif (in_array($op, ['-', '*', '/', '%'], true)) {
                    $t = 'NUMERO';
                    if (!$unk && ($a !== 'NUMERO' || $b !== 'NUMERO')) {
                        $this->E($e['line'], "Tipos incompatibles: « {$op} » solo funciona con números y recibió {$a} {$op} {$b}.", 'Usa números o variables numéricas.');
                    }
                    if (($op === '/' || $op === '%') && $e['b']['k'] === 'num' && $e['b']['v'] == 0) {
                        $this->E($e['line'], 'No se puede dividir entre 0.', 'Cambia el 0 por otro número.');
                    }
                } elseif (in_array($op, ['<', '>', '<=', '>='], true)) {
                    $t = 'BOOLEANO';
                    if (!$unk && ($a !== 'NUMERO' || $b !== 'NUMERO')) {
                        $this->E($e['line'], "Comparación incompatible: {$a} {$op} {$b}.", 'Con < > <= >= solo se comparan números.');
                    }
                } elseif ($op === '==' || $op === '!=') {
                    $t = 'BOOLEANO';
                    if (!$unk && $a !== $b) {
                        $this->E($e['line'], "Comparación incompatible: no se puede comparar {$a} con {$b}.", 'Compara valores del mismo tipo, por ejemplo: pasos == 3');
                    }
                } else {
                    $t = 'BOOLEANO';
                    if (!$unk && ($a !== 'BOOLEANO' || $b !== 'BOOLEANO')) {
                        $this->E($e['line'], "« {$op} » une condiciones, pero recibió {$a} {$op} {$b}.", 'Ejemplo: LIBRE Y NO META');
                    }
                }
                break;
        }

        $e['ty'] = $t;
        return $t;
    }

    private function need(array &$e, string $want, int $line, string $what, string $hint): string
    {
        $t = $this->ty($e);
        if ($t !== '?' && $t !== $want) {
            $this->E($line, "{$what} necesita un valor {$want} y recibió un {$t}.", $hint);
        }
        return $t;
    }

    private function needActive(array $n, ?string $msg = null): void
    {
        if (!$this->active) {
            $estado = $this->off ? 'apagado' : 'dormido';
            $this->E($n['line'], $msg ?? "El robot está {$estado}: no puede usar {$n['t']}.", 'Escribe ACTIVAR :D antes.');
        }
    }

    private function walk(array &$list, ?string $label): void
    {
        if ($label !== null) {
            $this->scopes[] = ['name' => $label, 'vars' => []];
        }

        foreach ($list as &$n) {
            switch ($n['t']) {
                case 'ACTIVAR':
                    if ($this->active) $this->W($n['line'], 'El robot ya estaba activo.');
                    $this->active = true;
                    $this->off = false;
                    break;

                case 'APAGAR':
                    if (!$this->active) $this->E($n['line'], 'No puedes APAGAR un robot que no está activo.', 'Usa ACTIVAR :D primero.');
                    $this->active = false;
                    $this->off = true;
                    $this->sawOff = true;
                    break;

                case 'AVANZAR':
                case 'RETROCEDER':
                    $estado = $this->off ? 'apagado' : 'dormido';
                    $this->needActive($n, "El robot está {$estado}: no puede {$n['t']}.");
                    if (isset($n['e']) && $n['e'] !== null) {
                        $this->need($n['e'], 'NUMERO', $n['line'], $n['t'], "Ejemplo: {$n['t']} 50 :D");
                        $v = self::lit($n['e']);
                        if ($v !== null && ($v < 1 || $v > 500)) {
                            $this->E($n['line'], "{$n['t']} {$v}: la distancia debe estar entre 1 y 500.", 'Prueba con 50 (una casilla).');
                        }
                    }
                    break;

                case 'GIRAR':
                    $this->needActive($n, 'El robot no está activo: no puede girar.');
                    if (isset($n['e']) && $n['e'] !== null) {
                        $this->need($n['e'], 'NUMERO', $n['line'], 'GIRAR', 'Ejemplo: GIRAR 90 :D');
                        $v = self::lit($n['e']);
                        if ($v !== null && ($v == 0 || abs($v) > 360 || fmod((float)$v, 45.0) != 0.0)) {
                            $this->E($n['line'], "GIRAR {$v}: los grados deben ser múltiplos de 45 (ej. 90 o -90).", 'GIRAR 90 gira a la derecha y GIRAR -90 a la izquierda.');
                        }
                    }
                    break;

                case 'FRENAR':
                case 'SONAR':
                case 'DETECTAR':
                case 'ESCANEAR':
                    $this->needActive($n, "El sensor {$n['t']} necesita energía: el robot no está activo.");
                    break;

                case 'LEER':
                    $this->needActive($n, 'LEER usa el sonar y el robot no está activo.');
                    $s = $this->look($n['v']);
                    if ($s !== null) {
                        $s->uses++;
                        $n['sym'] = $this->symRef($s);
                        if ($s->type !== 'NUMERO') {
                            $this->E($n['line'], "Tipos incompatibles: LEER guarda un NUMERO, pero «{$s->name}» es {$s->type}.", 'Usa otra variable para la distancia.');
                        }
                    } else {
                        $s = $this->declareVar($n['v'], $n['vlex'], 'NUMERO', $n['line'], '(sensor)', 'LEER');
                        $n['sym'] = $this->symRef($s);
                    }
                    break;

                case 'RECIBIR':
                    $s = $this->look($n['v']);
                    if ($s !== null) {
                        $s->uses++;
                        $n['sym'] = $this->symRef($s);
                    } else {
                        $s = $this->declareVar($n['v'], $n['vlex'], 'TEXTO', $n['line'], '(teclado)', 'RECIBIR');
                        $n['sym'] = $this->symRef($s);
                    }
                    break;

                case 'DECIR':
                case 'HABLAR':
                case 'RESPONDER':
                    $this->ty($n['e']);
                    break;

                case 'COMPROBAR':
                    $this->need($n['e'], 'BOOLEANO', $n['line'], 'COMPROBAR', 'Ejemplo: COMPROBAR pasos == 4 :D');
                    break;

                case 'MEMORIA':
                    $t = $this->ty($n['e']);
                    $curScope = $this->scopes[count($this->scopes) - 1]['vars'];
                    if (isset($curScope[$n['v']])) {
                        $cur = $curScope[$n['v']];
                        $this->E($n['line'], "La variable «{$n['vlex']}» ya fue declarada en la línea {$cur->line}.", "Para cambiar su valor usa ACTUALIZAR {$n['vlex']} = ... :D");
                        $n['sym'] = $this->symRef($cur);
                    } else {
                        $s = $this->declareVar($n['v'], $n['vlex'], $t === '?' ? 'NUMERO' : $t, $n['line'], self::esrc($n['e']), 'MEMORIA');
                        $n['sym'] = $this->symRef($s);
                    }
                    break;

                case 'ACTUALIZAR':
                    $s = $this->look($n['v']);
                    $t = $this->ty($n['e']);
                    if ($s === null) {
                        $this->E($n['line'], "No puedes ACTUALIZAR «{$n['vlex']}» porque no existe.", "Primero créala con MEMORIA {$n['vlex']} = ... :D");
                    } else {
                        $s->uses++;
                        $n['sym'] = $this->symRef($s);
                        if ($t !== '?' && $t !== $s->type) {
                            $this->E($n['line'], "Tipos incompatibles: «{$s->name}» es {$s->type} y le quieres guardar un {$t}.", "En «{$s->name}» solo se guardan valores de tipo {$s->type}.");
                        }
                    }
                    break;

                case 'REPETIR':
                    $this->need($n['e'], 'NUMERO', $n['line'], 'REPETIR', 'Ejemplo: REPETIR 3 { ... }');
                    $v = self::lit($n['e']);
                    if ($v !== null && ($v < 1 || $v > 50 || floor((float)$v) != (float)$v)) {
                        $this->E($n['line'], "REPETIR {$v}: se permiten de 1 a 50 repeticiones enteras.", 'Usa un número más pequeño.');
                    }
                    if (empty($n['kids'])) $this->W($n['line'], 'Este REPETIR está vacío.');
                    $this->walk($n['kids'], "REPETIR (l.{$n['line']})");
                    break;

                case 'PATRULLAR':
                    $this->needActive($n, 'PATRULLAR usa los sensores y el robot no está activo.');
                    $this->need($n['e'], 'BOOLEANO', $n['line'], 'PATRULLAR', 'Ejemplo: PATRULLAR LIBRE { AVANZAR 50 :D }');
                    if ($n['e']['k'] === 'bool' && $n['e']['v']) {
                        $this->W($n['line'], 'PATRULLAR VERDADERO nunca termina por sí solo (se detendrá a las 200 vueltas).');
                    }
                    if (empty($n['kids'])) $this->W($n['line'], 'Este PATRULLAR está vacío.');
                    $this->walk($n['kids'], "PATRULLAR (l.{$n['line']})");
                    break;

                case 'SI':
                    if (self::usesSensor($n['e'])) $this->needActive($n, 'SI usa los sensores y el robot no está activo.');
                    $this->need($n['e'], 'BOOLEANO', $n['line'], 'SI', 'Usa una condición, por ejemplo: SI OBSTACULO { ... } o SI pasos > 3 { ... }');
                    if (empty($n['kids'])) $this->W($n['line'], 'Este SI está vacío.');
                    $this->walk($n['kids'], "SI (l.{$n['line']})");
                    if (isset($n['els']) && is_array($n['els'])) {
                        $this->walk($n['els'], "SI NO (l.{$n['line']})");
                    }
                    break;
            }
        }
        unset($n);

        if ($label !== null) {
            array_pop($this->scopes);
        }
    }

    private function run(array &$ast): array
    {
        $this->scopes = [['name' => 'global', 'vars' => []]];
        $this->walk($ast['body'], null);

        foreach ($this->symbols as $s) {
            if ($s->uses === 0 && $s->how === 'MEMORIA') {
                $this->W($s->line, "La variable «{$s->name}» se creó pero nunca se usa.");
            }
        }

        if (!empty($ast['body']) && !$this->sawOff && empty($this->errs)) {
            $this->W(0, 'Terminaste sin APAGAR el robot (se recomienda cerrar con APAGAR :D).');
        }

        return [
            'errs'    => $this->errs,
            'warns'   => $this->warns,
            'symbols' => $this->symbols,
        ];
    }
}