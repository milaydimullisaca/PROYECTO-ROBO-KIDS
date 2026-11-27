

<?php

/* ============================================================
 * TOKENS DEL LENGUAJE
 * ============================================================
 */

const GRAFICOS    = 0;
const APAR        = 1;
const CPAR        = 2;
const ALLAVE      = 3;
const CLLAVE      = 4;
const IGUAL       = 5;
const PCOMA       = 6;
const CUADRADO    = 7;
const RECTANGULO  = 8;
const CIRCULO     = 9;
const LINEA       = 10;
const COMA        = 11;
const ID          = 12;
const NUM         = 13;
const CADENA      = 14;

const FIN         = 666;
const ERROR       = 999;


/* ============================================================
 * CLASE TABLA LÉXICA
 *
 * Contiene los lexemas que forman parte de la definición
 * fija del lenguaje.
 * ============================================================
 */

class TablaLexica
{
    private array $porToken = [];
    private array $porLexema = [];

    public function __construct()
    {
        $this->Agregar(GRAFICOS,   'graficos',   'palabra_reservada');

        $this->Agregar(APAR,       '(',           'simbolo');
        $this->Agregar(CPAR,       ')',           'simbolo');

        $this->Agregar(ALLAVE,     '{',           'simbolo');
        $this->Agregar(CLLAVE,     '}',           'simbolo');

        $this->Agregar(IGUAL,      '=',           'operador');

        $this->Agregar(PCOMA,      ';',           'separador');
        $this->Agregar(COMA,       ',',           'separador');

        $this->Agregar(CUADRADO,   'cuadrado',    'palabra_reservada');
        $this->Agregar(RECTANGULO, 'rectangulo',  'palabra_reservada');
        $this->Agregar(CIRCULO,    'circulo',     'palabra_reservada');
        $this->Agregar(LINEA,      'linea',       'palabra_reservada');
    }


    private function Agregar(
        int $token,
        string $lexema,
        string $tipo
    ): void {

        $dato = [
            'token'  => $token,
            'lexema' => $lexema,
            'tipo'   => $tipo
        ];

        /*
         * Índice por TOKEN
         *
         * Ejemplo:
         *
         * $porToken[GRAFICOS]
         */
        $this->porToken[$token] = $dato;


        /*
         * Índice por LEXEMA
         *
         * Ejemplo:
         *
         * $porLexema['graficos']
         */
        $this->porLexema[$lexema] = $dato;
    }


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


    /*
     * Busca el lexema válido MÁS LARGO que exista
     * en la TablaLexica desde una posición determinada.
     *
     * Esto permitirá reconocer:
     *
     * =
     * ==
     * ===
     * !=
     * !==
     * <=
     * >=
     *
     * si posteriormente son agregados al lenguaje.
     */
    public function BuscarLexemaMasLargo(
        string $codigo,
        int $posicion
    ): ?array {

        $encontrado = null;

        foreach ($this->porLexema as $lexema => $datos) {

            /*
             * Las palabras reservadas formadas por letras
             * se reconocen previamente en el Tokenizador.
             *
             * Aquí nos interesan principalmente los
             * lexemas fijos:
             *
             * (
             * )
             * {
             * }
             * =
             * ;
             * ,
             * etc.
             */
            if (ctype_alpha($lexema[0])) {
                continue;
            }

            $longitud = strlen($lexema);

            $fragmento = substr(
                $codigo,
                $posicion,
                $longitud
            );

            if ($fragmento === $lexema) {

                if (
                    $encontrado === null ||
                    $longitud > $encontrado['longitud']
                ) {

                    $encontrado = [
                        'token'    => $datos['token'],
                        'lexema'   => $lexema,
                        'tipo'     => $datos['tipo'],
                        'longitud' => $longitud
                    ];
                }
            }
        }

        return $encontrado;
    }


    public function getTabla(): array
    {
        return $this->porToken;
    }
}


/* ============================================================
 * CLASE TOKEN
 *
 * Cada llamada a getToken() devuelve un objeto Token.
 * ============================================================
 */

class Token
{
    public int $token;
    public ?string $lexema;
    public int $linea;
    public int $columna;


    public function __construct(
        int $token,
        ?string $lexema,
        int $linea,
        int $columna
    ) {
        $this->token = $token;
        $this->lexema = $lexema;
        $this->linea = $linea;
        $this->columna = $columna;
    }


    public function Mostrar(): void
    {
        echo "Token(" . $this->token . ") \t";

        echo "Lexema(" .
            ($this->lexema ?? 'null')
            . ") \t";

        echo "Linea(" . $this->linea . ") \t";

        echo "Columna(" . $this->columna . ")";

        echo PHP_EOL;
    }
}


