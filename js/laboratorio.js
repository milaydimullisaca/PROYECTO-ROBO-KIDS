/* =====================================================================
   LABORATORIO DEL COMPILADOR (TOKENS, AST, SÍMBOLOS, AFD, BNF, PRUEBAS)
   ===================================================================== */
const STATES = {
  0: 'inicio', 1: 'identificador / palabra reservada', 2: 'número entero',
  3: 'punto decimal (incompleto)', 4: 'número decimal', 5: 'leyendo texto (incompleto)',
  6: 'texto entre comillas', 7: 'dos puntos (incompleto)', 8: 'fin de instrucción :D',
  9: 'delimitador { } ( )', 10: 'operador + - * %', 11: 'operador /',
  12: 'comentario //', 13: 'asignación =', 14: 'igual ==',
  15: 'menor / mayor < >', 16: '<= >=', 17: 'signo ! (incompleto)', 18: 'distinto !='
};
const FINAL = {
  1: 'ID', 2: 'NUM', 4: 'NUM', 6: 'STR', 8: 'END', 9: 'DEL',
  10: 'OP', 11: 'OP', 12: 'CMT', 13: 'ASSIGN', 14: 'OP', 15: 'OP', 16: 'OP', 18: 'OP'
};

   const LABS = [
  ['tok', 'Tokens'],
  ['ast', 'Árbol sintáctico'],
  ['sym', 'Tabla de símbolos'],
  ['dfa', 'Autómata'],
  ['bnf', 'Gramática'],
  ['test', 'Pruebas']
];
let labTab = 'tok', dfaPath = null;

LABS.forEach(([k, t]) => {
  const b = document.createElement('button');
  b.className = 'btn';
  b.textContent = t;
  b.dataset.k = k;
  b.onclick = () => { labTab = k; renderLab(); };
  $('labTabs').append(b);
});

function renderLab() {
  document.querySelectorAll('#labTabs .btn').forEach(b => b.classList.toggle('on', b.dataset.k === labTab));
  const B = $('labBody'), r = lastRes;
  if (!r) return;
  const f = { tok: labTok, ast: labAst, sym: labSym, dfa: labDfa, bnf: labBnf, test: labTest }[labTab];
  B.innerHTML = '';
  f(B, r);
}

const pathTxt = p => p.map(s => 'q' + s).join(' → ');

function labTok(B, r) {
  const toks = r.lex.toks.filter(t => t.k !== 'EOF');
  const cnt = {};
  toks.forEach(t => cnt[t.grp] = (cnt[t.grp] || 0) + 1);
  B.innerHTML =
    `<div class="sum"><span><b>${toks.length}</b> tokens</span>${Object.entries(cnt).map(([g, n]) => `<span>${g}: <b>${n}</b></span>`).join('')}${r.lex.errs.length ? `<span class="er">Errores léxicos: <b>${r.lex.errs.length}</b></span>` : ''}</div>` +
    (r.lex.errs.length ? `<div class="er" style="margin-bottom:8px">${r.lex.errs.map(e => `✗ Línea ${e.line}, col ${e.col}: ${esc(e.msg)}`).join('<br>')}</div>` : '') +
    `<div class="hint" style="margin:0 0 6px">Toca una fila para ver su recorrido en el autómata.</div>
    <table class="t"><tr><th>#</th><th>LEXEMA</th><th>TOKEN</th><th>GRUPO (CUADRO 1)</th><th>LÍNEA:COL</th><th>RECORRIDO DEL AUTÓMATA</th></tr>${toks.map((t, i) => `<tr class="click" data-i="${i}"><td class="mu">${i + 1}</td><td><code class="t-${t.css}">${esc(t.lex)}</code></td><td><code>${t.name}</code></td><td>${t.grp}</td><td class="mu">${t.line}:${t.col}</td><td class="mu"><code>${pathTxt(t.path.length > 7 ? [...t.path.slice(0, 3), '…', ...t.path.slice(-2)] : t.path).replace('q…', '…')}</code></td></tr>`).join('')}</table>`;

  B.querySelectorAll('tr.click').forEach(tr => tr.onclick = () => {
    const t = toks[+tr.dataset.i];
    dfaPath = { lex: t.lex, path: t.path, ok: true, name: t.name };
    labTab = 'dfa';
    renderLab();
  });
}

