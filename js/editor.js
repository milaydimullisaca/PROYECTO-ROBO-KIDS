/* =====================================================================
   EDITOR DE CÓDIGO, CONEXIÓN AL COMPILADOR PHP, CONSOLA Y ROBO-BOT
   ===================================================================== */
const API_URL = 'php/api/compilar.php';

const norm = s => s.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

function esrc(e) {
  if (!e) return '';
  switch (e.k) {
    case 'num': return String(e.v);
    case 'str': return '"' + e.v + '"';
    case 'bool': return e.v ? 'VERDADERO' : 'FALSO';
    case 'sensor': return e.v;
    case 'var': return e.lex;
    case 'par': return '(' + esrc(e.a) + ')';
    case 'un': return e.op === 'NO' ? 'NO ' + esrc(e.a) : '-' + esrc(e.a);
    case 'bin': return esrc(e.a) + ' ' + e.op + ' ' + esrc(e.b);
  }
  return '';
}

async function apiPost(payload) {
  const res = await fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

const code = $('code'), hl = $('hl'), gut = $('gut');
let curLine = 0, errLines = new Set(), lastRes = null;
let compileSeq = 0, debounceId = null;

const esc = s => s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

function renderHL(spans) {
  const src = code.value;
  let out = '', pos = 0;
  const put = s => out += esc(s).replace(/\n/g, '\u0000');

  if (Array.isArray(spans)) {
    for (const s of spans) {
      if (s.a < pos) continue;
      put(src.slice(pos, s.a));
      out += `<span class="t-${s.css}">${esc(src.slice(s.a, s.b))}</span>`;
      pos = s.b;
    }
  }
  put(src.slice(pos));

  const lines = out.split('\u0000');
  hl.innerHTML = lines.map((l, i) => `<div class="L${i + 1 === curLine ? ' cur' : ''}${errLines.has(i + 1) ? ' bad' : ''}">${l || ' '}</div>`).join('');
  gut.innerHTML = lines.map((_, i) => `<div class="${i + 1 === curLine ? 'cur' : errLines.has(i + 1) ? 'bad' : ''}">${i + 1}</div>`).join('');
  syncScroll();
}

function syncScroll() {
  hl.scrollTop = code.scrollTop;
  hl.scrollLeft = code.scrollLeft;
  gut.scrollTop = code.scrollTop;
}

code.addEventListener('scroll', syncScroll);
code.addEventListener('keydown', e => {
  if (e.key === 'Tab' && !code.readOnly) {
    e.preventDefault();
    const s = code.selectionStart;
    code.setRangeText('  ', s, code.selectionEnd, 'end');
    code.dispatchEvent(new Event('input'));
  }
});

async function liveCompile(syncToBlocks = false) {
  const seq = ++compileSeq;
  const src = code.value;
  try {
    const r = await apiPost({ accion: 'compilar', code: src });
    if (seq !== compileSeq) return lastRes; // descarta respuesta vieja
    lastRes = r;
    errLines = new Set(r.errors.map(e => e.line));
    renderHL(r.lex.spans);

    const st = $('stat');
    if (r.errors.length) {
      st.className = 'chip bad';
      st.textContent = `✗ ${r.errors.length} error(es) · línea ${r.errors[0].line}`;
      st.title = r.errors[0].msg;
    } else {
      st.className = 'chip good';
      st.textContent = `✓ sin errores · ${r.tokens} tokens (PHP)`;
      st.title = '';
    }

    if (syncToBlocks && mode === 'txt' && !r.lex.errs.length && !r.parse.errs.length && r.ast) {
      prog.length = 0;
      lineOf = {};
      fromAst(r.ast.body).forEach(n => prog.push(n));
      if (r.ast.name) $('rName').value = r.ast.name;
      selArr = null;
      renderWs();
    }

    renderLab();
    return r;
  } catch (err) {
    const st = $('stat');
    st.className = 'chip bad';
    st.textContent = '✗ Error conectando a api/compilar.php';
    st.title = err.message;
    renderHL(null);
    return null;
  }
}

code.addEventListener('input', () => {
  if (mode !== 'txt') return;
  renderHL(null); // actualiza texto al instante mientras responde PHP
  save();
  clearTimeout(debounceId);
  debounceId = setTimeout(() => liveCompile(true), 120);
});

function setMode(m, quiet) {
  mode = m;
  code.readOnly = m === 'blk';
  $('tBlk').classList.toggle('on', m === 'blk');
  $('tTxt').classList.toggle('on', m === 'txt');
  if (m === 'blk' && !quiet) {
    code.value = genCode();
    renderWs();
    liveCompile();
    save();
  }
  if (m === 'txt' && !quiet) code.focus();
}

function setExec(line) {
  curLine = line;
  renderHL(lastRes && lastRes.src === code.value ? lastRes.lex.spans : null);
  document.querySelectorAll('#ws .blk.exec').forEach(b => b.classList.remove('exec'));
  if (line) {
    document.querySelectorAll(`#ws .blk[data-line="${line}"]`).forEach(b => {
      b.classList.add('exec');
      b.scrollIntoView({ block: 'nearest' });
    });
    const lh = 19.5, top = (line - 1) * lh;
    if (top < code.scrollTop || top > code.scrollTop + code.clientHeight - lh * 2) {
      code.scrollTop = Math.max(0, top - lh * 3);
    }
  }
}

/* Consola y mensajes de ROBO-BOT */
const con = $('con');
function log(cls, txt) {
  const d = document.createElement('div');
  d.className = cls;
  d.textContent = txt;
  con.append(d);
  con.scrollTop = con.scrollHeight;
}

function say(t, icon) {
  $('botMsg').innerHTML = '<b>ROBO-BOT:</b> ';
  $('botMsg').append(document.createTextNode((icon || '') + t));
}

function speak(t) {
  try {
    if (!window.speechSynthesis) return;
    const u = new SpeechSynthesisUtterance(t);
    u.lang = 'es-ES';
    u.rate = 1.05;
    u.pitch = 1.4;
    speechSynthesis.cancel();
    speechSynthesis.speak(u);
  } catch (e) { }
}