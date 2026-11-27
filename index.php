<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>RoboKids Studio · v3</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;700;800&family=JetBrains+Mono:wght@400;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
  <nav class="rail">
    <button id="nProg" class="on" title="Programar y jugar"><span data-ico="puzzle"></span>Programar</button>
    <button id="nLab" title="Ver cómo trabaja el compilador"><span data-ico="flask"></span>Laboratorio</button>
    <button id="nHelp" title="Lista de instrucciones"><span data-ico="book"></span>Ayuda</button>
    <div class="sp"></div>
    <div class="tip">ROBO-KIDS v3</div>
  </nav>

  <header class="top">
    <div class="brand">
      <div class="face" data-ico="robot"></div>
      <div>
        <h1><b>Robo</b><i>Kids</i> Studio</h1>
        <small>Programa tu robot · lenguaje ROBO-KIDS</small>
      </div>
      <span class="badge">v3</span>
    </div>
    <div class="sp"></div>
    <label class="pill"><span data-ico="map"></span> <select id="mis"></select></label>
    <label class="pill" title="Velocidad del robot"><span data-ico="gauge"></span> <input type="range" id="spd" min="0.5" max="3" step="0.5" value="1"> <b id="spdV">1x</b></label>
    <button class="btn run" id="bRun"><span data-ico="play"></span> ¡EJECUTAR!</button>
    <button class="btn stop ib" id="bStop" title="Detener" aria-label="Detener" data-ico="stop"></button>
    <button class="btn ib" id="bReset" title="Reiniciar robot">↺</button>
    <button class="btn ib" id="bClear" title="Borrar todos los bloques" aria-label="Borrar todos los bloques" data-ico="trash"></button>
    <button class="btn ib" id="bSave" title="Guardar el programa como archivo .rk" aria-label="Guardar programa" data-ico="save"></button>
    <button class="btn ib" id="bOpen" title="Abrir un archivo .rk" aria-label="Abrir programa" data-ico="folder"></button>
    <input type="file" id="fOpen" accept=".rk,.txt" hidden>
  </header>

  <div class="wrap">
    <div class="view" id="vProg">
      <aside class="card tool">
        <h2><span class="n">1</span>Bloques</h2>
        <div id="catTabs"></div>
        <div id="palette"></div>
        <div class="hint">Toca un bloque para agregarlo o arrástralo a tu programa.</div>
      </aside>

      <section class="card work">
        <h2><span class="n">2</span>Tu programa</h2>
        <div class="rname"><span data-ico="robot"></span> Mi robot se llama <input id="rName" value="Explorador" maxlength="20"></div>
        <div id="ws"></div>
        <div class="hint">Toca la zona de un REPETIR, SI o PATRULLAR para poner bloques dentro. Usa ▲ ▼ para ordenar.</div>
      </section>

      <section class="card stage">
        <h2><span class="n">3</span>Simulador</h2>
        <div class="mission"><span data-ico="target"></span> <b id="mTitle"></b><span id="mDesc"></span></div>
        <canvas id="cv" width="600" height="450"></canvas>
        <div class="sens">
          <div class="sn"><span class="ic" data-ico="search"></span><b>ESCÁNER</b><span id="sScan">—</span></div>
          <div class="sn"><span class="ic" data-ico="radar"></span><b>DETECTOR</b><span id="sDet">libre</span></div>
          <div class="sn"><span class="ic" data-ico="battery"></span><b>ENERGÍA <span id="sEn">100%</span></b>
            <div class="bar"><i id="eBar"></i></div>
          </div>
        </div>
        <div class="bot">
          <div class="av" data-ico="robot"></div>
          <p id="botMsg"><b>ROBO-BOT:</b> ¡Hola! Elige bloques y pulsa EJECUTAR.</p>
        </div>
      </section>

      <section class="card dock">
        <h2><span class="n">4</span>Código y consola</h2>
        <div class="dockin">
          <div>
            <div class="tabs">
              <button class="btn on" id="tBlk"><span data-ico="puzzle"></span> Desde bloques</button>
              <button class="btn" id="tTxt"><span data-ico="keyboard"></span> Escribir código</button>
              <span class="chip" id="stat">—</span>
            </div>
            <div class="ed">
              <div class="gut" id="gut"></div>
              <div class="edw">
                <pre id="hl" aria-hidden="true"></pre>
                <textarea id="code" spellcheck="false" wrap="off" autocomplete="off" autocapitalize="off"></textarea>
              </div>
            </div>
          </div>
          <div>
            <p class="sub"><span data-ico="monitor"></span> Consola del compilador</p>
            <div id="con"></div>
          </div>
        </div>
        <div class="ref">
          Cada instrucción termina en <code>:D</code> · <code>MEMORIA pasos = 3 :D</code>
          <code>AVANZAR pasos * 50 :D</code> <code>SI OBSTACULO { … } SI NO { … }</code>
          <code>PATRULLAR LIBRE { … }</code> <code>DECIR "Hola " + nombre :D</code>
        </div>
      </section>
    </div>

    <div class="view" id="vLab" hidden>
      <section class="card lab">
        <h2><span class="n" data-ico="flask"></span>Laboratorio del compilador <small>evidencias del análisis léxico, sintáctico y semántico</small></h2>
        <div class="tabs" id="labTabs"></div>
        <div class="lbody" id="labBody"></div>
      </section>
    </div>

    <div class="view" id="vHelp" hidden>
      <section class="card">
        <div class="intro">
          <div class="big" data-ico="book"></div>
          <div>
            <h2 style="margin:0">Instrucciones de ROBO-KIDS</h2>
            <div class="mu">Cada instrucción termina en <b>:D</b>. Los bloques REPETIR, SI y PATRULLAR guardan otras instrucciones entre llaves <b>{ }</b>.</div>
          </div>
        </div>
        <div class="helpg" id="helpG"></div>
      </section>
    </div>
  </div>

  <datalist id="dl-e">
    <option value="OBSTACULO">
    <option value="LIBRE">
    <option value="META">
    <option value="ESTRELLA">
    <option value="NO OBSTACULO">
    <option value="NO META">
    <option value="LIBRE Y NO META">
    <option value="VERDADERO">
  </datalist>

  <!-- Módulos de la aplicación -->
  <script src="js/iconos.js"></script>
  <script src="js/simulador.js"></script>
  <script src="js/bloques.js"></script>
  <script src="js/editor.js"></script>
  <script src="js/interprete.js"></script>
  <script src="js/laboratorio.js"></script>
  <script src="js/main.js"></script>
  
</body>

</html>