function exNode(e) {
  if (!e) return '<li><span class="ex mu">(valor por defecto)</span></li>';
  const ty = e.ty ? `<span class="ty">: ${e.ty}</span>` : '';
  switch (e.k) {
    case 'num': return `<li><span class="ex t-num">${e.v}</span>${ty}</li>`;
    case 'str': return `<li><span class="ex t-str">"${esc(e.v)}"</span>${ty}</li>`;
    case 'bool': return `<li><span class="ex t-bool">${e.v ? 'VERDADERO' : 'FALSO'}</span>${ty}</li>`;
    case 'sensor': return `<li><span class="ex t-sen">sensor ${e.v}</span>${ty}</li>`;
    case 'var': return `<li><span class="ex t-id">variable ${esc(e.lex)}</span>${ty}</li>`;
    case 'par': return `<li><span class="ex t-del">( )</span>${ty}<ul>${exNode(e.a)}</ul></li>`;
    case 'un': return `<li><span class="ex t-op">${e.op === '-' ? 'negativo' : 'NO'}</span>${ty}<ul>${exNode(e.a)}</ul></li>`;
    case 'bin': return `<li><span class="ex t-op">${esc(e.op)}</span>${ty}<ul>${exNode(e.a)}${exNode(e.b)}</ul></li>`;
  }
  return '';
}

function stNode(n) {
  const cat = catOf[n.t] || 'ctl';
  let inner = '';
  if (n.v != null) inner += `<li><span class="ex t-id">variable ${esc(n.vlex)}</span>${n.sym ? `<span class="ty">: ${n.sym.type}</span>` : ''}</li>`;
  if (n.e !== undefined) inner += n.kids || n.t === 'COMPROBAR' ? `<li><span class="ex mu">condición / valor</span><ul>${exNode(n.e)}</ul></li>` : exNode(n.e);
  if (n.kids) inner += `<li><span class="ex mu">${n.t === 'SI' ? 'ENTONCES' : 'CUERPO'}</span><ul>${n.kids.map(stNode).join('') || '<li class="mu">(vacío)</li>'}</ul></li>`;
  if (n.els) inner += `<li><span class="ex mu">SI NO</span><ul>${n.els.map(stNode).join('') || '<li class="mu">(vacío)</li>'}</ul></li>`;
  return `<li><span class="nd c-${cat}">${n.t}</span><span class="lnum">línea ${n.line}</span>${inner ? `<ul>${inner}</ul>` : ''}</li>`;
}

function labAst(B, r) {
  if (!r.ast) {
    B.innerHTML = '<div class="er">Corrige primero los errores léxicos para construir el árbol.</div>';
    return;
  }
  B.innerHTML =
    (r.parse.errs.length
      ? `<div class="er" style="margin-bottom:8px">El árbol está incompleto: hay ${r.parse.errs.length} error(es) sintáctico(s).<br>${r.parse.errs.map(e => `✗ Línea ${e.line}: ${esc(e.msg)}`).join('<br>')}</div>`
      : '<div class="ok" style="margin-bottom:8px">✓ La estructura respeta la gramática de ROBO-KIDS. Los tipos <span class="ty">: TIPO</span> los agrega el análisis semántico.</div>') +
    `<ul class="tree"><li><span class="nd c-pow">PROGRAMA${r.ast.name ? ' ' + esc(r.ast.name) : ''}</span><ul>${r.ast.body.map(stNode).join('')}</ul></li></ul>`;
}

function labSym(B, r) {
  const S = r.sem;
  if (r.lex.errs.length || r.parse.errs.length) {
    B.innerHTML = '<div class="wa">El análisis semántico se hace cuando no hay errores léxicos ni sintácticos.</div>';
    return;
  }
  B.innerHTML =
    (S.errs.length
      ? `<div class="er" style="margin-bottom:8px">${S.errs.map(e => `✗ Línea ${e.line}: ${esc(e.msg)} <span class="wa">Pista: ${esc(e.hint)}</span>`).join('<br>')}</div>`
      : '<div class="ok" style="margin-bottom:8px">✓ Sin errores semánticos: variables declaradas, tipos compatibles y condiciones booleanas.</div>') +
    (S.warns.length ? `<div class="wa" style="margin-bottom:8px">${S.warns.map(w => `Aviso: ${w.line ? 'Línea ' + w.line + ': ' : ''}${esc(w.msg)}`).join('<br>')}</div>` : '') +
    (S.symbols.length
      ? `<table class="t"><tr><th>NOMBRE</th><th>TIPO</th><th>ÁMBITO</th><th>DECLARADA EN</th><th>CREADA CON</th><th>VALOR INICIAL</th><th>USOS</th><th>VALOR ACTUAL</th></tr>${S.symbols.map(s => `<tr><td><code class="t-id">${esc(s.name)}</code></td><td class="wa">${s.type}</td><td>${esc(s.scope)}</td><td class="mu">línea ${s.line}</td><td>${s.how}</td><td><code>${esc(s.init || '')}</code></td><td>${s.uses}</td><td><code class="ok">${s.cur == null ? '—' : esc(fmt(s.cur))}</code></td></tr>`).join('')}</table>`
      : '<div class="mu">Este programa no usa variables. Prueba con <code>MEMORIA pasos = 3 :D</code>.</div>');
}

