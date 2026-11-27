<?php
declare(strict_types=1);

/* =====================================================================
   FASE 2: ANALIZADOR SINTÁCTICO EN PHP
   Descenso recursivo según la gramática BNF extendida de ROBO-KIDS
   ===================================================================== */
class PErr extends Exception
{
    public int $errLine;
    public string $msg;
    public string $hint;

    public function __construct(int $line, string $msg, string $hint)
    {
        parent::__construct($msg);
        $this->errLine = $line;
        $this->msg = $msg;
        $this->hint = $hint;
    }
}

class Sintactico
{
    private array $toks;
    private int $p = 0;
    private array $errs = [];

    public function __construct(array $toks)
    {
        $this->toks = $toks;
    }

    public static function parse(array $toks): array
    {
        $parser = new self($toks);
        return $parser->run();
    }

    private function peek(int $o = 0): array
    {
        $idx = min($this->p + $o, count($this->toks) - 1);
        return $this->toks[$idx];
    }

    private function nx(): array
    {
        $last = count($this->toks) - 1;
        return $this->toks[$this->p < $last ? $this->p++ : $this->p];
    }

    private function err(int $line, string $msg, string $hint): void
    {
        $this->errs[] = ['phase' => 'Sintáctico', 'line' => $line, 'msg' => $msg, 'hint' => $hint];
    }

    private function isW(array $t, string $w): bool
    {
        return $t['k'] === 'WORD' && $t['v'] === $w;
    }

    private function isOp(array $t, string $v): bool
    {
        return $t['k'] === 'OP' && $t['v'] === $v;
    }

    private function show(array $t): string
    {
        return $t['k'] === 'EOF' ? 'el final del programa' : "«{$t['lex']}»";
    }

    private function reserved(array $t): bool
    {
        return $t['k'] === 'WORD'
            || $t['k'] === 'SENS'
            || $t['k'] === 'BOOL'
            || ($t['k'] === 'OP' && preg_match('/^[A-Z]+$/', (string)$t['v']) === 1);
    }

    // Expresiones (de menor a mayor precedencia)
    private function expr(): array { return $this->orE(); }

    private function orE(): array
    {
        $a = $this->andE();
        while ($this->isOp($this->peek(), 'O')) {
            $t = $this->nx();
            $a = ['k' => 'bin', 'op' => 'O', 'a' => $a, 'b' => $this->andE(), 'line' => $t['line']];
        }
        return $a;
    }

    private function andE(): array
    {
        $a = $this->notE();
        while ($this->isOp($this->peek(), 'Y')) {
            $t = $this->nx();
            $a = ['k' => 'bin', 'op' => 'Y', 'a' => $a, 'b' => $this->notE(), 'line' => $t['line']];
        }
        return $a;
    }

    private function notE(): array
    {
        if ($this->isOp($this->peek(), 'NO')) {
            $t = $this->nx();
            return ['k' => 'un', 'op' => 'NO', 'a' => $this->notE(), 'line' => $t['line']];
        }
        return $this->rel();
    }

    private function rel(): array
    {
        $a = $this->sum();
        $pk = $this->peek();
        if ($pk['k'] === 'OP' && in_array($pk['v'], Lexico::REL, true)) {
            $t = $this->nx();
            $a = ['k' => 'bin', 'op' => $t['v'], 'a' => $a, 'b' => $this->sum(), 'line' => $t['line']];
        }
        return $a;
    }

    private function sum(): array
    {
        $a = $this->mul();
        while ($this->isOp($this->peek(), '+') || $this->isOp($this->peek(), '-')) {
            $t = $this->nx();
            $a = ['k' => 'bin', 'op' => $t['v'], 'a' => $a, 'b' => $this->mul(), 'line' => $t['line']];
        }
        return $a;
    }

    private function mul(): array
    {
        $a = $this->una();
        while ($this->isOp($this->peek(), '*') || $this->isOp($this->peek(), '/') || $this->isOp($this->peek(), '%')) {
            $t = $this->nx();
            $a = ['k' => 'bin', 'op' => $t['v'], 'a' => $a, 'b' => $this->una(), 'line' => $t['line']];
        }
        return $a;
    }

