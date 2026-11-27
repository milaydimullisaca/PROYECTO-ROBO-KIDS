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
 * 5. ANALIZADOR SINTACTICO ROBO-KIDS
 * ============================================================ */

class AnalizadorSintactico
{
    private Tokenizador $tokenizador;
    private Token $actual;
    private array $seguimiento = [];
    private ?array $error = null;
    private int $paso = 1;
    private array $transiciones;

    private array $nombresTokens = [
        ROBOT => 'ROBOT', INICIAR => 'INICIAR', FINALIZAR => 'FINALIZAR',
        DECIR => 'DECIR', LEER => 'LEER', MEMORIA => 'MEMORIA',
        ACTUALIZAR => 'ACTUALIZAR', SI => 'SI', SINO => 'SINO',
        REPETIR => 'REPETIR', PATRULLAR => 'PATRULLAR', FUNCION => 'FUNCION',
        COMPROBAR => 'COMPROBAR', AVANZAR => 'AVANZAR', RETROCEDER => 'RETROCEDER',
        GIRAR => 'GIRAR', FRENAR => 'FRENAR', BRILLAR => 'BRILLAR',
        OSCURECER => 'OSCURECER', SONAR => 'SONAR', ESCANEAR => 'ESCANEAR',
        DETECTAR => 'DETECTAR', DERECHA => 'DERECHA', IZQUIERDA => 'IZQUIERDA',
        AZUL => 'AZUL', ROJO => 'ROJO', VERDE => 'VERDE', DISTANCIA => 'DISTANCIA',
        OBSTACULO => 'OBSTACULO', LUZ => 'LUZ', SONIDO => 'SONIDO',
        Y => 'Y', O => 'O', NO => 'NO', CORCHETE_ABRE => 'CORCHETE_ABRE',
        CORCHETE_CIERRA => 'CORCHETE_CIERRA', PAREN_ABRE => 'PAREN_ABRE',
        PAREN_CIERRA => 'PAREN_CIERRA', COMA => 'COMA', ASIGNACION => 'ASIGNACION',
        TERMINADOR => 'TERMINADOR', SUMA => 'SUMA', RESTA => 'RESTA',
        MULTIPLICACION => 'MULTIPLICACION', DIVISION => 'DIVISION', MODULO => 'MODULO',
        IGUAL => 'IGUAL', DIFERENTE => 'DIFERENTE', MAYOR => 'MAYOR',
        MENOR => 'MENOR', MAYOR_IGUAL => 'MAYOR_IGUAL', MENOR_IGUAL => 'MENOR_IGUAL',
        ID => 'ID', NUM => 'NUM', DECIMAL => 'DECIMAL', CADENA => 'CADENA',
        BOOLEANO => 'BOOLEANO', FIN => 'FIN', ERROR => 'ERROR'
    ];

