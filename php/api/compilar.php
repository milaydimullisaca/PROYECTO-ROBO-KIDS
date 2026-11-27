<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Compilador.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$accion = $data['accion'] ?? 'compilar';

try {
    switch ($accion) {
        case 'probar_lexema':
            $lexema = (string)($data['lexema'] ?? '');
            echo json_encode([
                'ok'        => true,
                'resultado' => Lexico::tryLexeme($lexema),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'pruebas':
            $casos = is_array($data['casos'] ?? null) ? $data['casos'] : [];
            $resultados = [];
            foreach ($casos as $t) {
                $r = Compilador::compile((string)($t['c'] ?? ''));
                $got = !empty($r['errors']) ? $r['errors'][0]['phase'] : 'ok';
                $resultados[] = [
                    'got'  => $got,
                    'pass' => $got === ($t['x'] ?? 'ok'),
                    'msg'  => !empty($r['errors'])
                        ? "Línea {$r['errors'][0]['line']}: {$r['errors'][0]['msg']}"
                        : "{$r['tokens']} tokens, " . count($r['sem']['symbols']) . ' variable(s)',
                ];
            }
            echo json_encode(['ok' => true, 'resultados' => $resultados], JSON_UNESCAPED_UNICODE);
            break;

        case 'compilar':
        default:
            $codigo = (string)($data['code'] ?? '');
            echo json_encode(Compilador::compile($codigo), JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error interno en PHP: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}