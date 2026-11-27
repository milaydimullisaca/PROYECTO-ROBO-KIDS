/* =====================================================================
   SISTEMA DE BLOQUES VISUALES Y GENERACIÓN DE CÓDIGO
   ===================================================================== */
const CATS = [
  ['MOVIMIENTO', 'mov', [['AVANZAR', { e: '50' }], ['RETROCEDER', { e: '50' }], ['GIRAR', { e: '90' }], ['FRENAR', {}]]],
  ['ENERGÍA', 'pow', [['ACTIVAR', {}], ['APAGAR', {}]]],
  ['SENSORES', 'sen', [['SONAR', {}], ['DETECTAR', {}], ['ESCANEAR', {}], ['LEER', { v: 'distancia' }]]],
  ['COMUNICACIÓN', 'com', [['DECIR', { e: '"¡Hola!"' }], ['HABLAR', { e: '"Hola amigos"' }], ['RECIBIR', { v: 'respuesta' }], ['RESPONDER', { e: '"¡Gracias!"' }]]],
  ['MEMORIA', 'mem', [['MEMORIA', { v: 'pasos', e: '0' }], ['ACTUALIZAR', { v: 'pasos', e: 'pasos + 1' }]]],
  ['CONTROL', 'ctl', [['REPETIR', { e: '3', kids: [] }], ['PATRULLAR', { e: 'LIBRE', kids: [] }], ['SI', { e: 'OBSTACULO', kids: [] }], ['SI', { e: 'OBSTACULO', kids: [], els: [] }, 'SI … SI NO'], ['COMPROBAR', { e: 'META' }]]]
];

const catOf = {};
CATS.forEach(c => c[2].forEach(b => catOf[b[0]] = c[1]));

let prog = [], uid = 1, dragData = null, lineOf = {}, selArr = null, mode = 'blk';
const mk = (t, x) => ({ id: uid++, t, ...JSON.parse(JSON.stringify(x || {})) });
const DEF = { AVANZAR: '50', RETROCEDER: '50', GIRAR: '90' };

function fromAst(l) {
  return l.map(n => {
    const b = { id: uid++, t: n.t };
    if (n.e !== undefined) b.e = n.e ? esrc(n.e) : DEF[n.t];
    if (n.v != null) b.v = n.vlex;
    if (n.kids) b.kids = fromAst(n.kids);
    if (n.els) b.els = fromAst(n.els);
    lineOf[b.id] = n.line;
    return b;
  });
}

const subs = n => [n.kids, n.els].filter(Boolean);

function findParent(list, id) {
  for (let i = 0; i < list.length; i++) {
    if (list[i].id === id) return { arr: list, i };
    for (const s of subs(list[i])) {
      const r = findParent(s, id);
      if (r) return r;
    }
  }
  return null;
}

const hasDesc = (n, id) => subs(n).some(a => a.some(k => k.id === id || hasDesc(k, id)));
function inTree(arr, list = prog) {
  if (arr === list) return true;
  return list.some(n => subs(n).some(s => inTree(arr, s)));
}

function genCode() {
  const lines = [];
  lineOf = {};
  const name = ($('rName').value || '').replace(/[^A-Za-z0-9_ÁÉÍÓÚÑáéíóúñ]/g, '') || 'Explorador';
  lines.push(`ROBOT ${name} {`);
  const gl = (list, ind) => {
    for (const n of list) {
      lineOf[n.id] = lines.length + 1;
      const head = n.t
        + (n.v != null ? ' ' + n.v + (n.t === 'MEMORIA' || n.t === 'ACTUALIZAR' ? ' =' : '') : '')
        + (n.e != null && n.e !== '' ? ' ' + n.e : '');
      if (n.kids) {
        lines.push(`${ind}${head} {`);
        gl(n.kids, ind + '  ');
        if (n.els) {
          lines.push(`${ind}} SI NO {`);
          gl(n.els, ind + '  ');
        }
        lines.push(ind + '}');
      } else {
        lines.push(`${ind}${head} :D`);
      }
    }
  };
  gl(prog, '  ');
  lines.push('}');
  return lines.join('\n') + '\n';
}