    public function __construct(Tokenizador $tokenizador)
    {
        $this->tokenizador = $tokenizador;

        /*
         * Tabla de transiciones del lenguaje ROBO-KIDS.
         * Cada estado indica los tokens que pueden continuar
         * la estructura sintactica en ese punto.
         */
        $this->transiciones = [
            0  => ['ROBOT' => 1],
            1  => ['ID' => 2],
            2  => ['CORCHETE_ABRE' => 3],
            3  => ['INICIAR' => 4],
            4  => ['TERMINADOR' => 5],
            5  => [
                'DECIR' => 6, 'LEER' => 7, 'MEMORIA' => 8,
                'ACTUALIZAR' => 8, 'SI' => 9, 'REPETIR' => 10,
                'PATRULLAR' => 9, 'FUNCION' => 11, 'COMPROBAR' => 9,
                'AVANZAR' => 12, 'RETROCEDER' => 12, 'GIRAR' => 13,
                'FRENAR' => 14, 'BRILLAR' => 15, 'OSCURECER' => 15,
                'SONAR' => 16, 'ESCANEAR' => 17, 'DETECTAR' => 18,
                'FINALIZAR' => 19, 'CORCHETE_CIERRA' => 20
            ],
            6 => ['CADENA' => 21, 'ID' => 21],
            7 => ['ID' => 22],
            8 => ['ID' => 23],
            9 => ['expresion' => 24],
            10 => ['expresion' => 24],
            11 => ['ID' => 25],
            12 => ['expresion' => 26],
            13 => ['DERECHA' => 27, 'IZQUIERDA' => 27],
            14 => ['TERMINADOR' => 5],
            15 => ['AZUL' => 28, 'ROJO' => 28, 'VERDE' => 28, 'TERMINADOR' => 5],
            16 => ['CADENA' => 29, 'ID' => 29, 'NUM' => 29, 'DECIMAL' => 29],
            17 => ['DISTANCIA' => 30, 'OBSTACULO' => 30, 'LUZ' => 30, 'SONIDO' => 30],
            18 => ['DISTANCIA' => 30, 'OBSTACULO' => 30, 'LUZ' => 30, 'SONIDO' => 30],
            19 => ['TERMINADOR' => 20],
            20 => ['FIN' => 20],
            21 => ['TERMINADOR' => 5],
            22 => ['TERMINADOR' => 5],
            23 => ['ASIGNACION' => 31],
            24 => ['CORCHETE_ABRE' => 32],
            25 => ['CORCHETE_ABRE' => 32],
            26 => ['TERMINADOR' => 5],
            27 => ['expresion' => 33],
            28 => ['TERMINADOR' => 5],
            29 => ['TERMINADOR' => 5],
            30 => ['TERMINADOR' => 5],
            31 => ['expresion' => 34],
            32 => ['instrucciones' => 5, 'CORCHETE_CIERRA' => 5],
            33 => ['TERMINADOR' => 5],
            34 => ['TERMINADOR' => 5]
        ];

        $this->actual = $this->tokenizador->getToken();
    }

    public function Analizar(): bool
    {
        try {
            $this->Esperar(ROBOT, 'encabezado del programa');
            $this->Esperar(ID, 'nombre del robot');
            $this->Esperar(CORCHETE_ABRE, '[');
            $this->Esperar(INICIAR, 'inicio del programa');
            $this->Esperar(TERMINADOR, ':D despues de INICIAR');

            while ($this->actual->token !== FINALIZAR &&
                   $this->actual->token !== CORCHETE_CIERRA &&
                   $this->actual->token !== FIN) {
                $this->Instruccion();
            }

            if ($this->actual->token === FINALIZAR) {
                $this->Consumir(FINALIZAR, 'FINALIZAR');
                $this->Esperar(TERMINADOR, ':D despues de FINALIZAR');
            }

            $this->Esperar(CORCHETE_CIERRA, '] de cierre del robot');
            $this->Esperar(FIN, 'fin del archivo');
            return true;
        } catch (Throwable $e) {
            if ($this->error === null) {
                $this->error = [
                    'tipo' => 'sintactico',
                    'linea' => $this->actual->linea,
                    'columna' => $this->actual->columna,
                    'mensaje' => $e->getMessage()
                ];
            }
            return false;
        }
    }

