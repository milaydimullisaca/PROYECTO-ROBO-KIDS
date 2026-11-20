<?php

/* ╔══════════════════════════════════════════════════════════╗
 * ║              ANALIZADOR LÉXICO :: ROBO-KIDS              ║
 * ╠══════════════════════════════════════════════════════════╣
 * ║  1. Constantes de tokens (agrupadas por categoría)       ║
 * ║  2. TablaLexica   -> definición fija del lenguaje        ║
 * ║  3. Token         -> resultado de cada lectura           ║
 * ║  4. Tokenizador   -> getToken() devuelve UN token        ║
 * ║  5. Programa de prueba                                   ║
 * ╚══════════════════════════════════════════════════════════╝
 */


/* ============================================================
 * 1. CONSTANTES DE TOKENS
 * ============================================================ */

// --- ESTRUCTURA / INICIO / FIN -------------------------------
const ROBOT        = 1;    // ROBOT nombre [ ... ]
const INICIAR      = 2;    // INICIAR :D
const FINALIZAR    = 3;    // FINALIZAR :D

// --- ENTRADA / SALIDA ----------------------------------------
const DECIR        = 4;    // DECIR "Hola"
const LEER         = 5;    // LEER a :D

// --- VARIABLES -----------------------------------------------
const MEMORIA      = 6;    // MEMORIA energia = 100
const ACTUALIZAR   = 7;    // ACTUALIZAR energia = 80

// --- CONTROL DE FLUJO ----------------------------------------
const SI           = 8;    // SI cond [ ... ]
const SINO         = 9;    // SI NO [ ... ]
const REPETIR      = 10;   // REPETIR 5 [ ... ]
const PATRULLAR    = 11;   // PATRULLAR cond [ ... ]
const FUNCION      = 12;   // FUNCION nombre [ ... ]
const COMPROBAR    = 13;   // COMPROBAR cond [ ... ]

// --- MOVIMIENTO ----------------------------------------------
const AVANZAR      = 14;
const RETROCEDER   = 15;
const GIRAR        = 16;
const FRENAR       = 17;
const BRILLAR      = 18;
const OSCURECER    = 19;

// --- SONIDO / SENSORES ---------------------------------------
const SONAR        = 20;
const ESCANEAR     = 21;
const DETECTAR     = 22;

// --- DIRECCIONES / COLORES -----------------------------------
const DERECHA      = 23;
const IZQUIERDA    = 24;
const AZUL         = 25;
const ROJO         = 26;
const VERDE        = 27;

// --- ELEMENTOS DE SENSOR -------------------------------------
const DISTANCIA    = 28;
const OBSTACULO    = 29;
const LUZ          = 30;
const SONIDO       = 31;

// --- OPERADORES LÓGICOS --------------------------------------
const Y            = 32;
const O            = 33;
const NO           = 34;

// --- SÍMBOLOS ------------------------------------------------
const CORCHETE_ABRE   = 35;   // [
const CORCHETE_CIERRA = 36;   // ]
const PAREN_ABRE      = 37;   // (
const PAREN_CIERRA    = 38;   // )
const COMA            = 39;   // ,
const ASIGNACION      = 40;   // =
const TERMINADOR      = 41;   // :D

// --- OPERADORES ARITMÉTICOS ----------------------------------
const SUMA            = 42;
const RESTA           = 43;
const MULTIPLICACION  = 44;
const DIVISION        = 45;
const MODULO          = 46;

// --- OPERADORES DE COMPARACIÓN -------------------------------
const IGUAL        = 47;
const DIFERENTE    = 48;
const MAYOR        = 49;
const MENOR        = 50;
const MAYOR_IGUAL  = 51;
const MENOR_IGUAL  = 52;

// --- LITERALES E IDENTIFICADORES -----------------------------
const ID           = 53;
const NUM          = 54;
const DECIMAL      = 55;
const CADENA       = 56;
const BOOLEANO     = 57;

// --- ESPECIALES ----------------------------------------------
const FIN          = 666;
const ERROR        = 999;


/* ============================================================
 * 2. TABLA LÉXICA
 *
 * Cada grupo: [tipo => [lexema => token, ...]]
 * Las palabras reservadas se guardan SIN tilde (FUNCION,
 * OBSTACULO) porque el tokenizador normaliza lo leído.
 * ============================================================ */

class TablaLexica
{
    private array $porToken  = [];
    private array $porLexema = [];

    /** Símbolos ordenados de mayor a menor longitud. */
    private array $simbolos  = [];


    public function __construct()
    {
        $this->CargarGrupos();
        $this->OrdenarSimbolos();
    }


    /* ---------- Definición del lenguaje -------------------- */

