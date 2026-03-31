<?php
/*
 * ============================================================
 *  BLOQUE DVSwitch — añade esto dentro de mmdvm.php
 *  Justo después del último panel existente, antes del </div>
 *  principal o donde prefieras colocarlo.
 * ============================================================
 */
?>

<!-- ===================== PANEL DVSWITCH ===================== -->
<div class="row mt-4">
  <div class="col-12">
    <div class="card dark-card">
      <div class="card-header d-flex align-items-center justify-content-between"
           style="background: linear-gradient(90deg,#1a2540,#1e2d50); border-bottom:1px solid #7eb8f733;">
        <span style="color:#7eb8f7; font-weight:700; letter-spacing:2px; font-size:1.05rem;">
          <i class="bi bi-broadcast me-2"></i>DVSwitch Server
        </span>
        <div class="d-flex align-items-center gap-2">
          <!-- Indicador estado -->
          <span id="dvs-status-badge" class="badge" style="font-size:.8rem;">
            <span class="spinner-border spinner-border-sm me-1" role="status"></span>Conectando...
          </span>
          <!-- Botón RX Monitor -->
          <button id="dvs-rx-btn" class="btn btn-sm"
                  style="background:#0d6efd; border:none; color:#fff; font-weight:600; letter-spacing:1px;"
                  onclick="dvsToggleRX()">
            <i class="bi bi-volume-up-fill me-1"></i>RX Monitor
          </button>
        </div>
      </div>

      <div class="card-body p-2">
        <div class="row g-2">

          <!-- Columna izquierda: estado -->
          <div class="col-md-3">
            <div style="background:#0d1117; border:1px solid #7eb8f733; border-radius:6px; padding:10px; font-size:.82rem; font-family:monospace;">
              <div class="d-flex justify-content-between mb-1">
                <span style="color:#8892a4;">Callsign</span>
                <span id="dvs-callsign" style="color:#7eb8f7; font-weight:700;">---</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span style="color:#8892a4;">Modo</span>
                <span id="dvs-mode" style="color:#a8ff78;">---</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span style="color:#8892a4;">TG activo</span>
                <span id="dvs-tg" style="color:#f7c67e; font-weight:700;">---</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span style="color:#8892a4;">Servidor</span>
                <span id="dvs-server" style="color:#8892a4; font-size:.75rem;">---</span>
              </div>
              <hr style="border-color:#7eb8f722; margin:6px 0;">
              <div class="d-flex justify-content-between">
                <span style="color:#8892a4;">Actualizado</span>
                <span id="dvs-ts" style="color:#555;">---</span>
              </div>
            </div>

            <!-- Indicador TX activo -->
            <div id="dvs-tx-indicator" class="mt-2 text-center d-none"
                 style="background:#ff000022; border:1px solid #ff4444; border-radius:6px; padding:6px;
                        color:#ff6666; font-weight:700; letter-spacing:2px; font-size:.85rem; animation: dvsBlink 0.8s infinite;">
              ● TX ACTIVO
            </div>
          </div>

          <!-- Columna derecha: last-heard -->
          <div class="col-md-9">
            <div style="overflow-x:auto;">
              <table class="table table-sm dark-table mb-0" style="font-size:.80rem;">
                <thead>
                  <tr style="color:#7eb8f7; border-bottom:1px solid #7eb8f733;">
                    <th>Hora</th>
                    <th>Callsign</th>
                    <th>TG</th>
                    <th>Slot</th>
                    <th>Dur(s)</th>
                    <th>Loss</th>
                    <th>BER</th>
                    <th>Modo</th>
                  </tr>
                </thead>
                <tbody id="dvs-last-heard">
                  <tr><td colspan="8" class="text-center" style="color:#555; padding:20px;">
                    <span class="spinner-border spinner-border-sm me-2"></span>Cargando...
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>

        </div><!-- /row -->
      </div><!-- /card-body -->
    </div><!-- /card -->
  </div>
</div>

<!-- ===================== AUDIO RX MONITOR ===================== -->
<!-- El audio de DVSwitch viene del Web_Proxy en puerto 8080 via WebSocket -->
<audio id="dvs-audio" autoplay></audio>

<style>
@keyframes dvsBlink {
  0%,100% { opacity:1; }
  50%      { opacity:.3; }
}
#dvs-rx-btn.active {
  background: #dc3545 !important;
  box-shadow: 0 0 8px #dc354588;
}
</style>

