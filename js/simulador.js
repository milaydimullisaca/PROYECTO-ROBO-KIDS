/* =====================================================================
   MUNDO Y SIMULADOR 2D (CANVAS)
   ===================================================================== */
const C = 50, GW = 12, GH = 9;
const $ = id => document.getElementById(id);

const MISSIONS = [
  {
    name: '1 · Encuentra la salida',
    desc: 'Lleva al robot hasta el portal verde.',
    start: { c: 1, r: 4, a: 0 },
    map: [
      '############', '#..........#', '#....#.....#', '#....#.....#',
      '#S...#....M#', '#....#.....#', '#....#.....#', '#..........#', '############'
    ]
  },
  {
    name: '2 · Recoge las estrellas',
    desc: 'Recoge las 4 estrellas y entra al portal.',
    start: { c: 1, r: 1, a: 0 },
    map: [
      '############', '#S.....*...#', '#.####.###.#', '#.#*...#...#',
      '#.#.####.#.#', '#.#.....*#.#', '#.#######..#', '#*.......#M#', '############'
    ]
  },
  {
    name: '3 · Sonar en el laberinto',
    desc: 'Usa PATRULLAR LIBRE, GIRAR y variables para salir.',
    start: { c: 1, r: 1, a: 0 },
    map: [
      '############', '#S.........#', '#########..#', '#..........#',
      '#..#########', '#..........#', '#########..#', '#M.........#', '############'
    ]
  }
];

let walls, stars, meta, mi = 0, got = 0, R;
const canvas = $('cv'), g = canvas.getContext('2d');

function loadMission(i) {
  mi = i;
  const m = MISSIONS[i];
  walls = [];
  stars = [];
  m.map.forEach((row, r) => {
    walls[r] = [];
    [...row].forEach((ch, c) => {
      walls[r][c] = ch === '#';
      if (ch === '*') stars.push({ x: c * C + 25, y: r * C + 25, on: true });
      if (ch === 'M') meta = { x: c * C + 25, y: r * C + 25 };
    });
  });
  $('mTitle').textContent = m.name;
  $('mDesc').textContent = m.desc;
  $('mis').value = i;
  resetRobot();
}

function resetRobot() {
  const s = MISSIONS[mi].start;
  stars.forEach(s => s.on = true);
  got = 0;
  R = { x: s.c * C + 25, y: s.r * C + 25, a: s.a, active: false, energy: 100, trail: [], sonar: null, pulse: null, flash: 0, won: false };
  $('sScan').textContent = '—';
  $('sDet').textContent = 'libre';
  $('sDet').style.color = '';
  updEnergy();
}

function updEnergy() {
  const e = Math.max(0, Math.round(R.energy));
  $('sEn').textContent = e + '%';
  $('eBar').style.width = e + '%';
}

function isWall(px, py) {
  const c = Math.floor(px / C), r = Math.floor(py / C);
  return c < 0 || r < 0 || c >= GW || r >= GH || walls[r][c];
}

function hits(px, py) {
  const r = 15;
  return isWall(px - r, py - r) || isWall(px + r, py - r) || isWall(px - r, py + r) || isWall(px + r, py + r);
}

const rad = a => a * Math.PI / 180;

function ray(maxd = 600) {
  const dx = Math.cos(rad(R.a)), dy = Math.sin(rad(R.a));
  for (let d = 0; d < maxd; d += 2) {
    if (isWall(R.x + dx * d, R.y + dy * d)) return d;
  }
  return maxd;
}

function drawStar(x, y, r) {
  g.beginPath();
  for (let i = 0; i < 10; i++) {
    const rr = i % 2 ? r * .45 : r;
    const a = -Math.PI / 2 + i * Math.PI / 5;
    g.lineTo(x + Math.cos(a) * rr, y + Math.sin(a) * rr);
  }
  g.closePath();
}

function rr(x, y, w, h, r) {
  g.beginPath();
  if (g.roundRect) g.roundRect(x, y, w, h, r);
  else g.rect(x, y, w, h);
}

