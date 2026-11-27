/* =====================================================================
   MOTOR DE EJECUCIÓN / INTÉRPRETE DEL AST
   ===================================================================== */
let runId = 0, running = false, ops = 0, SPD = 1;

class RunErr {
  constructor(msg, hint, line) {
    this.msg = msg;
    this.hint = hint;
    this.line = line;
  }
}

const STOP = {}, WIN = {};
const frame = () => new Promise(r => requestAnimationFrame(r));

async function anim(ms, fn, id) {
  ms /= SPD;
  const t0 = performance.now();
  for (;;) {
    if (id !== runId) throw STOP;
    const k = Math.min(1, (performance.now() - t0) / ms);
    fn(k);
    if (k >= 1) return;
    await frame();
  }
}

const wait = (ms, id) => anim(ms, () => { }, id);

function checkWin() {
  if (R.won) return;
  const near = Math.hypot(R.x - meta.x, R.y - meta.y) < 22;
  if (near && stars.every(s => !s.on)) {
    R.won = true;
    throw WIN;
  }
}

async function move(dist, line, id) {
  const sgn = dist < 0 ? -1 : 1;
  let left = Math.abs(dist);
  const dx = Math.cos(rad(R.a)) * sgn, dy = Math.sin(rad(R.a)) * sgn;
  let last = performance.now();

  while (left > 0) {
    if (id !== runId) throw STOP;
    await frame();
    const now = performance.now();
    const st = Math.min(left, Math.min(50, now - last) * .2 * SPD);
    last = now;
    if (R.energy <= 0) throw new RunErr('¡Se acabó la energía!', 'Usa menos instrucciones o rutas más cortas.', line);
    const nx = R.x + dx * st, ny = R.y + dy * st;
    if (hits(nx, ny)) {
      R.flash = 25;
      throw new RunErr('El robot chocó con una pared.', 'Usa DETECTAR, SONAR o SI OBSTACULO antes de AVANZAR, o gira con GIRAR.', line);
    }
    R.x = nx; R.y = ny; left -= st; R.energy -= st / 50;
    updEnergy();
    R.trail.push([R.x, R.y]);
    if (R.trail.length > 600) R.trail.shift();
    for (const s of stars) {
      if (s.on && Math.hypot(s.x - R.x, s.y - R.y) < 22) {
        s.on = false;
        got++;
        say(`¡Estrella ${got}/${stars.length}!`);
        log('wa', `   ★ Estrella recogida (${got}/${stars.length})`);
      }
    }
    checkWin();
  }
}

const cells = d => Math.round(d / C * 10) / 10;

function cond(c) {
  const d = ray(60);
  switch (c) {
    case 'OBSTACULO': return d < 45;
    case 'LIBRE': return d >= 45;
    case 'META': return Math.hypot(R.x - meta.x, R.y - meta.y) < 30;
    case 'ESTRELLA': return stars.some(s => s.on && Math.hypot(s.x - R.x, s.y - R.y) < 75);
  }
  return false;
}

/* Entorno de variables con ámbitos */
class Env {
  constructor(p) { this.m = new Map(); this.p = p; }
  find(k) { return this.m.has(k) ? this : this.p ? this.p.find(k) : null; }
  get(k) { const e = this.find(k); return e ? e.m.get(k) : undefined; }
  set(k, v) { const e = this.find(k) || this; e.m.set(k, v); }
  decl(k, v) { this.m.set(k, v); }
}

const fmt = v => typeof v === 'boolean' ? (v ? 'VERDADERO' : 'FALSO') : typeof v === 'number' ? String(Math.round(v * 100) / 100) : String(v);

function ev(e, env) {
  switch (e.k) {
    case 'num': case 'str': case 'bool': return e.v;
    case 'sensor': return cond(e.v);
    case 'par': return ev(e.a, env);
    case 'var': {
      const v = env.get(e.name);
      if (v === undefined) throw new RunErr(`La variable «${e.lex}» no tiene valor aquí.`, 'Quizá se creó dentro de otro bloque.', e.line);
      return v;
    }
    case 'un': return e.op === '-' ? -ev(e.a, env) : !ev(e.a, env);
    case 'bin': {
      const a = ev(e.a, env);
      if (e.op === 'Y') return a && ev(e.b, env);
      if (e.op === 'O') return a || ev(e.b, env);
      const b = ev(e.b, env);
      switch (e.op) {
        case '+': return (typeof a === 'string' || typeof b === 'string') ? fmt(a) + fmt(b) : a + b;
        case '-': return a - b;
        case '*': return a * b;
        case '/': if (b === 0) throw new RunErr('¡División entre cero!', 'Revisa el valor del divisor.', e.line); return a / b;
        case '%': if (b === 0) throw new RunErr('¡División entre cero!', 'Revisa el valor del divisor.', e.line); return a % b;
        case '==': return a === b;
        case '!=': return a !== b;
        case '<': return a < b;
        case '>': return a > b;
        case '<=': return a <= b;
        case '>=': return a >= b;
      }
    }
  }
}

