/* =====================================================================
   INICIALIZACIÓN, PERSISTENCIA, NAVEGACIÓN DE VISTAS Y AYUDA
   ===================================================================== */
async function loadProgram(src, mission) {
  runId++;
  running = false;
  setMode('txt', true);
  code.value = src;
  if (mission != null) loadMission(mission);
  const res = await liveCompile(true);
  save();
  if (!res) return;
  if (mode === 'txt' && !res.errors.length) say('Programa cargado. ¡Pulsa EJECUTAR!');
  else if (res.errors.length) say(`Este programa tiene ${res.errors.length} error(es). Mira la consola al ejecutar.`);
}

const KEY = 'robokids-studio-v3';
function save() {
  try {
    localStorage.setItem(KEY, JSON.stringify({ code: code.value, mode, mission: mi, name: $('rName').value, spd: SPD }));
  } catch (e) { }
}
function restore() {
  try {
    return JSON.parse(localStorage.getItem(KEY) || 'null');
  } catch (e) {
    return null;
  }
}

/* Botones de barra superior */
$('bSave').onclick = () => {
  const name = (lastRes && lastRes.ast && lastRes.ast.name) || $('rName').value || 'programa';
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([code.value], { type: 'text/plain;charset=utf-8' }));
  a.download = name.replace(/[^\wÁÉÍÓÚÑáéíóúñ-]/g, '') + '.rk';
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  say('Programa guardado como ' + a.download + '.');
};

$('bOpen').onclick = () => $('fOpen').click();
$('fOpen').onchange = e => {
  const f = e.target.files[0];
  if (!f) return;
  f.text().then(t => { loadProgram(t); say('Abrí «' + f.name + '».'); });
  e.target.value = '';
};

MISSIONS.forEach((m, i) => $('mis').add(new Option(m.name, i)));
$('mis').onchange = () => {
  runId++;
  running = false;
  loadMission(+$('mis').value);
  say('Nueva misión: ' + MISSIONS[mi].desc);
  save();
};

$('spd').oninput = () => {
  SPD = +$('spd').value;
  $('spdV').textContent = SPD + 'x';
  save();
};

$('bRun').onclick = runProgram;
$('bStop').onclick = () => { runId++; running = false; setExec(0); };
$('bReset').onclick = () => { runId++; running = false; resetRobot(); setExec(0); say('Robot reiniciado.'); };
$('bClear').onclick = () => { prog.length = 0; selArr = null; syncB(true); };
$('tBlk').onclick = () => setMode('blk');
$('tTxt').onclick = () => setMode('txt');
$('rName').onchange = () => syncB(false);

/* Navegación entre vistas */
function showView(v) {
  [['prog', 'vProg', 'nProg'], ['lab', 'vLab', 'nLab'], ['help', 'vHelp', 'nHelp']].forEach(([k, vid, nid]) => {
    $(vid).hidden = k !== v;
    $(nid).classList.toggle('on', k === v);
  });
  if (v === 'lab') renderLab();
}

$('nProg').onclick = () => showView('prog');
$('nLab').onclick = () => showView('lab');
$('nHelp').onclick = () => showView('help');

/* Sección de ayuda */
const HELP = [
  ['mov', 'Movimiento', 'AVANZAR 50 :D\nRETROCEDER 50 :D\nGIRAR 90 :D\nGIRAR -90 :D\nFRENAR :D', '50 es una casilla. GIRAR usa múltiplos de 45. Sin número: AVANZAR = 50 y GIRAR = 90.'],
  ['pow', 'Energía y programa', 'ROBOT Explorador {\n  ACTIVAR :D\n  ...\n  APAGAR :D\n}', 'Activa el robot antes de moverlo y apágalo al final.'],
  ['sen', 'Sensores', 'SONAR :D\nDETECTAR :D\nESCANEAR :D\nLEER distancia :D', 'Condiciones: OBSTACULO, LIBRE, META, ESTRELLA.'],
  ['com', 'Comunicación', 'DECIR "Hola" :D\nHABLAR "Hola amigos" :D\nRECIBIR nombre :D\nRESPONDER "Hola " + nombre :D', 'HABLAR usa la voz de la computadora. RECIBIR te pide un dato.'],
  ['mem', 'Memoria (variables)', 'MEMORIA pasos = 3 :D\nMEMORIA nombre = "Robi" :D\nMEMORIA listo = VERDADERO :D\nACTUALIZAR pasos = pasos + 1 :D', 'Tipos: NUMERO, TEXTO y BOOLEANO. Una variable no cambia de tipo.'],
  ['ctl', 'Control', 'REPETIR 4 { ... }\nPATRULLAR LIBRE { ... }\nSI OBSTACULO { ... }\nSI NO { ... }\nCOMPROBAR META :D', 'PATRULLAR repite mientras la condición sea verdadera.'],
  ['ctl', 'Operadores', '+  -  *  /  %\n==  !=  <  >  <=  >=\nY   O   NO', 'Ejemplo: SI pasos > 3 Y NO OBSTACULO { ... }']
];

$('helpG').innerHTML = HELP.map(([c, t, codeTxt, d]) => `<div class="hc c-${c}"><h3>${ico(t === 'Operadores' ? 'plus' : ICON[c])} ${t}</h3><div><code>${esc(codeTxt)}</code>${esc(d)}</div></div>`).join('');

buildPalette();
const saved = restore();
loadMission(saved && saved.mission != null ? saved.mission : 0);

if (saved && saved.spd) {
  SPD = saved.spd;
  $('spd').value = SPD;
  $('spdV').textContent = SPD + 'x';
}

(async () => {
  if (saved && saved.code) {
    if (saved.name) $('rName').value = saved.name;
    await loadProgram(saved.code);
    if (saved.mode === 'blk' && lastRes && !lastRes.errors.length) setMode('blk');
    say('¡Hola de nuevo! Recuperé tu último programa.');
  } else {
    [['ACTIVAR'], ['DECIR', { e: '"Hola, mundo"' }], ['AVANZAR', { e: '100' }], ['GIRAR', { e: '90' }], ['AVANZAR', { e: '50' }], ['SONAR'], ['APAGAR']]
      .forEach(([t, x]) => prog.push(mk(t, x || {})));
    setMode('blk');
  }
})();

requestAnimationFrame(draw);