/* ============================================================
 * CLASE TOKENIZADOR
 *
 * Lee el código fuente.
 *
 * Cada llamada a:
 *
 * getToken()
 *
 * devuelve UN objeto Token.
 * ============================================================
 */

class Tokenizador
{
    private string $codigo;

    private int $indice = 0;

    private int $linea = 1;

    private int $columna = 1;

    private TablaLexica $tablaLexica;


    public function __construct(
        string $codigo,
        TablaLexica $tablaLexica
    ) {
        $this->codigo = $codigo;
        $this->tablaLexica = $tablaLexica;
    }


    /* ========================================================
     * GET TOKEN
     * ========================================================
     */

    public function getToken(): Token
    {
        /*
         * Ignorar:
         *
         * espacios
         * tabulaciones
         * saltos de línea
         * retornos de carro
         */
        $this->OmitirEspacios();


        /*
         * FIN DEL CÓDIGO
         */
        if ($this->FinCodigo()) {

            return new Token(
                FIN,
                null,
                $this->linea,
                $this->columna
            );
        }


        /*
         * Guardamos dónde comienza el token.
         */
        $lineaInicial = $this->linea;

        $columnaInicial = $this->columna;


        $caracter = $this->codigo[$this->indice];


        /* ====================================================
         * IDENTIFICADOR O PALABRA RESERVADA
         * ====================================================
         */

        if (ctype_alpha($caracter)) {

            $lexema = $this->LeerIdentificador();


            /*
             * Primero buscamos si el lexema está definido
             * dentro de TablaLexica.
             */
            $elemento =
                $this->tablaLexica
                     ->BuscarPorLexema($lexema);


            /*
             * Si existe, es una palabra reservada.
             */
            if ($elemento !== null) {

                return new Token(
                    $elemento['token'],
                    $lexema,
                    $lineaInicial,
                    $columnaInicial
                );
            }


            /*
             * Si no es palabra reservada,
             * entonces es un identificador.
             */
            return new Token(
                ID,
                $lexema,
                $lineaInicial,
                $columnaInicial
            );
        }


        /* ====================================================
         * NÚMERO
         * ====================================================
         */

        if (ctype_digit($caracter)) {

            $lexema = $this->LeerNumero();


            /*
             * Si LeerNumero devuelve null,
             * el número tiene un formato incorrecto.
             */
            if ($lexema === null) {

                return new Token(
                    ERROR,
                    null,
                    $lineaInicial,
                    $columnaInicial
                );
            }


            return new Token(
                NUM,
                $lexema,
                $lineaInicial,
                $columnaInicial
            );
        }


        /* ====================================================
         * CADENA
         *
         * Ejemplo:
         *
         * 'Hola mundo'
         * ====================================================
         */

        if ($caracter === "'") {

            $lexema = $this->LeerCadena();


            /*
             * Si no se encontró la comilla final.
             */
            if ($lexema === null) {

                return new Token(
                    ERROR,
                    null,
                    $lineaInicial,
                    $columnaInicial
                );
            }


            return new Token(
                CADENA,
                $lexema,
                $lineaInicial,
                $columnaInicial
            );
        }


        /* ====================================================
         * LEXEMA FIJO
         *
         * (
         * )
         * {
         * }
         * =
         * ;
         * ,
         *
         * Y posteriormente podría reconocer:
         *
         * ==
         * ===
         * !=
         * !==
         * <=
         * >=
         * etc.
         * ====================================================
         */

        $elemento = $this->LeerLexemaFijo();


        if ($elemento !== null) {

            return new Token(
                $elemento['token'],
                $elemento['lexema'],
                $lineaInicial,
                $columnaInicial
            );
        }


        /* ====================================================
         * CARÁCTER DESCONOCIDO
         * ====================================================
         */

        $lexema = $caracter;

        $this->Avanzar();


        return new Token(
            ERROR,
            $lexema,
            $lineaInicial,
            $columnaInicial
        );
    }


    /* ========================================================
     * OMITIR ESPACIOS EN BLANCO
     * ========================================================
     */

    private function OmitirEspacios(): void
    {
        while (!$this->FinCodigo()) {

            $caracter =
                $this->codigo[$this->indice];


            if (!ctype_space($caracter)) {
                break;
            }


            $this->Avanzar();
        }
    }


    /* ========================================================
     * LEER IDENTIFICADOR
     *
     * Regla actual:
     *
     * comienza con letra
     * después puede contener letras o números
     *
     * Ejemplos:
     *
     * posx
     * posx1
     * tam1
     * radio
     * ========================================================
     */