function assign(n, env, val, declare) {
  if (declare) env.decl(n.v, val);
  else env.set(n.v, val);
  if (n.sym && lastRes && lastRes.sem && lastRes.sem.symbols[n.sym.id]) {
    lastRes.sem.symbols[n.sym.id].cur = val;
    if (labTab === 'sym') renderLab();
  }
}

function ask(text, id) {
  return new Promise((res, rej) => {
    const box = $('botMsg');
    box.innerHTML = '<b>ROBO-BOT:</b> ';
    box.append(document.createTextNode(text), document.createElement('br'));
    const inp = document.createElement('input');
    inp.className = 'ask';
    const b = document.createElement('button');
    b.className = 'btn';
    b.textContent = 'Enviar';
    b.style.padding = '4px 10px';
    box.append(inp, b);
    inp.focus();
    const t = setInterval(() => { if (id !== runId) { clearInterval(t); rej(STOP); } }, 100);
    const done = () => { clearInterval(t); res(inp.value); };
    b.onclick = done;
    inp.onkeydown = e => { if (e.key === 'Enter') done(); };
  });
}

async function op(n, env, id) {
  if (++ops > 4000) throw new RunErr('El programa hace demasiadas acciones.', 'Revisa tus REPETIR y PATRULLAR.', n.line);
  if (id !== runId) throw STOP;
  setExec(n.line);
  const L = `(línea ${n.line})`;

  switch (n.t) {
    case 'ACTIVAR':
      R.active = true;
      R.pulse = { t0: performance.now(), r: 30, col: '#16a34a' };
      say('¡Sistemas en línea! Robot activado.');
      log('in', `  → ACTIVAR  ${L}`);
      await wait(500, id);
      break;
    case 'APAGAR':
      R.active = false;
      say('Robot apagado. ¡Buen trabajo!');
      log('in', `  → APAGAR   ${L}`);
      await wait(400, id);
      break;
    case 'AVANZAR':
    case 'RETROCEDER': {
      const d = n.e ? ev(n.e, env) : 50;
      if (typeof d !== 'number' || d < 1 || d > 500) throw new RunErr(`${n.t} ${fmt(d)}: la distancia debe estar entre 1 y 500.`, 'Revisa el valor de la expresión.', n.line);
      log('in', `  → ${n.t} ${fmt(d)}  ${L}`);
      await move(n.t === 'AVANZAR' ? d : -d, n.line, id);
      break;
    }
    case 'GIRAR': {
      const a = n.e ? ev(n.e, env) : 90;
      if (a === 0 || Math.abs(a) > 360 || a % 45 !== 0) throw new RunErr(`GIRAR ${fmt(a)}: los grados deben ser múltiplos de 45.`, 'Usa 90, -90, 180…', n.line);
      log('in', `  → GIRAR ${fmt(a)}°  ${L}`);
      const a0 = R.a;
      await anim(Math.abs(a) * 5 + 120, k => R.a = a0 + a * k, id);
      R.a = ((a0 + a) % 360 + 360) % 360;
      break;
    }
    case 'FRENAR':
      log('in', `  → FRENAR  ${L}`);
      await wait(300, id);
      break;
    case 'SONAR':
    case 'LEER': {
      const d = ray();
      R.energy -= n.t === 'SONAR' ? 1 : .5;
      updEnergy();
      R.sonar = { t0: performance.now(), d: Math.min(d, 300) };
      $('sDet').textContent = `pared a ${cells(d)} casillas`;
      $('sDet').style.color = '';
      if (n.t === 'LEER') {
        assign(n, env, cells(d), !env.find(n.v));
        log('in', `  → LEER ${n.vlex} = ${cells(d)} casillas  ${L}`);
        say(`Guardé ${cells(d)} en «${n.vlex}».`);
      } else {
        log('in', `  → SONAR: pared a ${cells(d)} casillas  ${L}`);
        say(`Mi sonar dice: pared a ${cells(d)} casillas.`);
      }
      await wait(950, id);
      break;
    }
    case 'DETECTAR': {
      const o = cond('OBSTACULO');
      R.pulse = { t0: performance.now(), r: 40, col: o ? '#dc2626' : '#16a34a' };
      R.energy -= .5;
      updEnergy();
      $('sDet').textContent = o ? '¡OBSTÁCULO!' : 'libre';
      $('sDet').style.color = o ? 'var(--er)' : 'var(--ok)';
      log('in', `  → DETECTAR: ${o ? 'obstáculo adelante' : 'camino libre'}  ${L}`);
      say(o ? '¡Ups! Hay un obstáculo. ¿Qué comando te ayudaría?' : 'Camino libre, ¡sigue!');
      await wait(600, id);
      break;
    }
    case 'ESCANEAR': {
      const near = stars.filter(s => s.on && Math.hypot(s.x - R.x, s.y - R.y) < 160).length;
      const dm = cells(Math.hypot(meta.x - R.x, meta.y - R.y));
      $('sScan').textContent = `★${near} · meta ${dm}`;
      R.pulse = { t0: performance.now(), r: 150, col: '#7c3aed' };
      log('in', `  → ESCANEAR: ${near} estrellas cerca, meta a ${dm} casillas  ${L}`);
      say(`Escaneo: ${near} estrella(s) cerca y la meta está a ${dm} casillas.`);
      await wait(800, id);
      break;
    }
    case 'DECIR': {
      const v = fmt(ev(n.e, env));
      say('«' + v + '»');
      log('in', '  → DECIR: ' + v);
      await wait(700, id);
      break;
    }
    case 'HABLAR': {
      const v = fmt(ev(n.e, env));
      say('«' + v + '»');
      speak(v);
      log('in', '  → HABLAR: ' + v);
      await wait(900, id);
      break;
    }
    case 'RESPONDER': {
      const v = fmt(ev(n.e, env));
      say(v);
      log('in', '  → RESPONDER: ' + v);
      await wait(700, id);
      break;
    }
    case 'RECIBIR': {
      const type = n.sym ? n.sym.type : 'TEXTO';
      let val;
      log('in', `  → RECIBIR ${n.vlex} (${type})  ${L}`);
      for (let msg = `Escribe un valor para «${n.vlex}» (${type}) y pulsa Enviar:`; ;) {
        const raw = (await ask(msg, id)).trim();
        if (type === 'NUMERO') {
          val = parseFloat(raw.replace(',', '.'));
          if (!isNaN(val)) break;
          msg = `«${raw}» no es un número. Escribe un número para «${n.vlex}»:`;
        } else if (type === 'BOOLEANO') {
          const w = norm(raw);
          if (['SI', 'VERDADERO'].includes(w)) { val = true; break; }
          if (['NO', 'FALSO'].includes(w)) { val = false; break; }
          msg = 'Responde SI o NO:';
        } else {
          val = raw;
          break;
        }
      }
      assign(n, env, val, !env.find(n.v));
      log('in', `     ${n.vlex} = ${fmt(val)}`);
      say(`¡Gracias! Guardé «${fmt(val)}».`);
      break;
    }
    case 'MEMORIA': {
      const v = ev(n.e, env);
      assign(n, env, v, true);
      log('in', `  → MEMORIA ${n.vlex} = ${fmt(v)}  ${L}`);
      await wait(250, id);
      break;
    }
    case 'ACTUALIZAR': {
      const v = ev(n.e, env);
      assign(n, env, v, false);
      log('in', `  → ACTUALIZAR ${n.vlex} = ${fmt(v)}  ${L}`);
      await wait(250, id);
      break;
    }
    case 'COMPROBAR': {
      const v = ev(n.e, env);
      if (!v) throw new RunErr(`La comprobación «${esrc(n.e)}» es FALSA.`, 'El robot no está como esperabas. Revisa tu programa.', n.line);
      log('ok', `  ✓ COMPROBAR ${esrc(n.e)} → VERDADERO  ${L}`);
      say(`Comprobado: ${esrc(n.e)} ✓`);
      await wait(400, id);
      break;
    }
    case 'REPETIR': {
      const k = ev(n.e, env);
      if (typeof k !== 'number' || k < 0 || k > 50) throw new RunErr(`REPETIR ${fmt(k)}: se permiten de 1 a 50 repeticiones.`, 'Usa un número más pequeño.', n.line);
      for (let i = 0; i < Math.floor(k); i++) await exec(n.kids, new Env(env), id);
      break;
    }
    case 'PATRULLAR': {
      let k = 0;
      while (ev(n.e, env)) {
        if (++k > 200) throw new RunErr('PATRULLAR dio más de 200 vueltas.', 'Revisa que la condición llegue a ser falsa.', n.line);
        await exec(n.kids, new Env(env), id);
        setExec(n.line);
      }
      break;
    }
    case 'SI': {
      const c = ev(n.e, env);
      log('mu', `  ? SI ${esrc(n.e)} → ${c ? 'VERDADERO' : 'FALSO'}  ${L}`);
      if (c) await exec(n.kids, new Env(env), id);
      else if (n.els) await exec(n.els, new Env(env), id);
      break;
    }
  }
  checkWin();
}