/* --- Diagrama SVG del autómata --- */
const POS = {
  0: [60, 328], 1: [220, 40], 2: [220, 104], 3: [360, 104], 4: [500, 104],
  5: [220, 168], 6: [360, 168], 7: [220, 232], 8: [360, 232], 9: [220, 296],
  10: [220, 360], 11: [220, 424], 12: [360, 424], 13: [220, 488], 14: [360, 488],
  15: [220, 552], 16: [360, 552], 17: [220, 616], 18: [360, 616]
};

const EDGES = [
  [0, 1, 'letra'], [1, 1, 'letra, dígito, _'], [0, 2, 'dígito'], [2, 2, 'dígito'], [2, 3, '.'],
  [3, 4, 'dígito'], [4, 4, 'dígito'], [0, 5, '"'], [5, 5, 'otro carácter'], [5, 6, '"'],
  [0, 7, ':'], [7, 8, 'D'], [0, 9, '{ } ( )'], [0, 10, '+ - * %'], [0, 11, '/'],
  [11, 12, '/'], [12, 12, 'otro carácter'], [0, 13, '='], [13, 14, '='], [0, 15, '< >'],
  [15, 16, '='], [0, 17, '!'], [17, 18, '=']
];

function dfaSvg(path) {
  const on = new Set(path || []), eon = new Set();
  if (path) for (let i = 1; i < path.length; i++) eon.add(path[i - 1] + '-' + path[i]);
  const last = path ? path.at(-1) : null;
  const R0 = 17;

  let s = `<svg viewBox="0 -10 620 660" xmlns="http://www.w3.org/2000/svg"><defs><marker id="ar" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0L10,5L0,10z" fill="#94a3b8"/></marker><marker id="ar2" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0L10,5L0,10z" fill="#0284c7"/></marker></defs>`;
  s += `<line x1="8" y1="328" x2="${60 - R0 - 2}" y2="328" class="ed2" marker-end="url(#ar)"/><text x="18" y="318" class="el">inicio</text>`;

  for (const [a, b, l] of EDGES) {
    const k = a + '-' + b, o = eon.has(k) ? ' on' : '', [x1, y1] = POS[a], [x2, y2] = POS[b];
    if (a === b) {
      s += `<path d="M${x1 + 6},${y1 - R0 + 1} C${x1 + 18},${y1 - 48} ${x1 + 44},${y1 - 22} ${x1 + R0 - 1},${y1 - 6}" class="ed2${o}" marker-end="url(#${o ? 'ar2' : 'ar'})"/><text x="${x1 + 40}" y="${y1 - 32}" class="el${o}" style="text-anchor:start">${esc(l)}</text>`;
      continue;
    }
    const dx = x2 - x1, dy = y2 - y1, d = Math.hypot(dx, dy), ux = dx / d, uy = dy / d;
    const sx = x1 + ux * R0, sy = y1 + uy * R0, ex = x2 - ux * (R0 + 2), ey = y2 - uy * (R0 + 2);
    const fan = a === 0, t = fan ? .8 : .5;
    const lx = sx + (ex - sx) * t - (fan ? 8 : 0), ly = sy + (ey - sy) * t + (fan ? 4 : -6);
    s += `<line x1="${sx}" y1="${sy}" x2="${ex}" y2="${ey}" class="ed2${o}" marker-end="url(#${o ? 'ar2' : 'ar'})"/><text x="${lx}" y="${ly}" class="el${o}"${fan ? ' style="text-anchor:end"' : ''}>${esc(l)}</text>`;
  }

  for (const q in POS) {
    const [x, y] = POS[q];
    const fin = FINAL[q] ? ' fin' : '';
    const cls = (on.has(+q) ? ' on' : '') + (path && +q === last && FINAL[q] ? ' acc' : '');
    s += `<g class="st${fin}${cls}"><circle cx="${x}" cy="${y}" r="${R0}"/>${fin ? `<circle class="in2" cx="${x}" cy="${y}" r="${R0 - 4}"/>` : ''}<text x="${x}" y="${y}">q${q}</text></g>`;
  }
  return s + '</svg>';
}