    private function una(): array
    {
        if ($this->isOp($this->peek(), '-')) {
            $t = $this->nx();
            return ['k' => 'un', 'op' => '-', 'a' => $this->una(), 'line' => $t['line']];
        }
        return $this->prim();
    }

    private function prim(): array
    {
        $t = $this->peek();
        switch ($t['k']) {
            case 'NUM':  $this->nx(); return ['k' => 'num', 'v' => $t['v'], 'line' => $t['line']];
            case 'STR':  $this->nx(); return ['k' => 'str', 'v' => $t['v'], 'line' => $t['line']];
            case 'BOOL': $this->nx(); return ['k' => 'bool', 'v' => $t['v'], 'line' => $t['line']];
            case 'SENS': $this->nx(); return ['k' => 'sensor', 'v' => $t['v'], 'line' => $t['line']];
            case 'ID':   $this->nx(); return ['k' => 'var', 'name' => $t['v'], 'lex' => $t['lex'], 'line' => $t['line']];
            case 'LP':
                $this->nx();
                $e = $this->expr();
                if ($this->peek()['k'] !== 'RP') {
                    throw new PErr($t['line'], 'Falta cerrar el paréntesis « ) ».', 'Cada « ( » necesita su « ) ».');
                }
                $this->nx();
                return ['k' => 'par', 'a' => $e, 'line' => $t['line']];
        }
        throw new PErr(
            $t['line'],
            'Se esperaba un valor (número, texto, variable o sensor) y encontré ' . $this->show($t) . '.',
            'Ejemplos de valores: 50, "hola", pasos, OBSTACULO'
        );
    }

    // Recuperación de errores y bloques
    private function sync(): void
    {
        while (!in_array($this->peek()['k'], ['END', 'RB', 'EOF', 'LB'], true)) {
            $this->p++;
        }
        if ($this->peek()['k'] === 'END') {
            $this->p++;
        } elseif ($this->peek()['k'] === 'LB') {
            $this->p++;
            $this->block();
            if ($this->peek()['k'] === 'RB') $this->p++;
        }
    }

    private function endStmt(array $t): void
    {
        if ($this->peek()['k'] === 'END') {
            $this->nx();
            return;
        }
        $this->err($t['line'], "Falta « :D » al final de {$t['v']}.", 'Todas las instrucciones terminan con :D');
        $n = $this->peek();
        if ($n['line'] !== $t['line'] || in_array($n['k'], ['WORD', 'RB', 'EOF'], true)) return;
        $this->sync();
    }

    private function body(array $t): array
    {
        if ($this->peek()['k'] !== 'LB') {
            $this->err($t['line'], "Falta la llave « { » después de {$t['v']}.", 'Abre el bloque con { y ciérralo con }');
        } else {
            $this->nx();
        }
        $kids = $this->block();
        if ($this->peek()['k'] !== 'RB') {
            $this->err($t['line'], "El bloque de {$t['v']} (línea {$t['line']}) no se cerró.", 'Falta una llave « } ».');
        } else {
            $this->nx();
        }
        return $kids;
    }

    private function ident(array $t): array
    {
        $a = $this->peek();
        if ($a['k'] === 'ID') {
            $this->nx();
            return $a;
        }
        if ($this->reserved($a)) {
            throw new PErr(
                $t['line'],
                "«{$a['lex']}» es una palabra reservada y no puede ser nombre de variable.",
                'Usa otro nombre, por ejemplo: pasos, nombre, giro'
            );
        }
        $ej = ($t['v'] === 'MEMORIA' || $t['v'] === 'ACTUALIZAR') ? "{$t['v']} pasos = 3" : "{$t['v']} respuesta";
        throw new PErr($t['line'], "{$t['v']} necesita el nombre de una variable.", "Ejemplo: {$ej} :D");
    }