    private function CargarGrupos(): void
    {
        $grupos = [

            'palabra_reservada' => [
                // Estructura, inicio y fin
                'ROBOT' => ROBOT, 'INICIAR' => INICIAR, 'FINALIZAR' => FINALIZAR,
                // Entrada / salida
                'DECIR' => DECIR, 'LEER' => LEER,
                // Variables
                'MEMORIA' => MEMORIA, 'ACTUALIZAR' => ACTUALIZAR,
                // Control de flujo (SI NO se resuelve en el Tokenizador)
                'SI' => SI, 'REPETIR' => REPETIR, 'PATRULLAR' => PATRULLAR,
                'FUNCION' => FUNCION, 'COMPROBAR' => COMPROBAR,
                // Movimiento
                'AVANZAR' => AVANZAR, 'RETROCEDER' => RETROCEDER, 'GIRAR' => GIRAR,
                'FRENAR' => FRENAR, 'BRILLAR' => BRILLAR, 'OSCURECER' => OSCURECER,
                // Sonido y sensores
                'SONAR' => SONAR, 'ESCANEAR' => ESCANEAR, 'DETECTAR' => DETECTAR,
                // Direcciones y colores
                'DERECHA' => DERECHA, 'IZQUIERDA' => IZQUIERDA,
                'AZUL' => AZUL, 'ROJO' => ROJO, 'VERDE' => VERDE,
                // Elementos de sensor
                'DISTANCIA' => DISTANCIA, 'OBSTACULO' => OBSTACULO,
                'LUZ' => LUZ, 'SONIDO' => SONIDO,
            ],

            'operador_logico' => [
                'Y' => Y, 'O' => O, 'NO' => NO,
            ],

            'booleano' => [
                'VERDADERO' => BOOLEANO, 'FALSO' => BOOLEANO,
            ],

            'simbolo' => [
                '[' => CORCHETE_ABRE, ']' => CORCHETE_CIERRA,
                '(' => PAREN_ABRE,    ')' => PAREN_CIERRA,
            ],

            'separador'   => [ ',' => COMA ],
            'terminador'  => [ ':D' => TERMINADOR ],

            'operador' => [
                '=' => ASIGNACION,
                '+' => SUMA, '-' => RESTA, '*' => MULTIPLICACION,
                '/' => DIVISION, '%' => MODULO,
            ],

            'operador_relacional' => [
                '==' => IGUAL, '!=' => DIFERENTE,
                '>=' => MAYOR_IGUAL, '<=' => MENOR_IGUAL,
                '>'  => MAYOR, '<' => MENOR,
            ],
        ];

        foreach ($grupos as $tipo => $lexemas) {
            foreach ($lexemas as $lexema => $token) {
                $this->Agregar($token, $lexema, $tipo);
            }
        }

        // SI NO no se busca por lexema: lo arma el Tokenizador.
        $this->porToken[SINO] = [
            'token' => SINO, 'lexema' => 'SI NO', 'tipo' => 'palabra_reservada'
        ];
    }


    private function Agregar(int $token, string $lexema, string $tipo): void
    {
        $dato = ['token' => $token, 'lexema' => $lexema, 'tipo' => $tipo];

        $this->porToken[$token]   = $dato;
        $this->porLexema[$lexema] = $dato;

        // Lo que no empieza con letra es un símbolo (se lee por coincidencia)
        if (!ctype_alpha($lexema[0])) {
            $this->simbolos[] = $dato;
        }
    }


    private function OrdenarSimbolos(): void
    {
        usort(
            $this->simbolos,
            fn($a, $b) => strlen($b['lexema']) <=> strlen($a['lexema'])
        );
    }


    /* ---------- Consultas ---------------------------------- */

    public function BuscarPorToken(int $token): ?array
    {
        return $this->porToken[$token] ?? null;
    }

    public function BuscarPorLexema(string $lexema): ?array
    {
        return $this->porLexema[$lexema] ?? null;
    }

    public function ExisteLexema(string $lexema): bool
    {
        return isset($this->porLexema[$lexema]);
    }

    /** Devuelve el símbolo más largo que coincide en $posicion. */
    public function BuscarSimboloMasLargo(string $codigo, int $posicion): ?array
    {
        foreach ($this->simbolos as $simbolo) {
            $longitud = strlen($simbolo['lexema']);

            if (substr($codigo, $posicion, $longitud) === $simbolo['lexema']) {
                return $simbolo + ['longitud' => $longitud];
            }
        }

        return null;
    }

    public function getTabla(): array
    {
        return $this->porToken;
    }
}


/* ============================================================
 * 3. TOKEN
 * ============================================================ */

class Token
{
    public function __construct(
        public int     $token,
        public ?string $lexema,
        public int     $linea,
        public int     $columna
    ) {}