    private function LeerIdentificador(): string
    {
        $lexema = '';


        while (!$this->FinCodigo()) {

            $caracter =
                $this->codigo[$this->indice];


            if (
                !ctype_alpha($caracter) &&
                !ctype_digit($caracter)
            ) {
                break;
            }


            $lexema .= $caracter;


            $this->Avanzar();
        }


        return $lexema;
    }


    /* ========================================================
     * LEER NÚMERO
     *
     * Permite:
     *
     * 10
     * 120
     * 3.14
     * 0.5
     *
     * No permite:
     *
     * 12.4.5
     * ========================================================
     */

    private function LeerNumero(): ?string
    {
        $lexema = '';

        $cantidadPuntos = 0;


        while (!$this->FinCodigo()) {

            $caracter =
                $this->codigo[$this->indice];


            /*
             * Dígito
             */
            if (ctype_digit($caracter)) {

                $lexema .= $caracter;

                $this->Avanzar();

                continue;
            }


            /*
             * Punto decimal
             */
            if ($caracter === '.') {

                $cantidadPuntos++;


                /*
                 * Solo permitimos un punto decimal.
                 */
                if ($cantidadPuntos > 1) {

                    /*
                     * Consumimos el carácter que provocó
                     * el error para evitar quedar detenidos
                     * en la misma posición.
                     */
                    $this->Avanzar();

                    return null;
                }


                $lexema .= $caracter;

                $this->Avanzar();

                continue;
            }


            break;
        }


        return $lexema;
    }


    /* ========================================================
     * LEER CADENA
     *
     * Ejemplo:
     *
     * 'Hola mundo'
     * ========================================================
     */

    private function LeerCadena(): ?string
    {
        /*
         * Consumir comilla inicial.
         */
        $this->Avanzar();


        $lexema = '';


        while (!$this->FinCodigo()) {

            $caracter =
                $this->codigo[$this->indice];


            /*
             * Comilla de cierre.
             */
            if ($caracter === "'") {

                $this->Avanzar();

                return $lexema;
            }


            $lexema .= $caracter;


            $this->Avanzar();
        }


        /*
         * Se llegó al final del código
         * sin encontrar la comilla final.
         */
        return null;
    }


    /* ========================================================
     * LEER LEXEMA FIJO
     *
     * Busca siempre el lexema válido más largo.
     * ========================================================
     */

    private function LeerLexemaFijo(): ?array
    {
        $elemento =
            $this->tablaLexica
                 ->BuscarLexemaMasLargo(
                     $this->codigo,
                     $this->indice
                 );


        if ($elemento === null) {
            return null;
        }


        /*
         * Avanzar tantos caracteres como
         * tenga el lexema encontrado.
         */
        for (
            $i = 0;
            $i < $elemento['longitud'];
            $i++
        ) {
            $this->Avanzar();
        }


        return $elemento;
    }


    /* ========================================================
     * AVANZAR UNA POSICIÓN
     * ========================================================
     */

    private function Avanzar(): void
    {
        if ($this->FinCodigo()) {
            return;
        }


        /*
         * Si encontramos salto de línea,
         * incrementamos línea y reiniciamos columna.
         */
        if (
            $this->codigo[$this->indice] === "\n"
        ) {

            $this->linea++;

            $this->columna = 1;

        } else {

            $this->columna++;
        }


        $this->indice++;
    }


    /* ========================================================
     * VERIFICAR FIN DEL CÓDIGO
     * ========================================================
     */

    private function FinCodigo(): bool
    {
        return
            $this->indice >= strlen($this->codigo);
    }
}


/* ============================================================
 * ANALIZADOR SINTACTICO
 * ============================================================
 */

class AnalizadorSintactico
{
    private Tokenizador $tokenizador;

    private int $estadoActual = 0;

    private bool $seguimientoActivo;

    private array $seguimiento = [];

    private ?array $error = null;

    private array $transiciones;