    private function noArg(array $t): bool
    {
        $pk = $this->peek();
        return $pk['k'] === 'END' || $pk['k'] === 'EOF' || $pk['line'] !== $t['line'];
    }

    private function stmt(): ?array
    {
        try {
            return $this->stmt0();
        } catch (PErr $e) {
            $this->err($e->errLine, $e->msg, $e->hint);
            $this->sync();
            return null;
        }
    }

    private function stmt0(): array
    {
        $t = $this->nx();
        if ($t['k'] === 'ID') {
            if ($this->peek()['k'] === 'ASSIGN') {
                throw new PErr(
                    $t['line'],
                    "No conozco la instrucción «{$t['lex']}».",
                    "Para crear una variable usa MEMORIA {$t['lex']} = ... :D y para cambiarla ACTUALIZAR {$t['lex']} = ... :D"
                );
            }
            $s = Lexico::suggest($t['lex'], Lexico::INSTR);
            throw new PErr(
                $t['line'],
                "No conozco la instrucción «{$t['lex']}».",
                $s ? "¿Quisiste escribir {$s}?" : 'Las instrucciones empiezan con AVANZAR, GIRAR, DECIR, MEMORIA, etc.'
            );
        }
        if ($t['k'] !== 'WORD') {
            throw new PErr(
                $t['line'],
                'Se esperaba una instrucción y encontré ' . $this->show($t) . '.',
                'Las instrucciones empiezan con AVANZAR, GIRAR, DECIR, MEMORIA, etc.'
            );
        }

        $w = $t['v'];
        $node = ['t' => $w, 'line' => $t['line']];

        switch ($w) {
            case 'ACTIVAR':
            case 'APAGAR':
            case 'FRENAR':
            case 'SONAR':
            case 'DETECTAR':
            case 'ESCANEAR':
                break;

            case 'AVANZAR':
            case 'RETROCEDER':
            case 'GIRAR':
                $node['e'] = $this->noArg($t) ? null : $this->expr();
                break;

            case 'DECIR':
            case 'HABLAR':
            case 'RESPONDER':
                if ($this->noArg($t)) {
                    throw new PErr($t['line'], "{$w} necesita un mensaje.", "Ejemplo: {$w} \"hola\" :D");
                }
                $node['e'] = $this->expr();
                break;

            case 'COMPROBAR':
                if ($this->noArg($t)) {
                    throw new PErr($t['line'], 'COMPROBAR necesita una condición.', 'Ejemplo: COMPROBAR META :D');
                }
                $node['e'] = $this->expr();
                break;

            case 'LEER':
            case 'RECIBIR':
                $id = $this->ident($t);
                $node['v'] = $id['v'];
                $node['vlex'] = $id['lex'];
                break;

            case 'MEMORIA':
            case 'ACTUALIZAR':
                $id = $this->ident($t);
                $node['v'] = $id['v'];
                $node['vlex'] = $id['lex'];
                if ($this->peek()['k'] !== 'ASSIGN') {
                    throw new PErr($t['line'], "Falta el « = » en {$w}.", "Ejemplo: {$w} {$id['lex']} = 10 :D");
                }
                $this->nx();
                if ($this->noArg($t)) {
                    throw new PErr($t['line'], "Falta el valor después de « = » en {$w}.", "Ejemplo: {$w} {$id['lex']} = 10 :D");
                }
                $node['e'] = $this->expr();
                break;

            case 'REPETIR':
            case 'PATRULLAR':
            case 'SI':
                if ($this->peek()['k'] === 'LB') {
                    $que = ($w === 'REPETIR') ? 'un número de veces' : 'una condición';
                    $ej = ($w === 'REPETIR')
                        ? 'Ejemplo: REPETIR 3 { ... }'
                        : "Ejemplo: {$w} " . ($w === 'SI' ? 'OBSTACULO' : 'LIBRE') . ' { ... }';
                    throw new PErr($t['line'], "{$w} necesita {$que} antes de « { ».", $ej);
                }
                $node['e'] = $this->expr();
                $node['kids'] = $this->body($t);
                if ($w === 'SI') {
                    $a = $this->peek();
                    $b = $this->peek(1);
                    $c = $this->peek(2);
                    $isElse = false;
                    if ($this->isW($a, 'SINO')) {
                        $this->nx();
                        $isElse = true;
                    } elseif ($this->isW($a, 'SI') && $this->isOp($b, 'NO') && ($c['k'] === 'LB' || $this->isW($c, 'SI'))) {
                        $this->nx();
                        $this->nx();
                        $isElse = true;
                    }
                    if ($isElse) {
                        if ($this->isW($this->peek(), 'SI')) {
                            $s = $this->stmt();
                            $node['els'] = $s ? [$s] : [];
                            $node['elseIf'] = true;
                        } else {
                            $node['els'] = $this->body(['v' => 'SI NO', 'line' => $this->peek()['line']]);
                        }
                    }
                }
                return $node;

            case 'SINO':
                throw new PErr($t['line'], 'Encontré « SI NO » sin un SI antes.', 'Escribe primero SI condición { ... } y luego SI NO { ... }');

            case 'ROBOT':
                throw new PErr($t['line'], 'ROBOT solo puede ir al inicio del programa.', 'Ejemplo: ROBOT Explorador { ... }');
        }

        $this->endStmt($t);
        return $node;
    }