function draw(t) {
  g.clearRect(0, 0, 600, 450);

  for (let r = 0; r < GH; r++) {
    for (let c = 0; c < GW; c++) {
      if (!walls[r][c]) {
        g.fillStyle = (r + c) % 2 ? '#e0f2fe' : '#f0f9ff';
        g.fillRect(c * C, r * C, C, C);
        g.fillStyle = '#bae6fd';
        g.fillRect(c * C + 23, r * C + 23, 4, 4);
      }
    }
  }

  // Portal
  const pulse = Math.sin(t / 300) * 3;
  g.save();
  g.fillStyle = '#bbf7d0';
  g.beginPath();
  g.arc(meta.x, meta.y, 22, 0, 7);
  g.fill();
  g.strokeStyle = '#16a34a';
  g.lineWidth = 3;
  for (let k = 0; k < 3; k++) {
    g.beginPath();
    g.arc(meta.x, meta.y, 6 + k * 6 + pulse * (k / 2), 0, 7);
    g.globalAlpha = 1 - k * .25;
    g.stroke();
  }
  g.restore();
  // Banderita de la meta (dibujada, sin emoji)
  const fx = meta.x + 8, fy = meta.y - 24;
  g.lineWidth = 2;
  g.strokeStyle = '#073b4c';
  g.beginPath();
  g.moveTo(fx, fy);
  g.lineTo(fx, fy + 16);
  g.stroke();
  g.fillStyle = '#ef476f';
  g.beginPath();
  g.moveTo(fx, fy);
  g.lineTo(fx + 12, fy + 4);
  g.lineTo(fx, fy + 8);
  g.closePath();
  g.fill();

  // Estrellas
  for (const s of stars) {
    if (s.on) {
      g.save();
      const y = s.y + Math.sin(t / 250 + s.x) * 2;
      g.fillStyle = '#fbbf24';
      g.strokeStyle = '#1e293b';
      g.lineWidth = 2.5;
      drawStar(s.x, y, 13);
      g.fill();
      g.stroke();
      g.fillStyle = '#1e293b';
      g.beginPath();
      g.arc(s.x - 3, y - 1, 1.4, 0, 7);
      g.arc(s.x + 3, y - 1, 1.4, 0, 7);
      g.fill();
      g.restore();
    }
  }

  // Rastro
  g.fillStyle = '#f472b6aa';
  R.trail.forEach((p, i) => {
    if (i % 3 === 0) {
      g.beginPath();
      g.arc(p[0], p[1], 2.2, 0, 7);
      g.fill();
    }
  });

  // Muros tipo bloque de juguete
  for (let r = 0; r < GH; r++) {
    for (let c = 0; c < GW; c++) {
      if (walls[r][c]) {
        const x = c * C, y = r * C;
        g.fillStyle = '#c2410c';
        g.fillRect(x, y, C, C);
        g.fillStyle = (r * 7 + c * 3) % 3 === 0 ? '#fb923c' : (r + c) % 2 ? '#f97316' : '#fdba74';
        g.fillRect(x + 2, y + 2, C - 4, C - 9);
        g.strokeStyle = '#1e293b';
        g.lineWidth = 2;
        g.strokeRect(x + 1, y + 1, C - 2, C - 2);
        g.fillStyle = '#ffffff55';
        g.beginPath();
        g.arc(x + 15, y + 15, 5, 0, 7);
        g.arc(x + 35, y + 15, 5, 0, 7);
        g.fill();
      }
    }
  }

  // Sonar y pulsos
  if (R.sonar) {
    const k = (t - R.sonar.t0) / 900;
    if (k > 1) R.sonar = null;
    else {
      g.save();
      g.translate(R.x, R.y);
      g.rotate(rad(R.a));
      g.strokeStyle = `rgba(2,132,199,${1 - k})`;
      g.lineWidth = 4;
      for (let i = 0; i < 3; i++) {
        const q = Math.min(R.sonar.d, (k * 1.4 - i * .15) * R.sonar.d);
        if (q > 0) { g.beginPath(); g.arc(0, 0, q, -.6, .6); g.stroke(); }
      }
      g.restore();
    }
  }

  if (R.pulse) {
    const k = (t - R.pulse.t0) / 700;
    if (k > 1) R.pulse = null;
    else {
      g.save();
      g.strokeStyle = (R.pulse.col || '#7c3aed') + Math.floor((1 - k) * 255).toString(16).padStart(2, '0');
      g.lineWidth = 4;
      g.beginPath();
      g.arc(R.x, R.y, 18 + k * R.pulse.r, 0, 7);
      g.stroke();
      g.restore();
    }
  }

  // Robot
  g.save();
  g.translate(R.x, R.y);
  g.rotate(rad(R.a));
  g.fillStyle = '#00000022'; rr(-14, -13, 32, 30, 9); g.fill();
  g.lineWidth = 2.5; g.strokeStyle = '#1e293b';
  g.fillStyle = '#1e293b'; g.fillRect(-12, -19, 20, 5); g.fillRect(-12, 14, 20, 5);
  g.fillStyle = R.active ? '#ffd166' : '#e2e8f0'; rr(-16, -15, 32, 30, 9); g.fill(); g.stroke();
  g.fillStyle = '#118ab2'; rr(-2, -10, 15, 20, 6); g.fill(); g.stroke();
  g.fillStyle = R.active ? '#06d6a0' : '#94a3b8';
  g.beginPath(); g.arc(6, -4, 3, 0, 7); g.arc(6, 4, 3, 0, 7); g.fill();
  g.strokeStyle = '#1e293b'; g.beginPath(); g.moveTo(-9, 0); g.lineTo(-17, 0); g.stroke();
  g.fillStyle = R.flash > 0 ? '#dc2626' : '#ef476f';
  g.beginPath(); g.arc(-18, 0, 3.5, 0, 7); g.fill(); g.stroke();
  g.restore();

  if (R.flash > 0) R.flash--;
  requestAnimationFrame(draw);
}