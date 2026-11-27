<?php
declare(strict_types=1);

require_once __DIR__ . '/Lexico.php';
require_once __DIR__ . '/Sintactico.php';
require_once __DIR__ . '/Semantico.php';

/* =====================================================================
   ORQUESTADOR DEL COMPILADOR ROBO-KIDS EN PHP
   Conecta las fases: Léxico -> Sintáctico -> Semántico
   ===================================================================== */
class Compilador
{
    public static function compile(string $src): array
    {
        $L = Lexico::lex($src);
        $P = !empty($L['errs']) ? ['ast' => null, 'errs' => []] : Sintactico::parse($L['toks']);
        $ast = $P['ast'];

        if (!empty($L['errs']) || !empty($P['errs']) || $ast === null) {
            $S = ['errs' => [], 'warns' => [], 'symbols' => []];
        } else {
            $S = Semantico::analizar($ast);
        }

        $errors = array_merge($L['errs'], $P['errs'], $S['errs']);
        usort($errors, static fn(array $a, array $b): int => $a['line'] <=> $b['line']);

        $warns = $S['warns'];
        usort($warns, static fn(array $a, array $b): int => $a['line'] <=> $b['line']);

        return [
            'src'    => $src,
            'lex'    => $L,
            'parse'  => ['errs' => $P['errs']],
            'sem'    => $S,
            'ast'    => $ast,
            'tokens' => max(0, count($L['toks']) - 1),
            'errors' => $errors,
            'warns'  => $warns,
        ];
    }
}