    private function block(): array
    {
        $kids = [];
        while ($this->peek()['k'] !== 'EOF' && $this->peek()['k'] !== 'RB') {
            $s = $this->stmt();
            if ($s !== null) $kids[] = $s;
            if (count($this->errs) > 30) break;
        }
        return $kids;
    }

    private function run(): array
    {
        $prog = ['t' => 'PROGRAMA', 'name' => null, 'body' => [], 'line' => 1, 'wrapped' => false];

        if ($this->isW($this->peek(), 'ROBOT')) {
            $t = $this->nx();
            $prog['line'] = $t['line'];
            $prog['wrapped'] = true;

            if ($this->peek()['k'] === 'ID') {
                $prog['name'] = $this->nx()['lex'];
            } elseif ($this->reserved($this->peek())) {
                $this->err($t['line'], "«{$this->peek()['lex']}» es una palabra reservada y no puede ser el nombre del robot.", 'Ejemplo: ROBOT Explorador { ... }');
                $this->nx();
            } else {
                $this->err($t['line'], 'Falta el nombre del robot después de ROBOT.', 'Ejemplo: ROBOT Explorador { ... }');
            }

            if ($this->peek()['k'] !== 'LB') {
                $this->err($t['line'], 'Falta la llave « { » después del nombre del robot.', 'Ejemplo: ROBOT Explorador { ... }');
            } else {
                $this->nx();
            }

            $prog['body'] = $this->block();

            if ($this->peek()['k'] !== 'RB') {
                $this->err($t['line'], 'Falta la llave « } » que cierra el bloque ROBOT.', 'Pon } al final del programa.');
            } else {
                $this->nx();
            }

            while ($this->peek()['k'] !== 'EOF') {
                $t2 = $this->peek();
                if ($t2['k'] === 'RB') {
                    $this->err($t2['line'], 'Hay una llave « } » de más.', 'Quítala o abre un bloque antes.');
                    $this->p++;
                    continue;
                }
                $this->err($t2['line'], 'Hay instrucciones fuera del bloque ROBOT { ... }.', 'Muévelas antes de la última llave }.');
                while ($this->peek()['k'] !== 'EOF') $this->p++;
            }
        } else {
            while ($this->peek()['k'] !== 'EOF') {
                array_push($prog['body'], ...$this->block());
                if ($this->peek()['k'] === 'RB') {
                    $this->err($this->peek()['line'], 'Hay una llave « } » de más.', 'Quítala o abre un bloque antes.');
                    $this->p++;
                }
                if (count($this->errs) > 30) break;
            }
        }

        return ['ast' => $prog, 'errs' => $this->errs];
    }
}