    public function __construct(
        Tokenizador $tokenizador,
        bool $seguimientoActivo = true
    ) {
        $this->tokenizador = $tokenizador;
        $this->seguimientoActivo = $seguimientoActivo;

        /*
         * La tabla conserva los estados 0 a 21 del automata
         * original. Solo se almacenan transiciones validas;
         * una combinacion ausente representa un error sintactico.
         */
        $this->transiciones = [
            0  => [GRAFICOS => 1],
            1  => [APAR => 2],
            2  => [CPAR => 3],
            3  => [ALLAVE => 4],
            4  => [
                ID => 5,
                CUADRADO => 6,
                CIRCULO => 6,
                RECTANGULO => 7,
                LINEA => 7,
                CLLAVE => 21
            ],
            5  => [IGUAL => 8],
            6  => [APAR => 10],
            7  => [APAR => 11],
            8  => [ID => 9, NUM => 9],
            9  => [PCOMA => 4],
            10 => [ID => 15, NUM => 15],
            11 => [ID => 12, NUM => 12],
            12 => [COMA => 13],
            13 => [ID => 14, NUM => 14],
            14 => [COMA => 16],
            15 => [COMA => 16],
            16 => [ID => 17, NUM => 17],
            17 => [COMA => 18],
            18 => [ID => 19, NUM => 19],
            19 => [CPAR => 20],
            20 => [PCOMA => 4],
            21 => [FIN => 21]
        ];
    }


    public function Analizar(): bool
    {
        $this->estadoActual = 0;
        $this->seguimiento = [];
        $this->error = null;
        $paso = 1;

        while (true) {
            $token = $this->tokenizador->getToken();
            $estadoAnterior = $this->estadoActual;

            if ($token->token === ERROR) {
                $this->RegistrarPaso(
                    $paso,
                    $estadoAnterior,
                    $token,
                    null
                );

                $this->error = $this->CrearError(
                    'lexico',
                    $token,
                    'El analizador lexico encontro un lexema no valido.'
                );

                return false;
            }

            $estadoSiguiente =
                $this->transiciones[$estadoAnterior][$token->token]
                ?? null;

            $this->RegistrarPaso(
                $paso,
                $estadoAnterior,
                $token,
                $estadoSiguiente
            );

            if ($estadoSiguiente === null) {
                $this->error = $this->CrearError(
                    'sintactico',
                    $token,
                    $this->DescribirError($estadoAnterior, $token)
                );

                return false;
            }

            $this->estadoActual = $estadoSiguiente;

            if ($token->token === FIN) {
                return true;
            }

            $paso++;
        }
    }


    public function getEstadoActual(): int
    {
        return $this->estadoActual;
    }


    public function getSeguimiento(): array
    {
        return $this->seguimiento;
    }


    public function getError(): ?array
    {
        return $this->error;
    }


    public function getTransiciones(): array
    {
        return $this->transiciones;
    }


    public static function NombreToken(int $token): string
    {
        return match ($token) {
            GRAFICOS => 'GRAFICOS',
            APAR => 'APAR',
            CPAR => 'CPAR',
            ALLAVE => 'ALLAVE',
            CLLAVE => 'CLLAVE',
            IGUAL => 'IGUAL',
            PCOMA => 'PCOMA',
            CUADRADO => 'CUADRADO',
            RECTANGULO => 'RECTANGULO',
            CIRCULO => 'CIRCULO',
            LINEA => 'LINEA',
            COMA => 'COMA',
            ID => 'ID',
            NUM => 'NUM',
            CADENA => 'CADENA',
            FIN => 'FIN',
            ERROR => 'ERROR',
            default => 'TOKEN_' . $token
        };
    }


    private function RegistrarPaso(
        int $paso,
        int $estadoActual,
        Token $token,
        ?int $estadoSiguiente
    ): void {
        if (!$this->seguimientoActivo) {
            return;
        }

        $this->seguimiento[] = [
            'paso' => $paso,
            'estadoActual' => $estadoActual,
            'token' => $token->token,
            'nombreToken' => self::NombreToken($token->token),
            'lexema' => $token->lexema,
            'linea' => $token->linea,
            'columna' => $token->columna,
            'estadoSiguiente' => $estadoSiguiente
        ];
    }


    private function CrearError(
        string $tipo,
        Token $token,
        string $descripcion
    ): array {
        $esperados = $this->TokensEsperados($this->estadoActual);
        $nombre = self::NombreToken($token->token);
        $lexema = $token->lexema === null
            ? 'null'
            : "'{$token->lexema}'";
        $listaEsperados = empty($esperados)
            ? 'ninguno'
            : implode(', ', $esperados);

        return [
            'tipo' => $tipo,
            'linea' => $token->linea,
            'columna' => $token->columna,
            'estado' => $this->estadoActual,
            'token' => $token->token,
            'nombreToken' => $nombre,
            'lexema' => $token->lexema,
            'esperados' => $esperados,
            'descripcion' => $descripcion,
            'mensaje' => sprintf(
                'Error %s en linea %d, columna %d: recibido %s (%s) '
                . 'en el estado %d; se esperaba: %s. %s',
                $tipo,
                $token->linea,
                $token->columna,
                $nombre,
                $lexema,
                $this->estadoActual,
                $listaEsperados,
                $descripcion
            )
        ];
    }


