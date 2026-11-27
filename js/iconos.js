/* =========================================================
   ROBO-KIDS · Íconos SVG
   Reemplazan a los emojis: se ven igual en cualquier
   computadora o celular y toman el color del texto.
   ========================================================= */

const ICO = {
  robot: 'M12 2v3M5 8h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2zM9 13v1M15 13v1M9 17h6M1 13v2M23 13v2',
  puzzle: 'M4 7h4a2 2 0 1 1 4 0h4v4a2 2 0 1 1 0 4v4H4z',
  flask: 'M9 3h6M10 3v6L4.5 19a1.5 1.5 0 0 0 1.3 2h12.4a1.5 1.5 0 0 0 1.3-2L14 9V3M7 15h10',
  book: 'M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4zM20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z',
  map: 'M9 4L3 6v14l6-2 6 2 6-2V4l-6 2zM9 4v14M15 6v14',
  stop: 'M6 6h12v12H6z',
  trash: 'M4 6h16M9 6V4h6v2M6 6l1 14h10l1-14',
  save: 'M5 3h11l3 3v15H5zM8 3v5h7M8 21v-7h8v7',
  folder: 'M3 6h6l2 2h10v11H3z',
  target: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
  search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5',
  radar: 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM8.5 8.5a5 5 0 0 0 0 7M15.5 8.5a5 5 0 0 1 0 7M5.6 5.6a9 9 0 0 0 0 12.8M18.4 5.6a9 9 0 0 1 0 12.8',
  battery: 'M3 8h15v8H3zM21 11v2M6 11v2M9 11v2',
  keyboard: 'M3 6h18v12H3zM7 10h.01M11 10h.01M15 10h.01M7 14h10',
  monitor: 'M3 4h18v12H3zM8 20h8M12 16v4',
  up: 'M12 19V5M6 11l6-6 6 6',
  bolt: 'M13 2L4 14h7l-1 8 9-12h-7z',
  chat: 'M4 4h16v12H8l-4 4z',
  chip: 'M7 7h10v10H7zM9 3v4M15 3v4M9 17v4M15 17v4M3 9h4M3 15h4M17 9h4M17 15h4',
  repeat: 'M17 2l4 4-4 4M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4M21 13v2a3 3 0 0 1-3 3H3',
  plus: 'M12 5v14M5 12h14',
  play: 'M7 4l13 8-13 8z',
  gauge: 'M12 14l4-4M3.5 17a9 9 0 1 1 17 0',
};

// Íconos que van rellenos en lugar de solo contorno
const ICO_RELLENO = new Set(['stop', 'bolt', 'play']);

// Devuelve el SVG de un ícono como texto HTML
function ico(nombre) {
  const d = ICO[nombre];
  if (!d) return '';
  const cls = ICO_RELLENO.has(nombre) ? 'i f' : 'i';
  return `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true"><path d="${d}"/></svg>`;
}

// Pone el ícono en cada elemento del HTML que tenga data-ico="nombre"
function ponerIconos() {
  document.querySelectorAll('[data-ico]').forEach(el => {
    el.insertAdjacentHTML('afterbegin', ico(el.dataset.ico));
    el.removeAttribute('data-ico');
  });
}

ponerIconos();