async function tryLexeme(w) {
  if (!w) return null;
  const r = await apiPost({ accion: 'probar_lexema', lexema: w });
  return r.resultado;
}

function labDfa(B) {
  const p = dfaPath;
  B.innerHTML = `<div class="auto"><div>${dfaSvg(p && p.path)}</div><div class="legend">
    <div><b>A = (Q, Σ, δ, q0, F)</b></div>
    <div>Q = { q0 … q18 } · q0 = estado inicial · F = estados con doble círculo</div>
    <div class="try"><input id="dfaIn" placeholder="Escribe un lexema: AVANZAR, 12.5, &quot;hola&quot;, :D, <=" value="${p ? esc(p.lex) : ''}"><button class="btn" id="dfaGo">Probar</button></div>
    <div class="path" id="dfaOut">${p ? (p.ok ? `<span class="ok">✓ ACEPTADO</span> «${esc(p.lex)}» → <b>${esc(p.name)}</b><br>` : `<span class="er">✗ RECHAZADO</span> «${esc(p.lex)}»: ${esc(p.stuck || '')}<br>`) + `<code>${pathTxt(p.path)}</code>` : 'Toca un token en la pestaña Tokens o escribe un lexema para ver su recorrido.'}</div>
    <div style="margin-top:10px">${Object.entries(STATES).map(([q, n]) => `<div><b>q${q}</b> ${n}${FINAL[q] ? ' <span class="ok">(final)</span>' : ''}</div>`).join('')}</div>
    <div class="hint">El analizador léxico usa esta misma función δ: lee carácter por carácter y se queda con el lexema más largo que termina en un estado final.</div></div></div>`;

  const go = async () => {
    dfaPath = await tryLexeme($('dfaIn').value);
    renderLab();
    const i = $('dfaIn');
    if (i) { i.focus(); i.setSelectionRange(i.value.length, i.value.length); }
  };
  $('dfaGo').onclick = go;
  $('dfaIn').onkeydown = e => { if (e.key === 'Enter') go(); };
}

function labBnf(B) {
  const k = s => s.replace(/"([^"]+)"/g, '<span class="k">"$1"</span>').replace(/&lt;([a-z_ñ]+)&gt;/g, '<span class="n">&lt;$1&gt;</span>');
  B.innerHTML = `<pre class="bnf">${k(esc(`GRAMÁTICA DE ROBO-KIDS (notación BNF extendida: { } = cero o más veces, [ ] = opcional, | = o)

<programa>      ::= "ROBOT" ID "{" <instrucciones> "}"  |  <instrucciones>
<instrucciones> ::= { <instruccion> }
<instruccion>   ::= <simple> ":D" | <movimiento> ":D" | <memoria> ":D" | <comunicacion> ":D"
                  | <lectura> ":D" | <comprobar> ":D" | <si> | <repetir> | <patrullar>
<simple>        ::= "ACTIVAR" | "APAGAR" | "FRENAR" | "SONAR" | "DETECTAR" | "ESCANEAR"
<movimiento>    ::= ( "AVANZAR" | "RETROCEDER" | "GIRAR" ) [ <expr> ]
<memoria>       ::= "MEMORIA" ID "=" <expr>  |  "ACTUALIZAR" ID "=" <expr>
<comunicacion>  ::= ( "DECIR" | "HABLAR" | "RESPONDER" ) <expr>  |  "RECIBIR" ID
<lectura>       ::= "LEER" ID
<comprobar>     ::= "COMPROBAR" <expr>
<si>            ::= "SI" <expr> <bloque> [ "SI NO" ( <bloque> | <si> ) ]
<repetir>       ::= "REPETIR" <expr> <bloque>
<patrullar>     ::= "PATRULLAR" <expr> <bloque>
<bloque>        ::= "{" <instrucciones> "}"