    public function Mostrar(): void
    {
        static $nombres = null;

        if ($nombres === null) {
            $constantes = get_defined_constants(true)['user'] ?? [];
            $nombres = array_flip(array_filter($constantes, 'is_int'));
        }

        // Relleno por caracteres (no bytes) para que las tildes no desalineen
        $pad = fn(string $t, int $n) =>
            $t . str_repeat(' ', max(0, $n - mb_strlen($t, 'UTF-8')));

        echo $pad((string)$this->token, 5) . ' '
           . $pad($nombres[$this->token] ?? '?', 16) . ' '
           . $pad($this->lexema ?? 'null', 26) . ' '
           . str_pad((string)$this->linea, 5, ' ', STR_PAD_LEFT) . ' '
           . str_pad((string)$this->columna, 8, ' ', STR_PAD_LEFT)
           . "\n";
    }
}


/* ============================================================
 * 4. TOKENIZADOR
 *
 * getToken() devuelve UN objeto Token por llamada.
 * ============================================================ */

class Tokenizador
{
    private int $indice  = 0;
    private int $linea   = 1;
    private int $columna = 1;


    public function __construct(
        private string      $codigo,
        private TablaLexica $tablaLexica
    ) {}


    /* ---------------- Punto de entrada --------------------- */

    public function getToken(): Token
    {
        $this->OmitirEspaciosYComentarios();

        $linea   = $this->linea;
        $columna = $this->columna;

        if ($this->FinCodigo()) {
            return new Token(FIN, null, $linea, $columna);
        }

        $c = $this->Actual();

        // 1) Identificador / palabra reservada
        if ($this->EsInicioIdentificador($c)) {
            return $this->TokenIdentificador($linea, $columna);
        }

        // 2) Número
        if (ctype_digit($c)) {
            return $this->TokenNumero($linea, $columna);
        }

        // 3) Cadena
        if ($c === '"') {
            $cadena = $this->LeerCadena();

            return $cadena === null
                ? new Token(ERROR, null, $linea, $columna)
                : new Token(CADENA, $cadena, $linea, $columna);
        }

        // 4) Símbolo / operador (lexema más largo)
        $simbolo = $this->tablaLexica->BuscarSimboloMasLargo($this->codigo, $this->indice);

        if ($simbolo !== null) {
            $this->Avanzar($simbolo['longitud']);
            return new Token($simbolo['token'], $simbolo['lexema'], $linea, $columna);
        }

        // 5) Carácter desconocido
        $this->Avanzar();
        return new Token(ERROR, $c, $linea, $columna);
    }


    /* ---------------- Identificadores ---------------------- */

    private function TokenIdentificador(int $linea, int $columna): Token
    {
        $lexema = $this->LeerIdentificador();
        $clave  = $this->Normalizar($lexema);

        // Caso especial: "SI NO [" => token SINO
        if ($clave === 'SI' && $this->SigueSiNo()) {
            return new Token(SINO, 'SI NO', $linea, $columna);
        }

        $elemento = $this->tablaLexica->BuscarPorLexema($clave);

        return new Token(
            $elemento['token'] ?? ID,
            $lexema,
            $linea,
            $columna
        );
    }


    /**
     * Detecta "SI NO" seguido de '['. Si es así, consume el NO.
     * Si no (ej. "SI NO energia > 0 [") es SI + operador NO.
     */
    private function SigueSiNo(): bool
    {
        $i = $this->indice;
        $n = strlen($this->codigo);

        while ($i < $n && ($this->codigo[$i] === ' ' || $this->codigo[$i] === "\t")) {
            $i++;
        }

        if (substr($this->codigo, $i, 2) !== 'NO') {
            return false;
        }

        $i += 2;

        if ($i < $n && $this->EsCaracterIdentificador($this->codigo[$i])) {
            return false;       // era algo como "NOMBRE"
        }

        while ($i < $n && ctype_space($this->codigo[$i])) {
            $i++;
        }

        if ($i >= $n || $this->codigo[$i] !== '[') {
            return false;
        }

        $this->Avanzar($this->PosicionHasta($i));   // consume espacios + NO
        return true;
    }


    private function PosicionHasta(int $destino): int
    {
        // Cantidad de caracteres hasta justo antes del '[' (sin consumirlo)
        $i = $destino;
        while ($i > $this->indice && ctype_space($this->codigo[$i - 1])) {
            $i--;
        }
        return $i - $this->indice;
    }


    private function LeerIdentificador(): string
    {
        $lexema = '';

        while (!$this->FinCodigo() && $this->EsCaracterIdentificador($this->Actual())) {
            $lexema .= $this->Actual();
            $this->Avanzar();
        }

        return $lexema;
    }