function drop(targetArr, beforeId) {
  if (!dragData) return;
  let node;
  if (dragData.pal) {
    const it = CATS[dragData.ci][2][dragData.ii];
    node = mk(it[0], it[1]);
  } else {
    const f = findParent(prog, dragData.id);
    if (!f) return;
    node = f.arr[f.i];
    if (beforeId === node.id || hasDesc(node, beforeId) || targetArr.__owner === node.id || (targetArr.__owner && hasDesc(node, targetArr.__owner))) return;
    f.arr.splice(f.i, 1);
  }
  const idx = beforeId != null ? targetArr.findIndex(x => x.id === beforeId) : -1;
  if (idx >= 0) targetArr.splice(idx, 0, node);
  else targetArr.push(node);
  dragData = null;
  syncB(true);
}

function mini(txt, cls, fn, title) {
  const s = document.createElement('span');
  s.className = cls;
  s.textContent = txt;
  if (title) s.title = title;
  s.onclick = e => { e.stopPropagation(); fn(); };
  return s;
}

function field(val, on, list) {
  const i = document.createElement('input');
  i.value = val;
  i.draggable = false;
  const fit = () => i.style.width = `calc(${Math.max(2, i.value.length) + 1}ch + 22px)`;
  fit();
  if (list) i.setAttribute('list', list);
  i.oninput = fit;
  i.onchange = () => on(i.value);
  i.onmousedown = e => e.stopPropagation();
  i.onclick = e => e.stopPropagation();
  return i;
}

function blockEl(n) {
  const cat = catOf[n.t], el = document.createElement('div');
  const hb = document.createElement('div');
  hb.className = 'blk c-' + cat;
  hb.draggable = true;
  hb.dataset.line = lineOf[n.id] || '';
  hb.append(n.t);

  if (n.v != null) {
    hb.append(field(n.v, v => { n.v = v.trim().replace(/\s+/g, '_') || 'x'; syncB(true); }));
    if (n.t === 'MEMORIA' || n.t === 'ACTUALIZAR') hb.append('=');
  }
  if (n.e != null) {
    hb.append(field(n.e, v => { n.e = v; syncB(false); }, (n.kids || n.t === 'COMPROBAR') ? 'dl-e' : null));
  }
  if (n.t === 'SI') {
    hb.append(mini(n.els ? '− SI NO' : '+ SI NO', 'tg', () => { n.els = n.els ? null : []; syncB(true); }, 'Agregar o quitar la parte SI NO'));
  }
  hb.append(
    mini('▲', 'mv', () => mvB(n, -1), 'Subir'),
    mini('▼', 'mv', () => mvB(n, 1), 'Bajar'),
    mini('✕', 'x', () => { const f = findParent(prog, n.id); f.arr.splice(f.i, 1); syncB(true); }, 'Quitar')
  );

  hb.ondragstart = e => { dragData = { id: n.id }; e.dataTransfer.setData('text/plain', 'b'); e.stopPropagation(); };
  hb.ondragover = e => { e.preventDefault(); e.stopPropagation(); };
  hb.ondrop = e => { e.preventDefault(); e.stopPropagation(); const f = findParent(prog, n.id); drop(f.arr, n.id); };

  if (n.kids) {
    el.className = 'cont';
    el.style.borderColor = `var(--${cat})`;
    el.append(hb);
    el.append(zoneEl(n.kids, n.id, n.t === 'SI' ? 'ENTONCES' : n.t === 'PATRULLAR' ? 'MIENTRAS SE CUMPLA' : 'HACER'));
    if (n.els) el.append(zoneEl(n.els, n.id, 'SI NO'));
  } else {
    el.append(hb);
  }
  return el;
}

function mvB(n, d) {
  const f = findParent(prog, n.id), j = f.i + d;
  if (j < 0 || j >= f.arr.length) return;
  [f.arr[f.i], f.arr[j]] = [f.arr[j], f.arr[f.i]];
  syncB(true);
}

function zoneEl(arr, owner, label) {
  const dz = document.createElement('div');
  dz.className = 'drop' + (selArr === arr ? ' sel' : '');
  arr.__owner = owner;
  const l = document.createElement('span');
  l.className = 'lbl';
  l.textContent = label + (arr.length ? '' : ' · suelta o toca aquí');
  dz.append(l);
  arr.forEach(k => dz.append(blockEl(k)));
  zone(dz, arr);
  dz.onclick = e => {
    if (e.target === dz || e.target === l) {
      e.stopPropagation();
      selArr = selArr === arr ? null : arr;
      renderWs();
    }
  };
  return dz;
}