<expr>          ::= <expr_y> { "O" <expr_y> }
<expr_y>        ::= <expr_no> { "Y" <expr_no> }
<expr_no>       ::= "NO" <expr_no> | <relacion>
<relacion>      ::= <suma> [ ( "==" | "!=" | "<" | ">" | "<=" | ">=" ) <suma> ]
<suma>          ::= <producto> { ( "+" | "-" ) <producto> }
<producto>      ::= <unario> { ( "*" | "/" | "%" ) <unario> }
<unario>        ::= "-" <unario> | <primario>
<primario>      ::= NUMERO | CADENA | "VERDADERO" | "FALSO"
                  | "OBSTACULO" | "LIBRE" | "META" | "ESTRELLA" | ID | "(" <expr> ")"

REGLAS SEMÁNTICAS
 1. Toda variable se declara con MEMORIA antes de usarse (LEER y RECIBIR también la crean).
 2. Una variable no se declara dos veces en el mismo ámbito; cada bloque { } abre un ámbito nuevo.
 3. Tipos: NUMERO, TEXTO, BOOLEANO. ACTUALIZAR debe guardar el mismo tipo que se declaró.
 4. + suma números o une textos; - * / % solo con números; < > <= >= comparan números;
    == y != comparan valores del mismo tipo; Y, O, NO trabajan con BOOLEANO.
 5. SI, PATRULLAR y COMPROBAR necesitan una condición BOOLEANO; REPETIR, AVANZAR y GIRAR un NUMERO.
 6. El robot debe estar ACTIVADO para moverse o usar sensores. GIRAR usa múltiplos de 45°.`))}</pre>`;
}

/* --- Casos de prueba --- */
const P = (name, body) => `ROBOT ${name} {\n${body.trim().split('\n').map(l => '  ' + l.trim().replace(/^\.+/, m => '  '.repeat(m.length))).join('\n')}\n}\n`;

const TESTS = [
  { n: 'Listing 1 del documento (Explorador)', x: 'ok', c: `ROBOT Explorador {\n  ACTIVAR :D\n\n  DECIR "Hola, mundo" :D\n\n  AVANZAR :D\n  GIRAR :D\n  SONAR :D\n\n  APAGAR :D\n}\n` },
  { n: 'Misión 1 resuelta', x: 'ok', m: 0, c: P('Explorador', `ACTIVAR :D\nGIRAR -90 :D\nAVANZAR 150 :D\nGIRAR 90 :D\nAVANZAR 450 :D\nGIRAR 90 :D\nAVANZAR 150 :D\nAPAGAR :D`) },
  { n: 'Misión 3 con PATRULLAR y variables', x: 'ok', m: 2, c: P('Laberinto', `ACTIVAR :D\nMEMORIA giro = 90 :D\nREPETIR 4 {\n.PATRULLAR LIBRE {\n..AVANZAR 50 :D\n.}\n.GIRAR giro :D\n.PATRULLAR LIBRE {\n..AVANZAR 50 :D\n.}\n.GIRAR giro :D\n.ACTUALIZAR giro = giro * -1 :D\n}\nAPAGAR :D`) },
  { n: 'Variables, tipos y operadores', x: 'ok', c: P('Contador', `ACTIVAR :D\nMEMORIA vueltas = 0 :D\nMEMORIA nombre = "Robi" :D\nREPETIR 4 {\n.ACTUALIZAR vueltas = vueltas + 1 :D\n.GIRAR 90 :D\n}\nDECIR "Soy " + nombre + " y di " + vueltas + " giros" :D\nCOMPROBAR vueltas == 4 Y NO META :D\nAPAGAR :D`) },
  { n: 'SI … SI NO con sensores', x: 'ok', c: P('Prudente', `ACTIVAR :D\nMEMORIA casillas = 0 :D\nREPETIR 6 {\n.SI OBSTACULO {\n..GIRAR 90 :D\n.} SI NO {\n..AVANZAR 50 :D\n..ACTUALIZAR casillas = casillas + 1 :D\n.}\n}\nDECIR "Avancé " + casillas + " casillas" :D\nAPAGAR :D`) },
  { n: 'Léxico: símbolo desconocido', x: 'Léxico', c: P('Prueba', `ACTIVAR :D\nAVANZAR 50 @ :D\nAPAGAR :D`) },
  { n: 'Léxico: texto sin cerrar', x: 'Léxico', c: P('Prueba', `ACTIVAR :D\nDECIR "Hola :D\nAPAGAR :D`) },
  { n: 'Sintáctico: falta :D', x: 'Sintáctico', c: P('Prueba', `ACTIVAR :D\nAVANZAR 50\nAPAGAR :D`) },
  { n: 'Sintáctico: llave sin cerrar', x: 'Sintáctico', c: P('Prueba', `ACTIVAR :D\nREPETIR 3 {\n.AVANZAR 50 :D\nAPAGAR :D`) },
  { n: 'Sintáctico: instrucción mal escrita', x: 'Sintáctico', c: P('Prueba', `ACTIVAR :D\nAVANSAR 50 :D\nAPAGAR :D`) },
  { n: 'Semántico: variable no declarada', x: 'Semántico', c: P('Prueba', `ACTIVAR :D\nAVANZAR pasos :D\nAPAGAR :D`) },
  { n: 'Semántico: tipos incompatibles', x: 'Semántico', c: P('Prueba', `ACTIVAR :D\nMEMORIA pasos = 3 :D\nACTUALIZAR pasos = "muchos" :D\nAPAGAR :D`) },
  { n: 'Semántico: condición no booleana', x: 'Semántico', c: P('Prueba', `ACTIVAR :D\nMEMORIA pasos = 3 :D\nSI pasos + 1 {\n.GIRAR 90 :D\n}\nAPAGAR :D`) },
  { n: 'Semántico: comparación incompatible', x: 'Semántico', c: P('Prueba', `ACTIVAR :D\nMEMORIA nombre = "Robi" :D\nSI nombre > 5 {\n.DECIR "grande" :D\n}\nAPAGAR :D`) },
  { n: 'Semántico: robot sin ACTIVAR', x: 'Semántico', c: P('Dormilon', `AVANZAR 50 :D\nAPAGAR :D`) }
];