    /** Letras, '_' y bytes UTF-8 (tildes, ñ). */
    private function EsInicioIdentificador(string $c): bool
    {
        return ctype_alpha($c) || $c === '_' || ord($c) >= 128;
    }

    private function EsCaracterIdentificador(string $c): bool
    {
        return $this->EsInicioIdentificador($c) || ctype_digit($c);
    }


    /** Quita tildes para comparar con la tabla (FUNCIÓN -> FUNCION). */
    private function Normalizar(string $texto): string
    {
        return strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
    }


    /* ---------------- Números ------------------------------ */

    private function TokenNumero(int $linea, int $columna): Token
    {
        $lexema  = '';
        $puntos  = 0;

        while (!$this->FinCodigo()) {
            $c = $this->Actual();

            if (ctype_digit($c)) {
                $lexema .= $c;
            } elseif ($c === '.') {
                $puntos++;
                $lexema .= $c;
            } else {
                break;
            }

            $this->Avanzar();
        }

        // Más de un punto (12.4.5) o punto final (12.) => ERROR
        if ($puntos > 1 || str_ends_with($lexema, '.')) {
            return new Token(ERROR, $lexema, $linea, $columna);
        }

        return new Token($puntos === 1 ? DECIMAL : NUM, $lexema, $linea, $columna);
    }


    /* ---------------- Cadenas ------------------------------ */

    private function LeerCadena(): ?string
    {
        $lexema = '';
        $this->Avanzar();                       // comilla inicial

        while (!$this->FinCodigo()) {
            $c = $this->Actual();

            if ($c === '"') {
                $this->Avanzar();
                return $lexema;
            }

            if ($c === "\n") {
                return null;                    // cadena sin cerrar
            }

            $lexema .= $c;
            $this->Avanzar();
        }

        return null;
    }


    /* ---------------- Espacios y comentarios --------------- */

    private function OmitirEspaciosYComentarios(): void
    {
        while (!$this->FinCodigo()) {
            $c = $this->Actual();

            if (ctype_space($c)) {
                $this->Avanzar();
                continue;
            }

            if ($c === '/' && ($this->codigo[$this->indice + 1] ?? '') === '/') {
                while (!$this->FinCodigo() && $this->Actual() !== "\n") {
                    $this->Avanzar();
                }
                continue;
            }

            break;
        }
    }


    /* ---------------- Utilidades de cursor ----------------- */

    private function Actual(): string
    {
        return $this->codigo[$this->indice];
    }

    private function Avanzar(int $cantidad = 1): void
    {
        for ($i = 0; $i < $cantidad && !$this->FinCodigo(); $i++) {
            if ($this->Actual() === "\n") {
                $this->linea++;
                $this->columna = 1;
            } else {
                $this->columna++;
            }
            $this->indice++;
        }
    }

    private function FinCodigo(): bool
    {
        return $this->indice >= strlen($this->codigo);
    }
}


/* ============================================================
 * 5. PROGRAMA DE PRUEBA
 * ============================================================ */

$codigo = <<<'ROBO'
ROBOT Explorador [

    INICIAR :D

    MEMORIA energía = 100 :D
    MEMORIA contador = 0 :D

    DECIR "Iniciando exploracion" :D
    LEER contador :D

    FUNCIÓN SALUDAR [
        DECIR "Hola,soy tu Robot \:D/":D
    ]

    REPETIR 5 [
        AVANZAR 30 :D
        ESCANEAR DISTANCIA :D

        SI DISTANCIA < 20 Y NO DETECTAR OBSTÁCULO [
            FRENAR :D
            GIRAR DERECHA 90 :D
            SONAR "BEEP" :D
        ] SI NO [
            AVANZAR 20 :D
        ]

        ACTUALIZAR energía = energía - 10 :D
    ]

    PATRULLAR energía > 0 [
        ACTUALIZAR energía = energía - 25 :D
    ]

    COMPROBAR energía == 100 [
        DECIR "Energia completa" :D
    ]

]
ROBO;

$tokenizador = new Tokenizador($codigo, new TablaLexica());

echo "<pre>";
echo "ANALIZADOR LÉXICO ROBO-KIDS\n";
echo str_repeat('=', 66) . "\n";
printf("%-5s %-16s %-26s %5s %8s\n", 'TOKEN', 'NOMBRE', 'LEXEMA', 'LINEA', 'COLUMNA');
echo str_repeat('-', 66) . "\n";

while (true) {
    $token = $tokenizador->getToken();

    if ($token->token === FIN) {
        break;                  // no se muestra la fila FIN
    }

    $token->Mostrar();
}

echo "</pre>";