async function exec(list, env, id) {
  for (const n of list) await op(n, env, id);
}

async function runProgram() {
  if (running) return;
  con.innerHTML = '';
  const res = await liveCompile();
  if (!res) {
    log('er', '✗ No se pudo conectar con api/compilar.php. Verifica que estás abriendo el proyecto desde XAMPP (localhost).');
    return;
  }
  log('mu', `Compilando en PHP programa ROBO-KIDS${res.ast && res.ast.name ? ' «' + res.ast.name + '»' : ''}…`);
  const phase = (name, list) => {
    if (list.length) {
      log('er', `✗ Análisis ${name}: ${list.length} error(es)`);
      return false;
    }
    log('ok', `✓ Análisis ${name} correcto` + (name === 'léxico' ? ` (${res.tokens} tokens)` : name === 'semántico' ? ` (${res.sem.symbols.length} variable(s))` : ''));
    return true;
  };

  let ok = phase('léxico', res.lex.errs);
  if (ok) ok = phase('sintáctico', res.parse.errs);
  if (ok) ok = phase('semántico', res.sem.errs);

  for (const e of res.errors) {
    log('er', `   [${e.phase}] Línea ${e.line}: ${e.msg}`);
    log('wa', '   Pista: ' + e.hint);
  }
  for (const w of res.warns) {
    log('wa', `   Aviso: ${w.line ? 'Línea ' + w.line + ': ' : ''}${w.msg}`);
  }

  if (!res.errors.length && !res.ast.body.length) {
    log('wa', 'El programa está vacío.');
    say('Arrastra algunos bloques primero.');
    return;
  }
  if (res.errors.length) {
    const e = res.errors[0];
    say(`¡Ups! Error en la línea ${e.line}. ${e.hint}`);
    return;
  }

  log('ok', '✓ Ejecución: iniciando simulación…');
  resetRobot();
  ops = 0;
  running = true;
  const id = ++runId;
  res.sem.symbols.forEach(s => s.cur = undefined);

  try {
    await exec(res.ast.body, new Env(null), id);
    log('wa', '■ Programa terminado. El robot no completó la misión.');
    const left = stars.filter(s => s.on).length;
    say(left ? `Aún faltan ${left} estrella(s) y llegar al portal. ¡Inténtalo otra vez!` : '¡Casi! Aún falta llegar al portal verde.');
  } catch (e) {
    if (e === WIN) {
      log('ok', '★ ¡MISIÓN CUMPLIDA! El robot llegó a la meta.');
      say('¡Lo lograste! ¡Eres un gran programador de robots!');
      R.pulse = { t0: performance.now(), r: 120, col: '#16a34a' };
    } else if (e === STOP) {
      log('wa', '■ Ejecución detenida.');
    } else if (e instanceof RunErr) {
      log('er', `✗ Error de ejecución (línea ${e.line}): ${e.msg}`);
      log('wa', '   Pista: ' + e.hint);
      say(`${e.msg} ${e.hint}`);
      errLines = new Set([e.line]);
    } else {
      console.error(e);
      log('er', 'Error inesperado: ' + e.message);
    }
  } finally {
    if (id === runId) {
      running = false;
      setTimeout(() => { if (!running) setExec(0); }, 600);
    }
  }
}