    private function Instruccion(): void
    {
        switch ($this->actual->token) {
            case DECIR:
                $this->Consumir(DECIR, 'instruccion DECIR');
                if ($this->actual->token === CADENA) {
                    $this->Consumir(CADENA, 'texto para DECIR');
                } else {
                    $this->Expresion();
                }
                $this->Esperar(TERMINADOR, ':D al final de DECIR');
                break;

            case LEER:
                $this->Consumir(LEER, 'instruccion LEER');
                $this->Esperar(ID, 'variable que recibira el dato');
                $this->Esperar(TERMINADOR, ':D al final de LEER');
                break;

            case MEMORIA:
            case ACTUALIZAR:
                $nombre = $this->actual->token === MEMORIA ? 'MEMORIA' : 'ACTUALIZAR';
                $this->Consumir($this->actual->token, $nombre);
                $this->Esperar(ID, 'nombre de variable');
                $this->Esperar(ASIGNACION, '= en la asignacion');
                $this->Expresion();
                $this->Esperar(TERMINADOR, ':D al final de la asignacion');
                break;

            case SI:
            case PATRULLAR:
            case COMPROBAR:
                $nombre = $this->Nombre($this->actual->token);
                $this->Consumir($this->actual->token, $nombre);
                $this->Expresion();
                $this->Bloque();
                if ($this->actual->token === SINO) {
                    $this->Consumir(SINO, 'SI NO');
                    $this->Bloque();
                }
                break;

            case REPETIR:
                $this->Consumir(REPETIR, 'REPETIR');
                $this->Expresion();
                $this->Bloque();
                break;

            case FUNCION:
                $this->Consumir(FUNCION, 'FUNCION');
                $this->Esperar(ID, 'nombre de la funcion');
                $this->Bloque();
                break;

            case AVANZAR:
            case RETROCEDER:
                $nombre = $this->Nombre($this->actual->token);
                $this->Consumir($this->actual->token, $nombre);
                $this->Expresion();
                $this->Esperar(TERMINADOR, ':D al final del movimiento');
                break;

            case GIRAR:
                $this->Consumir(GIRAR, 'GIRAR');
                if (!in_array($this->actual->token, [DERECHA, IZQUIERDA], true)) {
                    $this->Fallar('DERECHA o IZQUIERDA despues de GIRAR');
                }
                $this->Consumir($this->actual->token, 'direccion del giro');
                $this->Expresion();
                $this->Esperar(TERMINADOR, ':D al final de GIRAR');
                break;

            case FRENAR:
                $this->Consumir(FRENAR, 'FRENAR');
                $this->Esperar(TERMINADOR, ':D al final de FRENAR');
                break;

            case BRILLAR:
            case OSCURECER:
                $nombre = $this->Nombre($this->actual->token);
                $this->Consumir($this->actual->token, $nombre);
                if (in_array($this->actual->token, [AZUL, ROJO, VERDE], true)) {
                    $this->Consumir($this->actual->token, 'color');
                }
                $this->Esperar(TERMINADOR, ':D al final de la instruccion');
                break;

            case SONAR:
                $this->Consumir(SONAR, 'SONAR');
                if (in_array($this->actual->token, [CADENA, ID, NUM, DECIMAL], true)) {
                    if ($this->actual->token === CADENA) {
                        $this->Consumir(CADENA, 'sonido');
                    } else {
                        $this->Expresion();
                    }
                } else {
                    $this->Fallar('texto, identificador o numero despues de SONAR');
                }
                $this->Esperar(TERMINADOR, ':D al final de SONAR');
                break;

            case ESCANEAR:
            case DETECTAR:
                $nombre = $this->Nombre($this->actual->token);
                $this->Consumir($this->actual->token, $nombre);
                if (!in_array($this->actual->token, [DISTANCIA, OBSTACULO, LUZ, SONIDO], true)) {
                    $this->Fallar('DISTANCIA, OBSTACULO, LUZ o SONIDO');
                }
                $this->Consumir($this->actual->token, 'elemento del sensor');
                $this->Esperar(TERMINADOR, ':D al final del sensor');
                break;

            default:
                $this->Fallar('una instruccion valida de ROBO-KIDS');
        }
    }

    private function Bloque(): void
    {
        $this->Esperar(CORCHETE_ABRE, '[ para abrir el bloque');
        while ($this->actual->token !== CORCHETE_CIERRA &&
               $this->actual->token !== FIN) {
            $this->Instruccion();
        }
        $this->Esperar(CORCHETE_CIERRA, '] para cerrar el bloque');
    }

    private function Expresion(): void
    {
        $this->ExpresionLogica();
    }

    private function ExpresionLogica(): void
    {
        $this->ExpresionComparacion();
        while (in_array($this->actual->token, [Y, O], true)) {
            $this->Consumir($this->actual->token, 'operador logico');
            $this->ExpresionComparacion();
        }
    }

    private function ExpresionComparacion(): void
    {
        $this->ExpresionAditiva();
        if (in_array($this->actual->token, [IGUAL, DIFERENTE, MAYOR, MENOR, MAYOR_IGUAL, MENOR_IGUAL], true)) {
            $this->Consumir($this->actual->token, 'operador de comparacion');
            $this->ExpresionAditiva();
        }
    }