<script>
// ─── DVSwitch Panel ───────────────────────────────────────────
(function() {
  const AJAX_URL  = 'dvswitch_ajax.php';
  const WS_PORT   = 8080;            // Puerto Web_Proxy DVSwitch
  let   rxActive  = false;
  let   wsAudio   = null;
  let   audioCtx  = null;
  let   refreshTimer = null;

  // ── Actualizar datos ──────────────────────────────────────
  function dvsRefresh() {
    fetch(AJAX_URL + '?t=' + Date.now())
      .then(r => r.json())
      .then(data => {
        // Estado badge
        const badge = document.getElementById('dvs-status-badge');
        if (data.connected) {
          badge.className = 'badge bg-success';
          badge.innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size:.6rem;"></i>Conectado DMR+';
        } else {
          badge.className = 'badge bg-danger';
          badge.innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size:.6rem;"></i>Desconectado';
        }

        // Info lateral
        document.getElementById('dvs-callsign').textContent = data.callsign || '---';
        document.getElementById('dvs-mode').textContent     = data.mode     || '---';
        document.getElementById('dvs-tg').textContent       = data.tg       || '---';
        document.getElementById('dvs-server').textContent   = data.server   ? data.server.split(':')[0] : '---';
        document.getElementById('dvs-ts').textContent       = data.timestamp || '---';

        // Last-heard table
        const tbody = document.getElementById('dvs-last-heard');
        if (!data.last_heard || data.last_heard.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="color:#555; padding:15px;">Sin actividad reciente</td></tr>';
        } else {
          tbody.innerHTML = data.last_heard.map((e, i) => {
            const lossColor = parseInt(e.loss) > 5  ? '#ff6666' : (parseInt(e.loss) > 0 ? '#f7c67e' : '#a8ff78');
            const rowStyle  = i === 0 ? 'background:#7eb8f711;' : '';
            return `<tr style="${rowStyle}">
              <td style="color:#8892a4;">${e.time}</td>
              <td style="color:#7eb8f7; font-weight:600;">${e.callsign}</td>
              <td style="color:#f7c67e; font-weight:700;">TG ${e.tg}</td>
              <td style="color:#8892a4;">TS${e.slot}</td>
              <td style="color:#fff;">${e.dur}s</td>
              <td style="color:${lossColor};">${e.loss}%</td>
              <td style="color:#8892a4;">${e.ber}%</td>
              <td><span class="badge" style="background:#1e3a5f; color:#7eb8f7;">${e.mode}</span></td>
            </tr>`;
          }).join('');
        }
      })
      .catch(() => {
        document.getElementById('dvs-status-badge').className = 'badge bg-secondary';
        document.getElementById('dvs-status-badge').innerHTML = 'Sin datos';
      });
  }

  // ── RX Monitor audio via WebSocket ────────────────────────
  window.dvsToggleRX = function() {
    const btn = document.getElementById('dvs-rx-btn');
    if (rxActive) {
      // Parar
      if (wsAudio) { wsAudio.close(); wsAudio = null; }
      if (audioCtx) { audioCtx.close(); audioCtx = null; }
      btn.classList.remove('active');
      btn.innerHTML = '<i class="bi bi-volume-up-fill me-1"></i>RX Monitor';
      rxActive = false;
    } else {
      // Iniciar
      rxActive = true;
      btn.classList.add('active');
      btn.innerHTML = '<i class="bi bi-volume-mute-fill me-1"></i>Parar RX';

      try {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 8000 });
        const wsUrl = `ws://${window.location.hostname}:${WS_PORT}`;
        wsAudio = new WebSocket(wsUrl);
        wsAudio.binaryType = 'arraybuffer';

        wsAudio.onmessage = function(ev) {
          if (!(ev.data instanceof ArrayBuffer)) return;
          try {
            // PCM s16le 8kHz mono → float32
            const pcm   = new Int16Array(ev.data);
            const float = new Float32Array(pcm.length);
            for (let i = 0; i < pcm.length; i++) float[i] = pcm[i] / 32768.0;

            const buf = audioCtx.createBuffer(1, float.length, 8000);
            buf.copyToChannel(float, 0);
            const src = audioCtx.createBufferSource();
            src.buffer = buf;
            src.connect(audioCtx.destination);
            src.start();
          } catch(e) {}
        };

        wsAudio.onerror = function() {
          btn.innerHTML = '<i class="bi bi-volume-up-fill me-1"></i>RX Monitor';
          btn.classList.remove('active');
          rxActive = false;
          // Fallback: abrir dashboard DVSwitch en popup
          window.open(`http://${window.location.hostname}:8080`, 'dvswitch_rx',
            'width=900,height=650,resizable=yes');
        };

        wsAudio.onclose = function() {
          if (rxActive) {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="bi bi-volume-up-fill me-1"></i>RX Monitor';
            rxActive = false;
          }
        };
      } catch(e) {
        // Si WebAudio no soportado, popup fallback
        window.open(`http://${window.location.hostname}:8080`, 'dvswitch_rx',
          'width=900,height=650,resizable=yes');
        btn.classList.remove('active');
        btn.innerHTML = '<i class="bi bi-volume-up-fill me-1"></i>RX Monitor';
        rxActive = false;
      }
    }
  };

  // ── Arrancar ──────────────────────────────────────────────
  dvsRefresh();
  refreshTimer = setInterval(dvsRefresh, 5000);   // Refresco cada 5s
})();
</script>
<!-- ============= FIN PANEL DVSWITCH ============= -->