let testRes = null;

async function runTests() {
  const r = await apiPost({
    accion: 'pruebas',
    casos: TESTS.map(t => ({ c: t.c, x: t.x }))
  });
  testRes = r.resultados;
}

function labTest(B) {
  if (!testRes) {
    B.innerHTML = '<div class="mu">Ejecutando pruebas en PHP…</div>';
    runTests().then(() => { if (labTab === 'test') renderLab(); });
    return;
  }
  const ok = testRes.filter(x => x.pass).length;
  B.innerHTML =
    `<div class="sum"><span>Casos: <b>${TESTS.length}</b></span><span class="${ok === TESTS.length ? 'ok' : 'er'}">Correctos: <b>${ok}/${TESTS.length}</b></span><button class="btn" id="tAll" style="padding:3px 10px;font-size:12px">↻ Ejecutar todas las pruebas (PHP)</button></div>
    <table class="t"><tr><th>#</th><th>CASO DE PRUEBA</th><th>ESPERADO</th><th>OBTENIDO</th><th>RESULTADO</th><th>DETALLE</th><th></th></tr>${TESTS.map((t, i) => {
      const r = testRes[i];
      return `<tr><td class="mu">${i + 1}</td><td>${esc(t.n)}</td><td>${t.x === 'ok' ? '<span class="ok">válido</span>' : 'error ' + t.x.toLowerCase()}</td><td>${r.got === 'ok' ? '<span class="ok">válido</span>' : 'error ' + r.got.toLowerCase()}</td><td class="${r.pass ? 'pass' : 'fail'}">${r.pass ? '✓ PASA' : '✗ FALLA'}</td><td class="mu">${esc(r.msg)}</td><td><button class="btn" data-i="${i}" style="padding:2px 8px;font-size:11px">Cargar</button></td></tr>`;
    }).join('')}</table>
    <div class="hint">“Cargar” te lleva a Programar y pone el programa en el editor. Los casos válidos con misión cambian también el mapa: pulsa EJECUTAR para verlos funcionar.</div>`;

  $('tAll').onclick = async () => {
    testRes = null;
    renderLab();
  };
  B.querySelectorAll('button[data-i]').forEach(b => b.onclick = () => {
    loadProgram(TESTS[+b.dataset.i].c, TESTS[+b.dataset.i].m);
    showView('prog');
  });
}