    private function ExpresionAditiva(): void
    {
        $this->ExpresionMultiplicativa();
        while (in_array($this->actual->token, [SUMA, RESTA], true)) {
            $this->Consumir($this->actual->token, 'operador aritmetico');
            $this->ExpresionMultiplicativa();
        }
    }

    private function ExpresionMultiplicativa(): void
    {
        $this->Unario();
        while (in_array($this->actual->token, [MULTIPLICACION, DIVISION, MODULO], true)) {
            $this->Consumir($this->actual->token, 'operador multiplicativo');
            $this->Unario();
        }
    }

    private function Unario(): void
    {
        if ($this->actual->token === NO) {
            $this->Consumir(NO, 'operador NO');
            $this->Unario();
            return;
        }

        if ($this->actual->token === RESTA) {
            $this->Consumir(RESTA, 'signo negativo');
            $this->Unario();
            return;
        }

        $this->Primario();
    }

    private function Primario(): void
    {
        if (in_array($this->actual->token, [ID, NUM, DECIMAL, BOOLEANO, DISTANCIA, OBSTACULO, LUZ, SONIDO, DERECHA, IZQUIERDA, AZUL, ROJO, VERDE], true)) {
            $this->Consumir($this->actual->token, 'valor de expresion');
            return;
        }

        if ($this->actual->token === DETECTAR) {
            $this->Consumir(DETECTAR, 'DETECTAR');
            if (!in_array($this->actual->token, [DISTANCIA, OBSTACULO, LUZ, SONIDO], true)) {
                $this->Fallar('elemento del sensor despues de DETECTAR');
            }
            $this->Consumir($this->actual->token, 'elemento del sensor');
            return;
        }

        if ($this->actual->token === PAREN_ABRE) {
            $this->Consumir(PAREN_ABRE, '(');
            $this->Expresion();
            $this->Esperar(PAREN_CIERRA, ')');
            return;
        }

        $this->Fallar('un identificador, numero, valor booleano o expresion entre parentesis');
    }

    private function Esperar(int $token, string $esperado): void
    {
        if ($this->actual->token !== $token) {
            $this->Fallar($esperado);
        }
        $this->Consumir($token, $esperado);
    }

    private function Consumir(int $tokenEsperado, string $contexto): void
    {
        if ($this->actual->token !== $tokenEsperado) {
            $this->Fallar($contexto);
        }

        $token = $this->actual;
        $nombre = $this->Nombre($token->token);
        $siguiente = $this->Nombre($this->SiguienteEsperado($contexto, $token->token));

        $this->seguimiento[] = [
            'paso' => $this->paso++,
            'estadoActual' => $contexto,
            'token' => $nombre,
            'lexema' => $token->lexema ?? 'FIN',
            'linea' => $token->linea,
            'columna' => $token->columna,
            'estadoSiguiente' => $siguiente
        ];

        $this->actual = $this->tokenizador->getToken();
        if ($this->actual->token === ERROR) {
            $this->error = [
                'tipo' => 'lexico',
                'linea' => $this->actual->linea,
                'columna' => $this->actual->columna,
                'mensaje' => "Error lexico en linea {$this->actual->linea}, columna {$this->actual->columna}: lexema no valido '" . ($this->actual->lexema ?? '') . "'."
            ];
            throw new RuntimeException($this->error['mensaje']);
        }
    }

    private function SiguienteEsperado(string $contexto, int $token): int
    {
        $mapa = [
            'encabezado del programa' => ROBOT,
            'nombre del robot' => CORCHETE_ABRE,
            '[' => INICIAR,
            'inicio del programa' => TERMINADOR,
            ':D despues de INICIAR' => null,
        ];
        return $mapa[$contexto] ?? $token;
    }