function zone(dz, arr) {
  dz.ondragover = e => { e.preventDefault(); e.stopPropagation(); dz.classList.add('over'); };
  dz.ondragleave = () => dz.classList.remove('over');
  dz.ondrop = e => { e.preventDefault(); e.stopPropagation(); dz.classList.remove('over'); drop(arr); };
}

function renderWs() {
  const ws = $('ws');
  ws.innerHTML = '';
  ws.classList.toggle('sel', !selArr);
  prog.forEach(n => ws.append(blockEl(n)));
  if (!prog.length) {
    const d = document.createElement('div');
    d.className = 'hint';
    d.textContent = 'Programa vacío. Arrastra o toca bloques.';
    ws.append(d);
  }
}

// Nombre del ícono SVG (ver js/iconos.js) para cada categoría de bloques
const ICON = { mov: 'up', pow: 'bolt', sen: 'radar', com: 'chat', mem: 'chip', ctl: 'repeat' };
const HELPTXT = {
  AVANZAR: 'Camina hacia adelante (50 = 1 casilla)', RETROCEDER: 'Camina hacia atrás',
  GIRAR: 'Gira: 90 derecha, -90 izquierda', FRENAR: 'Se detiene un momento',
  ACTIVAR: 'Enciende el robot (¡siempre primero!)', APAGAR: 'Apaga el robot al terminar',
  SONAR: 'Mide la distancia a la pared', DETECTAR: '¿Hay un obstáculo adelante?',
  ESCANEAR: 'Busca estrellas y la meta', LEER: 'Guarda la distancia del sonar en una variable',
  DECIR: 'Muestra un mensaje', HABLAR: 'Dice el mensaje en voz alta',
  RECIBIR: 'Te pide un dato por teclado', RESPONDER: 'El robot te responde',
  MEMORIA: 'Crea una variable con un valor', ACTUALIZAR: 'Cambia el valor de una variable',
  REPETIR: 'Repite los bloques varias veces', PATRULLAR: 'Repite mientras la condición se cumpla',
  SI: 'Hace algo solo si se cumple', COMPROBAR: 'Verifica que algo sea verdad'
};

let curCat = 0;

function buildPalette() {
  const tabs = $('catTabs');
  CATS.forEach(([name, cl], ci) => {
    const t = document.createElement('button');
    t.className = 'ct c-' + cl;
    t.innerHTML = `<span>${ico(ICON[cl])}</span>${name.charAt(0) + name.slice(1).toLowerCase()}`;
    t.onclick = () => showCat(ci);
    tabs.append(t);
  });
  showCat(0);
}

function showCat(ci) {
  curCat = ci;
  const p = $('palette'), [name, cl, items] = CATS[ci];
  p.innerHTML = '';
  [...$('catTabs').children].forEach((b, i) => b.classList.toggle('on', i === ci));
  const h = document.createElement('div');
  h.className = 'cath';
  h.innerHTML = ico(ICON[cl]) + ' ' + name;
  p.append(h);

  items.forEach(([t, x, label], ii) => {
    const w = document.createElement('div');
    w.className = 'pi';
    const b = document.createElement('div');
    b.className = 'blk c-' + cl;
    b.draggable = true;
    b.textContent = label || t;
    b.ondragstart = e => { dragData = { pal: 1, ci, ii }; e.dataTransfer.setData('text/plain', 'p'); };
    b.onclick = () => {
      const tgt = selArr && inTree(selArr) ? selArr : prog;
      tgt.push(mk(t, x));
      syncB(true);
    };
    const s = document.createElement('small');
    s.textContent = label ? 'SI con camino alternativo SI NO' : HELPTXT[t] || '';
    w.append(b, s);
    p.append(w);
  });
}

const wsEl = $('ws');
zone(wsEl, prog);
wsEl.onclick = e => { if (e.target === wsEl && selArr) { selArr = null; renderWs(); } };

/* sincroniza bloques -> código */
function syncB(rerender) {
  if (mode !== 'blk') setMode('blk', true);
  code.value = genCode();
  if (rerender) renderWs();
  liveCompile();
  save();
}