    private function TokensEsperados(int $estado): array
    {
        $tokens = array_keys($this->transiciones[$estado] ?? []);

        return array_map(
            fn (int $token): string => self::NombreToken($token),
            $tokens
        );
    }


    private function DescribirError(int $estado, Token $token): string
    {
        if ($token->token === FIN) {
            return match ($estado) {
                0 => 'La entrada esta vacia; falta el bloque principal graficos().',
                3 => 'Falta abrir el bloque principal con una llave.',
                4 => 'Falta cerrar el bloque principal con una llave.',
                5 => 'Falta el operador de asignacion despues del identificador.',
                8 => 'Falta un identificador o numero como valor de la asignacion.',
                9, 20 => 'Falta el punto y coma al final de la sentencia.',
                6, 7 => 'Falta el parentesis de apertura de la figura.',
                10, 11, 13, 16, 18 => 'Falta un argumento de la figura.',
                12, 14, 15, 17 => 'Falta una coma entre argumentos.',
                19 => 'Falta el parentesis de cierre de la figura.',
                default => 'El programa termino antes de completar la estructura esperada.'
            };
        }

        if (
            $token->token === CPAR &&
            in_array($estado, [12, 14, 15, 17], true)
        ) {
            return 'La figura tiene una cantidad insuficiente de argumentos.';
        }

        return match ($estado) {
            0 => 'El programa debe comenzar con la palabra reservada graficos.',
            1 => 'Despues de graficos debe aparecer un parentesis de apertura.',
            2 => 'El encabezado graficos no recibe argumentos y debe cerrarse con ).',
            3 => 'El encabezado debe continuar con la llave de apertura del bloque.',
            4 => 'Se esperaba una sentencia valida o la llave de cierre del bloque.',
            5 => 'Despues de un identificador debe aparecer el operador de asignacion.',
            6, 7 => 'Despues del nombre de la figura debe aparecer (.',
            8 => 'El valor de una asignacion debe ser un identificador o un numero.',
            9, 20 => 'La sentencia debe terminar con punto y coma.',
            10, 11, 13, 16, 18 => 'Se esperaba un argumento de tipo ID o NUM.',
            12, 14, 15, 17 => 'Se esperaba una coma para separar argumentos.',
            19 => 'La figura tiene una cantidad incorrecta de argumentos o falta ).',
            21 => 'No se permite codigo adicional despues de cerrar el bloque principal.',
            default => 'El token recibido no tiene una transicion valida.'
        };
    }
}


/* ============================================================
 * DEMOSTRACION EJECUTABLE
 * ============================================================
 */


/*
 * Código fuente de prueba.
 */

function EjecutarDemostracion(): void
{
    $codigo = "
graficos(){
    posx1=120;
    posx2=175;
    radio=24;
    circulo(posx1,posx2,radio);
    cuadrado(100,100,50);
}
";

    $tablaLexica = new TablaLexica();

    echo "<pre>";
    echo "FLUJO DE TOKENS\n";
    echo "============================================================\n\n";

    $tokenizadorLexico = new Tokenizador($codigo, $tablaLexica);

    while (true) {
        $token = $tokenizadorLexico->getToken();
        $token->Mostrar();

        if ($token->token === FIN) {
            break;
        }
    }

    echo "\nANALISIS SINTACTICO\n";
    echo "============================================================\n\n";

    /*
     * El parser recibe su propio flujo secuencial porque la
     * demostracion anterior ya consumio el primer tokenizador.
     */
    $parser = new AnalizadorSintactico(
        new Tokenizador($codigo, $tablaLexica),
        true
    );
    $valido = $parser->Analizar();

    printf(
        "%-5s %-15s %-14s %-18s %-16s\n",
        'Paso',
        'Estado actual',
        'Token',
        'Lexema',
        'Estado siguiente'
    );

    foreach ($parser->getSeguimiento() as $paso) {
        printf(
            "%-5d %-15d %-14s %-18s %-16s\n",
            $paso['paso'],
            $paso['estadoActual'],
            $paso['nombreToken'],
            $paso['lexema'] ?? 'null',
            $paso['estadoSiguiente'] ?? 'ERROR'
        );
    }

    echo "\nResultado: ", $valido ? 'ACEPTADO' : 'RECHAZADO', "\n";

    if (!$valido) {
        echo $parser->getError()['mensaje'], "\n";
    }

    echo "</pre>";
}


if (
    isset($_SERVER['SCRIPT_FILENAME']) &&
    realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__
) {
    EjecutarDemostracion();
}
