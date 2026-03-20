<?php

require_once __DIR__ . '/auth.php';

header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';

// ── DMR status ──────────────────────────────────────────────────────────────
if ($action === 'status') {
    $gw  = trim(shell_exec('systemctl is-active dmrgateway 2>/dev/null'));
    $mmd = trim(shell_exec('systemctl is-active mmdvmhost 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['gateway' => $gw, 'mmdvm' => $mmd]);
    exit;
}

if ($action === 'start') {
    shell_exec('sudo systemctl start dmrgateway 2>/dev/null');
    sleep(2);
    shell_exec('sudo systemctl start mmdvmhost 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'stop') {
    shell_exec('sudo systemctl stop mmdvmhost 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop dmrgateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── YSF status ───────────────────────────────────────────────────────────────
if ($action === 'ysf-status') {
    $st = trim(shell_exec('sudo /usr/local/bin/ysf_status.sh 2>/dev/null'));
    if ($st === 'active') {
        header('Content-Type: application/json');
        echo json_encode(['ysf' => 'active']);
        exit;
    }
    $pid = trim(@file_get_contents('/tmp/ysfgateway.pid'));
    $active = ($pid && is_numeric($pid) && file_exists('/proc/' . $pid)) ? 'active' : 'inactive';
    header('Content-Type: application/json');
    echo json_encode(['ysf' => $active]);
    exit;
}

if ($action === 'ysf-start') {
    // Solo arranca YSFGateway — NO toca mmdvmhost ni mmdvmysf
    shell_exec('sudo systemctl start ysfgateway 2>/dev/null');
    sleep(1);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'ysf-stop') {
    // Solo para YSFGateway
    shell_exec('sudo systemctl stop ysfgateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── MMDVMHost YSF status ─────────────────────────────────────────────────────
if ($action === 'mmdvmysf-status') {
    $st = trim(shell_exec('systemctl is-active mmdvmysf 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['mmdvmysf' => $st]);
    exit;
}

if ($action === 'mmdvmysf-start') {
    // Solo arranca MMDVMHost YSF — NO toca mmdvmhost
    shell_exec('sudo systemctl start mmdvmysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mmdvmysf-stop') {
    // Para YSFGateway primero, luego MMDVMHost YSF
    shell_exec('sudo systemctl stop ysfgateway 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop mmdvmysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mmdvmysf-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $log = shell_exec("sudo journalctl -u mmdvmysf -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['mmdvmysf' => htmlspecialchars($log ?? '')]);
    exit;
}

// ── Reboot ────────────────────────────────────────────────────────────────────
if ($action === 'reboot') {
    shell_exec('sudo /usr/bin/systemctl reboot 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── logs ─────────────────────────────────────────────────────────────────────
if ($action === 'logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $gw  = shell_exec("sudo journalctl -u dmrgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmhost  -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['gateway' => htmlspecialchars($gw ?? ''), 'mmdvm' => htmlspecialchars($mmd ?? '')]);
    exit;
}

// ── YSF logs ─────────────────────────────────────────────────────────────────
if ($action === 'ysf-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $log = shell_exec("sudo journalctl -u ysfgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) {
        $log = shell_exec("tail -n {$lines} /tmp/ysfgateway.log 2>/dev/null");
    }
    if (empty(trim($log))) {
        $logFile = glob('/home/pi/YSFClients/YSFGateway/YSFGateway-*.log');
        if ($logFile) {
            $latest = end($logFile);
            $log = shell_exec("tail -n {$lines} " . escapeshellarg($latest) . " 2>/dev/null");
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ysf' => htmlspecialchars($log ?? '')]);
    exit;
}

// ── DMR Id lookup ─────────────────────────────────────────────────────────────
function lookupCall($callsign) {
    $datFiles = [
        '/home/pi/MMDVMHost/DMRIds.dat',
        '/etc/DMRIds.dat',
        '/usr/local/etc/DMRIds.dat',
    ];
    $cs = strtoupper(trim($callsign));
    foreach ($datFiles as $f) {
        if (!file_exists($f)) continue;
        $cmd = "awk -F'\t' '{if (toupper(\$2)==\"" . $cs . "\") {print \$1\"\t\"\$2\"\t\"\$3; exit}}' " . escapeshellarg($f) . " 2>/dev/null";
        $row = trim(shell_exec($cmd));
        if ($row !== '') {
            $parts = explode("\t", $row);
            return ['dmrid' => trim($parts[0] ?? ''), 'name' => trim($parts[2] ?? '')];
        }
    }
    return ['dmrid' => '', 'name' => ''];
}

if ($action === 'transmission') {
    $log   = shell_exec("sudo journalctl -u mmdvmhost -n 200 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log));

    $active = false; $callsign = ''; $dmrid = ''; $name = ''; $tg = ''; $slot = ''; $source = '';

    foreach ($lines as $line) {
        if (preg_match('/DMR Slot \d.*end of voice transmission/i', $line)) { $active = false; break; }
        if (preg_match('/DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $active = true; $slot = $m[1]; $source = strtoupper($m[2]);
            $callsign = strtoupper(rtrim($m[3], ',')); $tg = $m[4]; break;
        }
    }

    if ($callsign) { $info = lookupCall($callsign); $dmrid = $info['dmrid']; $name = $info['name']; }

    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $cs = strtoupper(rtrim($m[4], ','));
            if (!in_array($cs, $seen)) {
                $inf = lookupCall($cs);
                $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'dmrid'=>$inf['dmrid'],'tg'=>$m[5],'slot'=>$m[2],'source'=>strtoupper($m[3]),'time'=>$m[1]];
                $seen[] = $cs;
                if (count($lastHeard) >= 5) break;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dmrid'=>$dmrid,'tg'=>$tg,'slot'=>$slot,'source'=>$source,'lastHeard'=>$lastHeard]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MMDVM Control · EA3EIZ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0a0e14;
    --surface:   #111720;
    --border:    #1e2d3d;
    --green:     #00ff9f;
    --green-dim: #00cc7a;
    --red:       #ff4560;
    --amber:     #ffb300;
    --cyan:      #00d4ff;
    --violet:    #b57aff;
    --text:      #a8b9cc;
    --text-dim:  #4a5568;
    --font-mono: 'Share Tech Mono', monospace;
    --font-ui:   'Rajdhani', sans-serif;
    --font-orb:  'Orbitron', monospace;
  }
  * { box-sizing: border-box; }
  body { background: var(--bg); color: var(--text); font-family: var(--font-ui); font-size: 1rem; min-height: 100vh; padding: 0; margin: 0; }

  /* ── header ── */
  .ctrl-header {
    border-bottom: 1px solid var(--border);
    padding: 1.2rem 2rem; display: flex; align-items: center; gap: 1rem;
    background: var(--surface);
  }
  .ctrl-header h1 {
    font-family: var(--font-ui); font-weight: 700; font-size: 1.5rem;
    letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase;
  }
  .ctrl-header .uptime { margin-left: auto; font-family: var(--font-mono); font-size: .8rem; color: var(--text-dim); }
  .btn-reboot {
    font-family: var(--font-mono); font-size: .75rem; letter-spacing: .1em;
    text-transform: uppercase; background: transparent; color: var(--red);
    border: 1px solid var(--red); border-radius: 4px;
    padding: .35rem .9rem; cursor: pointer; transition: background .2s, color .2s;
  }
  .btn-reboot:hover { background: rgba(255,69,96,.15); }
  .btn-reboot:disabled { opacity: .5; pointer-events: none; }

  .ctrl-body { padding: 2rem; max-width: 1400px; margin: 0 auto; }

  /* ── dots ── */
  .status-bar { display: flex; gap: 2rem; margin-bottom: 1.8rem; flex-wrap: wrap; align-items: center; }
  .status-item { display: flex; align-items: center; gap: .5rem; font-family: var(--font-mono); font-size: .85rem; text-transform: uppercase; letter-spacing: .08em; }
  .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--text-dim); transition: background .4s, box-shadow .4s; }
  .dot.active { background: var(--green); box-shadow: 0 0 8px var(--green); animation: pulse 2s infinite; }
  .dot.error  { background: var(--red); box-shadow: 0 0 8px var(--red); }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

  .section-divider { width: 1px; height: 20px; background: var(--border); margin: 0 .5rem; }

  /* ══ CONTROLS SECTION ══ */
  .controls-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 2rem; }
  @media (max-width: 800px) { .controls-section { grid-template-columns: 1fr; } }

  .service-card { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 1.2rem 1.4rem; }
  .service-card-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; margin-bottom: .8rem; }
  .service-card-label.dmr { color: var(--amber); }
  .service-card-label.ysf { color: var(--violet); }
  .service-card-btns { display: flex; gap: .6rem; flex-wrap: nowrap; margin-top: .8rem; }

  /* ── buttons ── */
  .btn-mmdvm {
    font-family: var(--font-ui); font-weight: 700; font-size: 1rem;
    letter-spacing: .12em; text-transform: uppercase; padding: .75rem 2rem;
    border-radius: 6px; border: none; color: #fff; background: #c82333; width: 100%;
    transition: background .2s, box-shadow .2s; cursor: pointer;
  }
  .btn-mmdvm:hover { background: #218838; box-shadow: 0 4px 15px rgba(40,167,69,.4); }
  .btn-mmdvm.running { background: #218838; }
  .btn-mmdvm.running:hover { background: #c82333; }
  .btn-mmdvm.busy { background: #6c757d; pointer-events: none; }

  .btn-ysf {
    font-family: var(--font-ui); font-weight: 700; font-size: 1rem;
    letter-spacing: .12em; text-transform: uppercase; padding: .75rem 2rem;
    border-radius: 6px; border: none; color: #fff; background: #5a2d82; width: 100%;
    transition: background .2s, box-shadow .2s; cursor: pointer;
  }
  .btn-ysf:hover { background: #218838; box-shadow: 0 4px 15px rgba(40,167,69,.4); }
  .btn-ysf.running { background: #218838; }
  .btn-ysf.running:hover { background: #5a2d82; }
  .btn-ysf.busy { background: #6c757d; pointer-events: none; }

  .auto-badge { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); display: flex; align-items: center; gap: .4rem; margin-top: .5rem; }
  .auto-badge .dot-sm { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
  .auto-badge.ysf .dot-sm { background: var(--violet); }

  /* ── ini buttons ── */
  .ini-btn {
    font-family: var(--font-mono); font-size: .72rem; text-transform: uppercase;
    letter-spacing: .06em; padding: .3rem .7rem; border-radius: 3px;
    border: 1px solid var(--border); background: transparent;
    cursor: pointer; text-decoration: none; transition: all .2s;
    display: inline-flex; align-items: center; gap: .3rem;
  }
  .ini-btn.edit { color: var(--amber); border-color: rgba(255,179,0,.3); }
  .ini-btn.edit:hover { border-color: var(--amber); background: rgba(255,179,0,.08); }
  .ini-btn.view { color: var(--cyan); border-color: rgba(0,212,255,.3); }
  .ini-btn.view:hover { border-color: var(--cyan); background: rgba(0,212,255,.08); }
  .ini-btn.edit.ysf { color: var(--violet); border-color: rgba(181,122,255,.3); }
  .ini-btn.edit.ysf:hover { border-color: var(--violet); background: rgba(181,122,255,.08); }
  .ini-btn.view.ysf { color: #c9a0ff; border-color: rgba(181,122,255,.2); }
  .ini-btn.view.ysf:hover { border-color: var(--violet); background: rgba(181,122,255,.06); }

  /* ══ DISPLAY ROW ══ */
  .display-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin: 2rem 0; align-items: start; }
  @media (max-width: 900px) { .display-row { grid-template-columns: 1fr; } }
  .panel-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; color: var(--amber); text-transform: uppercase; margin-bottom: .5rem; }

  /* ── Nextion ── */
  .nextion {
    background: #060c10; border: 2px solid #1a3a4a; border-radius: 6px;
    box-shadow: 0 0 0 1px #0d2030, inset 0 0 40px rgba(0,212,255,.04), 0 0 30px rgba(0,212,255,.08);
    position: relative; overflow: hidden; height: 210px;
    display: flex; align-items: center; justify-content: center;
  }
  .nextion::before, .nextion::after { content: '◈'; position: absolute; font-size: .6rem; color: #1a3a4a; }
  .nextion::before { top: .5rem; left: .7rem; }
  .nextion::after  { bottom: .5rem; right: .7rem; }
  .nx-topbar {
    position: absolute; top: 0; left: 0; right: 0; height: 30px;
    background: #1c1c24; border-bottom: 1px solid #1a3a4a;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .1em;
  }
  .nx-topbar .nx-mode { color: var(--cyan); opacity: .7; }
  .nx-topbar .nx-tg   { color: var(--amber); opacity: .85; min-width: 5rem; text-align: right; }
  .nx-botbar {
    position: absolute; bottom: 0; left: 0; right: 0; height: 28px;
    background: #0d1e2a; border-top: 1px solid #1a3a4a;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .08em;
  }
  .nx-botbar .nx-dmrid { color: #3a6a8a; min-width: 6rem; }
  .nx-botbar .nx-source { padding: .1rem .45rem; border-radius: 2px; font-size: .6rem; letter-spacing: .1em; }
  .nx-botbar .nx-source.rf  { background: rgba(0,255,159,.15); color: var(--green); border: 1px solid rgba(0,255,159,.3); }
  .nx-botbar .nx-source.net { background: rgba(0,212,255,.15); color: var(--cyan);  border: 1px solid rgba(0,212,255,.3); }
  .nx-vu { position: absolute; left: 1rem; top: 38px; bottom: 32px; width: 6px; display: flex; flex-direction: column-reverse; gap: 2px; }
  .nx-vu.right { left: auto; right: 1rem; }
  .nx-vu-bar { height: 5px; border-radius: 1px; background: #0d2030; transition: background .08s; }
  .nx-vu-bar.lit-g { background: var(--green); box-shadow: 0 0 4px var(--green); }
  .nx-vu-bar.lit-a { background: var(--amber); box-shadow: 0 0 4px var(--amber); }
  .nx-vu-bar.lit-r { background: var(--red);   box-shadow: 0 0 4px var(--red); }
  .nx-txbar { position: absolute; bottom: 28px; left: 0; right: 0; height: 3px; }
  .nx-txbar.active {
    background: linear-gradient(90deg, transparent 0%, var(--green) 50%, transparent 100%);
    background-size: 200% 100%; animation: scan 1.4s linear infinite;
  }
  @keyframes scan { from{background-position:200% 0} to{background-position:-200% 0} }
  .nx-center { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; z-index: 1; }
  .nx-clock { font-family: var(--font-orb); font-size: 4rem; font-weight: 700; color: #f5bd06; letter-spacing: .06em; line-height: 1; }
  .nx-date  { font-family: var(--font-mono); font-size: .7rem; color: #ff0; letter-spacing: .12em; text-transform: uppercase; margin-top: .2rem; }
  .nx-callsign { font-family: var(--font-orb); font-size: 3.4rem; font-weight: 900; letter-spacing: .04em; line-height: 1; color: var(--green); text-shadow: 0 0 20px rgba(0,255,159,.55), 0 0 60px rgba(0,255,159,.2); animation: glow-in .3s ease; }
  .nx-name { font-family: var(--font-ui); font-weight: 500; font-size: 1.2rem; color: var(--cyan); letter-spacing: .18em; text-transform: uppercase; opacity: .9; margin-top: .15rem; }
  @keyframes glow-in { from{opacity:0;transform:scale(.96)} to{opacity:1;transform:scale(1)} }

  /* ══ LAST HEARD ══ */
  .lh-panel { background: var(--surface); border: 3px solid #1a3a4a; border-radius: 6px; display: flex; flex-direction: column; }
  .lh-header {
    background: #1c1c24; border-bottom: 1px solid var(--border); padding: .4rem 1rem;
    display: grid; grid-template-columns: 1.1fr 1.5fr .7fr .7fr .5fr;
    gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .1em; text-transform: uppercase;
  }
  .lh-body { flex: 1; overflow-y: auto; }
  .lh-body::-webkit-scrollbar { width: 3px; }
  .lh-body::-webkit-scrollbar-thumb { background: var(--border); }
  .lh-row { display: grid; grid-template-columns: 1.1fr 1.5fr .7fr .7fr .5fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(30,45,61,.6); align-items: center; transition: background .2s; }
  .lh-row:last-child { border-bottom: none; }
  .lh-row:hover { background: rgba(0,212,255,.04); }
  .lh-row.lh-active { background: rgba(0,255,159,.06); }
  .lh-call-wrap { display: flex; align-items: center; gap: .35rem; }
  .lh-tx-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 1s infinite; flex-shrink: 0; }
  .lh-call { font-family: var(--font-mono); font-size: .82rem; color: var(--green); letter-spacing: .05em; font-weight: bold; }
  .lh-row.lh-active .lh-call { text-shadow: 0 0 8px rgba(0,255,159,.5); }
  .lh-name { font-family: var(--font-ui); font-size: .82rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .lh-tg   { font-family: var(--font-mono); font-size: .72rem; color: var(--amber); }
  .lh-time { font-family: var(--font-mono); font-size: .68rem; color: var(--text-dim); }
  .lh-src  { font-family: var(--font-mono); font-size: .6rem; }
  .lh-src.rf  { color: var(--green); }
  .lh-src.net { color: var(--cyan); }
  .lh-empty { padding: 1.5rem 1rem; font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); text-align: center; }

  /* ── logs ── */
  .log-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
  @media (max-width: 900px) { .log-grid { grid-template-columns: 1fr; } }
  .log-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
  .log-panel-header { display: flex; align-items: center; justify-content: space-between; padding: .5rem 1rem; border-bottom: 1px solid var(--border); background: rgba(0,0,0,.3); }
  .log-panel-header .svc-name { font-family: var(--font-mono); font-size: .8rem; letter-spacing: .1em; color: var(--green); text-transform: uppercase; }
  .log-panel-header .svc-name.gw  { color: var(--amber); }
  .log-panel-header .svc-name.ysf { color: var(--violet); }
  .log-panel-header .btn-clear { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); background: none; border: none; cursor: pointer; padding: 0; transition: color .2s; }
  .log-panel-header .btn-clear:hover { color: var(--text); }
  .log-output { font-family: var(--font-mono); font-size: .72rem; line-height: 1.55; color: #7a9ab5; padding: .8rem 1rem; height: 190px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
  .log-output::-webkit-scrollbar { width: 4px; }
  .log-output::-webkit-scrollbar-track { background: transparent; }
  .log-output::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
  .ln-info { color: #7a9ab5; }
  .ln-warn { color: var(--amber); }
  .ln-err  { color: var(--red); }
  .ln-ok   { color: var(--green-dim); }
</style>
</head>
<body>

<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ" style="height:40px; width:auto;">
  <h1>MMDVMxx &amp; YSF Control prueba</h1>
  <span class="uptime" id="clock">--:--:--</span>
  <button id="btnReboot" class="btn-reboot" onclick="rebootPi()">⏻ Reiniciar Pi</button>
</header>

<main class="ctrl-body">

  <!-- ── Status bar ── -->
  <div class="status-bar">
    <div class="status-item"><div class="dot" id="dot-mosquitto"></div><span>Mosquittoxx</span></div>
    <div class="status-item"><div class="dot" id="dot-gateway"></div><span>DMRGatewayxx</span></div>
    <div class="status-item"><div class="dot" id="dot-mmdvm"></div><span>MMDVMHostxx</span></div>
    <div class="section-divider"></div>
    <div class="status-item"><div class="dot" id="dot-ysf"></div><span style="color:var(--violet)">YSFGateway</span></div>
    <div class="status-item"><div class="dot" id="dot-mmdvmysf"></div><span style="color:#26c6da">MMDVMHost YSF</span></div>
  </div>

  <!-- ══ Controls section ══ -->
  <div class="controls-section">

    <!-- DMR card -->
    <div class="service-card">
      <div class="service-card-label dmr">▸ DMR · MMDVMHost + DMRGateway</div>
      <button class="btn-mmdvm" id="btnToggle" onclick="toggleServices()">
        <span id="btnLabel">Abrir MMDVM Host</span>
      </button>
      <div class="auto-badge" id="autoRefreshBadge" style="display:none">
        <div class="dot-sm"></div> auto-refresh 3s
      </div>
      <div class="service-card-btns">
        <a href="mmdvm_config.php"            target="_blank" class="ini-btn edit" style="flex:1;justify-content:center;">⚙ MMDVM Config</a>
        <a href="dmrgateway_config.php"        target="_blank" class="ini-btn edit" style="flex:1;justify-content:center;">⚙ Gateway Config</a>
      </div>
      <div class="service-card-btns" style="margin-top:.4rem;">
        <a href="edit_ini.php?file=mmdvm"      target="_blank" class="ini-btn view" style="flex:1;justify-content:center;">📄 MMDVM.ini</a>
        <a href="edit_ini.php?file=dmrgateway" target="_blank" class="ini-btn view" style="flex:1;justify-content:center;">📄 Gateway.ini</a>
      </div>
    </div>

    <!-- YSF card -->
    <div class="service-card">
      <div class="service-card-label ysf">▸ C4FM · YSFGateway + MMDVMHost YSF</div>
      <button class="btn-ysf" id="btnYSF" onclick="toggleYSF()">
        <span id="btnYSFLabel">Abrir C4FM</span>
      </button>
      <div class="auto-badge ysf" id="ysfRefreshBadge" style="display:none">
        <div class="dot-sm"></div> C4FM activo
      </div>
      <div class="service-card-btns">
        <a href="ysfgateway_config.php" target="_blank" class="ini-btn edit ysf" style="flex:1;justify-content:center;">⚙ YSFGATEWAY CONFIG</a>
        <a href="mmdvmysf_config.php"   target="_blank" class="ini-btn edit"     style="flex:1;justify-content:center;color:#26c6da;border-color:rgba(38,198,218,.3);">⚙ MMDVMYSF CONFIG</a>
      </div>
      <div class="service-card-btns" style="margin-top:.4rem;">
        <a href="edit_ini.php?file=ysfgateway" target="_blank" class="ini-btn view ysf" style="flex:1;justify-content:center;">📄 YSFGateway.ini</a>
        <a href="edit_ini.php?file=mmdvmysf"   target="_blank" class="ini-btn view"     style="flex:1;justify-content:center;color:#80deea;border-color:rgba(38,198,218,.2);">📄 MMDVMYSF.ini</a>
      </div>
    </div>

  </div>

  <!-- ══ Display row ══ -->
  <div class="display-row">
    <div>
      <div class="panel-label">▸ DMR Display</div>
      <div class="nextion">
        <div class="nx-topbar">
          <span class="nx-mode">DMR · SIMPLEX</span>
          <span>EA3EIZ · ADER</span>
          <span class="nx-tg" id="nxTG">—</span>
        </div>
        <div class="nx-vu"       id="vuLeft"></div>
        <div class="nx-vu right" id="vuRight"></div>
        <div class="nx-center" id="nxCenter">
          <div class="nx-clock" id="nxClock">00:00:00</div>
          <div class="nx-date"  id="nxDate">—</div>
        </div>
        <div class="nx-txbar" id="nxTxBar"></div>
        <div class="nx-botbar">
          <span class="nx-dmrid" id="nxDmrid">—</span>
          <span>SLOT <span id="nxSlot">—</span></span>
          <span class="nx-source" id="nxSource"></span>
        </div>
      </div>
    </div>

    <div>
      <div class="panel-label">▸ Últimos escuchados</div>
      <div class="lh-panel">
        <div class="lh-header">
          <span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span>
        </div>
        <div class="lh-body" id="lhBody">
          <div class="lh-empty">Sin actividad reciente</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Logs ── -->
  <div class="log-grid">
    <div class="log-panel">
      <div class="log-panel-header">
        <span class="svc-name gw">▸ DMRGateway</span>
        <button class="btn-clear" onclick="clearLog('logGw')">limpiar</button>
      </div>
      <div class="log-output" id="logGw">Esperando servicios…</div>
    </div>
    <div class="log-panel">
      <div class="log-panel-header">
        <span class="svc-name">▸ MMDVMHost</span>
        <button class="btn-clear" onclick="clearLog('logMmd')">limpiar</button>
      </div>
      <div class="log-output" id="logMmd">Esperando servicios…</div>
    </div>
    <div class="log-panel">
      <div class="log-panel-header">
        <span class="svc-name ysf">▸ YSFGateway</span>
        <button class="btn-clear" onclick="clearLog('logYsf')">limpiar</button>
      </div>
      <div class="log-output" id="logYsf">Esperando YSFGateway…</div>
    </div>
    <div class="log-panel">
      <div class="log-panel-header">
        <span class="svc-name" style="color:#26c6da">▸ MMDVMHost YSF</span>
        <button class="btn-clear" onclick="clearLog('logMmdvmYsf')">limpiar</button>
      </div>
      <div class="log-output" id="logMmdvmYsf">Esperando MMDVMHost YSF…</div>
    </div>
  </div>

</main>

<script>
let refreshTimer    = null;
let txTimer         = null;
let vuTimer         = null;
let ysfTimer        = null;
let mmdvmYsfTimer   = null;
let running         = false;
let ysfRunning      = false;
let mmdvmYsfRunning = false;
let currentlyActive = false;

function buildVU(id) {
  const el = document.getElementById(id);
  for (let i = 0; i < 18; i++) {
    const d = document.createElement('div');
    d.className = 'nx-vu-bar'; d.id = `${id}-${i}`; el.appendChild(d);
  }
}
buildVU('vuLeft'); buildVU('vuRight');

function animateVU(on) {
  clearInterval(vuTimer);
  ['vuLeft','vuRight'].forEach(id => {
    for (let i = 0; i < 18; i++) document.getElementById(`${id}-${i}`).className = 'nx-vu-bar';
  });
  if (!on) return;
  vuTimer = setInterval(() => {
    ['vuLeft','vuRight'].forEach(id => {
      const lvl = Math.floor(Math.random() * 16) + 1;
      for (let i = 0; i < 18; i++) {
        document.getElementById(`${id}-${i}`).className =
          i < lvl ? (i < 10 ? 'nx-vu-bar lit-g' : i < 14 ? 'nx-vu-bar lit-a' : 'nx-vu-bar lit-r') : 'nx-vu-bar';
      }
    });
  }, 80);
}

function updateClock() {
  const now  = new Date();
  const hms  = now.toLocaleTimeString('es-ES');
  const date = now.toLocaleDateString('es-ES', {weekday:'short', day:'2-digit', month:'short', year:'numeric'}).toUpperCase();
  document.getElementById('clock').textContent = hms;
  if (!currentlyActive) {
    const clk = document.getElementById('nxClock');
    if (clk) { clk.textContent = hms; document.getElementById('nxDate').textContent = date; }
  }
}
setInterval(updateClock, 1000);
updateClock();

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function showIdle() {
  currentlyActive = false; animateVU(false);
  document.getElementById('nxTxBar').classList.remove('active');
  document.getElementById('nxTG').textContent    = '—';
  document.getElementById('nxSlot').textContent  = '—';
  document.getElementById('nxDmrid').textContent = '—';
  const src = document.getElementById('nxSource');
  src.textContent = ''; src.className = 'nx-source';
  document.getElementById('nxCenter').innerHTML =
    '<div class="nx-clock" id="nxClock">00:00:00</div><div class="nx-date" id="nxDate">—</div>';
  updateClock();
}

function showActive(d) {
  currentlyActive = true; animateVU(true);
  document.getElementById('nxTxBar').classList.add('active');
  document.getElementById('nxTG').textContent    = d.tg    ? 'TG ' + d.tg : '—';
  document.getElementById('nxSlot').textContent  = d.slot  || '—';
  document.getElementById('nxDmrid').textContent = d.dmrid || '—';
  const src = document.getElementById('nxSource');
  if (d.source === 'RF')      { src.textContent = 'RF';  src.className = 'nx-source rf'; }
  else if (d.source === 'NETWORK') { src.textContent = 'NET'; src.className = 'nx-source net'; }
  else { src.textContent = ''; src.className = 'nx-source'; }
  document.getElementById('nxCenter').innerHTML =
    `<div class="nx-callsign">${esc(d.callsign)}</div>` +
    (d.name ? `<div class="nx-name">${esc(d.name)}</div>` : '');
}

function renderLastHeard(list, activeCall) {
  const body = document.getElementById('lhBody');
  if (!list || list.length === 0) { body.innerHTML = '<div class="lh-empty">Sin actividad reciente</div>'; return; }
  body.innerHTML = list.map(r => {
    const isActive = activeCall && r.callsign === activeCall;
    const srcCls = (r.source === 'RF') ? 'rf' : 'net';
    const srcLbl = (r.source === 'RF') ? 'RF' : 'NET';
    const dot = isActive ? '<span class="lh-tx-dot"></span>' : '';
    return `<div class="lh-row${isActive ? ' lh-active' : ''}">
      <div class="lh-call-wrap">${dot}<span class="lh-call">${esc(r.callsign)}</span></div>
      <span class="lh-name">${esc(r.name || '—')}</span>
      <span class="lh-tg">${esc(r.tg || '—')}</span>
      <span class="lh-time">${esc(r.time || '—')}</span>
      <span class="lh-src ${srcCls}">${srcLbl}</span>
    </div>`;
  }).join('');
}

async function fetchTransmission() {
  try {
    const r = await fetch('?action=transmission');
    const d = await r.json();
    if (d.active) showActive(d); else showIdle();
    renderLastHeard(d.lastHeard || [], d.active ? d.callsign : null);
  } catch(e) {}
}

// ── DMR status ────────────────────────────────────────────────────────────────
async function checkStatus() {
  try {
    const r = await fetch('?action=status');
    const d = await r.json();
    const gw = d.gateway === 'active', mmd = d.mmdvm === 'active';
    setDot('dot-gateway',   gw  ? 'active' : 'off');
    setDot('dot-mmdvm',     mmd ? 'active' : 'off');
    setDot('dot-mosquitto', gw  ? 'active' : 'off');
    running = gw || mmd;
    updateDMRButton(running);
  } catch(e) {}
}

// ── YSF status ────────────────────────────────────────────────────────────────
async function checkYSFStatus() {
  try {
    const r = await fetch('?action=ysf-status');
    const d = await r.json();
    ysfRunning = d.ysf === 'active';
    setDot('dot-ysf', ysfRunning ? 'active' : 'off');
    refreshYSFButton();
  } catch(e) {}
}

// ── MMDVMHost YSF status ──────────────────────────────────────────────────────
async function checkMMDVMYSFStatus() {
  try {
    const r = await fetch('?action=mmdvmysf-status');
    const d = await r.json();
    mmdvmYsfRunning = d.mmdvmysf === 'active';
    setDot('dot-mmdvmysf', mmdvmYsfRunning ? 'active' : 'off');
    refreshYSFButton();
  } catch(e) {}
}

function setDot(id, state) {
  document.getElementById(id).className = 'dot' + (state === 'active' ? ' active' : state === 'error' ? ' error' : '');
}

function updateDMRButton(on) {
  const btn = document.getElementById('btnToggle');
  btn.classList.toggle('running', on);
  document.getElementById('btnLabel').textContent = on ? 'Cerrar MMDVM Host' : 'Abrir MMDVM Host';
}

// Estado combinado del botón YSF = cualquiera de los dos activo
function refreshYSFButton() {
  const combinedOn = ysfRunning || mmdvmYsfRunning;
  const btn = document.getElementById('btnYSF');
  btn.classList.toggle('running', combinedOn);
  document.getElementById('btnYSFLabel').textContent = combinedOn ? 'Cerrar C4FM' : 'Abrir C4FM';
  document.getElementById('ysfRefreshBadge').style.display = combinedOn ? 'flex' : 'none';
}

// ── DMR toggle (independiente — no toca YSF) ─────────────────────────────────
async function toggleServices() {
  const btn = document.getElementById('btnToggle');
  const wasOpen = running;
  btn.classList.add('busy');
  document.getElementById('btnLabel').textContent = wasOpen ? 'Deteniendo…' : 'Iniciando…';
  try {
    await fetch(wasOpen ? '?action=stop' : '?action=start');
    await new Promise(r => setTimeout(r, 2200));
    await checkStatus();
    if (wasOpen) {
      stopRefresh(); clearLog('logGw'); clearLog('logMmd'); showIdle();
      document.getElementById('lhBody').innerHTML = '<div class="lh-empty">Sin actividad reciente</div>';
    } else {
      startRefresh();
    }
  } finally { btn.classList.remove('busy'); }
}

// ── YSF toggle (independiente — no toca DMR mmdvmhost) ───────────────────────
async function toggleYSF() {
  const btn = document.getElementById('btnYSF');
  const wasOpen = ysfRunning || mmdvmYsfRunning;
  btn.classList.add('busy');
  document.getElementById('btnYSFLabel').textContent = wasOpen ? 'Deteniendo…' : 'Iniciando…';
  try {
    if (wasOpen) {
      // Cerrar: YSFGateway → MMDVMHost YSF
      await fetch('?action=ysf-stop');
      await new Promise(r => setTimeout(r, 1000));
      await fetch('?action=mmdvmysf-stop');
      await new Promise(r => setTimeout(r, 2000));
      clearLog('logYsf'); clearLog('logMmdvmYsf');
      stopYSFLogs(); stopMMDVMYSFLogs();
    } else {
      // Abrir: MMDVMHost YSF → YSFGateway
      await fetch('?action=mmdvmysf-start');
      await new Promise(r => setTimeout(r, 2000));
      await fetch('?action=ysf-start');
      await new Promise(r => setTimeout(r, 1500));
      startYSFLogs(); startMMDVMYSFLogs();
    }
    await checkYSFStatus();
    await checkMMDVMYSFStatus();
  } finally { btn.classList.remove('busy'); }
}

// ── Reboot ────────────────────────────────────────────────────────────────────
async function rebootPi() {
  if (!confirm('¿Seguro que quieres reiniciar la Raspberry Pi?')) return;
  const btn = document.getElementById('btnReboot');
  btn.textContent = '⏻ Reiniciando…';
  btn.disabled = true;
  await fetch('?action=reboot');
}

// ── logs ──────────────────────────────────────────────────────────────────────
function colorize(text) {
  return text.split('\n').map(l => {
    const ll = l.toLowerCase();
    if (/error|fail|abort|assert/.test(ll)) return `<span class="ln-err">${l}</span>`;
    if (/warn/.test(ll))                    return `<span class="ln-warn">${l}</span>`;
    if (/connect|start|open|loaded|success/.test(ll)) return `<span class="ln-ok">${l}</span>`;
    return `<span class="ln-info">${l}</span>`;
  }).join('\n');
}
function clearLog(id) { document.getElementById(id).innerHTML = ''; }

async function fetchLogs() {
  try {
    const r = await fetch('?action=logs&lines=15');
    const d = await r.json();
    ['logGw:gateway','logMmd:mmdvm'].forEach(pair => {
      const [id, key] = pair.split(':');
      const el = document.getElementById(id);
      const atBot = el.scrollHeight - el.clientHeight <= el.scrollTop + 10;
      el.innerHTML = colorize(d[key]);
      if (atBot) el.scrollTop = el.scrollHeight;
    });
  } catch(e) {}
}

async function fetchYSFLogs() {
  try {
    const r = await fetch('?action=ysf-logs&lines=15');
    const d = await r.json();
    const el = document.getElementById('logYsf');
    const atBot = el.scrollHeight - el.clientHeight <= el.scrollTop + 10;
    el.innerHTML = colorize(d.ysf);
    if (atBot) el.scrollTop = el.scrollHeight;
  } catch(e) {}
}

async function fetchMMDVMYSFLogs() {
  try {
    const r = await fetch('?action=mmdvmysf-logs&lines=15');
    const d = await r.json();
    const el = document.getElementById('logMmdvmYsf');
    const atBot = el.scrollHeight - el.clientHeight <= el.scrollTop + 10;
    el.innerHTML = colorize(d.mmdvmysf);
    if (atBot) el.scrollTop = el.scrollHeight;
  } catch(e) {}
}

function startRefresh() {
  fetchLogs(); fetchTransmission();
  refreshTimer = setInterval(fetchLogs, 5000);
  txTimer      = setInterval(fetchTransmission, 3000);
  document.getElementById('autoRefreshBadge').style.display = 'flex';
}
function stopRefresh() {
  clearInterval(refreshTimer); clearInterval(txTimer);
  refreshTimer = txTimer = null;
  document.getElementById('autoRefreshBadge').style.display = 'none';
}
function startYSFLogs()      { fetchYSFLogs();      ysfTimer      = setInterval(fetchYSFLogs,      4000); }
function stopYSFLogs()       { clearInterval(ysfTimer);      ysfTimer      = null; }
function startMMDVMYSFLogs() { fetchMMDVMYSFLogs(); mmdvmYsfTimer = setInterval(fetchMMDVMYSFLogs, 4000); }
function stopMMDVMYSFLogs()  { clearInterval(mmdvmYsfTimer); mmdvmYsfTimer = null; }

// ── init ──────────────────────────────────────────────────────────────────────
(async () => {
  await checkStatus();
  await checkYSFStatus();
  await checkMMDVMYSFStatus();
  setInterval(checkStatus,         10000);
  setInterval(checkYSFStatus,      8000);
  setInterval(checkMMDVMYSFStatus, 8000);
  if (running) startRefresh();
  else { showIdle(); fetchTransmission(); }
  startYSFLogs();
  startMMDVMYSFLogs();
})();
</script>
</body>
</html>