    private function Fallar(string $esperado): never
    {
        $nombre = $this->Nombre($this->actual->token);
        $lexema = $this->actual->lexema === null ? 'fin del archivo' : "'" . $this->actual->lexema . "'";
        $this->error = [
            'tipo' => $this->actual->token === ERROR ? 'lexico' : 'sintactico',
            'linea' => $this->actual->linea,
            'columna' => $this->actual->columna,
            'mensaje' => "Error sintactico en linea {$this->actual->linea}, columna {$this->actual->columna}: se recibio {$nombre} ({$lexema}); se esperaba {$esperado}."
        ];
        throw new RuntimeException($this->error['mensaje']);
    }

    private function Nombre(int $token): string
    {
        return $this->nombresTokens[$token] ?? 'TOKEN_' . $token;
    }

    public function getSeguimiento(): array { return $this->seguimiento; }
    public function getError(): ?array { return $this->error; }
    public function getTransiciones(): array { return $this->transiciones; }
}

/* ============================================================
 * 6. PROGRAMA DE PRUEBA Y SALIDA
 * ============================================================ */

$codigo = <<<'ROBO'
ROBOT Explorador [
    INICIAR :D
    MEMORIA energia = 100 :D
    MEMORIA contador = 0 :D
    DECIR "Iniciando exploracion" :D
    LEER contador :D

    FUNCION SALUDAR [
        DECIR "Hola, soy tu robot" :D
    ]

    REPETIR 5 [
        AVANZAR 30 :D
        ESCANEAR DISTANCIA :D
        SI DISTANCIA < 20 Y NO DETECTAR OBSTACULO [
            FRENAR :D
            GIRAR DERECHA 90 :D
        ] SI NO [
            AVANZAR 20 :D
        ]
        ACTUALIZAR energia = energia - 10 :D
    ]

    PATRULLAR energia > 0 [
        ACTUALIZAR energia = energia - 25 :D
    ]

    COMPROBAR energia == 100 [
        DECIR "Energia completa" :D
    ]

    FINALIZAR :D
]
ROBO;

function EjecutarDemostracion(): void
{
    global $codigo;

    echo "<pre>";
    echo "ANALIZADOR LEXICO Y SINTACTICO ROBO-KIDS\n";
    echo str_repeat("=", 110) . "\n\n";

    $tablaLexica = new TablaLexica();

    echo "TABLA DE TRANSICIONES SINTACTICAS\n";
    echo str_repeat("-", 110) . "\n";
    printf("%-10s %-45s %-20s\n", "ESTADO", "TOKEN / TRANSICION PERMITIDA", "SIGUIENTE ESTADO");
    echo str_repeat("-", 110) . "\n";

    $parser = new AnalizadorSintactico(new Tokenizador($codigo, $tablaLexica));
    foreach ($parser->getTransiciones() as $estado => $transiciones) {
        foreach ($transiciones as $token => $siguiente) {
            printf("%-10s %-45s %-20s\n", (string)$estado, (string)$token, (string)$siguiente);
        }
    }

    echo "\nSEGUIMIENTO DEL ANALISIS SINTACTICO\n";
    echo str_repeat("-", 110) . "\n";
    $valido = $parser->Analizar();

    printf("%-6s %-34s %-18s %-24s %-7s %-8s %-22s\n",
        "PASO", "ESTADO / CONTEXTO", "TOKEN", "LEXEMA", "LINEA", "COLUMNA", "SIGUIENTE");
    echo str_repeat("-", 125) . "\n";

    foreach ($parser->getSeguimiento() as $fila) {
        printf("%-6d %-34s %-18s %-24s %-7d %-8d %-22s\n",
            $fila['paso'],
            substr($fila['estadoActual'], 0, 33),
            $fila['token'],
            substr((string)$fila['lexema'], 0, 23),
            $fila['linea'],
            $fila['columna'],
            $fila['estadoSiguiente']
        );
    }

    echo "\nRESULTADO DEL ANALISIS: " . ($valido ? "ACEPTADO" : "RECHAZADO") . "\n";
    if (!$valido && $parser->getError() !== null) {
        echo $parser->getError()['mensaje'] . "\n";
    }
    echo "</pre>";
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    EjecutarDemostracion();
}
